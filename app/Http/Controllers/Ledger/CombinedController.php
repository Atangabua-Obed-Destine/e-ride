<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\ERide\Books;
use App\Ledger\Ledger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Combined statements of a bus company (E-Ride): the head office's books and each
 * branch's, side by side, and their sum. Balance sheet accounts (classes 1 to 5)
 * at the end date, income and expenses (classes 6 to 8) over the period. The
 * liaison accounts (class 18: the branches' 1811 "Head office" and the head
 * office's 182x per branch) mirror each other and cancel in the combined column;
 * what is left there is listed apart, as a difference to look into.
 * Only from a head office's books (its director or accountant).
 */
class CombinedController extends LedgerController
{
    public function index(Request $request)
    {
        $this->allow('trial-balance-view');
        $books = Books::current();
        abort_unless(($books['type'] ?? null) === Books::COMPANY, 404);
        $company = $books['owner'];

        $to = $this->date($request->input('end_date')) ?? Carbon::today()->toDateString();
        $from = $this->date($request->input('start_date')) ?? Carbon::parse($to)->startOfYear()->toDateString();

        // the sets of books: the head office first, then each branch that keeps books
        $sets = [['id' => Ledger::businessId(), 'name' => __('ledger.combined_head_office')]];
        $branches = DB::table('bus_agencies')->where('company_id', $company->id)->get(['id', 'name', 'branch_label']);
        foreach ($branches as $b) {
            $bid = (int) DB::table('ledger_businesses')->where('owner_type', Books::AGENCY)->where('owner_id', $b->id)->value('id');
            if ($bid && Ledger::isSetUp($bid)) {
                $sets[] = ['id' => $bid, 'name' => $b->branch_label ?: $b->name];
            }
        }
        $ids = array_column($sets, 'id');

        $rows = DB::table('journal_entry_lines as l')->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('j.business_id', $ids)->whereNull('j.deleted_at')->where('j.is_posted', 1)
            ->where('j.entry_date', '<=', $to)
            ->where(fn ($w) => $w->where('a.class_number', '<=', 5)->orWhere('j.entry_date', '>=', $from))
            ->groupBy('j.business_id', 'a.account_code', 'a.class_number')
            ->selectRaw('j.business_id as b, a.account_code as code, a.class_number as cls, SUM(l.debit) - SUM(l.credit) as net')
            ->get();
        $names = DB::table('chart_of_accounts')->whereIn('business_id', $ids)->whereIn('account_code', $rows->pluck('code')->unique())
            ->orderByRaw('business_id = ? desc', [$ids[0]])->get(['account_code', 'account_name', 'account_name_fr'])
            ->unique('account_code')->keyBy('account_code');

        $table = [];
        foreach ($rows as $r) {
            $liaison = str_starts_with((string) $r->code, '18');
            $key = $liaison ? '18' : (string) $r->code;
            $table[$key] ??= ['code' => $key, 'class' => (int) $r->cls, 'liaison' => $liaison, 'by' => [], 'total' => 0.0];
            $table[$key]['by'][(int) $r->b] = ($table[$key]['by'][(int) $r->b] ?? 0) + (float) $r->net;
            $table[$key]['total'] += (float) $r->net;
        }
        ksort($table, SORT_STRING);
        foreach ($table as $k => &$t) {
            $n = $names[$k] ?? null;
            $t['name'] = $t['liaison'] ? __('ledger.combined_liaison') : ($n ? (app()->getLocale() === 'fr' && $n->account_name_fr ? $n->account_name_fr : $n->account_name) : '');
        }
        unset($t);
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = -1 * array_sum(array_map(fn ($t) => $t['class'] >= 6 ? ($t['by'][$id] ?? 0) : 0, $table));
        }

        return view('ledger.combined.index', [
            'sets' => $sets, 'table' => $table, 'result' => $result, 'from' => $from, 'to' => $to,
            'company' => $company,
        ]);
    }

    private function date($v): ?string
    {
        if (! $v) {
            return null;
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
