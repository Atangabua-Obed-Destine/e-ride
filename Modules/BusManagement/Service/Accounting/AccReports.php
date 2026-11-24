<?php

namespace Modules\BusManagement\Service\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusAccAccount;
use Modules\BusManagement\Entities\BusAccDocument;
use Modules\BusManagement\Entities\BusAccEntry;
use Modules\BusManagement\Entities\BusAccLine;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusAccAsset;
use Modules\BusManagement\Entities\BusCompany;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusMoneyMove;
use Modules\BusManagement\Entities\BusVehicle;

/**
 * Every figure is summed from posted journal lines (never from a stored balance), so the reports
 * always agree with the journal and with each other.
 */
class AccReports
{
    public function __construct(protected Ledger $ledger, protected AccountingService $service)
    {
    }

    public function accounts(BusAgency|BusCompany|string $agency): Collection
    {
        return BusAccAccount::where('agency_id', $this->id($agency))->orderBy('code')->get();
    }

    /** Sums per account for a period (or up to a date): debit, credit, and the balance in the account's normal direction. */
    public function sums(BusAgency|BusCompany|string $agency, ?string $from, ?string $to, bool $noClosing = false): Collection
    {
        $q = BusAccLine::where('agency_id', $this->id($agency));
        if ($noClosing) {
            $q->whereNotIn('entry_id', BusAccEntry::where('agency_id', $this->id($agency))->where('journal', 'closing')->select('id'));
        }
        if ($from) {
            $q->where('date', '>=', $from);
        }
        if ($to) {
            $q->where('date', '<=', $to);
        }
        return $q->groupBy('account_id')->selectRaw('account_id, sum(debit) d, sum(credit) c')->get()->keyBy(fn($r) => (string)$r->account_id);
    }

    public function overview(BusAgency|BusCompany $agency): array
    {
        $accounts = $this->accounts($agency)->keyBy(fn($a) => (string)$a->id);
        $all = $this->sums($agency, null, null);
        $bal = function (?BusAccAccount $a) use ($all) {
            if (!$a) {
                return 0.0;
            }
            $r = $all[(string)$a->id] ?? null;
            $net = $r ? (float)$r->d - (float)$r->c : 0;
            return round($a->debitNormal() ? $net : -$net, 2);
        };
        $role = fn(string $r) => $accounts->first(fn($a) => $a->role === $r);
        $money = $accounts->filter(fn($a) => $a->money && $a->active)->map(fn($a) => ['account' => $a, 'balance' => $bal($a)])->values();
        $ho = $role('head_office');
        if ($ho && (($agency instanceof BusAgency && $agency->company_id) || abs($bal($ho)) >= 0.005)) {
            $money->push(['account' => $ho, 'balance' => $bal($ho)]);
        }
        $month = $this->incomeStatement($agency, today()->startOfMonth()->toDateString(), today()->toDateString());
        $totals = DB::table('bus_acc_lines')->where('agency_id', $agency->id)->selectRaw('coalesce(sum(debit),0) d, coalesce(sum(credit),0) c')->first();
        $bills = BusAccDocument::where('agency_id', $agency->id)->where('type', BusAccDocument::EXPENSE)->where('status', 'posted')->where('owed', '>', 0)
            ->orderByRaw('due_on is null')->orderBy('due_on')->orderBy('date')->limit(8)->get();
        return [
            'money' => $money,
            'month' => $month,
            'held' => $bal($role('unearned')),
            'refunds_owed' => $bal($role('refunds_owed')),
            'eride' => $bal($role('eride')),
            'suppliers' => $bal($role('suppliers')),
            'bills' => $bills,
            'agents' => $agency instanceof BusAgency ? $this->service->agentsHolding($agency) : [],
            // A head office: what it holds for each branch (its account of the branch).
            'branches' => $accounts->filter(fn($a) => $a->role === 'branch')->map(fn($a) => ['account' => $a, 'balance' => $bal($a)])->values(),
            'loans' => $bal($role('loans')),
            'buses' => ['count' => BusAccAsset::where('agency_id', $agency->id)->where('status', 'active')->count(),
                'value' => $bal($role('buses')) + $bal($role('bus_wear'))],
            'balanced' => abs((float)$totals->d - (float)$totals->c) < 0.01,
            'health' => $this->health($agency),
            'pending' => BusMoneyMove::where('agency_id', $agency->id)->whereNull('entry_id')->whereNull('skipped')->count(),
            'closed' => DB::table('bus_acc_months')->where('agency_id', $agency->id)->whereNotNull('closed_at')->orderByDesc('month')->value('month'),
        ];
    }

    /** Income and spending per account for a period, and the result. */
    public function incomeStatement(BusAgency|BusCompany|string $agency, string $from, string $to): array
    {
        $sums = $this->sums($agency, $from, $to, true);
        $income = [];
        $spending = [];
        foreach ($this->accounts($agency) as $a) {
            $r = $sums[(string)$a->id] ?? null;
            if (!$r) {
                continue;
            }
            if ($a->type === 'revenue') {
                $income[] = ['account' => $a, 'amount' => round((float)$r->c - (float)$r->d, 2)];
            } elseif ($a->type === 'expense') {
                $spending[] = ['account' => $a, 'amount' => round((float)$r->d - (float)$r->c, 2)];
            }
        }
        $in = round(array_sum(array_column($income, 'amount')), 2);
        $out = round(array_sum(array_column($spending, 'amount')), 2);
        return ['from' => $from, 'to' => $to, 'income' => $income, 'spending' => $spending, 'total_income' => $in, 'total_spending' => $out, 'result' => round($in - $out, 2)];
    }

    /**
     * Profit per bus, per route or per trip: income and spending tagged to it, and E-Ride's
     * commission on its tickets (from the bookings, as the monthly statement is not split by trip).
     */
    public function profit(BusAgency|BusCompany|string $agency, string $from, string $to, string $by): array
    {
        $agency = is_string($agency) ? (object)['id' => $agency] : $agency;
        $col = ['bus' => 'vehicle_id', 'route' => 'destination_id', 'trip' => 'departure_id'][$by] ?? 'vehicle_id';
        $rows = BusAccLine::query()->from('bus_acc_lines as l')->join('bus_acc_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.agency_id', $agency->id)->whereBetween('l.date', [$from, $to])->whereIn('a.type', ['revenue', 'expense'])
            ->whereNotIn('l.entry_id', BusAccEntry::where('agency_id', $agency->id)->where('journal', 'closing')->select('id'))
            ->groupBy("l.$col")
            ->selectRaw("l.$col as k,
                sum(case when a.type = 'revenue' then l.credit - l.debit else 0 end) as income,
                sum(case when a.type = 'expense' and a.role = 'fuel' then l.debit - l.credit else 0 end) as fuel,
                sum(case when a.type = 'expense' and (a.role is null or a.role not in ('fuel', 'eride_commission')) then l.debit - l.credit else 0 end) as other,
                sum(case when a.role = 'eride_commission' then l.debit - l.credit else 0 end) as eride")
            ->get()->keyBy(fn($r) => (string)$r->k);

        // E-Ride's commission on the tickets of the trips recognized in the period.
        $commission = DB::table('bus_acc_lines as l')->join('bus_acc_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.agency_id', $agency->id)->whereBetween('l.date', [$from, $to])->whereIn('a.role', ['sales_online', 'sales_counter'])
            ->whereNotNull('l.departure_id')->distinct()->pluck('l.departure_id');
        $perDeparture = DB::table('bus_bookings')->whereIn('departure_id', $commission)->whereNotNull('paid_at')
            ->groupBy('departure_id')->selectRaw('departure_id, sum(commission) c')->pluck('c', 'departure_id');
        $deps = BusDeparture::whereIn('id', $commission)->get(['id', 'vehicle_id', 'destination_id'])->keyBy(fn($d) => (string)$d->id);
        $estimate = [];
        foreach ($perDeparture as $depId => $c) {
            $d = $deps[(string)$depId] ?? null;
            $key = match ($col) {
                'vehicle_id' => $d?->vehicle_id ? (string)$d->vehicle_id : '',
                'destination_id' => $d?->destination_id ? (string)$d->destination_id : '',
                default => (string)$depId,
            };
            $estimate[$key] = ($estimate[$key] ?? 0) + (float)$c;
        }

        $labels = $this->labels($col, $rows->keys()->filter()->all());
        $out = [];
        foreach ($rows as $k => $r) {
            $commissionShare = $k !== '' ? round($estimate[$k] ?? 0, 2) : round((float)$r->eride - array_sum($estimate), 2);
            $income = round((float)$r->income, 2);
            $fuel = round((float)$r->fuel, 2);
            $other = round((float)$r->other, 2);
            $out[] = [
                'key' => $k, 'label' => $k === '' ? null : ($labels[$k] ?? '?'), 'income' => $income, 'fuel' => $fuel, 'other' => $other,
                'commission' => $commissionShare, 'profit' => round($income - $fuel - $other - $commissionShare, 2),
            ];
        }
        usort($out, fn($a, $b) => [$a['key'] === '' ? 1 : 0, -$a['income']] <=> [$b['key'] === '' ? 1 : 0, -$b['income']]);
        return $out;
    }

    protected function labels(string $col, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        return match ($col) {
            'vehicle_id' => BusVehicle::whereIn('id', $ids)->get()->mapWithKeys(fn($v) => [(string)$v->id => $v->registration . ($v->name ? ' · ' . $v->name : '')])->all(),
            'destination_id' => BusDestination::whereIn('id', $ids)->get()->mapWithKeys(fn($d) => [(string)$d->id => $d->city])->all(),
            default => BusDeparture::with(['destination', 'vehicle'])->whereIn('id', $ids)->get()->mapWithKeys(fn($d) => [(string)$d->id =>
                $d->leavesAt()->format('d/m H:i') . ' → ' . ($d->destination?->city ?? '') . ($d->vehicle ? ' · ' . $d->vehicle->registration : '')])->all(),
        };
    }

    /** Each account: balance before the period, debits and credits in it, balance at its end. Debits equal credits in every column. */
    public function trialBalance(BusAgency|BusCompany $agency, string $from, string $to): array
    {
        $before = $this->sums($agency, null, Carbon::parse($from)->subDay()->toDateString());
        $period = $this->sums($agency, $from, $to);
        $rows = [];
        $tot = ['od' => 0, 'oc' => 0, 'd' => 0, 'c' => 0, 'cd' => 0, 'cc' => 0];
        foreach ($this->accounts($agency) as $a) {
            $b = $before[(string)$a->id] ?? null;
            $p = $period[(string)$a->id] ?? null;
            if (!$b && !$p) {
                continue;
            }
            $open = ($b ? (float)$b->d - (float)$b->c : 0);
            $d = $p ? (float)$p->d : 0;
            $c = $p ? (float)$p->c : 0;
            $close = $open + $d - $c;
            $row = ['account' => $a, 'od' => max($open, 0), 'oc' => max(-$open, 0), 'd' => $d, 'c' => $c, 'cd' => max($close, 0), 'cc' => max(-$close, 0)];
            foreach ($tot as $k => $v) {
                $tot[$k] += $row[$k];
            }
            $rows[] = $row;
        }
        return ['rows' => $rows, 'totals' => array_map(fn($v) => round($v, 2), $tot), 'balanced' => abs($tot['d'] - $tot['c']) < 0.01 && abs($tot['cd'] - $tot['cc']) < 0.01];
    }

    /** What the branch has and owes at a date. The result since the start sits in equity until a year is closed. */
    public function balanceSheet(BusAgency|BusCompany $agency, string $at): array
    {
        $sums = $this->sums($agency, null, $at);
        $has = [];
        $owes = [];
        $equity = [];
        $result = 0.0;
        foreach ($this->accounts($agency) as $a) {
            $r = $sums[(string)$a->id] ?? null;
            if (!$r) {
                continue;
            }
            $net = (float)$r->d - (float)$r->c;
            if ($a->type === 'revenue' || $a->type === 'expense') {
                $result -= $net;
                continue;
            }
            if (abs($net) < 0.005) {
                continue;
            }
            if ($a->type === 'equity') {
                $equity[] = ['account' => $a, 'amount' => round(-$net, 2)];
            } elseif ($a->type === 'asset' || ($a->class === 4 && $net > 0 && $a->type !== 'liability')) {
                $has[] = ['account' => $a, 'amount' => round($net, 2)];
            } else {
                $owes[] = ['account' => $a, 'amount' => round(-$net, 2)];
            }
        }
        $totalHas = round(array_sum(array_column($has, 'amount')), 2);
        $totalOwes = round(array_sum(array_column($owes, 'amount')), 2);
        $totalEquity = round(array_sum(array_column($equity, 'amount')) + $result, 2);
        return ['at' => $at, 'has' => $has, 'owes' => $owes, 'equity' => $equity, 'result' => round($result, 2),
            'total_has' => $totalHas, 'total_owes' => $totalOwes, 'total_equity' => $totalEquity, 'balanced' => abs($totalHas - $totalOwes - $totalEquity) < 0.01];
    }

    /** An account's lines for a period, with the balance before and a running balance. */
    public function ledger(BusAccAccount $a, string $from, string $to, ?string $agent = null): array
    {
        $tags = $agent ? ['staff_user_id' => $agent] : [];
        $opening = $this->ledger->balance($a, Carbon::parse($from)->subDay()->toDateString(), $tags);
        $q = BusAccLine::with('entry')->where('agency_id', $a->agency_id)->where('account_id', $a->id)->whereBetween('date', [$from, $to]);
        if ($agent) {
            $q->where('staff_user_id', $agent);
        }
        $lines = $q->orderBy('date')->orderBy('id')->limit(3000)->get();
        $running = $opening;
        $rows = [];
        foreach ($lines as $l) {
            $running += $a->debitNormal() ? $l->debit - $l->credit : $l->credit - $l->debit;
            $rows[] = ['line' => $l, 'balance' => round($running, 2)];
        }
        return ['opening' => $opening, 'rows' => $rows, 'closing' => round($running, 2), 'truncated' => $lines->count() >= 3000];
    }

    /**
     * Cash flow (direct): the money accounts at the start of the period, every movement in it by
     * kind (tickets paid, refunds, spending, other income, E-Ride, owner, charges), and at the end.
     * Money moved between the branch's own accounts nets to zero and is shown apart.
     */
    public function cashFlow(BusAgency|BusCompany $agency, string $from, string $to): array
    {
        $money = BusAccAccount::where('agency_id', $agency->id)->where(fn($q) => $q->where('money', true)->orWhere('role', 'head_office'))->pluck('id')->map(fn($id) => (string)$id);
        $balanceAt = fn(?string $date) => (float)BusAccLine::where('agency_id', $agency->id)->whereIn('account_id', $money)
            ->when($date, fn($q) => $q->where('date', '<=', $date))->selectRaw('coalesce(sum(debit) - sum(credit), 0) as b')->value('b');
        $opening = round($balanceAt(Carbon::parse($from)->subDay()->toDateString()), 2);
        $closing = round($balanceAt($to), 2);

        $lines = DB::table('bus_acc_lines as l')->join('bus_acc_entries as e', 'e.id', '=', 'l.entry_id')
            ->leftJoin('bus_money_moves as m', function ($j) {
                $j->on(DB::raw('cast(m.id as char)'), '=', 'e.source_id')->where('e.source_type', '=', 'move');
            })
            ->leftJoin('bus_acc_documents as d', function ($j) {
                $j->on('d.id', '=', 'e.source_id')->where('e.source_type', '=', 'document');
            })
            ->where('l.agency_id', $agency->id)->whereIn('l.account_id', $money)->whereBetween('l.date', [$from, $to])
            ->select('l.debit', 'l.credit', 'l.entry_id', 'e.source_type', 'e.reverses_id', 'm.kind as move_kind', 'd.type as doc_type')->get();
        // Movements inside one entry between two money accounts are transfers.
        $perEntry = $lines->groupBy('entry_id');
        $rows = [];
        $internal = 0.0;
        foreach ($perEntry as $entryLines) {
            $net = round($entryLines->sum(fn($l) => (float)$l->debit - (float)$l->credit), 2);
            $gross = round($entryLines->sum(fn($l) => (float)$l->debit), 2);
            $f = $entryLines->first();
            $kind = match (true) {
                $f->source_type === 'move' && in_array($f->move_kind, ['sale', 'extra'], true) => 'tickets',
                $f->source_type === 'move' && in_array($f->move_kind, ['refund_sent', 'cash_refund'], true) => 'refunds',
                $f->source_type === 'statement_paid' => 'eride',
                $f->source_type === 'fee' => 'charges',
                in_array($f->doc_type, ['expense', 'bill_payment'], true) => 'spending',
                $f->doc_type === 'income' => 'income',
                $f->doc_type === 'check' => 'charges',
                $f->doc_type === 'opening' => 'opening',
                default => 'other',
            };
            if (in_array($f->doc_type, ['transfer', 'cash_count'], true)) {
                // Between own accounts: what left equals what arrived, except a charge or a cash difference.
                $internal += $gross;
                $kind = $f->doc_type === 'transfer' ? 'charges' : 'cash_diff';
            }
            if (abs($net) < 0.005) {
                continue;
            }
            $rows[$kind] = ($rows[$kind] ?? 0) + $net;
        }
        $labels = [
            'opening' => 'Opening balances', 'tickets' => 'Tickets paid (counter, app and web)', 'refunds' => 'Refunds paid to passengers',
            'income' => 'Other income received', 'spending' => 'Spending and bills paid', 'eride' => 'E-Ride commission paid',
            'charges' => 'Bank and Mobile Money charges, interest', 'cash_diff' => 'Cash short or over at counts', 'other' => 'Journal entries and other',
        ];
        $out = [];
        foreach ($labels as $k => $label) {
            if (isset($rows[$k])) {
                $out[] = ['key' => $k, 'label' => $label, 'amount' => round($rows[$k], 2)];
            }
        }
        $net = round(array_sum(array_column($out, 'amount')), 2);
        return ['from' => $from, 'to' => $to, 'opening' => $opening, 'rows' => $out, 'net' => $net, 'closing' => $closing,
            'internal' => round($internal, 2), 'agrees' => abs($opening + $net - $closing) < 0.01];
    }

    /**
     * The books checked against the bus records, not against themselves:
     *  - every entry balanced, and debits equal credits overall;
     *  - 4771 tie-out: what is held for each trip not gone yet equals what its paid tickets hold
     *    (fare less any refund), and nothing stays held for a trip that should have left;
     *  - till tie-out: each agent's counter cash equals their cash sales, less the cash refunds
     *    they gave, plus or less what they took in or paid out by hand, less what they handed in.
     * Returns a list of problems (empty when all is well).
     */
    public function health(BusAgency|BusCompany $agency): array
    {
        $problems = [];
        $books = $this->ledger->books($agency);
        if (!$books) {
            return $problems;
        }
        $start = $books->starts_on->toDateString();
        $tot = DB::table('bus_acc_lines')->where('agency_id', $agency->id)->selectRaw('coalesce(sum(debit),0) d, coalesce(sum(credit),0) c')->first();
        if (abs((float)$tot->d - (float)$tot->c) >= 0.01) {
            $problems[] = translate('Debits and credits are not equal.');
        }
        $unbalanced = DB::table('bus_acc_lines')->where('agency_id', $agency->id)->groupBy('entry_id')->havingRaw('abs(sum(debit) - sum(credit)) >= 0.01')->select('entry_id')->get()->count();
        if ($unbalanced) {
            $problems[] = translate(key: '{n} entries do not balance.', replace: ['n' => $unbalanced]);
        }

        if ($agency instanceof BusCompany) {
            // The head office's account of each branch must equal that branch's own 1811.
            foreach (BusAgency::where('company_id', $agency->id)->get() as $branch) {
                if (!$this->ledger->books($branch)) {
                    continue;
                }
                $hoSide = $this->ledger->balance($this->ledger->branchAccount($agency, $branch));
                $brSide = $this->ledger->balance($this->ledger->account($branch, 'head_office'));
                if (abs($hoSide - $brSide) >= 0.01) {
                    $problems[] = translate(key: 'Branch {branch}: the head office books say it holds {ho} for the branch, the branch books say {br}.',
                        replace: ['branch' => $branch->branchLabel(), 'ho' => \Modules\BusManagement\Lib\AccFormat::n($hoSide), 'br' => \Modules\BusManagement\Lib\AccFormat::n($brSide)]);
                }
            }
            return $problems;
        }
        // 4771 against the tickets.
        $unearned = BusAccAccount::where('agency_id', $agency->id)->where('role', 'unearned')->first();
        $held = BusAccLine::where('agency_id', $agency->id)->where('account_id', $unearned->id)->groupBy('departure_id')
            ->selectRaw('departure_id, sum(credit) - sum(debit) as held')->pluck('held', 'departure_id')->mapWithKeys(fn($v, $k) => [(string)$k => round((float)$v, 2)]);
        $recognized = DB::table('bus_acc_recognized')->where('agency_id', $agency->id)->pluck('departure_id')->map(fn($id) => (string)$id)->flip();
        $tickets = DB::table('bus_bookings as b')->join('bus_departures as d', 'd.id', '=', 'b.departure_id')
            ->where('b.agency_id', $agency->id)->whereNotNull('b.paid_at')->where('d.date', '>=', $start)
            ->groupBy('b.departure_id')->selectRaw('b.departure_id, sum(b.amount - coalesce(b.refund_amount, 0)) as held')->pluck('held', 'departure_id')
            ->mapWithKeys(fn($v, $k) => [(string)$k => round((float)$v, 2)]);
        $deps = BusDeparture::with('destination')->whereIn('id', $held->keys()->merge($tickets->keys())->filter()->unique()->values())->get()->keyBy(fn($d) => (string)$d->id);
        $label = fn($id) => isset($deps[$id]) ? $deps[$id]->leavesAt()->format('d/m H:i') . ' → ' . ($deps[$id]->destination?->city ?? '') : translate('a trip');
        foreach ($held->keys()->merge($tickets->keys())->unique() as $id) {
            $inBooks = $held[$id] ?? 0.0;
            $d = $deps[$id] ?? null;
            if ($id === '' || !$d || $d->leavesAt()->lt($books->starts_on->copy()->startOfDay())) {
                if (abs($inBooks) >= 0.01) {
                    $problems[] = translate(key: '{amount} is held on 4771 without a trip of the books.', replace: ['amount' => \Modules\BusManagement\Lib\AccFormat::n($inBooks)]);
                }
                continue;
            }
            $should = isset($recognized[$id]) ? 0.0 : ($tickets[$id] ?? 0.0);
            if (abs($inBooks - $should) >= 0.01) {
                $problems[] = translate(key: 'Trip {trip}: {books} held in the books, {tickets} by its tickets.', replace: ['trip' => $label($id), 'books' => \Modules\BusManagement\Lib\AccFormat::n($inBooks), 'tickets' => \Modules\BusManagement\Lib\AccFormat::n($should)]);
            } elseif (!isset($recognized[$id]) && abs($inBooks) >= 0.01 && $d->leavesAt()->lt(now()->subDay())) {
                $problems[] = translate(key: 'Trip {trip} should have left: mark it departed or cancelled so its tickets count.', replace: ['trip' => $label($id)]);
            }
        }

        // Each agent's counter cash against their sales, refunds, hand-ins.
        $cash = BusAccAccount::where('agency_id', $agency->id)->where('role', 'counter_cash')->first();
        $ledgerCash = BusAccLine::where('agency_id', $agency->id)->where('account_id', $cash->id)->groupBy('staff_user_id')
            ->selectRaw('staff_user_id, sum(debit) - sum(credit) as b')->pluck('b', 'staff_user_id')->mapWithKeys(fn($v, $k) => [(string)$k => round((float)$v, 2)]);
        $sales = DB::table('bus_bookings')->where('agency_id', $agency->id)->where('source', 'counter')->where('payment_method', 'cash')
            ->where('paid_at', '>=', $start)->groupBy('staff_user_id')->selectRaw('staff_user_id, sum(amount) s')->pluck('s', 'staff_user_id');
        $refunds = DB::table('bus_money_moves')->where('agency_id', $agency->id)->where('kind', 'cash_refund')->where('method', 'cash')
            ->where('at', '>=', $start)->groupBy('staff_user_id')->selectRaw('staff_user_id, sum(amount) s')->pluck('s', 'staff_user_id');
        $docs = BusAccDocument::where('agency_id', $agency->id)->where('status', 'posted')->get();
        $names = [];
        foreach ($ledgerCash->keys()->merge($sales->keys()->map(fn($k) => (string)$k))->unique() as $agent) {
            if ($agent === '') {
                continue;
            }
            $should = (float)($sales[$agent] ?? 0) - (float)($refunds[$agent] ?? 0)
                + $docs->where('type', 'income')->where('money_account_id', (string)$cash->id)->where('staff_user_id', $agent)->sum('amount')
                - $docs->whereIn('type', ['expense', 'bill_payment'])->where('money_account_id', (string)$cash->id)->where('staff_user_id', $agent)->sum('amount')
                - $docs->where('type', 'cash_count')->where('staff_user_id', $agent)->sum('expected');
            $inBooks = $ledgerCash[$agent] ?? 0.0;
            if (abs($inBooks - $should) >= 0.01) {
                $names[$agent] ??= $this->service->userName($agent);
                $problems[] = translate(key: 'Counter cash of {agent}: {books} in the books, {records} by the sales, refunds and counts.', replace: ['agent' => $names[$agent], 'books' => \Modules\BusManagement\Lib\AccFormat::n($inBooks), 'records' => \Modules\BusManagement\Lib\AccFormat::n($should)]);
            }
        }
        return $problems;
    }

    /** The buses carried in these books, with their wear so far and the value left. */
    public function assets(BusAgency|BusCompany $agency): Collection
    {
        return BusAccAsset::with('vehicle')->where('agency_id', $agency->id)->orderByRaw("status = 'active' desc")->orderBy('name')->get()
            ->map(function (BusAccAsset $a) {
                $worn = in_array($a->status, ['active'], true) ? round($this->service->wornSoFar($a), 2) : null;
                return ['asset' => $a, 'worn' => $worn, 'left' => $worn !== null ? round($a->value - $worn, 2) : null, 'monthly' => $a->monthly(),
                    'ends' => $a->lastMonth()];
            });
    }

    /**
     * The company as a whole: the head office's books and every branch's added together, account by
     * account (same OHADA numbers), the accounts between head office and branches left out (they are
     * the same money seen from both sides, and must cancel). Returns the figures in the shape of the
     * single-book reports, so the same tables show them.
     */
    public function combined(BusCompany $company, string $tab, string $from, string $to, string $by = 'bus'): array
    {
        $ids = collect([(string)$company->id])->merge(BusAgency::where('company_id', $company->id)->pluck('id')->map(fn($i) => (string)$i))
            ->filter(fn($id) => $this->ledger->books($id))->values()->all();
        // Head office / branch accounts cancel out only when both sides are in: a branch without books
        // keeps its head office account (money held for it), and branches keep 1811 if the head office has no books.
        $companyIn = in_array((string)$company->id, $ids, true);
        $accounts = BusAccAccount::whereIn('agency_id', $ids)->orderBy('code')->get()->reject(fn($a) =>
            ($a->role === 'branch' && in_array((string)$a->link_id, $ids, true)) || ($a->role === 'head_office' && $companyIn))->values();
        $byCode = $accounts->groupBy('code')->map(fn($g) => $g->first());
        $idToCode = $accounts->mapWithKeys(fn($a) => [(string)$a->id => $a->code]);
        $sum = function (?string $f, ?string $t, bool $noClosing) use ($ids, $idToCode) {
            $q = BusAccLine::whereIn('agency_id', $ids)->whereIn('account_id', $idToCode->keys());
            if ($f) { $q->where('date', '>=', $f); }
            if ($t) { $q->where('date', '<=', $t); }
            if ($noClosing) { $q->whereNotIn('entry_id', BusAccEntry::whereIn('agency_id', $ids)->where('journal', 'closing')->select('id')); }
            $out = [];
            foreach ($q->groupBy('account_id')->selectRaw('account_id, sum(debit) d, sum(credit) c')->get() as $r) {
                $code = $idToCode[(string)$r->account_id];
                $out[$code] = ['d' => ($out[$code]['d'] ?? 0) + (float)$r->d, 'c' => ($out[$code]['c'] ?? 0) + (float)$r->c];
            }
            return $out;
        };
        if ($tab === 'income') {
            $sums = $sum($from, $to, true);
            $income = []; $spending = [];
            foreach ($byCode as $code => $a) {
                if (!isset($sums[$code])) { continue; }
                if ($a->type === 'revenue') { $income[] = ['account' => $a, 'amount' => round($sums[$code]['c'] - $sums[$code]['d'], 2)]; }
                elseif ($a->type === 'expense') { $spending[] = ['account' => $a, 'amount' => round($sums[$code]['d'] - $sums[$code]['c'], 2)]; }
            }
            $in = round(array_sum(array_column($income, 'amount')), 2);
            $out = round(array_sum(array_column($spending, 'amount')), 2);
            return ['from' => $from, 'to' => $to, 'income' => $income, 'spending' => $spending, 'total_income' => $in, 'total_spending' => $out, 'result' => round($in - $out, 2)];
        }
        if ($tab === 'trial') {
            $before = $sum(null, Carbon::parse($from)->subDay()->toDateString(), false);
            $period = $sum($from, $to, false);
            $rows = []; $tot = ['od' => 0, 'oc' => 0, 'd' => 0, 'c' => 0, 'cd' => 0, 'cc' => 0];
            foreach ($byCode as $code => $a) {
                if (!isset($before[$code]) && !isset($period[$code])) { continue; }
                $open = isset($before[$code]) ? $before[$code]['d'] - $before[$code]['c'] : 0;
                $d = $period[$code]['d'] ?? 0; $c = $period[$code]['c'] ?? 0; $close = $open + $d - $c;
                $row = ['account' => $a, 'od' => max($open, 0), 'oc' => max(-$open, 0), 'd' => $d, 'c' => $c, 'cd' => max($close, 0), 'cc' => max(-$close, 0)];
                foreach ($tot as $k => $v) { $tot[$k] += $row[$k]; }
                $rows[] = $row;
            }
            return ['rows' => $rows, 'totals' => array_map(fn($v) => round($v, 2), $tot), 'balanced' => abs($tot['d'] - $tot['c']) < 0.01 && abs($tot['cd'] - $tot['cc']) < 0.01];
        }
        if ($tab === 'profit') {
            $rows = [];
            foreach ($ids as $id) {
                foreach ($this->profit($id, $from, $to, $by) as $r) {
                    $k = $r['key'];
                    $rows[$k] ??= ['key' => $k, 'label' => $r['label'], 'income' => 0, 'fuel' => 0, 'other' => 0, 'commission' => 0, 'profit' => 0];
                    foreach (['income', 'fuel', 'other', 'commission', 'profit'] as $f) { $rows[$k][$f] = round($rows[$k][$f] + $r[$f], 2); }
                    $rows[$k]['label'] ??= $r['label'];
                }
            }
            $rows = array_values($rows);
            usort($rows, fn($a, $b) => [$a['key'] === '' ? 1 : 0, -$a['income']] <=> [$b['key'] === '' ? 1 : 0, -$b['income']]);
            return $rows;
        }
        // Balance sheet.
        $sums = $sum(null, $to, false);
        $has = []; $owes = []; $equity = []; $result = 0.0;
        foreach ($byCode as $code => $a) {
            if (!isset($sums[$code])) { continue; }
            $net = $sums[$code]['d'] - $sums[$code]['c'];
            if (in_array($a->type, ['revenue', 'expense'], true)) { $result -= $net; continue; }
            if (abs($net) < 0.005) { continue; }
            if ($a->type === 'equity') { $equity[] = ['account' => $a, 'amount' => round(-$net, 2)]; }
            elseif ($a->type === 'asset') { $has[] = ['account' => $a, 'amount' => round($net, 2)]; }
            else { $owes[] = ['account' => $a, 'amount' => round(-$net, 2)]; }
        }
        $totalHas = round(array_sum(array_column($has, 'amount')), 2);
        $totalOwes = round(array_sum(array_column($owes, 'amount')), 2);
        $totalEquity = round(array_sum(array_column($equity, 'amount')) + $result, 2);
        return ['at' => $to, 'has' => $has, 'owes' => $owes, 'equity' => $equity, 'result' => round($result, 2),
            'total_has' => $totalHas, 'total_owes' => $totalOwes, 'total_equity' => $totalEquity, 'balanced' => abs($totalHas - $totalOwes - $totalEquity) < 0.01];
    }

    /** One branch in figures, for the head office. */
    public function branchFigures(BusAgency $agency, string $from, string $to): ?array
    {
        if (!$this->ledger->books($agency)) {
            return null;
        }
        $is = $this->incomeStatement($agency, $from, $to);
        $ov = $this->overview($agency);
        $tickets = collect($is['income'])->filter(fn($r) => in_array($r['account']->role, ['sales_online', 'sales_counter', 'refunds_given'], true))->sum('amount');
        $fuel = collect($is['spending'])->filter(fn($r) => $r['account']->role === 'fuel')->sum('amount');
        return [
            'tickets' => round($tickets, 2), 'other_income' => round($is['total_income'] - $tickets, 2), 'fuel' => round($fuel, 2),
            'spending' => $is['total_spending'], 'result' => $is['result'],
            'money' => round($ov['money']->sum('balance'), 2), 'agents' => round(collect($ov['agents'])->sum('held'), 2),
            'held' => $ov['held'], 'owes' => round($ov['refunds_owed'] + $ov['eride'] + $ov['suppliers'], 2),
            'cash_short' => round((float)collect($is['spending'])->filter(fn($r) => $r['account']->role === 'cash_short')->sum('amount'), 2),
            'balanced' => $ov['balanced'] && !$ov['health'], 'pending' => $ov['pending'], 'health' => $ov['health'],
        ];
    }

    protected function id(BusAgency|BusCompany|string $agency): string
    {
        return is_string($agency) ? $agency : (string)$agency->id;
    }
}
