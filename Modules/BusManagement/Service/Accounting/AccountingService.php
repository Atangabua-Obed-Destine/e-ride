<?php

namespace Modules\BusManagement\Service\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAccAccount;
use Modules\BusManagement\Entities\BusAccBook;
use Modules\BusManagement\Entities\BusAccDocument;
use Modules\BusManagement\Entities\BusAccEntry;
use Modules\BusManagement\Entities\BusAccLine;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusMoneyMove;
use Modules\BusManagement\Entities\BusStaff;
use Modules\BusManagement\Entities\BusStatement;
use Modules\BusManagement\Entities\BusVehicle;
use Modules\BusManagement\Lib\AccChart;

/**
 * What people record in a branch's books by hand. Each action validates, writes its document and
 * posts its entry through Ledger::post(), all in one transaction, so the preview (the same call,
 * rolled back) shows exactly what confirming does.
 */
class AccountingService
{
    public function __construct(protected Ledger $ledger, protected LedgerSync $sync)
    {
    }

    // ------------------------------------------------------------------ opening the books

    /**
     * Opens a branch's books: the chart of accounts, the start date, the default accounts and the
     * opening balances (money the branch has, what it owes suppliers). The tickets already paid for
     * trips still ahead, the refunds owed and E-Ride's unpaid statements are added from the records.
     */
    public function open(BusAgency $agency, array $data, string $userId): BusAccDocument
    {
        if ($this->ledger->books($agency)) {
            throw ValidationException::withMessages(['books' => translate('The books of this branch are already open.')]);
        }
        $v = Validator::make($data, [
            'starts_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . today()->subDays(92)->toDateString()],
            'balances' => ['array'],
            'balances.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'suppliers_owed' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'refunds_from' => ['nullable', Rule::in(['mtn', 'orange', 'bank', 'safe', 'fapshi'])],
            'eride_from' => ['nullable', Rule::in(['mtn', 'orange', 'bank', 'safe', 'fapshi'])],
        ], [], ['starts_on' => translate('start date')]);
        $v->validate();
        $start = Carbon::parse($data['starts_on'])->startOfDay();

        return DB::transaction(function () use ($agency, $data, $userId, $start) {
            BusAccBook::create([
                'agency_id' => (string)$agency->id, 'starts_on' => $start->toDateString(), 'opened_by' => $userId,
                'settings' => ['refunds_from' => $data['refunds_from'] ?? 'mtn', 'eride_from' => $data['eride_from'] ?? 'mtn'],
            ]);
            $this->ledger->seedChart($agency);
            $acc = fn(string $role) => $this->ledger->account($agency, $role);
            $lines = [];
            $debits = 0.0;
            $credits = 0.0;
            foreach (AccChart::OPENING_MONEY as $role) {
                $amount = round((float)($data['balances'][$role] ?? 0), 2);
                if ($amount > 0) {
                    $lines[] = [$acc($role), $amount, 0];
                    $debits += $amount;
                }
            }
            $owed = round((float)($data['suppliers_owed'] ?? 0), 2);
            if ($owed > 0) {
                $lines[] = [$acc('suppliers'), 0, $owed, 'memo' => translate('Owed to suppliers at the start')];
                $credits += $owed;
            }
            // From the records: what was already paid or owed before the start.
            foreach ($this->openingFromRecords($agency, $start) as $l) {
                $lines[] = $l;
                $debits += $l[1];
                $credits += $l[2];
            }
            $equity = round($debits - $credits, 2);
            if (abs($equity) >= 0.005) {
                $lines[] = $equity > 0 ? [$acc('capital'), 0, $equity, 'memo' => translate('Balance of the opening')] : [$acc('capital'), -$equity, 0, 'memo' => translate('Balance of the opening')];
            }
            $number = $this->ledger->nextDocumentNumber((string)$agency->id, BusAccDocument::OPENING);
            $doc = BusAccDocument::create([
                'agency_id' => (string)$agency->id, 'number' => $number, 'type' => BusAccDocument::OPENING, 'date' => $start->toDateString(),
                'amount' => round(max($debits, $credits), 2), 'note' => translate('Opening balances'), 'created_by' => $userId,
            ]);
            if (count($lines) >= 2) {
                $entry = $this->ledger->post($agency, [
                    'date' => $start, 'journal' => 'opening', 'memo' => translate('Opening balances') . ' · ' . $number, 'lines' => $lines,
                    'source_type' => 'document', 'source_id' => (string)$doc->id, 'source_key' => 'doc:' . $doc->id, 'created_by' => $userId,
                ]);
                $doc->update(['entry_id' => (string)$entry->id]);
            }
            // Everything from the start date on posts now.
            $this->sync->sync($agency);
            return $doc->fresh();
        });
    }

    /**
     * Opening lines taken from the bus records, as at the start date: tickets paid for trips still
     * ahead (held, per trip and channel), refunds owed to passengers, E-Ride statements not paid.
     */
    public function openingFromRecords(BusAgency $agency, Carbon $start): array
    {
        $acc = fn(string $role) => $this->ledger->account($agency, $role);
        $lines = [];
        $moves = BusMoneyMove::where('agency_id', $agency->id)->where('at', '<', $start)->get();

        // Held for trips leaving on or after the start.
        $held = [];
        foreach ($moves as $m) {
            $sign = match ($m->kind) {
                BusMoneyMove::SALE => 1,
                BusMoneyMove::REFUND_OWED, BusMoneyMove::CASH_REFUND => -1,
                default => 0,
            };
            if ($sign !== 0 && $m->departure_id) {
                $held[$m->departure_id][$m->channel] = ($held[$m->departure_id][$m->channel] ?? 0) + $sign * $m->amount;
            }
            if ($m->kind === BusMoneyMove::REBOOK) {
                $held[$m->departure_id][$m->channel] = ($held[$m->departure_id][$m->channel] ?? 0) + $m->amount;
                if ($m->from_departure_id) {
                    $held[$m->from_departure_id][$m->channel] = ($held[$m->from_departure_id][$m->channel] ?? 0) - $m->amount;
                }
            }
        }
        $departures = BusDeparture::whereIn('id', array_keys($held))->get()->keyBy(fn($d) => (string)$d->id);
        foreach ($held as $depId => $channels) {
            $d = $departures[$depId] ?? null;
            if (!$d || $d->leavesAt()->lt($start)) {
                continue;
            }
            foreach ($channels as $channel => $amount) {
                $amount = round($amount, 2);
                if ($amount > 0.004) {
                    $lines[] = [$acc('unearned'), 0, $amount, 'memo' => translate('Tickets paid before the start')] + $this->sync->tripTags($d, $channel);
                }
            }
        }

        // Refunds owed at the start.
        $owed = 0.0;
        foreach ($moves as $m) {
            $owed += match ($m->kind) {
                BusMoneyMove::EXTRA, BusMoneyMove::REFUND_OWED => $m->amount,
                BusMoneyMove::REFUND_SENT => -$m->amount,
                default => 0,
            };
        }
        if (round($owed, 2) > 0.004) {
            $lines[] = [$acc('refunds_owed'), 0, round($owed, 2), 'memo' => translate('Refunds owed at the start')];
        }

        // E-Ride statements of months before the start month that were not paid by the start.
        $unpaid = BusStatement::where('agency_id', $agency->id)->where('month', '<', $start->copy()->startOfMonth()->toDateString())
            ->where(fn($q) => $q->where('status', BusStatement::DUE)->orWhere('paid_at', '>=', $start))->sum('total');
        if ((float)$unpaid > 0.004) {
            $lines[] = [$acc('eride'), 0, round((float)$unpaid, 2), 'memo' => translate('E-Ride statements not paid at the start')];
        }
        return $lines;
    }

    // ------------------------------------------------------------------ spending and income

    /** An expense, paid now from a money account, or a supplier bill to pay later. */
    public function expense(BusAgency $agency, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $data = $this->validate($agency, $data, [
            'date' => ['required', 'date'],
            'account_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'paid' => ['required', Rule::in(['now', 'later'])],
            'money_account_id' => ['required_if:paid,now', 'nullable', 'string'],
            'staff_user_id' => ['nullable', 'string'],
            'supplier' => ['required_if:paid,later', 'nullable', 'string', 'max:120'],
            'due_on' => ['nullable', 'date', 'after_or_equal:date'],
            'vehicle_id' => ['nullable', 'string'],
            'destination_id' => ['nullable', 'string'],
            'departure_id' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['account_id' => translate('category'), 'money_account_id' => translate('paid from'), 'supplier' => translate('supplier')]);
        $category = $this->ownAccount($agency, $data['account_id'], 'expense');
        $tags = $this->tags($agency, $data);
        $amount = round((float)$data['amount'], 2);
        $later = $data['paid'] === 'later';

        return DB::transaction(function () use ($agency, $data, $userId, $category, $tags, $amount, $later) {
            $credit = $later ? [$this->ledger->account($agency, 'suppliers'), 0, $amount, 'memo' => $data['supplier']]
                : $this->moneyLine($agency, $data, 0, $amount);
            $doc = $this->document($agency, BusAccDocument::EXPENSE, $data, $userId, [
                'amount' => $amount, 'account_id' => (string)$category->id,
                'money_account_id' => $later ? null : (string)$credit[0]->id,
                'staff_user_id' => $credit['staff_user_id'] ?? null,
                'supplier' => $data['supplier'] ?? null, 'owed' => $later ? $amount : 0, 'due_on' => $later ? ($data['due_on'] ?? null) : null,
            ] + $tags);
            $memo = translate($category->name) . ($data['supplier'] ?? null ? ' · ' . $data['supplier'] : '') . ($data['note'] ?? null ? ' · ' . $data['note'] : '');
            $this->postDocument($agency, $doc, $later ? 'purchase' : 'cash', $memo, [[$category, $amount, 0] + $tags, $credit], $userId);
            return $doc->fresh();
        });
    }

    /** Pays a supplier bill (all or part of what is owed on it). */
    public function payBill(BusAgency $agency, BusAccDocument $bill, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $this->own($agency, $bill);
        $data = $this->validate($agency, $data, [
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'money_account_id' => ['required', 'string'],
            'staff_user_id' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
        ], ['money_account_id' => translate('paid from')]);

        return DB::transaction(function () use ($agency, $bill, $data, $userId) {
            $bill = BusAccDocument::whereKey($bill->id)->lockForUpdate()->first();
            if ($bill->type !== BusAccDocument::EXPENSE || $bill->status !== 'posted' || $bill->owed <= 0.004) {
                throw ValidationException::withMessages(['bill' => translate('Nothing is owed on this bill.')]);
            }
            $amount = round((float)$data['amount'], 2);
            if ($amount > $bill->owed + 0.004) {
                throw ValidationException::withMessages(['amount' => translate(key: 'Only {amount} is owed on this bill.', replace: ['amount' => $this->money($bill->owed)])]);
            }
            if (Carbon::parse($data['date'])->lt($bill->date)) {
                throw ValidationException::withMessages(['date' => translate('A bill cannot be paid before its date.')]);
            }
            $credit = $this->moneyLine($agency, $data, 0, $amount);
            $doc = $this->document($agency, BusAccDocument::BILL_PAYMENT, $data, $userId, [
                'amount' => $amount, 'parent_id' => (string)$bill->id, 'money_account_id' => (string)$credit[0]->id,
                'staff_user_id' => $credit['staff_user_id'] ?? null, 'supplier' => $bill->supplier,
            ]);
            $this->postDocument($agency, $doc, 'cash', translate('Bill paid') . ' · ' . $bill->number . ' · ' . $bill->supplier,
                [[$this->ledger->account($agency, 'suppliers'), $amount, 0, 'memo' => $bill->supplier], $credit], $userId);
            $bill->update(['owed' => round($bill->owed - $amount, 2)]);
            return $doc->fresh();
        });
    }

    /** Other income (parcels, luggage, bus hire...), received into a money account. */
    public function income(BusAgency $agency, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $data = $this->validate($agency, $data, [
            'date' => ['required', 'date'],
            'account_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'money_account_id' => ['required', 'string'],
            'staff_user_id' => ['nullable', 'string'],
            'vehicle_id' => ['nullable', 'string'],
            'destination_id' => ['nullable', 'string'],
            'departure_id' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['account_id' => translate('category'), 'money_account_id' => translate('received into')]);
        $category = $this->ownAccount($agency, $data['account_id'], 'revenue');
        $tags = $this->tags($agency, $data);
        $amount = round((float)$data['amount'], 2);

        return DB::transaction(function () use ($agency, $data, $userId, $category, $tags, $amount) {
            $debit = $this->moneyLine($agency, $data, $amount, 0);
            $doc = $this->document($agency, BusAccDocument::INCOME, $data, $userId, [
                'amount' => $amount, 'account_id' => (string)$category->id, 'money_account_id' => (string)$debit[0]->id, 'staff_user_id' => $debit['staff_user_id'] ?? null,
            ] + $tags);
            $this->postDocument($agency, $doc, 'cash', translate($category->name) . ($data['note'] ?? null ? ' · ' . $data['note'] : ''),
                [$debit, [$category, 0, $amount] + $tags], $userId);
            return $doc->fresh();
        });
    }

    /** Money moved between two of the branch's money accounts (cash to the bank, Mobile Money withdrawn...), with any charge taken. */
    public function transfer(BusAgency $agency, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $data = $this->validate($agency, $data, [
            'date' => ['required', 'date'],
            'money_account_id' => ['required', 'string'],
            'to_account_id' => ['required', 'string', 'different:money_account_id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['money_account_id' => translate('from'), 'to_account_id' => translate('to')]);
        $from = $this->ownAccount($agency, $data['money_account_id'], 'money');
        $to = $this->ownAccount($agency, $data['to_account_id'], 'money');
        if ($from->role === 'counter_cash' || $to->role === 'counter_cash') {
            throw ValidationException::withMessages(['money_account_id' => translate('Counter cash moves only through an agent cash count.')]);
        }
        $amount = round((float)$data['amount'], 2);
        $fee = round((float)($data['fee'] ?? 0), 2);

        return DB::transaction(function () use ($agency, $data, $userId, $from, $to, $amount, $fee) {
            $doc = $this->document($agency, BusAccDocument::TRANSFER, $data, $userId, [
                'amount' => $amount, 'fee' => $fee, 'money_account_id' => (string)$from->id, 'to_account_id' => (string)$to->id,
            ]);
            $lines = [[$to, $amount, 0], [$from, 0, $amount + $fee]];
            if ($fee > 0) {
                $lines[] = [$this->ledger->account($agency, 'charges'), $fee, 0, 'memo' => translate('Charge on the transfer')];
            }
            $this->postDocument($agency, $doc, 'cash', translate('Money moved') . ' · ' . translate($from->name) . ' → ' . translate($to->name), $lines, $userId);
            return $doc->fresh();
        });
    }

    // ------------------------------------------------------------------ counter cash

    /** What a counter agent holds now: the counter cash posted to their name (sales in, refunds and counts out). */
    public function agentCash(BusAgency $agency, string $userId): float
    {
        $this->sync->sync($agency);
        return $this->ledger->balance($this->ledger->account($agency, 'counter_cash'), null, ['staff_user_id' => $userId]);
    }

    /** Every agent holding counter cash, with what they hold. */
    public function agentsHolding(BusAgency $agency): array
    {
        $acc = BusAccAccount::where('agency_id', $agency->id)->where('role', 'counter_cash')->first();
        if (!$acc) {
            return [];
        }
        $rows = BusAccLine::where('agency_id', $agency->id)->where('account_id', $acc->id)->groupBy('staff_user_id')
            ->selectRaw('staff_user_id, sum(debit) - sum(credit) as held, max(date) as last')->get();
        $names = \Modules\UserManagement\Entities\User::whereIn('id', $rows->pluck('staff_user_id')->filter())->get()->keyBy(fn($u) => (string)$u->id);
        $lastCount = BusAccDocument::where('agency_id', $agency->id)->where('type', BusAccDocument::CASH_COUNT)->where('status', 'posted')
            ->groupBy('staff_user_id')->selectRaw('staff_user_id, max(created_at) as at')->pluck('at', 'staff_user_id');
        return $rows->filter(fn($r) => abs((float)$r->held) >= 0.005)->map(fn($r) => [
            'user_id' => $r->staff_user_id ? (string)$r->staff_user_id : null,
            'name' => $r->staff_user_id ? trim(($names[(string)$r->staff_user_id]->first_name ?? '') . ' ' . ($names[(string)$r->staff_user_id]->last_name ?? '')) ?: translate('Staff') : translate('Not linked to an agent'),
            'held' => round((float)$r->held, 2),
            'last_count' => $r->staff_user_id ? ($lastCount[(string)$r->staff_user_id] ?? null) : null,
        ])->sortByDesc('held')->values()->all();
    }

    /**
     * An agent's cash counted (end of a shift or a day): the counted cash goes to the safe or the
     * bank; a difference with what the agent should hold is posted as cash short or cash over.
     */
    public function cashCount(BusAgency $agency, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $data = $this->validate($agency, $data, [
            'staff_user_id' => ['required', 'string'],
            'counted' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'to_account_id' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['staff_user_id' => translate('agent'), 'to_account_id' => translate('put in')]);
        $data['date'] = today()->toDateString();
        $to = $this->ownAccount($agency, $data['to_account_id'], 'money');
        if ($to->role === 'counter_cash') {
            throw ValidationException::withMessages(['to_account_id' => translate('Choose where the counted cash goes: the safe or the bank.')]);
        }
        $agent = $data['staff_user_id'];
        $expected = $this->agentCash($agency, $agent);
        $counted = round((float)$data['counted'], 2);
        $diff = round($counted - $expected, 2);
        if (abs($diff) >= 0.005 && blank($data['note'] ?? null)) {
            throw ValidationException::withMessages(['note' => $diff < 0 ? translate('The cash is short: say why in the note.') : translate('The cash is over: say why in the note.')]);
        }
        if ($counted <= 0 && abs($diff) < 0.005) {
            throw ValidationException::withMessages(['counted' => translate('This agent holds no cash to count.')]);
        }

        return DB::transaction(function () use ($agency, $data, $userId, $to, $agent, $expected, $counted, $diff) {
            $cash = $this->ledger->account($agency, 'counter_cash');
            $doc = $this->document($agency, BusAccDocument::CASH_COUNT, $data, $userId, [
                'amount' => $counted, 'expected' => $expected, 'counted' => $counted, 'staff_user_id' => $agent,
                'money_account_id' => (string)$cash->id, 'to_account_id' => (string)$to->id,
            ]);
            // The counted cash goes in; what the agent should hold comes off their counter cash (to zero);
            // the difference is cash short (Dr 6581) or cash over (Cr 7581) on the agent.
            $lines = [[$to, $counted, 0]];
            $lines[] = $expected >= 0 ? [$cash, 0, $expected, 'staff_user_id' => $agent] : [$cash, -$expected, 0, 'staff_user_id' => $agent];
            if ($diff < 0) {
                $lines[] = [$this->ledger->account($agency, 'cash_short'), -$diff, 0, 'staff_user_id' => $agent, 'memo' => $data['note']];
            } elseif ($diff > 0) {
                $lines[] = [$this->ledger->account($agency, 'cash_over'), 0, $diff, 'staff_user_id' => $agent, 'memo' => $data['note']];
            }
            $this->postDocument($agency, $doc, 'cash', translate('Agent cash counted') . ' · ' . $this->userName($agent), $lines, $userId);
            return $doc->fresh();
        });
    }

    // ------------------------------------------------------------------ reconciliation

    /**
     * Movements of a money account not yet matched to a statement, up to a date (oldest first).
     * A reconciliation offers them; ticked ones are then never offered again.
     */
    public function unmatched(BusAccAccount $account, string $upTo)
    {
        return BusAccLine::with('entry')->where('agency_id', $account->agency_id)->where('account_id', $account->id)->where('date', '<=', $upTo)
            ->whereNotExists(fn($q) => $q->from('bus_acc_cleared')->whereColumn('bus_acc_cleared.line_id', 'bus_acc_lines.id'))
            ->orderBy('date')->orderBy('id')->limit(1000)->get();
    }

    /**
     * Reconciles a bank or Mobile Money account with its statement. The movements ticked are the
     * ones on the statement; the rest are timing (money in not yet on the statement, payments not
     * yet out of it):
     *     statement balance + in transit - outstanding = the balance the books should show.
     * It completes only when that agrees with the books, after posting any charges or interest the
     * statement shows (that posting is matched at once).
     */
    public function check(BusAgency $agency, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $data = $this->validate($agency, $data, [
            'date' => ['required', 'date'],
            'account_id' => ['required', 'string'],
            'counted' => ['required', 'numeric', 'min:-9999999999', 'max:9999999999'],
            'cleared' => ['nullable', 'array'],
            'cleared.*' => ['integer'],
            'post_as' => ['required', Rule::in(['none', 'charges', 'income'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['account_id' => translate('account'), 'counted' => translate('statement balance')]);
        $account = $this->ownAccount($agency, $data['account_id'], 'money');
        if ($account->role === 'counter_cash') {
            throw ValidationException::withMessages(['account_id' => translate('Counter cash is checked with an agent cash count.')]);
        }
        $this->sync->sync($agency);
        $date = Carbon::parse($data['date'])->toDateString();
        $p = $this->position($account, $date, (float)$data['counted'], array_map('intval', $data['cleared'] ?? []));
        $diff = $p['difference'];
        if ($data['post_as'] === 'charges' && $diff >= 0) {
            throw ValidationException::withMessages(['post_as' => translate('The statement is not below the books: there are no charges to post.')]);
        }
        if ($data['post_as'] === 'income' && $diff <= 0) {
            throw ValidationException::withMessages(['post_as' => translate('The statement is not above the books: there is no income to post.')]);
        }
        if ($data['post_as'] === 'none' && abs($diff) >= 0.005) {
            throw ValidationException::withMessages(['counted' => translate(key: 'The statement and the books differ by {amount}. Tick the movements that are on the statement, record what is missing, or post the difference as charges or interest.', replace: ['amount' => $this->money($diff)])]);
        }

        return DB::transaction(function () use ($agency, $data, $userId, $account, $p, $diff) {
            $doc = $this->document($agency, BusAccDocument::CHECK, $data, $userId, [
                'amount' => abs($diff), 'account_id' => (string)$account->id, 'expected' => $p['book'], 'counted' => $p['statement'],
                'lines' => ['cleared' => $p['ticked'], 'in_transit' => $p['in_transit'], 'outstanding' => $p['outstanding']],
            ]);
            $cleared = $p['ticked'];
            if ($data['post_as'] !== 'none' && abs($diff) >= 0.005) {
                $lines = $data['post_as'] === 'charges'
                    ? [[$this->ledger->account($agency, 'charges'), -$diff, 0], [$account, 0, -$diff]]
                    : [[$account, $diff, 0], [$this->ledger->account($agency, 'other_income'), 0, $diff]];
                $entry = $this->postDocument($agency, $doc, 'cash', translate('Statement reconciled') . ' · ' . translate($account->name), $lines, $userId);
                // The charge or interest is on the statement: matched at once.
                $cleared[] = (int)BusAccLine::where('entry_id', $entry->id)->where('account_id', $account->id)->value('id');
            }
            foreach ($cleared as $lineId) {
                DB::table('bus_acc_cleared')->insert(['agency_id' => (string)$agency->id, 'line_id' => $lineId, 'document_id' => (string)$doc->id, 'created_at' => now()]);
            }
            return $doc->fresh();
        });
    }

    /** The reconciliation figures for a statement balance and the movements ticked. */
    public function position(BusAccAccount $account, string $date, float $statement, array $ticked): array
    {
        $lines = $this->unmatched($account, $date);
        $ids = $lines->pluck('id')->map(fn($id) => (int)$id)->all();
        if (array_diff($ticked, $ids)) {
            throw ValidationException::withMessages(['cleared' => translate('A ticked movement is not one of this account, or was already matched. Reload the page.')]);
        }
        $unticked = $lines->reject(fn($l) => in_array((int)$l->id, $ticked, true));
        $inTransit = round($unticked->sum('debit'), 2);
        $outstanding = round($unticked->sum('credit'), 2);
        $book = $this->ledger->balance($account, $date);
        $adjusted = round($statement + $inTransit - $outstanding, 2);
        return ['book' => $book, 'statement' => round($statement, 2), 'in_transit' => $inTransit, 'outstanding' => $outstanding,
            'adjusted' => $adjusted, 'difference' => round($adjusted - $book, 2), 'ticked' => array_values($ticked)];
    }

    // ------------------------------------------------------------------ journal entries

    /** A journal entry by hand (corrections, accruals). It can reverse itself on a later date (the 1st of next month for an accrual). */
    public function journal(BusAgency $agency, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $data = $this->validate($agency, $data, [
            'date' => ['required', 'date'],
            'note' => ['required', 'string', 'max:500'],
            'reverse_on' => ['nullable', 'date', 'after:date'],
            'lines' => ['required', 'array', 'min:2', 'max:30'],
            'lines.*.account_id' => ['nullable', 'string'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'lines.*.memo' => ['nullable', 'string', 'max:200'],
            'lines.*.vehicle_id' => ['nullable', 'string'],
        ], ['note' => translate('description')]);
        $lines = [];
        $stored = [];
        foreach ($data['lines'] as $l) {
            if (blank($l['account_id'] ?? null) && (float)($l['debit'] ?? 0) == 0 && (float)($l['credit'] ?? 0) == 0) {
                continue;
            }
            $account = $this->ownAccount($agency, (string)($l['account_id'] ?? ''), 'any');
            if (in_array($account->role, ['counter_cash', 'unearned'], true)) {
                throw ValidationException::withMessages(['lines' => translate(key: '{account} moves only from tickets and cash counts.', replace: ['account' => $account->label()])]);
            }
            $tags = $this->tags($agency, ['vehicle_id' => $l['vehicle_id'] ?? null]);
            $lines[] = [$account, (float)($l['debit'] ?? 0), (float)($l['credit'] ?? 0), 'memo' => $l['memo'] ?? null] + $tags;
            $stored[] = ['account_id' => (string)$account->id, 'debit' => round((float)($l['debit'] ?? 0), 2), 'credit' => round((float)($l['credit'] ?? 0), 2), 'memo' => $l['memo'] ?? null, 'vehicle_id' => $tags['vehicle_id']];
        }

        return DB::transaction(function () use ($agency, $data, $userId, $lines, $stored) {
            $doc = $this->document($agency, BusAccDocument::JOURNAL, $data, $userId, [
                'amount' => round(array_sum(array_column($stored, 'debit')), 2), 'reverse_on' => $data['reverse_on'] ?? null, 'lines' => $stored,
            ]);
            $this->postDocument($agency, $doc, 'general', $data['note'], $lines, $userId);
            return $doc->fresh();
        });
    }

    // ------------------------------------------------------------------ void

    /** Voids a document: its entry is reversed (in its month if open, else today). The document stays, marked void. */
    public function void(BusAgency $agency, BusAccDocument $doc, string $reason, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $this->own($agency, $doc);
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => translate('Say why it is voided.')]);
        }
        return DB::transaction(function () use ($agency, $doc, $reason, $userId) {
            $doc = BusAccDocument::whereKey($doc->id)->lockForUpdate()->first();
            if ($doc->status !== 'posted') {
                throw ValidationException::withMessages(['document' => translate('It is already void.')]);
            }
            if ($doc->type === BusAccDocument::OPENING) {
                throw ValidationException::withMessages(['document' => translate('Opening balances are corrected with a journal entry.')]);
            }
            if ($doc->type === BusAccDocument::EXPENSE && BusAccDocument::where('parent_id', $doc->id)->where('status', 'posted')->exists()) {
                throw ValidationException::withMessages(['document' => translate('Payments were made on this bill: void them first.')]);
            }
            $memo = translate('Void') . ' · ' . $doc->number . ' · ' . $reason;
            $reversal = null;
            if ($doc->entry_id) {
                $reversal = $this->ledger->reverse(BusAccEntry::find($doc->entry_id), $memo, $userId, null, 'void:' . $doc->id);
            }
            if ($doc->auto_reverse_entry_id) {
                // The planned reversal already happened: undo it too, so nothing is left.
                $this->ledger->reverse(BusAccEntry::find($doc->auto_reverse_entry_id), $memo, $userId, null, 'void2:' . $doc->id);
            }
            if ($doc->type === BusAccDocument::CHECK) {
                if (BusAccDocument::where('agency_id', $doc->agency_id)->where('type', BusAccDocument::CHECK)->where('status', 'posted')
                    ->where('account_id', $doc->account_id)->where('created_at', '>', $doc->created_at)->exists()) {
                    throw ValidationException::withMessages(['document' => translate('A later reconciliation of this account exists: void it first.')]);
                }
                DB::table('bus_acc_cleared')->where('document_id', $doc->id)->delete();
            }
            if ($doc->type === BusAccDocument::BILL_PAYMENT && $doc->parent_id) {
                $bill = BusAccDocument::whereKey($doc->parent_id)->lockForUpdate()->first();
                $bill->update(['owed' => round($bill->owed + $doc->amount, 2)]);
            }
            $doc->update(['status' => 'void', 'void_entry_id' => $reversal ? (string)$reversal->id : null, 'void_reason' => mb_substr($reason, 0, 300),
                'voided_by' => $userId, 'voided_at' => now(), 'owed' => $doc->type === BusAccDocument::EXPENSE ? 0 : $doc->owed]);
            return $doc->fresh();
        });
    }

    // ------------------------------------------------------------------ months

    /**
     * Closes a month: nothing more posts into it (automatic postings land in the next open month).
     * Months close in order, and only once they are over.
     */
    public function closeMonth(BusAgency $agency, string $month, string $userId): array
    {
        $books = $this->needBooks($agency);
        $m = Carbon::parse($month)->startOfMonth();
        $problems = [];
        if ($m->copy()->endOfMonth()->gte(today())) {
            $problems[] = translate('A month closes once it is over.');
        }
        if ($m->lt($books->starts_on->copy()->startOfMonth())) {
            $problems[] = translate('This month is before the books start.');
        }
        $earlier = $books->starts_on->copy()->startOfMonth();
        while ($earlier->lt($m)) {
            if (!$this->ledger->monthClosed((string)$agency->id, $earlier)) {
                $problems[] = translate(key: 'Close {month} first: months close in order.', replace: ['month' => $earlier->translatedFormat('F Y')]);
                break;
            }
            $earlier->addMonth();
        }
        if ($this->ledger->monthClosed((string)$agency->id, $m)) {
            $problems[] = translate('This month is already closed.');
        }
        $this->sync->sync($agency);
        $pending = BusMoneyMove::where('agency_id', $agency->id)->whereNull('entry_id')->whereNull('skipped')->where('at', '<', $m->copy()->addMonth())->count();
        if ($pending > 0) {
            $problems[] = translate(key: '{n} ticket payments or refunds of this month are not in the books yet.', replace: ['n' => $pending]);
        }
        if ($problems) {
            throw ValidationException::withMessages(['month' => $problems]);
        }
        return DB::transaction(function () use ($agency, $m, $userId) {
            BusAccBook::where('agency_id', $agency->id)->lockForUpdate()->first();
            DB::table('bus_acc_months')->updateOrInsert(['agency_id' => (string)$agency->id, 'month' => $m->toDateString()],
                ['closed_at' => now(), 'closed_by' => $userId, 'updated_at' => now(), 'created_at' => now()]);
            return ['month' => $m->toDateString()];
        });
    }

    /** Reopens the last closed month, with the reason (kept). Only the manager. */
    public function reopenMonth(BusAgency $agency, string $month, string $reason, string $userId): array
    {
        $this->needBooks($agency);
        $m = Carbon::parse($month)->startOfMonth();
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => translate('Say why the month is reopened.')]);
        }
        if (!$this->ledger->monthClosed((string)$agency->id, $m)) {
            throw ValidationException::withMessages(['month' => translate('This month is not closed.')]);
        }
        if (DB::table('bus_acc_months')->where('agency_id', $agency->id)->where('month', '>', $m->toDateString())->whereNotNull('closed_at')->exists()) {
            throw ValidationException::withMessages(['month' => translate('Reopen the later months first.')]);
        }
        DB::table('bus_acc_months')->where('agency_id', $agency->id)->where('month', $m->toDateString())
            ->update(['closed_at' => null, 'reopened_at' => now(), 'reopened_by' => $userId, 'reopen_reason' => mb_substr(trim($reason), 0, 300), 'updated_at' => now()]);
        return ['month' => $m->toDateString()];
    }

    // ------------------------------------------------------------------ chart of accounts

    public function saveAccount(BusAgency $agency, array $data, ?BusAccAccount $account = null): BusAccAccount
    {
        $this->needBooks($agency);
        if ($account) {
            $this->own($agency, $account);
            $v = Validator::make($data, ['name' => ['required', 'string', 'max:120'], 'active' => ['nullable', 'boolean']]);
            $v->validate();
            $active = array_key_exists('active', $data) ? (bool)$data['active'] : $account->active;
            if (!$active && $account->active) {
                if ($account->role) {
                    throw ValidationException::withMessages(['active' => translate('This account is used by automatic postings and stays on.')]);
                }
                if (abs($this->ledger->balance($account)) >= 0.005) {
                    throw ValidationException::withMessages(['active' => translate('An account with a balance stays on. Move its balance first.')]);
                }
            }
            $account->update(['name' => trim($data['name']), 'active' => $active]);
            return $account;
        }
        $v = Validator::make($data, [
            'code' => ['required', 'regex:/^[1-8][0-9]{2,7}$/', Rule::unique('bus_acc_accounts', 'code')->where('agency_id', $agency->id)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(['asset', 'liability'])],
            'money' => ['nullable', 'boolean'],
        ], ['code.regex' => translate('An OHADA account number: 3 to 8 digits, starting with the class (1 to 8).')], ['code' => translate('number')]);
        $v->validate();
        $class = (int)$data['code'][0];
        $type = AccChart::typeForClass($class) ?? ($data['type'] ?? null);
        if (!$type) {
            throw ValidationException::withMessages(['type' => translate('Say whether this account is something the branch has or owes.')]);
        }
        return BusAccAccount::create([
            'agency_id' => (string)$agency->id, 'code' => $data['code'], 'name' => trim($data['name']), 'class' => $class, 'type' => $type,
            'money' => $class === 5 && !empty($data['money']), 'role' => null, 'category' => in_array($class, [6, 7], true), 'active' => true,
        ]);
    }

    public function saveSettings(BusAgency $agency, array $data): BusAccBook
    {
        $books = $this->needBooks($agency);
        Validator::make($data, [
            'refunds_from' => ['required', Rule::in(['mtn', 'orange', 'bank', 'safe', 'fapshi'])],
            'eride_from' => ['required', Rule::in(['mtn', 'orange', 'bank', 'safe', 'fapshi'])],
        ])->validate();
        $books->update(['settings' => array_merge($books->settings ?? [], ['refunds_from' => $data['refunds_from'], 'eride_from' => $data['eride_from']])]);
        return $books;
    }

    // ------------------------------------------------------------------ helpers

    public function needBooks(BusAgency $agency): BusAccBook
    {
        $books = $this->ledger->books($agency);
        if (!$books) {
            throw ValidationException::withMessages(['books' => translate('Open the books first.')]);
        }
        return $books;
    }

    protected function validate(BusAgency $agency, array $data, array $rules, array $names = []): array
    {
        return Validator::make($data, $rules, [], $names + ['date' => translate('date'), 'amount' => translate('amount')])->validate();
    }

    /** An account of this branch, active, of the kind asked (expense / revenue category, money, any). */
    protected function ownAccount(BusAgency $agency, string $id, string $kind): BusAccAccount
    {
        $a = BusAccAccount::where('agency_id', $agency->id)->where('id', $id)->first();
        $ok = $a && $a->active && match ($kind) {
            'expense' => $a->type === 'expense' && $a->category,
            'revenue' => $a->type === 'revenue' && $a->category,
            'money' => $a->money,
            default => true,
        };
        if (!$ok) {
            throw ValidationException::withMessages(['account_id' => translate('Choose an account from the list.')]);
        }
        return $a;
    }

    /** The money line of a payment or receipt; counter cash needs the agent whose cash it is. */
    protected function moneyLine(BusAgency $agency, array $data, float $debit, float $credit): array
    {
        $acc = $this->ownAccount($agency, (string)($data['money_account_id'] ?? ''), 'money');
        $line = [$acc, $debit, $credit];
        if ($acc->role === 'counter_cash') {
            $agent = (string)($data['staff_user_id'] ?? '');
            if ($agent === '' || !BusStaff::where('agency_id', $agency->id)->where('user_id', $agent)->exists()) {
                throw ValidationException::withMessages(['staff_user_id' => translate('Say which agent cash it is.')]);
            }
            $line['staff_user_id'] = $agent;
        }
        return $line;
    }

    /** Tags given by hand (a bus, a route, a trip of this branch or its company). */
    protected function tags(BusAgency $agency, array $data): array
    {
        $tags = ['vehicle_id' => null, 'destination_id' => null, 'departure_id' => null];
        if (filled($data['departure_id'] ?? null)) {
            $d = BusDeparture::where('agency_id', $agency->id)->where('id', $data['departure_id'])->first();
            if (!$d) {
                throw ValidationException::withMessages(['departure_id' => translate('Choose a trip from the list.')]);
            }
            $tags = ['departure_id' => (string)$d->id, 'vehicle_id' => $d->vehicle_id ? (string)$d->vehicle_id : null, 'destination_id' => (string)$d->destination_id];
        }
        if (filled($data['vehicle_id'] ?? null)) {
            $bus = BusVehicle::usableByAgency($agency)->where('bus_vehicles.id', $data['vehicle_id'])->first();
            if (!$bus) {
                throw ValidationException::withMessages(['vehicle_id' => translate('Choose a bus from the list.')]);
            }
            $tags['vehicle_id'] = (string)$bus->id;
        }
        if (filled($data['destination_id'] ?? null)) {
            if (!BusDestination::where('agency_id', $agency->id)->where('id', $data['destination_id'])->exists()) {
                throw ValidationException::withMessages(['destination_id' => translate('Choose a route from the list.')]);
            }
            $tags['destination_id'] = (string)$data['destination_id'];
        }
        return $tags;
    }

    protected function document(BusAgency $agency, string $type, array $data, string $userId, array $fields): BusAccDocument
    {
        return BusAccDocument::create($fields + [
            'agency_id' => (string)$agency->id,
            'number' => $this->ledger->nextDocumentNumber((string)$agency->id, $type),
            'type' => $type,
            'date' => Carbon::parse($data['date'])->toDateString(),
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => $userId,
            'status' => 'posted',
        ]);
    }

    protected function postDocument(BusAgency $agency, BusAccDocument $doc, string $journal, string $memo, array $lines, string $userId): BusAccEntry
    {
        $entry = $this->ledger->post($agency, [
            'date' => $doc->date, 'journal' => $journal, 'memo' => $doc->number . ' · ' . $memo, 'lines' => $lines,
            'source_type' => 'document', 'source_id' => (string)$doc->id, 'source_key' => 'doc:' . $doc->id, 'created_by' => $userId,
        ]);
        $doc->update(['entry_id' => (string)$entry->id]);
        return $entry;
    }

    protected function own(BusAgency $agency, $model): void
    {
        abort_unless((string)$model->agency_id === (string)$agency->id, 404);
    }

    public function userName(?string $id): string
    {
        $u = $id ? \Modules\UserManagement\Entities\User::find($id) : null;
        return $u ? trim($u->first_name . ' ' . $u->last_name) : translate('Not linked to an agent');
    }

    public function money(float $amount): string
    {
        return \Modules\BusManagement\Lib\AccFormat::n($amount) . ' FCFA';
    }
}
