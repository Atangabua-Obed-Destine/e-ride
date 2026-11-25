<?php

namespace Modules\BusManagement\Service\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAccAccount;
use Modules\BusManagement\Entities\BusAccAsset;
use Modules\BusManagement\Entities\BusAccBook;
use Modules\BusManagement\Entities\BusAccDocument;
use Modules\BusManagement\Entities\BusAccEntry;
use Modules\BusManagement\Entities\BusAccLine;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompany;
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
     * Opens a set of books: a branch's, or a head office's (its own books, beside its branches').
     * The chart of accounts, the start date, the default accounts and the opening balances (money
     * on hand, what is owed to suppliers and lenders). What the records already know is added:
     * for a branch, tickets paid for trips still ahead, refunds owed, E-Ride statements not paid,
     * and what the head office holds for it; for a head office, what it holds for each branch.
     */
    public function open(BusAgency|BusCompany $agency, array $data, string $userId): BusAccDocument
    {
        $company = $agency instanceof BusCompany;
        if ($this->ledger->books($agency)) {
            throw ValidationException::withMessages(['books' => $company ? translate('The head office books are already open.') : translate('The books of this branch are already open.')]);
        }
        $v = Validator::make($data, [
            'starts_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . today()->subDays(92)->toDateString()],
            'balances' => ['array'],
            'balances.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'suppliers_owed' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'loans' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'refunds_from' => ['nullable', Rule::in(['mtn', 'orange', 'bank', 'safe', 'fapshi'])],
            'eride_from' => ['nullable', Rule::in(['mtn', 'orange', 'bank', 'safe', 'fapshi'])],
        ], [], ['starts_on' => translate('start date')]);
        $v->validate();
        $start = Carbon::parse($data['starts_on'])->startOfDay();

        return DB::transaction(function () use ($agency, $data, $userId, $start, $company) {
            BusAccBook::create([
                'agency_id' => (string)$agency->id, 'kind' => $company ? 'company' : 'branch', 'starts_on' => $start->toDateString(), 'opened_by' => $userId,
                'settings' => $company ? ['eride_from' => $data['eride_from'] ?? 'bank']
                    : ['refunds_from' => $data['refunds_from'] ?? 'mtn', 'eride_from' => $data['eride_from'] ?? 'mtn'],
            ]);
            $this->ledger->seedChart($agency);
            $acc = fn(string $role) => $this->ledger->account($agency, $role);
            $lines = [];
            $debits = 0.0;
            $credits = 0.0;
            $records = $company ? $this->companyOpening($agency, $start) : $this->openingFromRecords($agency, $start);
            $fromRecords = collect($records)->map(fn($l) => (string)$l[0]->id)->all();
            foreach ($company ? AccChart::COMPANY_OPENING_MONEY : AccChart::OPENING_MONEY as $role) {
                $amount = round((float)($data['balances'][$role] ?? 0), 2);
                // What the head office holds for the branch comes from the head office books when they exist.
                if ($amount > 0 && !in_array((string)$acc($role)->id, $fromRecords, true)) {
                    $lines[] = [$acc($role), $amount, 0];
                    $debits += $amount;
                }
            }
            foreach (['suppliers_owed' => ['suppliers', 'Owed to suppliers at the start'], 'loans' => ['loans', 'Loans at the start']] as $field => [$role, $memo]) {
                $owed = round((float)($data[$field] ?? 0), 2);
                if ($owed > 0) {
                    $lines[] = [$acc($role), 0, $owed, 'memo' => translate($memo)];
                    $credits += $owed;
                }
            }
            foreach ($records as $l) {
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

    /** A head office's opening from the records: what it holds for each branch that already keeps books (the mirror of the branch's 1811). */
    public function companyOpening(BusCompany $company, Carbon $start): array
    {
        $lines = [];
        foreach (BusAgency::where('company_id', $company->id)->get() as $branch) {
            if (!$this->ledger->books($branch)) {
                continue;
            }
            $held = $this->ledger->balance($this->ledger->account($branch, 'head_office'), $start->copy()->subDay()->toDateString());
            if (abs($held) >= 0.005) {
                $acc = $this->ledger->branchAccount($company, $branch);
                $lines[] = $held > 0 ? [$acc, 0, $held, 'memo' => translate('Held for the branch at the start')] : [$acc, -$held, 0, 'memo' => translate('Held for the branch at the start')];
            }
        }
        return $lines;
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
        // What the head office holds for the branch, when the head office already keeps books: the same figure on both sides.
        if ($agency->company_id && ($company = BusCompany::find($agency->company_id)) && $this->ledger->books($company)) {
            $held = $this->ledger->balance($this->ledger->branchAccount($company, $agency), $start->copy()->subDay()->toDateString());
            if (abs($held) >= 0.005) {
                $lines[] = $held > 0 ? [$acc('head_office'), $held, 0, 'memo' => translate('Held by the head office at the start')]
                    : [$acc('head_office'), 0, -$held, 'memo' => translate('Held by the head office at the start')];
            }
        }
        return $lines;
    }

    // ------------------------------------------------------------------ spending and income

    /** An expense, paid now from a money account, or a supplier bill to pay later. */
    public function expense(BusAgency|BusCompany $agency, array $data, string $userId): BusAccDocument
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
            'vat' => ['nullable', 'numeric', 'min:0', 'lt:amount'],
        ], ['account_id' => translate('category'), 'money_account_id' => translate('paid from'), 'supplier' => translate('supplier'), 'vat' => translate('VAT on the invoice')]);
        $category = $this->ownAccount($agency, $data['account_id'], 'expense');
        $vat = $this->vatRegistered($agency) ? round((float)($data['vat'] ?? 0), 2) : 0.0;
        $tags = $this->tags($agency, $data);
        $amount = round((float)$data['amount'], 2);
        $later = $data['paid'] === 'later';

        return DB::transaction(function () use ($agency, $data, $userId, $category, $tags, $amount, $later, $vat) {
            $credit = $later ? [$this->ledger->account($agency, 'suppliers'), 0, $amount, 'memo' => $data['supplier']]
                : $this->moneyLine($agency, $data, 0, $amount);
            $doc = $this->document($agency, BusAccDocument::EXPENSE, $data, $userId, [
                'amount' => $amount, 'account_id' => (string)$category->id,
                'money_account_id' => $later ? null : (string)$credit[0]->id,
                'staff_user_id' => $credit['staff_user_id'] ?? null,
                'supplier' => $data['supplier'] ?? null, 'owed' => $later ? $amount : 0, 'due_on' => $later ? ($data['due_on'] ?? null) : null,
            ] + $tags);
            $memo = $category->displayName() . ($data['supplier'] ?? null ? ' · ' . $data['supplier'] : '') . ($data['note'] ?? null ? ' · ' . $data['note'] : '');
            $debits = $vat > 0 ? [[$category, $amount - $vat, 0] + $tags, [$this->ledger->account($agency, 'vat_in'), $vat, 0]] : [[$category, $amount, 0] + $tags];
            if ($vat > 0) {
                $doc->update(['lines' => ['vat' => $vat]]);
            }
            $this->postDocument($agency, $doc, $later ? 'purchase' : 'cash', $memo, array_merge($debits, [$credit]), $userId);
            return $doc->fresh();
        });
    }

    /** Pays a supplier bill (all or part of what is owed on it). */
    public function payBill(BusAgency|BusCompany $agency, BusAccDocument $bill, array $data, string $userId): BusAccDocument
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
    public function income(BusAgency|BusCompany $agency, array $data, string $userId): BusAccDocument
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
            'vat_included' => ['nullable', 'boolean'],
        ], ['account_id' => translate('category'), 'money_account_id' => translate('received into')]);
        $category = $this->ownAccount($agency, $data['account_id'], 'revenue');
        $vatIn = $this->vatRegistered($agency) && !empty($data['vat_included']);
        $tags = $this->tags($agency, $data);
        $amount = round((float)$data['amount'], 2);

        return DB::transaction(function () use ($agency, $data, $userId, $category, $tags, $amount, $vatIn) {
            $debit = $this->moneyLine($agency, $data, $amount, 0);
            $doc = $this->document($agency, BusAccDocument::INCOME, $data, $userId, [
                'amount' => $amount, 'account_id' => (string)$category->id, 'money_account_id' => (string)$debit[0]->id, 'staff_user_id' => $debit['staff_user_id'] ?? null,
            ] + $tags);
            $vat = $vatIn ? round($amount - $amount / (1 + $this->vatRateOf($agency) / 100), 2) : 0.0;
            $credits = $vat > 0 ? [[$category, 0, $amount - $vat] + $tags, [$this->ledger->account($agency, 'vat_out'), 0, $vat]] : [[$category, 0, $amount] + $tags];
            if ($vat > 0) {
                $doc->update(['lines' => ['vat' => $vat]]);
            }
            $this->postDocument($agency, $doc, 'cash', $category->displayName() . ($data['note'] ?? null ? ' · ' . $data['note'] : ''),
                array_merge([$debit], $credits), $userId);
            return $doc->fresh();
        });
    }

    /** Money moved between two of the branch's money accounts (cash to the bank, Mobile Money withdrawn...), with any charge taken. */
    public function transfer(BusAgency|BusCompany $agency, array $data, string $userId): BusAccDocument
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
            $this->postDocument($agency, $doc, 'cash', translate('Money moved') . ' · ' . $from->displayName() . ' → ' . $to->displayName(), $lines, $userId);
            return $doc->fresh();
        });
    }

    // ------------------------------------------------------------------ counter cash

    /** What a counter agent holds now: the counter cash posted to their name (sales in, refunds and counts out). */
    public function agentCash(BusAgency|BusCompany $agency, string $userId): float
    {
        $this->sync->sync($agency);
        return $this->ledger->balance($this->ledger->account($agency, 'counter_cash'), null, ['staff_user_id' => $userId]);
    }

    /** Every agent holding counter cash, with what they hold. */
    public function agentsHolding(BusAgency|BusCompany $agency): array
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
    public function cashCount(BusAgency|BusCompany $agency, array $data, string $userId): BusAccDocument
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
    public function check(BusAgency|BusCompany $agency, array $data, string $userId): BusAccDocument
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
                $entry = $this->postDocument($agency, $doc, 'cash', translate('Statement reconciled') . ' · ' . $account->displayName(), $lines, $userId);
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
    public function journal(BusAgency|BusCompany $agency, array $data, string $userId): BusAccDocument
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
    public function void(BusAgency|BusCompany $agency, BusAccDocument $doc, string $reason, string $userId): BusAccDocument
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
            $asset = in_array($doc->type, [BusAccDocument::ASSET, BusAccDocument::DISPOSAL], true)
                ? BusAccAsset::where($doc->type === BusAccDocument::ASSET ? 'document_id' : 'disposal_document_id', $doc->id)->lockForUpdate()->first() : null;
            if ($doc->type === BusAccDocument::ASSET && $asset) {
                if ($asset->status !== 'active') {
                    throw ValidationException::withMessages(['document' => translate('This bus was sold or retired: void that first.')]);
                }
                if ($this->wornSoFar($asset) >= 0.005) {
                    throw ValidationException::withMessages(['document' => translate('Its wear is already posted: sell or retire the bus instead, or correct it with a journal entry.')]);
                }
                $asset->update(['status' => 'void']);
            }
            if ($doc->type === BusAccDocument::DISPOSAL && $asset) {
                $asset->update(['status' => 'active', 'disposed_on' => null, 'sale_price' => null, 'disposal_document_id' => null]);
            }
            if ($doc->type === BusAccDocument::BRANCH_MONEY && $doc->parent_id) {
                // Both sides go together: the other book's document is voided with it.
                $other = BusAccDocument::whereKey($doc->parent_id)->lockForUpdate()->first();
                if ($other && $other->status === 'posted') {
                    $memo2 = translate('Void') . ' · ' . $other->number . ' · ' . $reason;
                    $rev2 = $other->entry_id ? $this->ledger->reverse(BusAccEntry::find($other->entry_id), $memo2, $userId, null, 'void:' . $other->id) : null;
                    $other->update(['status' => 'void', 'void_entry_id' => $rev2 ? (string)$rev2->id : null, 'void_reason' => mb_substr($reason, 0, 300), 'voided_by' => $userId, 'voided_at' => now()]);
                }
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
            if ($doc->type === BusAccDocument::PAYROLL && BusAccDocument::where('parent_id', $doc->id)->where('type', BusAccDocument::STAFF_PAY)->where('status', 'posted')->exists()) {
                throw ValidationException::withMessages(['document' => translate('The staff were paid on this payroll: void that payment first.')]);
            }
            if ($doc->type === BusAccDocument::STAFF_PAY && $doc->parent_id) {
                $payroll = BusAccDocument::whereKey($doc->parent_id)->lockForUpdate()->first();
                $payroll->update(['owed' => round($payroll->owed + $doc->amount, 2)]);
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
    public function closeMonth(BusAgency|BusCompany $agency, string $month, string $userId): array
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
    public function reopenMonth(BusAgency|BusCompany $agency, string $month, string $reason, string $userId): array
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

    public function saveAccount(BusAgency|BusCompany $agency, array $data, ?BusAccAccount $account = null): BusAccAccount
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

    public function saveSettings(BusAgency|BusCompany $agency, array $data): BusAccBook
    {
        $books = $this->needBooks($agency);
        Validator::make($data, [
            'refunds_from' => [$agency instanceof BusCompany ? 'nullable' : 'required', Rule::in(['mtn', 'orange', 'bank', 'safe', 'fapshi'])],
            'eride_from' => ['required', Rule::in(['mtn', 'orange', 'bank', 'safe', 'fapshi'])],
        ])->validate();
        Validator::make($data, [
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'pay_rates' => ['nullable', 'array'],
            'pay_rates.*' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ])->validate();
        $extra = [];
        if (array_key_exists('vat_registered', $data)) {
            $extra['vat_registered'] = (bool)$data['vat_registered'];
            $extra['vat_on_tickets'] = (bool)($data['vat_on_tickets'] ?? false) && $extra['vat_registered'];
            $extra['vat_rate'] = round((float)($data['vat_rate'] ?? 19.25), 2);
        }
        if (isset($data['pay_rates']) && is_array($data['pay_rates'])) {
            $extra['pay_rates'] = array_map(fn($v) => round((float)$v, 3), array_intersect_key($data['pay_rates'], \Modules\BusManagement\Lib\CameroonPayroll::EMPLOYER_DEFAULTS));
        }
        $books->update(['settings' => array_merge($books->settings ?? [], array_filter(['refunds_from' => $data['refunds_from'] ?? null, 'eride_from' => $data['eride_from']]), $extra)]);
        return $books;
    }

    // ------------------------------------------------------------------ buses as assets

    /** Where a bus's value is kept: the head office's books for a company, the agency's own books otherwise. */
    public function assetOwner(BusAgency|BusCompany $owner): void
    {
        if ($owner instanceof BusAgency && $owner->company_id) {
            throw ValidationException::withMessages(['vehicle_id' => translate('The buses of a company are carried in the head office books.')]);
        }
    }

    /**
     * A bus comes into the books as an asset: its value, the date its wear starts (that month), how
     * long it runs and what it is worth at the end. Brought in by the owner (Cr capital), paid from
     * a money account, or owed (to a supplier or a lender).
     */
    public function addAsset(BusAgency|BusCompany $agency, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $this->assetOwner($agency);
        $data = $this->validate($agency, $data, [
            'date' => ['required', 'date'],
            'vehicle_id' => ['required', 'string'],
            'value' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'end_value' => ['nullable', 'numeric', 'min:0', 'lt:value'],
            'years' => ['required', 'numeric', 'min:0.5', 'max:40'],
            'funded_by' => ['required', Rule::in(['owner', 'paid', 'owed'])],
            'money_account_id' => ['required_if:funded_by,paid', 'nullable', 'string'],
            'staff_user_id' => ['nullable', 'string'],
            'owed_to' => ['required_if:funded_by,owed', 'nullable', Rule::in(['supplier', 'loan'])],
            'supplier' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['vehicle_id' => translate('bus'), 'value' => translate('value'), 'end_value' => translate('value at the end'), 'years' => translate('years')]);
        $bus = $this->ownBus($agency, $data['vehicle_id']);
        if (BusAccAsset::where('vehicle_id', $bus->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['vehicle_id' => translate('This bus is already in the books.')]);
        }
        $value = round((float)$data['value'], 2);
        $end = round((float)($data['end_value'] ?? 0), 2);
        $months = max(1, (int)round((float)$data['years'] * 12));

        return DB::transaction(function () use ($agency, $data, $userId, $bus, $value, $end, $months) {
            $credit = match ($data['funded_by']) {
                'owner' => [$this->ledger->account($agency, 'capital'), 0, $value, 'memo' => translate('Bus brought in by the owner')],
                'paid' => $this->moneyLine($agency, $data, 0, $value),
                default => [$this->ledger->account($agency, ($data['owed_to'] ?? 'supplier') === 'loan' ? 'loans' : 'suppliers'), 0, $value, 'memo' => $data['supplier'] ?? null],
            };
            $doc = $this->document($agency, BusAccDocument::ASSET, $data, $userId, [
                'amount' => $value, 'account_id' => (string)$this->ledger->account($agency, 'buses')->id, 'vehicle_id' => (string)$bus->id,
                'money_account_id' => $data['funded_by'] === 'paid' ? (string)$credit[0]->id : null, 'staff_user_id' => $credit['staff_user_id'] ?? null,
                'supplier' => $data['funded_by'] === 'owed' ? ($data['supplier'] ?? null) : null,
                'owed' => $data['funded_by'] === 'owed' && ($data['owed_to'] ?? 'supplier') === 'supplier' ? $value : 0,
                'lines' => ['end_value' => $end, 'life_months' => $months, 'funded_by' => $data['funded_by'], 'owed_to' => $data['owed_to'] ?? null],
            ]);
            BusAccAsset::create([
                'agency_id' => (string)$agency->id, 'vehicle_id' => (string)$bus->id, 'name' => $bus->registration . ($bus->name ? ' · ' . $bus->name : ''),
                'value' => $value, 'end_value' => $end, 'life_months' => $months, 'starts_on' => $doc->date->toDateString(),
                'funded_by' => $data['funded_by'], 'status' => 'active', 'document_id' => (string)$doc->id,
            ]);
            $this->postDocument($agency, $doc, 'general', translate('Bus added to the books') . ' · ' . $bus->registration,
                [[$this->ledger->account($agency, 'buses'), $value, 0, 'vehicle_id' => (string)$bus->id], $credit], $userId);
            return $doc->fresh();
        });
    }

    /** How much of a bus's value has worn off in the books (its share of 2845). */
    public function wornSoFar(BusAccAsset $asset): float
    {
        return -$this->ledger->balance($this->ledger->account($asset->agency_id, 'bus_wear'), null, ['vehicle_id' => (string)$asset->vehicle_id]);
    }

    /**
     * A bus leaves the books: sold (price received into a money account) or retired. Its wear is
     * brought up to date first (every month ended before), then its cost and wear come off: the
     * value left is charged (8120) and the sale price is income (8220).
     */
    public function disposeAsset(BusAgency|BusCompany $agency, BusAccAsset $asset, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($agency);
        $this->own($agency, $asset);
        $data = $this->validate($agency, $data, [
            'date' => ['required', 'date'],
            'how' => ['required', Rule::in(['sold', 'retired'])],
            'price' => ['required_if:how,sold', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'money_account_id' => ['required_if:how,sold', 'nullable', 'string'],
            'staff_user_id' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['price' => translate('sale price')]);
        if ($asset->status !== 'active') {
            throw ValidationException::withMessages(['asset' => translate('This bus already left the books.')]);
        }
        $date = Carbon::parse($data['date']);
        if ($date->lt($asset->starts_on)) {
            throw ValidationException::withMessages(['date' => translate('A bus cannot leave the books before it came in.')]);
        }

        return DB::transaction(function () use ($agency, $asset, $data, $userId, $date) {
            // The wear of every month ended before the sale.
            $this->sync->depreciate($this->ledger->books($agency), $date->copy()->startOfMonth()->subDay());
            $worn = round($this->wornSoFar($asset), 2);
            $left = round($asset->value - $worn, 2);
            $price = $data['how'] === 'sold' ? round((float)($data['price'] ?? 0), 2) : 0.0;
            $tag = ['vehicle_id' => (string)$asset->vehicle_id];
            $lines = [
                [$this->ledger->account($agency, 'bus_wear'), $worn, 0] + $tag,
                [$this->ledger->account($agency, 'disposal_value'), $left, 0] + $tag,
                [$this->ledger->account($agency, 'buses'), 0, $asset->value] + $tag,
            ];
            $money = null;
            if ($price > 0) {
                $money = $this->moneyLine($agency, $data, $price, 0);
                $lines[] = $money;
                $lines[] = [$this->ledger->account($agency, 'disposal_price'), 0, $price] + $tag;
            }
            $doc = $this->document($agency, BusAccDocument::DISPOSAL, $data, $userId, [
                'amount' => $price, 'vehicle_id' => (string)$asset->vehicle_id, 'parent_id' => (string)$asset->document_id,
                'money_account_id' => $money ? (string)$money[0]->id : null, 'staff_user_id' => $money['staff_user_id'] ?? null,
                'expected' => $left, 'counted' => $price, 'lines' => ['how' => $data['how'], 'worn' => $worn, 'value' => $asset->value, 'asset_id' => (string)$asset->id],
            ]);
            $this->postDocument($agency, $doc, 'general', ($data['how'] === 'sold' ? translate('Bus sold') : translate('Bus retired')) . ' · ' . $asset->name, $lines, $userId);
            $asset->update(['status' => $data['how'], 'disposed_on' => $date->toDateString(), 'sale_price' => $price, 'disposal_document_id' => (string)$doc->id]);
            return $doc->fresh();
        });
    }

    protected function ownBus(BusAgency|BusCompany $agency, string $id): BusVehicle
    {
        $bus = $agency instanceof BusCompany
            ? BusVehicle::where('company_id', $agency->id)->find($id)
            : BusVehicle::usableByAgency($agency)->where('bus_vehicles.id', $id)->first();
        if (!$bus) {
            throw ValidationException::withMessages(['vehicle_id' => translate('Choose a bus from the list.')]);
        }
        return $bus;
    }

    // ------------------------------------------------------------------ head office and branches

    /**
     * Money between the head office and a branch (takings handed in, cash sent to a branch). Posted
     * in both books at once, so the head office's account of the branch and the branch's 1811 agree.
     */
    public function branchMoney(BusCompany $company, array $data, string $userId): BusAccDocument
    {
        $this->needBooks($company);
        $data = $this->validate($company, $data, [
            'date' => ['required', 'date'],
            'branch_id' => ['required', 'string'],
            'direction' => ['required', Rule::in(['from_branch', 'to_branch'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'money_account_id' => ['required', 'string'],
            'branch_account_id' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['branch_id' => translate('branch'), 'money_account_id' => translate('head office account'), 'branch_account_id' => translate('branch account')]);
        $branch = BusAgency::where('company_id', $company->id)->find($data['branch_id']);
        if (!$branch) {
            throw ValidationException::withMessages(['branch_id' => translate('Choose a branch from the list.')]);
        }
        if (!$this->ledger->books($branch)) {
            throw ValidationException::withMessages(['branch_id' => translate('This branch has not opened its books yet.')]);
        }
        $hoMoney = $this->ownAccount($company, $data['money_account_id'], 'money');
        $brMoney = $this->ownAccount($branch, $data['branch_account_id'], 'money');
        if ($brMoney->role === 'counter_cash') {
            throw ValidationException::withMessages(['branch_account_id' => translate('Count the agent cash into the safe first: counter cash moves only through a count.')]);
        }
        $amount = round((float)$data['amount'], 2);
        $in = $data['direction'] === 'from_branch';

        return DB::transaction(function () use ($company, $branch, $data, $userId, $hoMoney, $brMoney, $amount, $in) {
            $liaison = $this->ledger->branchAccount($company, $branch);
            $hoDoc = $this->document($company, BusAccDocument::BRANCH_MONEY, $data, $userId, [
                'amount' => $amount, 'account_id' => (string)$liaison->id, 'money_account_id' => (string)$hoMoney->id, 'destination_id' => null,
                'lines' => ['branch_id' => (string)$branch->id, 'direction' => $data['direction']],
            ]);
            $brDoc = $this->document($branch, BusAccDocument::BRANCH_MONEY, $data, $userId, [
                'amount' => $amount, 'account_id' => (string)$this->ledger->account($branch, 'head_office')->id, 'money_account_id' => (string)$brMoney->id,
                'parent_id' => (string)$hoDoc->id, 'lines' => ['company_id' => (string)$company->id, 'direction' => $data['direction']],
            ]);
            $hoDoc->update(['parent_id' => (string)$brDoc->id]);
            $memo = $in ? translate('Money handed in by the branch') . ' · ' . $branch->branchLabel() : translate('Money sent to the branch') . ' · ' . $branch->branchLabel();
            $this->postDocument($company, $hoDoc, 'cash', $memo, $in ? [[$hoMoney, $amount, 0], [$liaison, 0, $amount]] : [[$liaison, $amount, 0], [$hoMoney, 0, $amount]], $userId);
            $ho = $this->ledger->account($branch, 'head_office');
            $memoBr = $in ? translate('Money handed in to the head office') : translate('Money received from the head office');
            $this->postDocument($branch, $brDoc, 'cash', $memoBr, $in ? [[$ho, $amount, 0], [$brMoney, 0, $amount]] : [[$brMoney, $amount, 0], [$ho, 0, $amount]], $userId);
            return $hoDoc->fresh();
        });
    }

    // ------------------------------------------------------------------ years

    /** The books a year-end touches: a head office closes itself and all its branches together. */
    public function yearBooks(BusAgency|BusCompany $owner): array
    {
        if ($owner instanceof BusAgency && $owner->company_id) {
            throw ValidationException::withMessages(['year' => translate('The head office closes the year for all its branches.')]);
        }
        $all = [$owner];
        if ($owner instanceof BusCompany) {
            foreach (BusAgency::where('company_id', $owner->id)->get() as $b) {
                if ($this->ledger->books($b)) {
                    $all[] = $b;
                }
            }
        }
        return $all;
    }

    /**
     * Closes a financial year (OHADA: 1 January to 31 December). Every month of it must be closed and
     * the year over. The closing entry, dated 31 December, brings every income and spending account
     * to zero and puts the result on 1301 "Result of the year". Nothing can be dated in a closed year.
     */
    public function closeYear(BusAgency|BusCompany $owner, int $year, string $userId): array
    {
        $this->needBooks($owner);
        $books = $this->yearBooks($owner);
        $end = Carbon::create($year, 12, 31)->startOfDay();
        $problems = [];
        if ($end->gte(today())) {
            $problems[] = translate('A year closes once it is over.');
        }
        foreach ($books as $o) {
            $b = $this->ledger->books($o);
            $name = $o instanceof BusCompany ? translate('Head office') : $o->branchLabel();
            if ($b->starts_on->year > $year) {
                continue;
            }
            if ($this->ledger->yearClosed((string)$o->id, $end)) {
                $problems[] = translate(key: '{books}: this year is already closed.', replace: ['books' => $name]);
                continue;
            }
            $m = $b->starts_on->copy()->startOfMonth()->max(Carbon::create($year, 1, 1));
            while ($m->lte($end)) {
                if (!DB::table('bus_acc_months')->where('agency_id', $o->id)->where('month', $m->toDateString())->whereNotNull('closed_at')->exists()) {
                    $problems[] = translate(key: '{books}: close {month} first.', replace: ['books' => $name, 'month' => $m->translatedFormat('F Y')]);
                    break;
                }
                $m->addMonth();
            }
            if (DB::table('bus_acc_years')->where('agency_id', $o->id)->where('year', $year - 1)->whereNull('closed_at')->exists()
                || ($b->starts_on->year < $year && !$this->ledger->yearClosed((string)$o->id, Carbon::create($year - 1, 12, 31)))) {
                $problems[] = translate(key: '{books}: close {year} first.', replace: ['books' => $name, 'year' => $year - 1]);
            }
        }
        if ($problems) {
            throw ValidationException::withMessages(['year' => $problems]);
        }
        return DB::transaction(function () use ($books, $year, $end, $userId) {
            $results = [];
            foreach ($books as $o) {
                $b = $this->ledger->books($o);
                if ($b->starts_on->year > $year) {
                    continue;
                }
                $sums = BusAccLine::query()->from('bus_acc_lines as l')->join('bus_acc_accounts as a', 'a.id', '=', 'l.account_id')
                    ->where('l.agency_id', $o->id)->whereBetween('l.date', [Carbon::create($year, 1, 1)->toDateString(), $end->toDateString()])
                    ->whereIn('a.type', ['revenue', 'expense'])->groupBy('l.account_id')->selectRaw('l.account_id, sum(l.debit) - sum(l.credit) as net')->get();
                $lines = [];
                $result = 0.0;
                foreach ($sums as $s) {
                    $net = round((float)$s->net, 2);
                    if (abs($net) < 0.005) {
                        continue;
                    }
                    $lines[] = $net > 0 ? [$s->account_id, 0, $net] : [$s->account_id, -$net, 0];
                    $result -= $net;
                }
                $result = round($result, 2);
                $resultAcc = $this->ledger->account($o, 'result');
                if (abs($result) >= 0.005) {
                    $lines[] = $result > 0 ? [$resultAcc, 0, $result] : [$resultAcc, -$result, 0];
                }
                $entry = null;
                if (count($lines) >= 2) {
                    $entry = $this->ledger->post($o, [
                        'date' => $end, 'journal' => 'closing', 'closing' => true, 'lines' => $lines, 'created_by' => $userId,
                        'memo' => translate(key: 'Year {year} closed: its result goes to the result of the year', replace: ['year' => $year]),
                        'source_type' => 'year', 'source_id' => (string)$year, 'source_key' => 'year:' . $year . ':' . (DB::table('bus_acc_years')->where('agency_id', $o->id)->where('year', $year)->value('id') ? 'again' . now()->timestamp : 'first'),
                    ]);
                }
                DB::table('bus_acc_years')->updateOrInsert(['agency_id' => (string)$o->id, 'year' => $year],
                    ['closed_at' => now(), 'closed_by' => $userId, 'entry_id' => $entry ? (string)$entry->id : null, 'result' => $result, 'updated_at' => now(), 'created_at' => now()]);
                $results[] = ['books' => $o instanceof BusCompany ? translate('Head office') : $o->branchLabel(), 'result' => $result];
            }
            return ['year' => $year, 'results' => $results];
        });
    }

    /** Reopens a closed year (with the reason): the closing entry is mirrored on 31 December. Only the last closed year. */
    public function reopenYear(BusAgency|BusCompany $owner, int $year, string $reason, string $userId): array
    {
        $this->needBooks($owner);
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => translate('Say why the year is reopened.')]);
        }
        $books = $this->yearBooks($owner);
        foreach ($books as $o) {
            if (DB::table('bus_acc_years')->where('agency_id', $o->id)->where('year', '>', $year)->whereNotNull('closed_at')->exists()) {
                throw ValidationException::withMessages(['year' => translate('Reopen the later years first.')]);
            }
        }
        return DB::transaction(function () use ($books, $year, $reason, $userId) {
            foreach ($books as $o) {
                $row = DB::table('bus_acc_years')->where('agency_id', $o->id)->where('year', $year)->whereNotNull('closed_at')->first();
                if (!$row) {
                    continue;
                }
                DB::table('bus_acc_years')->where('id', $row->id)->update(['closed_at' => null, 'reopened_at' => now(), 'reopened_by' => $userId,
                    'reopen_reason' => mb_substr(trim($reason), 0, 300), 'updated_at' => now()]);
                if ($row->entry_id && ($entry = BusAccEntry::with('lines')->find($row->entry_id))) {
                    $mirror = $this->ledger->post($o, [
                        'date' => $entry->date, 'journal' => 'closing', 'closing' => true, 'created_by' => $userId, 'reverses_id' => (string)$entry->id,
                        'memo' => translate(key: 'Year {year} reopened', replace: ['year' => $year]) . ' · ' . $reason,
                        'lines' => $entry->lines->map(fn($l) => [$l->account_id, $l->credit, $l->debit])->all(),
                        'source_type' => 'year', 'source_id' => (string)$year, 'source_key' => 'yearopen:' . $entry->id,
                    ]);
                    $entry->update(['reversed_by_id' => (string)$mirror->id]);
                }
            }
            return ['year' => $year];
        });
    }

    // ------------------------------------------------------------------ helpers

    public function needBooks(BusAgency|BusCompany $agency): BusAccBook
    {
        $books = $this->ledger->books($agency);
        if (!$books) {
            throw ValidationException::withMessages(['books' => translate('Open the books first.')]);
        }
        return $books;
    }

    protected function validate(BusAgency|BusCompany $agency, array $data, array $rules, array $names = []): array
    {
        return Validator::make($data, $rules, [], $names + ['date' => translate('date'), 'amount' => translate('amount')])->validate();
    }

    /** An account of this branch, active, of the kind asked (expense / revenue category, money, any). */
    protected function ownAccount(BusAgency|BusCompany $agency, string $id, string $kind): BusAccAccount
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
    protected function moneyLine(BusAgency|BusCompany $agency, array $data, float $debit, float $credit): array
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
    protected function tags(BusAgency|BusCompany $agency, array $data): array
    {
        $tags = ['vehicle_id' => null, 'destination_id' => null, 'departure_id' => null];
        if ($agency instanceof BusCompany) {
            if (filled($data['vehicle_id'] ?? null)) {
                $tags['vehicle_id'] = (string)$this->ownBus($agency, (string)$data['vehicle_id'])->id;
            }
            return $tags;
        }
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

    protected function document(BusAgency|BusCompany $agency, string $type, array $data, string $userId, array $fields): BusAccDocument
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

    protected function postDocument(BusAgency|BusCompany $agency, BusAccDocument $doc, string $journal, string $memo, array $lines, string $userId): BusAccEntry
    {
        $entry = $this->ledger->post($agency, [
            'date' => $doc->date, 'journal' => $journal, 'memo' => $doc->number . ' · ' . $memo, 'lines' => $lines,
            'source_type' => 'document', 'source_id' => (string)$doc->id, 'source_key' => 'doc:' . $doc->id, 'created_by' => $userId,
        ]);
        $doc->update(['entry_id' => (string)$entry->id]);
        return $entry;
    }

    protected function own(BusAgency|BusCompany $agency, $model): void
    {
        abort_unless((string)$model->agency_id === (string)$agency->id, 404);
    }

    public function vatRegistered(BusAgency|BusCompany $agency): bool
    {
        return (bool)$this->ledger->books($agency)?->setting('vat_registered', false);
    }

    public function vatRateOf(BusAgency|BusCompany $agency): float
    {
        return (float)($this->ledger->books($agency)?->setting('vat_rate', 19.25) ?? 19.25);
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
