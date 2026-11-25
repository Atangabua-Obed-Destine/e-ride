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
        // E-Ride: a sale is a bus ticket (a booking) of this branch whose money came in during the year:
        // billed its fare less what was refunded on it, paid its ticket money (bus_money_moves) less the
        // same refunds; the customer is the passenger's account, none for a counter walk-in. (posinnova:
        // final POS sales, their returns and payments, and POS contacts.)
        $agency = DB::table('ledger_businesses')->where('id', $bid)->where('owner_type', \App\Ledger\ERide\Books::AGENCY)->value('owner_id');
        $money = DB::table('bus_money_moves')->where('agency_id', (string) $agency)->whereNull('skipped')
            ->whereIn('kind', ['sale', 'refund_owed', 'cash_refund'])
            ->where('at', '>=', $from.' 00:00:00')->where('at', '<=', $to.' 23:59:59')
            ->groupBy('booking_id')
            ->select('booking_id', DB::raw("SUM(IF(kind = 'sale', amount, 0)) AS sold"), DB::raw("SUM(IF(kind = 'sale', 0, amount)) AS refunded"));
        $sales = DB::query()->fromSub($money, 'mv')
            ->leftJoin('bus_bookings as b', 'b.id', '=', 'mv.booking_id')
            ->leftJoin('users as u', 'u.id', '=', 'b.customer_id')
            ->where('mv.sold', '>', 0)
            ->get(['mv.booking_id as id', 'b.customer_id as contact_id', DB::raw('GREATEST(COALESCE(b.amount, 0), mv.sold) AS final_total'),
                'mv.refunded as returned', DB::raw('mv.sold - mv.refunded AS paid'), DB::raw("TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS name"),
                DB::raw('NULL AS supplier_business_name'), DB::raw('(b.customer_id IS NULL) AS is_default')]);

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

            $k = (string) $s->contact_id; // E-Ride: user ids are UUIDs
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
                // E-Ride: ticket money, counter and app (posinnova: payments on sales, 'sell')
                if (str_starts_with($r['source'], 'bus_move_') && $r['date'] >= $m['start'] && $r['date'] <= $m['end']) {
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
