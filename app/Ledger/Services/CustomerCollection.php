<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use Illuminate\Support\Facades\DB;

/**
 * Who has paid, and who has not (paxhitestbed App\Services\FeeCollection,
 * for a shop): the final sales of the year, each counted once as paid in
 * full, paid in part or paid nothing, with the money behind the counts, the
 * receipts on sales month by month, and the largest balances still owed.
 *
 * paxhitestbed counts students, because a student is billed several fees. A
 * shop sells mostly to walk-in customers under one shared contact, so here
 * the unit is the sale (the invoice), and the customers come in only for the
 * largest balances owed. Instalments, programmes and admissions have no
 * equivalent and are not ported.
 *
 * A POSITION, not a flow: what was billed in the year and what of it has been
 * received, whenever it was received. The month-by-month receipts are a flow,
 * read from the daybook so they tie to its money-in figures.
 */
class CustomerCollection
{
    public function forYear(string $from, string $to, array $daybookRows = [], array $months = []): array
    {
        $bid = Ledger::businessId();
        $sales = DB::table('transactions as t')
            ->where('t.business_id', $bid)->where('t.type', 'sell')->where('t.status', 'final')
            ->whereDate('t.transaction_date', '>=', $from)->whereDate('t.transaction_date', '<=', $to)
            ->leftJoin(DB::raw('(SELECT return_parent_id, SUM(final_total) AS returned FROM transactions
                                 WHERE business_id = '.(int) $bid." AND type = 'sell_return' AND return_parent_id IS NOT NULL
                                 GROUP BY return_parent_id) r"), 'r.return_parent_id', '=', 't.id')
            ->leftJoin(DB::raw('(SELECT transaction_id, SUM(IF(is_return = 1, -amount, amount)) AS paid FROM transaction_payments
                                 WHERE business_id = '.(int) $bid.' AND transaction_id IS NOT NULL
                                 GROUP BY transaction_id) p'), 'p.transaction_id', '=', 't.id')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->get(['t.id', 't.contact_id', 't.final_total', DB::raw('COALESCE(r.returned, 0) as returned'), DB::raw('COALESCE(p.paid, 0) as paid'),
                'c.name', 'c.supplier_business_name', 'c.is_default']);

        $count = ['total' => 0, 'full' => 0, 'part' => 0, 'none' => 0];
        $billed = 0.0;
        $collected = 0.0;
        $byCustomer = [];
        foreach ($sales as $s) {
            $b = round(max(0, (float) $s->final_total - (float) $s->returned), 2);
            if ($b <= 0) {
                continue;
            }
            $paid = round(min($b, max(0, (float) $s->paid)), 2);
            $owing = round($b - $paid, 2);
            $count['total']++;
            $count[$owing <= 0.5 ? 'full' : ($paid <= 0.5 ? 'none' : 'part')]++;
            $billed += $b;
            $collected += $paid;

            $k = (int) $s->contact_id;
            $byCustomer[$k] = $byCustomer[$k] ?? ['name' => $s->supplier_business_name ?: ($s->name ?: __('ledger_daybook.walk_in')),
                'walk_in' => (bool) $s->is_default, 'billed' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0, 'sales' => 0];
            $byCustomer[$k]['billed'] += $b;
            $byCustomer[$k]['paid'] += $paid;
            $byCustomer[$k]['outstanding'] += $owing;
            $byCustomer[$k]['sales']++;
        }

        $debtors = array_values(array_filter($byCustomer, fn ($c) => $c['outstanding'] > 0.5));
        usort($debtors, fn ($a, $b) => $b['outstanding'] <=> $a['outstanding']);
        $debtors = array_map(fn ($c) => $c + ['share' => $c['billed'] > 0 ? $c['outstanding'] / $c['billed'] * 100 : 0.0], array_slice($debtors, 0, 10));

        // receipts on sales, month by month, from the book itself
        $byMonth = [];
        $running = 0.0;
        foreach ($months as $m) {
            $cash = 0.0;
            foreach ($daybookRows as $r) {
                if ($r['source'] === 'sell' && $r['date'] >= $m['start'] && $r['date'] <= $m['end']) {
                    $cash += $r['direction'] === DaybookService::IN ? $r['amount'] : -$r['amount'];
                }
            }
            $running += $cash;
            $byMonth[] = ['label' => $m['label'], 'cash' => round($cash, 2), 'cumulative' => round($running, 2), 'all_in' => round((float) $m['in'], 2)];
        }
        if ($running == 0.0) {
            $byMonth = [];
        }

        return [
            'sales' => $count,
            'billed' => round($billed, 2),
            'collected' => round($collected, 2),
            'outstanding' => round($billed - $collected, 2),
            'rate' => $billed > 0 ? $collected / $billed * 100 : 0.0,
            'top_debtors' => $debtors,
            'by_month' => $byMonth,
            'from' => $from,
            'to' => $to,
        ];
    }
}
