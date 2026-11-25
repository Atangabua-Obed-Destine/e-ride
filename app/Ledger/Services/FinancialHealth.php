<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Financial health (paxhitestbed App\Services\Accounting\FinancialHealth):
 * the handful of figures an accountant and an owner look at first, read from
 * the books (posted journal lines of this business), each with what it means
 * and a plain verdict.
 *
 *   cash and months of cover    can the business pay its way?
 *   surplus and margin          is it living within its income this year?
 *   payroll share of income     the cost least easily cut
 *   sales collected (411)       how much of what customers were invoiced is in
 *   supplier bills overdue      is the business paying its suppliers on time?
 *   held for customers (419)    advances not yet used: owed to customers
 *
 * plus twelve months of income, expenses, surplus and closing cash, and this
 * year to date against the same months last year.
 *
 * PosInnova differences: a shop or clinic, not a school. Fees collected
 * becomes what customers were invoiced on account 411 (every sale passes
 * through it) and paid; "held for students" becomes customer advances (419);
 * overdue supplier bills are the received purchases past their payment term;
 * no budget measure until PosInnova has budgets. Every raw query carries
 * business_id (they are not covered by BelongsToBusiness). Ledger health
 * (do the documents and the ledger agree?) stays on its own screen; this one
 * links to it and says when postings have failed.
 *
 * Nothing here is stored: every call reads the ledger as it stands.
 */
class FinancialHealth
{
    public const OK = 'ok';
    public const WATCH = 'watch';
    public const ACT = 'act';

    /**
     * Income and expenses: classes 7 and 6, and the HAO accounts of class 8
     * (82 disposal proceeds and the other income of 84 / 86 / 88; 81 book
     * value of disposals, 83 / 85 / 87, 89 income tax). paxhitestbed reads
     * 7 and 6 only, so a disposal's gain or loss (81 / 82) never reached its
     * surplus.
     */
    public const INCOME = ['7', '82', '84', '86', '88'];
    public const EXPENSES = ['6', '81', '83', '85', '87', '89'];

    private $bid;

    public function __construct()
    {
        $this->bid = Ledger::businessId();
    }

    public function summary(?string $asOf = null): array
    {
        $asOf = Carbon::parse($asOf ?: now())->toDateString();
        $year = FiscalYear::forDate($asOf);
        $from = $year ? $year->start_date->toDateString() : Carbon::parse($asOf)->startOfYear()->toDateString();
        $nice = fn ($d) => Carbon::parse($d)->format('d/m/Y');
        $m = fn ($n) => Ledger::money($n);

        $cash = $this->balance('5', $asOf, ['59']);
        $income = $this->movement(self::INCOME, $from, $asOf, 'credit');
        $expenses = $this->movement(self::EXPENSES, $from, $asOf, 'debit');
        $payroll = $this->movement('66', $from, $asOf, 'debit');
        $surplus = round($income - $expenses, 2);

        // Average cash spending per month over the last three complete months:
        // class 6 without depreciation (68), which spends no cash.
        $end = Carbon::parse($asOf)->startOfMonth()->subDay();
        $start = (clone $end)->subMonthsNoOverflow(2)->startOfMonth();
        $monthlySpend = round(($this->movement('6', $start->toDateString(), $end->toDateString(), 'debit')
            - $this->movement('68', $start->toDateString(), $end->toDateString(), 'debit')) / 3, 2);
        $cover = $monthlySpend > 0 ? round($cash / $monthlySpend, 1) : null;

        $sales = $this->salesCollected($from, $asOf);
        $payables = $this->payables($asOf);
        $credit = -$this->balance('419', $asOf);
        $lastYear = $this->sameTimeLastYear($from, $asOf);

        $indicators = [
            $this->indicator('cash', __('ledger_assets.fh_cash'), $cash, 'money',
                $cash < 0 ? self::ACT : self::OK,
                __('ledger_assets.fh_cash_detail', ['date' => $nice($asOf)])),
            $this->indicator('cover', __('ledger_assets.fh_cover'), $cover, 'months',
                $cover === null ? self::WATCH : ($cover < 1 ? self::ACT : ($cover < 3 ? self::WATCH : self::OK)),
                __('ledger_assets.fh_cover_detail', ['amount' => $m($monthlySpend)])),
            $this->indicator('surplus', __('ledger_assets.fh_surplus'), $surplus, 'money',
                $surplus < 0 ? self::WATCH : self::OK,
                __('ledger_assets.fh_surplus_detail', ['date' => $nice($from)])),
            $this->indicator('margin', __('ledger_assets.fh_margin'), $income > 0 ? round($surplus / $income * 100, 1) : null, 'percent',
                $income <= 0 ? self::WATCH : ($surplus / $income < 0 ? self::ACT : ($surplus / $income < 0.05 ? self::WATCH : self::OK)),
                __('ledger_assets.fh_margin_detail')),
            $this->indicator('payroll', __('ledger_assets.fh_payroll'), $income > 0 ? round($payroll / $income * 100, 1) : null, 'percent',
                $income <= 0 ? self::WATCH : ($payroll / $income > 0.8 ? self::ACT : ($payroll / $income > 0.65 ? self::WATCH : self::OK)),
                __('ledger_assets.fh_payroll_detail')),
            $this->indicator('collection', __('ledger_assets.fh_collection'), $sales['rate'], 'percent',
                $sales['rate'] === null ? self::WATCH : ($sales['rate'] < 60 ? self::ACT : ($sales['rate'] < 80 ? self::WATCH : self::OK)),
                __('ledger_assets.fh_collection_detail', ['start' => $nice($from), 'owed' => $m($sales['owed']), 'billed' => $m($sales['billed']),
                    'date' => $nice($asOf), 'arrears' => $m($sales['arrears'])])),
            $this->indicator('payables', __('ledger_assets.fh_payables'), $payables['overdue'], 'money',
                $payables['overdue'] > 0.005 ? self::WATCH : self::OK,
                __('ledger_assets.fh_payables_detail', ['owed' => $m($payables['owed']), 'overdue' => $m($payables['overdue'])])),
            $this->indicator('credit', __('ledger_assets.fh_credit'), $credit, 'money', self::OK,
                __('ledger_assets.fh_credit_detail')),
        ];

        return [
            'as_of' => $asOf,
            'from' => $from,
            'year' => $year->name ?? null,
            'income' => $income,
            'expenses' => $expenses,
            'surplus' => $surplus,
            'cash' => $cash,
            'indicators' => $indicators,
            'attention' => array_values(array_filter($indicators, fn ($i) => $i['state'] !== self::OK)),
            'months' => $this->months($asOf),
            'last_year' => $lastYear,
            'sales' => $sales,
            'payables' => $payables,
            'failed_postings' => DB::table('transaction_mappings')->where('business_id', $this->bid)->where('status', 'failed')->count(),
        ];
    }

    /** Twelve months to $asOf: income, expenses, surplus and closing cash, from the books. */
    public function months(string $asOf): array
    {
        $end = Carbon::parse($asOf)->endOfMonth();
        $start = (clone $end)->subMonthsNoOverflow(11)->startOfMonth();

        $rows = $this->lines()
            ->where('e.journal_type', '!=', 'closing')
            ->whereDate('e.entry_date', '>=', $start->toDateString())
            ->whereDate('e.entry_date', '<=', min($end->toDateString(), $asOf))
            ->selectRaw("DATE_FORMAT(e.entry_date, '%Y-%m') as ym,
                SUM(CASE WHEN ".self::likeSql(self::INCOME)." THEN l.credit - l.debit ELSE 0 END) as income,
                SUM(CASE WHEN ".self::likeSql(self::EXPENSES)." THEN l.debit - l.credit ELSE 0 END) as expenses,
                SUM(CASE WHEN a.account_code LIKE '5%' AND a.account_code NOT LIKE '59%' THEN l.debit - l.credit ELSE 0 END) as cash_move")
            ->groupBy('ym')->get()->keyBy('ym');

        $cash = $this->balance('5', (clone $start)->subDay()->toDateString(), ['59']);
        $out = [];
        for ($mo = clone $start; $mo <= $end; $mo->addMonthNoOverflow()) {
            $key = $mo->format('Y-m');
            $r = $rows->get($key);
            $income = round((float) ($r->income ?? 0), 2);
            $expenses = round((float) ($r->expenses ?? 0), 2);
            $cash = round($cash + (float) ($r->cash_move ?? 0), 2);
            $out[] = [
                'month' => $key,
                'label' => $mo->translatedFormat('M Y'),
                'income' => $income,
                'expenses' => $expenses,
                'surplus' => round($income - $expenses, 2),
                'cash' => $cash,
            ];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */

    /** Posted lines of this business's live entries, with their account. */
    private function lines()
    {
        return DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.business_id', $this->bid)->where('a.business_id', $this->bid)
            ->where('e.is_posted', 1)->whereNull('e.deleted_at');
    }

    /** Balance (debit − credit) of every account under $prefix at the end of $asOf, opening balances included. */
    public function balance(string $prefix, string $asOf, array $except = []): float
    {
        $q = $this->lines()->where('a.account_code', 'like', $prefix.'%')->whereDate('e.entry_date', '<=', $asOf);
        $open = DB::table('chart_of_accounts')->where('business_id', $this->bid)->whereNull('deleted_at')->where('account_code', 'like', $prefix.'%');
        foreach ($except as $x) {
            $q->where('a.account_code', 'not like', $x.'%');
            $open->where('account_code', 'not like', $x.'%');
        }
        $opening = (float) $open->sum(DB::raw("CASE WHEN normal_balance = 'credit' THEN -opening_balance ELSE opening_balance END"));

        return round($opening + (float) $q->sum(DB::raw('l.debit - l.credit')), 2);
    }

    /** Movement on accounts under $prefix (one or several) between two dates, in their normal direction; closing entries left out. */
    public function movement($prefix, string $from, string $to, string $normal): float
    {
        return round((float) $this->lines()->where('e.journal_type', '!=', 'closing')
            ->where(function ($q) use ($prefix) {
                foreach ((array) $prefix as $p) {
                    $q->orWhere('a.account_code', 'like', $p.'%');
                }
            })
            ->whereDate('e.entry_date', '>=', $from)->whereDate('e.entry_date', '<=', $to)
            ->sum(DB::raw($normal === 'credit' ? 'l.credit - l.debit' : 'l.debit - l.credit')), 2);
    }

    /** SQL true for an account under one of $prefixes (constants only: digits). */
    private static function likeSql(array $prefixes): string
    {
        return '('.implode(' OR ', array_map(fn ($p) => "a.account_code LIKE '".preg_replace('/\D/', '', $p)."%'", $prefixes)).')';
    }

    /** One side of the movement on accounts under $prefix between two dates. */
    private function side(string $prefix, string $from, string $to, string $side): float
    {
        return round((float) $this->lines()->where('e.journal_type', '!=', 'closing')
            ->where('a.account_code', 'like', $prefix.'%')
            ->whereDate('e.entry_date', '>=', $from)->whereDate('e.entry_date', '<=', $to)
            ->sum('l.'.$side), 2);
    }

    /**
     * Customers (411): what was invoiced to them this year and how much of it
     * has been paid, and apart, what is still owed from before the year.
     * Payments settle the oldest debt first: they clear what was owed at the
     * start of the year before they count against this year's invoices, so
     * old arrears do not flatter this year's rate.
     */
    protected function salesCollected(string $from, string $asOf): array
    {
        $opening = max(0, $this->balance('411', Carbon::parse($from)->subDay()->toDateString()));
        $billed = $this->side('411', $from, $asOf, 'debit');
        $paid = $this->side('411', $from, $asOf, 'credit');
        $arrears = round(max(0, $opening - $paid), 2);
        $collected = round(min($billed, max(0, $paid - $opening)), 2);

        return [
            'billed' => $billed,
            'collected' => $collected,
            'owed' => round(max(0, $billed - $collected), 2),
            'arrears' => $arrears,
            'rate' => $billed > 0 ? round($collected / $billed * 100, 1) : null,
        ];
    }

    /**
     * Owed to suppliers: the credit balance of 40 (less 409, advances paid to
     * them) in the ledger; overdue: received purchases still unpaid after
     * their payment term (no term: due on the purchase date).
     */
    protected function payables(string $asOf): array
    {
        $owed = round(-$this->balance('40', $asOf, ['409']), 2);
        $paid = DB::table('transaction_payments')->select('transaction_id', DB::raw('SUM(IF(is_return = 1, -amount, amount)) as paid'))
            ->where('business_id', $this->bid)->groupBy('transaction_id');
        $rows = DB::table('transactions as t')
            ->leftJoinSub($paid, 'p', 'p.transaction_id', '=', 't.id')
            ->where('t.business_id', $this->bid)->where('t.type', 'purchase')->where('t.status', 'received')
            ->whereIn('t.payment_status', ['due', 'partial'])
            ->whereDate('t.transaction_date', '<=', $asOf)
            ->get(['t.transaction_date', 't.pay_term_number', 't.pay_term_type', 't.final_total', 'p.paid']);
        $overdue = 0.0;
        foreach ($rows as $r) {
            $due = Carbon::parse($r->transaction_date)->startOfDay();
            if ($r->pay_term_number) {
                $r->pay_term_type === 'months' ? $due->addMonthsNoOverflow((int) $r->pay_term_number) : $due->addDays((int) $r->pay_term_number);
            }
            if ($due->toDateString() < $asOf) {
                $overdue += max(0, (float) $r->final_total - (float) $r->paid);
            }
        }

        return ['owed' => max(0, $owed), 'overdue' => round($overdue, 2)];
    }

    protected function sameTimeLastYear(string $from, string $to): array
    {
        $lyFrom = Carbon::parse($from)->subYear()->toDateString();
        $lyTo = Carbon::parse($to)->subYear()->toDateString();
        $income = $this->movement(self::INCOME, $lyFrom, $lyTo, 'credit');
        $expenses = $this->movement(self::EXPENSES, $lyFrom, $lyTo, 'debit');

        return ['from' => $lyFrom, 'to' => $lyTo, 'income' => $income, 'expenses' => $expenses, 'surplus' => round($income - $expenses, 2)];
    }

    protected function indicator(string $key, string $label, $value, string $unit, string $state, string $detail): array
    {
        return compact('key', 'label', 'value', 'unit', 'state', 'detail');
    }
}
