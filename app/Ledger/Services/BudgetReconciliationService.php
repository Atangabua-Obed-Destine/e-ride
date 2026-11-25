<?php

namespace App\Ledger\Services;

use App\Ledger\Ledger;
use App\Ledger\Models\BudgetLine;
use Illuminate\Support\Facades\DB;

/**
 * Does the budget sheet agree with the ledger? (paxhitestbed
 * BudgetReconciliationService.)
 *
 * The sheet side is BudgetActualsService (the one place that decides where
 * money lands on the sheet); the ledger side is a plain sum of the posted
 * journal lines per class, read without any of the sheet's joins, so a fault
 * in how the sheet resolves a document (a line counted twice, one lost) shows
 * up as a difference. Both read the same entries: posted, not deleted, not
 * the year-end closing.
 *
 * Money the sheet could not place on a line makes the difference, and is named
 * as an issue. Known differences are named apart: documents that failed to
 * post and draft entries are in neither the ledger nor the sheet yet.
 */
class BudgetReconciliationService
{
    protected const SECTION_CLASSES = [
        'income' => ['class' => 7, 'normal' => 'credit'],
        'expenditure' => ['class' => 6, 'normal' => 'debit'],
        'capital' => ['class' => 2, 'normal' => 'debit'],
    ];

    /** @var BudgetActualsService */
    protected $actuals;

    public function __construct(BudgetActualsService $actuals)
    {
        $this->actuals = $actuals;
    }

    public function reconcile(?string $from = null, ?string $to = null): array
    {
        $actuals = $this->actuals->forPeriod($from, $to);

        $sections = [];
        foreach (self::SECTION_CLASSES as $section => $meta) {
            $sheet = $this->actuals->sectionTotal($actuals['lines'], $section);
            $accounts = $this->ledgerAccounts($meta['class'], $meta['normal'], $from, $to);
            $ledger = round(array_sum(array_column($accounts, 'amount')), 2);
            $difference = round($sheet - $ledger, 2);
            $sections[] = [
                'section' => $section,
                'label' => BudgetLine::sectionLabel($section),
                'class' => $meta['class'],
                'sheet' => $sheet,
                'ledger' => $ledger,
                'difference' => $difference,
                'explained' => 0.0,
                'unexplained' => $difference,
                'agrees' => abs($difference) < 0.01,
                'accounts' => $accounts,
            ];
        }

        $issues = [];
        foreach ($actuals['unallocated'] as $section => $amount) {
            $issues[] = [
                'type' => 'unallocated',
                'detail' => __('ledger_budget.issue_unallocated', ['section' => BudgetLine::sectionLabel($section), 'amount' => Ledger::money($amount)]),
            ];
        }
        $issues = array_merge($issues, $this->shadowedLines());

        $agrees = true;
        foreach ($sections as $s) {
            $agrees = $agrees && $s['agrees'];
        }

        return [
            'sections' => $sections,
            'issues' => $issues,
            'known_differences' => $this->knownDifferences($from, $to),
            'unallocated' => $actuals['unallocated'],
            'agrees' => $agrees && $issues === [],
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Net movement per account in one ledger class, in its normal direction.
     * paxhitestbed summed every journal line here, drafts and deleted entries
     * included; only posted, live entries count.
     *
     * @return array<int, array{code:string,name:string,amount:float}>
     */
    protected function ledgerAccounts(int $class, string $normal, ?string $from, ?string $to): array
    {
        $signed = $normal === 'credit' ? 'SUM(l.credit) - SUM(l.debit)' : 'SUM(l.debit) - SUM(l.credit)';
        $bid = Ledger::businessId();
        $rows = DB::table('journal_entry_lines as l')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('l.business_id', $bid)->where('a.business_id', $bid)->where('j.business_id', $bid)
            ->where('j.is_posted', 1)->whereNull('j.deleted_at')->where('j.journal_type', '<>', 'closing')
            ->where('a.class_number', $class)
            ->when($class === 2, fn ($q) => $q->where('a.account_code', 'not like', '28%')->where('a.account_code', 'not like', '29%'))
            ->when($from, fn ($q) => $q->whereDate('j.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('j.entry_date', '<=', $to))
            ->groupBy('a.account_code', 'a.account_name', 'a.account_name_fr')
            ->havingRaw($signed.' <> 0')
            ->orderBy('a.account_code')
            ->selectRaw('a.account_code, a.account_name, a.account_name_fr, '.$signed.' as amount')
            ->get();

        return $rows->map(fn ($r) => [
            'code' => $r->account_code,
            'name' => FinancialStatements::name($r),
            'amount' => round((float) $r->amount, 2),
        ])->all();
    }

    /**
     * Lines that can never receive money because another line has exactly the
     * same target and comes first: all the money goes to the first.
     */
    protected function shadowedLines(): array
    {
        $seen = [];
        $issues = [];
        foreach (BudgetLine::active()->postable()->whereNotNull('account_id')->orderBy('sort_order')->orderBy('id')->get() as $line) {
            $key = $line->account_id.'|'.$line->expense_category_id.'|'.$line->location_id;
            if (isset($seen[$key])) {
                $issues[] = ['type' => 'shadowed_line', 'detail' => __('ledger_budget.issue_shadowed', ['line' => $line->label, 'first' => $seen[$key]->label])];
                continue;
            }
            $seen[$key] = $line;
        }

        return $issues;
    }

    /** What is in neither the ledger nor the sheet yet, with its amount. */
    protected function knownDifferences(?string $from, ?string $to): array
    {
        $bid = Ledger::businessId();
        $out = [];

        // E-Ride: the records that failed to post are the mapping rows marked failed (bus records, income,
        // expenses, bills, payroll...), dated and valued by the mapping when it knows (posinnova: POS transactions)
        $failed = DB::table('transaction_mappings as m')
            ->where('m.business_id', $bid)->where('m.status', 'failed')
            ->when($from, fn ($q) => $q->where(fn ($w) => $w->whereNull('m.transaction_date')->orWhereDate('m.transaction_date', '>=', $from)))
            ->when($to, fn ($q) => $q->where(fn ($w) => $w->whereNull('m.transaction_date')->orWhereDate('m.transaction_date', '<=', $to)))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(m.amount), 0) as total')->first();
        if ($failed && (int) $failed->n > 0) {
            $out[] = ['section' => null, 'amount' => (float) $failed->total,
                'detail' => __('ledger_budget.known_failed', ['count' => (int) $failed->n, 'amount' => Ledger::money($failed->total)])];
        }

        $drafts = DB::table('journal_entries')->where('business_id', $bid)->where('is_posted', 0)->whereNull('deleted_at')
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total_debit), 0) as total')->first();
        if ($drafts && (int) $drafts->n > 0) {
            $out[] = ['section' => null, 'amount' => (float) $drafts->total,
                'detail' => __('ledger_budget.known_drafts', ['count' => (int) $drafts->n, 'amount' => Ledger::money($drafts->total)])];
        }

        return $out;
    }

    /**
     * What each line reads, keyed by line id: "6052 Electricity · Electricity
     * (category) · Shop (location)". The account names the line's place in the
     * ledger, the filters say what narrows it.
     *
     * @return array<int, string[]>
     */
    public function accountsByLine(): array
    {
        $map = [];
        foreach (BudgetLine::postable()->with(['account', 'expenseCategory', 'location'])->get() as $line) {
            if (! $line->account) {
                continue;
            }
            $parts = [$line->account->account_code.' '.Preview::accountName($line->account)];
            if ($line->expenseCategory) {
                $parts[] = __('ledger_budget.ref_category', ['name' => $line->expenseCategory->name]);
            }
            if ($line->location) {
                $parts[] = __('ledger_budget.ref_location', ['name' => $line->location->name]);
            }
            $map[$line->id] = $parts;
        }

        return $map;
    }
}
