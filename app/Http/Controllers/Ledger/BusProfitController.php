<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Services\DefaultAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusVehicle;

/**
 * Profit by bus, trip or route (E-Ride, bus books). Every line the bus records post
 * carries its trip, bus and route (PostingRules::tags); an expense or a supplier bill
 * line may name its bus; a bus's monthly wear carries the bus. This reads the posted
 * income and expense lines (classes 6, 7 and 8, as the income statement) of a period
 * and adds them up per bus, trip or route. What no bus carries (staff pay, rent, E-Ride's
 * commission) is one row of its own, so the total is the period's result.
 */
class BusProfitController extends LedgerController
{
    public const BY = ['bus' => 'vehicle_id', 'trip' => 'departure_id', 'route' => 'destination_id'];

    public function index(Request $request)
    {
        $this->allow('accounting-report-view');
        $by = array_key_exists($request->input('by'), self::BY) ? $request->input('by') : 'bus';
        $from = $this->date($request->input('from'), Carbon::today()->startOfMonth());
        $to = $this->date($request->input('to'), Carbon::today());
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $report = $this->report($by, $from->toDateString(), $to->toDateString());

        if ($request->input('export') === 'csv') {
            return $this->csv($report, $by, $from, $to);
        }

        return view('ledger.bus-profit.index', $report + ['by' => $by, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }

    /** The rows of the report: one per bus, trip or route, then what none carries, then the total. */
    public function report(string $by, string $from, string $to): array
    {
        $col = self::BY[$by];
        $sales = (int) DefaultAccount::id('ticket_sales');
        $fees = array_values(array_filter([(int) DefaultAccount::id('gateway_fees'), (int) DefaultAccount::id('eride_commission')]));
        $lines = DB::table('journal_entry_lines as l')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.business_id', Ledger::businessId())->whereNull('j.deleted_at')->where('j.is_posted', 1)
            ->whereBetween('j.entry_date', [$from, $to])
            ->whereIn('a.class_number', [6, 7, 8])
            ->groupBy('l.'.$col, 'l.account_id', 'a.account_code', 'a.class_number', 'a.normal_balance')
            ->selectRaw('l.'.$col.' as k, l.account_id, a.account_code, a.class_number, a.normal_balance, SUM(l.credit) - SUM(l.debit) as net, COUNT(DISTINCT l.departure_id) as trips')
            ->get();

        $rows = [];
        $blank = ['sales' => 0.0, 'other_income' => 0.0, 'fees' => 0.0, 'running' => 0.0, 'wear' => 0.0, 'trips' => 0, 'result' => 0.0, 'margin' => null];
        foreach ($lines as $l) {
            $key = $l->k === null || $l->k === '' ? '' : (string) $l->k;
            $r = $rows[$key] ?? $blank;
            $net = round((float) $l->net, 2);
            $income = (int) $l->class_number === 7 || ((int) $l->class_number === 8 && $l->normal_balance === 'credit');
            if ((int) $l->account_id === $sales) {
                $r['sales'] += $net;
                $r['trips'] = max($r['trips'], (int) $l->trips);
            } elseif ($income) {
                $r['other_income'] += $net;
            } elseif (in_array((int) $l->account_id, $fees, true)) {
                $r['fees'] -= $net;
            } elseif (str_starts_with((string) $l->account_code, '681')) {
                $r['wear'] -= $net;
            } else {
                $r['running'] -= $net;
            }
            $rows[$key] = $r;
        }
        // every bus of the fleet shows, even one that did nothing in the period
        if ($by === 'bus') {
            foreach (array_keys(\App\Ledger\ERide\Books::fleet()) as $vid) {
                $rows[$vid] = $rows[$vid] ?? $blank;
            }
        }
        $labels = $this->labels($by, array_filter(array_keys($rows), fn ($k) => $k !== ''));
        $out = [];
        $total = $blank;
        foreach ($rows as $key => $r) {
            $r['result'] = round($r['sales'] + $r['other_income'] - $r['fees'] - $r['running'] - $r['wear'], 2);
            $income = $r['sales'] + $r['other_income'];
            $r['margin'] = $income > 0 ? round($r['result'] / $income * 100, 1) : null;
            foreach (['sales', 'other_income', 'fees', 'running', 'wear', 'result'] as $f) {
                $total[$f] = round(($total[$f] ?? 0) + $r[$f], 2);
            }
            $total['trips'] += $r['trips'];
            if ($key === '') {
                continue;
            }
            $r['key'] = $key;
            [$r['label'], $r['sort']] = $labels[$key] ?? [__('ledger_profit.unknown', ['id' => substr($key, 0, 8)]), 'zzz'.$key];
            $out[] = $r;
        }
        usort($out, fn ($a, $b) => $by === 'trip' ? strcmp($b['sort'], $a['sort']) : strcmp($a['sort'], $b['sort']));
        $shared = $rows[''] ?? null;
        if ($shared) {
            $shared['result'] = round($shared['sales'] + $shared['other_income'] - $shared['fees'] - $shared['running'] - $shared['wear'], 2);
        }
        $income = $total['sales'] + $total['other_income'];
        $total['margin'] = $income > 0 ? round($total['result'] / $income * 100, 1) : null;

        return ['rows' => $out, 'shared' => $shared, 'total' => $total];
    }

    /** key => [label, sort key] for the buses, trips or routes. */
    private function labels(string $by, array $keys): array
    {
        $out = [];
        if (! $keys) {
            return $out;
        }
        if ($by === 'bus') {
            foreach (BusVehicle::whereIn('id', $keys)->get() as $v) {
                $out[(string) $v->id] = [trim($v->registration.' · '.$v->name, ' ·'), (string) $v->registration];
            }
        } elseif ($by === 'route') {
            foreach (BusDestination::whereIn('id', $keys)->get() as $d) {
                $out[(string) $d->id] = [$d->city, (string) $d->city];
            }
        } else {
            foreach (BusDeparture::with(['destination', 'vehicle'])->whereIn('id', $keys)->get() as $d) {
                $when = Carbon::parse($d->date->toDateString().' '.$d->time);
                $out[(string) $d->id] = [trim($when->format('d/m/Y H:i').' · '.($d->destination->city ?? '').' · '.($d->vehicle->registration ?? ''), ' ·'),
                    $when->format('Y-m-d H:i')];
            }
        }

        return $out;
    }

    private function date($value, Carbon $default): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function csv(array $report, string $by, Carbon $from, Carbon $to)
    {
        $name = 'profit-by-'.$by.'-'.$from->toDateString().'-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($report, $by) {
            $h = fopen('php://output', 'w');
            fputcsv($h, [__('ledger_profit.col_'.$by), __('ledger_profit.col_trips'), __('ledger_profit.col_sales'), __('ledger_profit.col_other_income'),
                __('ledger_profit.col_fees'), __('ledger_profit.col_running'), __('ledger_profit.col_wear'), __('ledger_profit.col_result'), __('ledger_profit.col_margin')]);
            $row = fn ($label, $r) => [$label, $r['trips'], $r['sales'], $r['other_income'], $r['fees'], $r['running'], $r['wear'], $r['result'], $r['margin'] ?? ''];
            foreach ($report['rows'] as $r) {
                fputcsv($h, $row($r['label'], $r));
            }
            if ($report['shared']) {
                fputcsv($h, $row(__('ledger_profit.shared_'.$by), $report['shared'] + ['margin' => null]));
            }
            fputcsv($h, $row(__('ledger_profit.total'), $report['total']));
            fclose($h);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
