<?php

namespace Modules\BusManagement\Service\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusAccAccount;
use Modules\BusManagement\Entities\BusAccDocument;
use Modules\BusManagement\Entities\BusAccLine;
use Modules\BusManagement\Entities\BusAgency;
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

    public function accounts(BusAgency|string $agency): Collection
    {
        return BusAccAccount::where('agency_id', $this->id($agency))->orderBy('code')->get();
    }

    /** Sums per account for a period (or up to a date): debit, credit, and the balance in the account's normal direction. */
    public function sums(BusAgency|string $agency, ?string $from, ?string $to): Collection
    {
        $q = BusAccLine::where('agency_id', $this->id($agency));
        if ($from) {
            $q->where('date', '>=', $from);
        }
        if ($to) {
            $q->where('date', '<=', $to);
        }
        return $q->groupBy('account_id')->selectRaw('account_id, sum(debit) d, sum(credit) c')->get()->keyBy(fn($r) => (string)$r->account_id);
    }

    public function overview(BusAgency $agency): array
    {
        $accounts = $this->accounts($agency)->keyBy(fn($a) => (string)$a->id);
        $all = $this->sums($agency, null, null);
        $bal = function (BusAccAccount $a) use ($all) {
            $r = $all[(string)$a->id] ?? null;
            $net = $r ? (float)$r->d - (float)$r->c : 0;
            return round($a->debitNormal() ? $net : -$net, 2);
        };
        $role = fn(string $r) => $accounts->first(fn($a) => $a->role === $r);
        $money = $accounts->filter(fn($a) => $a->money && $a->active)->map(fn($a) => ['account' => $a, 'balance' => $bal($a)])->values();
        $ho = $role('head_office');
        if ($ho && ($agency->company_id || abs($bal($ho)) >= 0.005)) {
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
            'agents' => $this->service->agentsHolding($agency),
            'balanced' => abs((float)$totals->d - (float)$totals->c) < 0.01,
            'pending' => BusMoneyMove::where('agency_id', $agency->id)->whereNull('entry_id')->whereNull('skipped')->count(),
            'closed' => DB::table('bus_acc_months')->where('agency_id', $agency->id)->whereNotNull('closed_at')->orderByDesc('month')->value('month'),
        ];
    }

    /** Income and spending per account for a period, and the result. */
    public function incomeStatement(BusAgency|string $agency, string $from, string $to): array
    {
        $sums = $this->sums($agency, $from, $to);
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
    public function profit(BusAgency $agency, string $from, string $to, string $by): array
    {
        $col = ['bus' => 'vehicle_id', 'route' => 'destination_id', 'trip' => 'departure_id'][$by] ?? 'vehicle_id';
        $rows = BusAccLine::query()->from('bus_acc_lines as l')->join('bus_acc_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.agency_id', $agency->id)->whereBetween('l.date', [$from, $to])->whereIn('a.type', ['revenue', 'expense'])
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
    public function trialBalance(BusAgency $agency, string $from, string $to): array
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
    public function balanceSheet(BusAgency $agency, string $at): array
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
    public function cashFlow(BusAgency $agency, string $from, string $to): array
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
            'balanced' => $ov['balanced'], 'pending' => $ov['pending'],
        ];
    }

    protected function id(BusAgency|string $agency): string
    {
        return $agency instanceof BusAgency ? (string)$agency->id : (string)$agency;
    }
}
