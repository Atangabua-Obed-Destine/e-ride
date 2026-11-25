<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Exports\BudgetVsActualExport;
use App\Ledger\Ledger;
use App\Ledger\Models\Budget;
use App\Ledger\Models\BudgetAllocation;
use App\Ledger\Models\BudgetLine;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Services\BudgetActualsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Budget reports (paxhitestbed BudgetReportController: performance, variance,
 * department, cash-flow projection) and Budget vs Actual (paxhitestbed
 * AccountingReportsController@budgetVsActual). Figures are refreshed from the
 * posted ledger before they are read.
 *
 * Fixed on the way: paxhitestbed's "Export PDF" called an unimported class and
 * failed; the cash-flow projection never matched an allocation period (it
 * tested monthly / quarterly / annual, the form offers yearly / q1..q4 /
 * semesters) and laid every budget on January to December; an income line
 * that under-collected showed as favourable; Budget vs Actual's screen read
 * variables the controller never passed, so it always showed "No data found".
 */
class BudgetReportController extends LedgerController
{
    /** @var BudgetActualsService */
    protected $actuals;

    public function __construct(BudgetActualsService $actuals)
    {
        parent::__construct();
        $this->actuals = $actuals;
    }

    /** Budget vs actual by allocation. */
    public function performance(Request $request)
    {
        $this->allow('budget-view');
        $budgets = Budget::query()
            ->when($request->filled('budget_id'), fn ($q) => $q->where('id', $request->budget_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('budget_type'), fn ($q) => $q->where('type', $request->budget_type))
            ->when($request->filled('fiscal_year'), fn ($q) => $q->where('fiscal_year', $request->fiscal_year))
            ->orderBy('budget_code')->get();
        $reportData = [];
        foreach ($budgets as $budget) {
            foreach ($this->allocationRows($budget) as $row) {
                $reportData[] = $row;
            }
        }
        if ($request->get('export') === 'pdf') {
            return $this->tablePdf(__('ledger_budget.budget_performance_report'),
                [__('ledger_budget.budget_code'), __('ledger_budget.budget_title'), __('ledger_budget.category'), __('ledger_budget.allocated'),
                    __('ledger_budget.spent'), __('ledger_budget.remaining'), __('ledger_budget.variance'), __('ledger_budget.variance_percent'), __('ledger_budget.status')],
                array_map(fn ($r) => [$r['budget_code'], $r['budget_title'], $r['category'], Ledger::money($r['allocated']), Ledger::money($r['spent']),
                    Ledger::money($r['remaining']), Ledger::money($r['variance']), number_format($r['variance_percent'], 1).'%', __('ledger_budget.'.$r['status'])], $reportData),
                ['', '', __('ledger_budget.total'), Ledger::money(collect($reportData)->sum('allocated')), Ledger::money(collect($reportData)->sum('spent')),
                    Ledger::money(collect($reportData)->sum('remaining')), Ledger::money(collect($reportData)->sum('variance')), '', ''],
                'budget-performance-report-'.date('Y-m-d').'.pdf', [3, 4, 5, 6, 7]);
        }

        return view('ledger.budget.reports.performance', [
            'reportData' => $reportData,
            'allBudgets' => Budget::orderBy('title')->get(),
            'fiscalYears' => Budget::select('fiscal_year')->distinct()->orderBy('fiscal_year', 'desc')->pluck('fiscal_year')->filter(),
        ]);
    }

    /** Favourable and unfavourable variances. */
    public function variance(Request $request)
    {
        $this->allow('budget-view');
        $budgets = Budget::query()->when($request->filled('budget_id'), fn ($q) => $q->where('id', $request->budget_id))->orderBy('budget_code')->get();
        $rows = collect();
        foreach ($budgets as $budget) {
            foreach ($this->allocationRows($budget) as $row) {
                $rows->push($row);
            }
        }
        if ($request->filled('variance_type')) {
            $rows = $rows->where('status', $request->variance_type === 'favorable' ? 'favorable' : 'unfavorable')->values();
        }
        $summary = [
            'total_allocations' => $rows->count(),
            'favorable_count' => $rows->where('status', 'favorable')->count(),
            'unfavorable_count' => $rows->where('status', 'unfavorable')->count(),
            'total_variance' => round($rows->sum('variance'), 2),
        ];
        if ($request->get('export') === 'pdf') {
            return $this->tablePdf(__('ledger_budget.budget_variance_report'),
                [__('ledger_budget.budget_code'), __('ledger_budget.budget_title'), __('ledger_budget.category'), __('ledger_budget.allocated'),
                    __('ledger_budget.spent'), __('ledger_budget.variance'), __('ledger_budget.variance_percent'), __('ledger_budget.status')],
                $rows->map(fn ($r) => [$r['budget_code'], $r['budget_title'], $r['category'], Ledger::money($r['allocated']), Ledger::money($r['spent']),
                    Ledger::money($r['variance']), number_format($r['variance_percent'], 1).'%', __('ledger_budget.'.$r['status'])])->all(),
                ['', '', __('ledger_budget.total_variance'), '', '', Ledger::money($summary['total_variance']), '', ''],
                'budget-variance-report-'.date('Y-m-d').'.pdf', [3, 4, 5, 6]);
        }

        return view('ledger.budget.reports.variance', [
            'reportData' => $rows->all(),
            'summary' => $summary,
            'allBudgets' => Budget::orderBy('title')->get(),
        ]);
    }

    /** Spending compared across departments (locations). */
    public function department(Request $request)
    {
        $this->allow('budget-view');
        $budgets = Budget::with('location')->where('type', 'departmental')->whereNotNull('location_id')
            ->when($request->filled('fiscal_year'), fn ($q) => $q->where('fiscal_year', $request->fiscal_year))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->get();
        $data = [];
        foreach ($budgets as $budget) {
            $this->actuals->refresh($budget);
            $id = $budget->location_id;
            if (! isset($data[$id])) {
                $data[$id] = ['department' => optional($budget->location)->name ?? '#'.$id, 'budget_count' => 0, 'total_budget' => 0,
                    'total_allocated' => 0, 'total_spent' => 0, 'total_remaining' => 0, 'utilization_rate' => 0];
            }
            $data[$id]['budget_count']++;
            $data[$id]['total_budget'] += (float) $budget->total_amount;
            $data[$id]['total_allocated'] += (float) $budget->allocated_amount;
            $data[$id]['total_spent'] += (float) $budget->spent_amount;
            $data[$id]['total_remaining'] += (float) $budget->remaining_amount;
        }
        foreach ($data as $k => $d) {
            $data[$k]['utilization_rate'] = $d['total_budget'] > 0 ? $d['total_spent'] / $d['total_budget'] * 100 : 0;
        }
        usort($data, fn ($a, $b) => $b['total_spent'] <=> $a['total_spent']);
        if ($request->get('export') === 'pdf') {
            return $this->tablePdf(__('ledger_budget.department_expenditure_report'),
                [__('ledger_budget.department'), __('ledger_budget.budgets'), __('ledger_budget.total_budget'), __('ledger_budget.total_spent'),
                    __('ledger_budget.total_remaining'), __('ledger_budget.utilization')],
                array_map(fn ($r) => [$r['department'], $r['budget_count'], Ledger::money($r['total_budget']), Ledger::money($r['total_spent']),
                    Ledger::money($r['total_remaining']), number_format($r['utilization_rate'], 1).'%'], $data),
                null, 'department-expenditure-report-'.date('Y-m-d').'.pdf', [2, 3, 4, 5]);
        }

        return view('ledger.budget.reports.department', [
            'reportData' => $data,
            'fiscalYears' => Budget::select('fiscal_year')->distinct()->orderBy('fiscal_year', 'desc')->pluck('fiscal_year')->filter(),
        ]);
    }

    /** Planned against actual spending, month by month over the budget's own months. */
    public function cashflow(Request $request)
    {
        $this->allow('budget-view');
        $allBudgets = Budget::orderBy('title')->get();
        if (! $request->filled('budget_id')) {
            return view('ledger.budget.reports.cashflow', ['allBudgets' => $allBudgets]);
        }
        $budget = Budget::with(['allocations.budgetLine', 'allocations.account'])->findOrFail($request->budget_id);
        $this->actuals->refresh($budget);
        $budget->load('allocations');

        $months = [];
        $cursor = $budget->start_date->copy()->startOfMonth();
        while ($cursor->lte($budget->end_date) && count($months) < 60) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }
        $projections = [];
        foreach ($months as $ym) {
            $projections[$ym] = ['month' => Carbon::parse($ym.'-01')->locale(app()->getLocale())->translatedFormat('M Y'),
                'planned' => 0.0, 'actual' => 0.0, 'cumulative_planned' => 0.0, 'cumulative_actual' => 0.0];
        }
        $n = count($months);
        $spendingLines = BudgetLine::whereIn('section', ['expenditure', 'capital'])->where('is_header', false)->pluck('id')->flip();
        foreach ($budget->allocations as $a) {
            if ($budget->is_institutional && ! $spendingLines->has($a->budget_line_id)) {
                continue; // income is money expected in, not spending
            }
            // The months an allocation's period covers, counted from the budget's first month.
            $span = ['q1' => [0, 2], 'q2' => [3, 5], 'q3' => [6, 8], 'q4' => [9, 11], 'semester1' => [0, 5], 'semester2' => [6, 11]][$a->period] ?? [0, $n - 1];
            $covered = array_slice($months, $span[0], $span[1] - $span[0] + 1);
            if (! $covered) {
                $covered = $months;
            }
            foreach ($covered as $ym) {
                $projections[$ym]['planned'] += (float) $a->allocated_amount / count($covered);
            }
        }
        $from = $budget->start_date->format('Y-m-d');
        $to = $budget->end_date->format('Y-m-d');
        if ($budget->is_institutional) {
            foreach ($this->actuals->byLineAndMonth($from, $to) as $lineId => $byMonth) {
                if ($spendingLines->has($lineId)) {
                    foreach ($byMonth as $ym => $amount) {
                        if (isset($projections[$ym])) {
                            $projections[$ym]['actual'] += $amount;
                        }
                    }
                }
            }
        } else {
            foreach ($this->actuals->allocationActuals($budget, $budget->allocations, $from, $to, true) as $byMonth) {
                foreach ($byMonth as $ym => $amount) {
                    if (isset($projections[$ym])) {
                        $projections[$ym]['actual'] += $amount;
                    }
                }
            }
        }
        $cp = 0.0;
        $ca = 0.0;
        foreach ($projections as &$p) {
            $p['planned'] = round($p['planned'], 2);
            $p['actual'] = round($p['actual'], 2);
            $cp += $p['planned'];
            $ca += $p['actual'];
            $p['cumulative_planned'] = round($cp, 2);
            $p['cumulative_actual'] = round($ca, 2);
        }
        unset($p);
        $projections = array_values($projections);

        if ($request->get('export') === 'pdf') {
            return $this->tablePdf(__('ledger_budget.cashflow_projection').' — '.$budget->budget_code.' '.$budget->title,
                [__('ledger_budget.month'), __('ledger_budget.planned_spending'), __('ledger_budget.actual_spending'), __('ledger_budget.variance'),
                    __('ledger_budget.cumulative_planned'), __('ledger_budget.cumulative_actual'), __('ledger_budget.cumulative_variance')],
                array_map(fn ($p) => [$p['month'], Ledger::money($p['planned']), Ledger::money($p['actual']), Ledger::money($p['planned'] - $p['actual']),
                    Ledger::money($p['cumulative_planned']), Ledger::money($p['cumulative_actual']), Ledger::money($p['cumulative_planned'] - $p['cumulative_actual'])], $projections),
                null, 'cashflow-projection-'.$budget->budget_code.'-'.date('Y-m-d').'.pdf', [1, 2, 3, 4, 5, 6]);
        }

        return view('ledger.budget.reports.cashflow', compact('budget', 'projections', 'allBudgets'));
    }

    /**
     * Budget vs Actual for a fiscal year, against the sheet that covers it,
     * line by line, from the same resolver the sheet itself uses.
     */
    public function budgetVsActual(Request $request)
    {
        $this->allow('accounting-report-view', 'budget-view');
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
        $fiscalYear = $request->filled('fiscal_year_id') ? FiscalYear::find($request->fiscal_year_id)
            : (FiscalYear::getActiveFiscalYear() ?: $fiscalYears->first());
        if (! $fiscalYear) {
            return $this->err(route('ledger.fiscal-years.index'), __('ledger_budget.no_active_fiscal_year'));
        }
        $data = $this->budgetVsActualData($fiscalYear, (string) $request->get('period', 'ytd'), (string) $request->get('month', date('Y-m')));

        if ($request->get('export') === 'excel') {
            return Excel::download(new BudgetVsActualExport($data['report']), 'budget-vs-actual-'.$fiscalYear->name.'.xlsx');
        }

        return view('ledger.budget.reports.budget-vs-actual', $data + ['fiscalYears' => $fiscalYears, 'fiscalYear' => $fiscalYear]);
    }

    protected function budgetVsActualData(FiscalYear $fiscalYear, string $period, string $month): array
    {
        $budget = Budget::where('is_institutional', true)->where('status', '!=', 'cancelled')
            ->whereDate('start_date', '<=', $fiscalYear->end_date)->whereDate('end_date', '>=', $fiscalYear->start_date)
            ->orderByDesc('start_date')->first();
        $pFrom = $budget ? $budget->start_date->copy() : $fiscalYear->start_date->copy();
        $pTo = $budget ? $budget->end_date->copy() : $fiscalYear->end_date->copy();

        // The days measured: the whole period, or the year to the end of a month, a month, a quarter.
        $m = preg_match('/^\d{4}-\d{2}$/', $month) ? Carbon::parse($month.'-01') : Carbon::now()->startOfMonth();
        $from = $pFrom->copy();
        $to = $pTo->copy();
        if ($period === 'ytd') {
            $to = $m->copy()->endOfMonth()->min($pTo);
        } elseif ($period === 'monthly') {
            $from = $m->copy()->startOfMonth()->max($pFrom);
            $to = $m->copy()->endOfMonth()->min($pTo);
        } elseif ($period === 'quarterly') {
            $from = $m->copy()->firstOfQuarter()->max($pFrom);
            $to = $m->copy()->lastOfQuarter()->min($pTo);
        }
        if ($to->lt($from)) {
            $from = $pFrom->copy();
            $to = $pTo->copy();
        }
        $days = $from->diffInDays($to) + 1;
        $periodDays = $pFrom->diffInDays($pTo) + 1;
        $factor = $periodDays > 0 ? min(1, $days / $periodDays) : 1;

        $notices = [];
        if (! $budget) {
            $notices[] = __('ledger_budget.bva_no_budget', ['year' => $fiscalYear->name]);
        } elseif (! $budget->start_date->isSameDay($fiscalYear->start_date) || ! $budget->end_date->isSameDay($fiscalYear->end_date)) {
            $notices[] = __('ledger_budget.bva_other_days', ['from' => $pFrom->format('d/m/Y'), 'to' => $pTo->format('d/m/Y'), 'title' => $budget->title,
                'year' => $fiscalYear->name, 'yfrom' => $fiscalYear->start_date->format('d/m/Y'), 'yto' => $fiscalYear->end_date->format('d/m/Y')]);
        }
        if ($budget && $factor < 0.99999) {
            $notices[] = __('ledger_budget.bva_prorated', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'days' => $days, 'of' => $periodDays]);
        }

        $actuals = $this->actuals->withHeaderTotals($this->actuals->forPeriod($from->format('Y-m-d'), $to->format('Y-m-d'))['lines']);
        $budgeted = [];
        if ($budget) {
            $budgeted = BudgetAllocation::where('budget_id', $budget->id)->whereNotNull('budget_line_id')
                ->pluck('allocated_amount', 'budget_line_id')->map(fn ($v) => round((float) $v * $factor, 2))->toArray();
            $budgeted = $this->actuals->withHeaderTotals($budgeted);
        }

        $report = [];
        foreach (BudgetLine::sheet() as $line) {
            $b = (float) ($budgeted[$line->id] ?? 0);
            $a = (float) ($actuals[$line->id] ?? 0);
            if ($b == 0.0 && $a == 0.0) {
                continue; // no activity either side: left out so the report can be read
            }
            $report[] = [
                'account_code' => $line->code,
                'account_name' => $line->display_name,
                'section' => $line->section,
                'is_header' => (bool) $line->is_header,
                'budget' => $b,
                'actual' => $a,
                // actual minus budget: good news above zero on income, below zero on spending
                'variance' => round($a - $b, 2),
                'variance_pct' => $b != 0.0 ? ($a - $b) / $b * 100 : 0,
                'achievement_pct' => $b != 0.0 ? $a / $b * 100 : 0,
            ];
        }
        $sum = function (array $rows) {
            $b = collect($rows)->where('is_header', false)->sum('budget');
            $a = collect($rows)->where('is_header', false)->sum('actual');

            return ['budget' => round($b, 2), 'actual' => round($a, 2), 'variance' => round($a - $b, 2), 'variance_pct' => $b != 0 ? ($a - $b) / $b * 100 : 0];
        };
        $revenue = array_values(array_filter($report, fn ($r) => $r['section'] === 'income'));
        $expenses = array_values(array_filter($report, fn ($r) => $r['section'] === 'expenditure'));
        $capital = array_values(array_filter($report, fn ($r) => $r['section'] === 'capital'));

        return [
            'report' => $report,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'capital' => $capital,
            'revenueTotals' => $sum($revenue),
            'expenseTotals' => $sum($expenses),
            'capitalTotals' => $sum($capital),
            'totals' => $sum(array_merge($expenses, $capital)),
            'periodNotices' => $notices,
            'budget' => $budget,
            'from' => $from,
            'to' => $to,
        ];
    }

    /** The rows of the performance and variance reports, one per allocation, refreshed from the ledger. */
    protected function allocationRows(Budget $budget): array
    {
        $this->actuals->refresh($budget);
        $rows = [];
        foreach (BudgetAllocation::with(['expenseCategory', 'budgetLine', 'account'])->where('budget_id', $budget->id)->get() as $a) {
            $allocated = (float) $a->allocated_amount;
            $spent = (float) $a->spent_amount;
            // On an income line, collecting more than planned is the good news.
            $income = $a->budgetLine && $a->budgetLine->section === 'income';
            $variance = $income ? $spent - $allocated : $allocated - $spent;
            $rows[] = [
                'budget_id' => $budget->id,
                'budget_code' => $budget->budget_code,
                'budget_title' => $budget->title,
                'category' => $a->target_label,
                'allocated' => $allocated,
                'spent' => $spent,
                'remaining' => (float) $a->remaining_amount,
                'variance' => round($variance, 2),
                'variance_percent' => $allocated > 0 ? $variance / $allocated * 100 : 0,
                'status' => $variance >= 0 ? 'favorable' : 'unfavorable',
            ];
        }

        return $rows;
    }

    /** A report as a PDF table (mpdf, PosInnova's accounting PDF layout). */
    protected function tablePdf(string $title, array $columns, array $rows, ?array $totals, string $filename, array $numeric = [])
    {
        $html = view('ledger.budget.reports.pdf', [
            'title' => $title, 'columns' => $columns, 'rows' => $rows, 'totals' => $totals, 'numeric' => $numeric,
            'businessName' => optional(session('business'))->name ?: DB::table('ledger_businesses')->where('id', Ledger::businessId())->value('name'),
        ])->render();
        $mpdf = $this->getMpdf('L');
        $mpdf->SetTitle($title);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
