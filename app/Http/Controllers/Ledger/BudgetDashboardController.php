<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\Budget;
use App\Ledger\Models\BudgetAllocation;
use App\Ledger\Services\BudgetActualsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The budget dashboard (paxhitestbed BudgetDashboardController): KPIs over the
 * active departmental budgets, the annual sheet on its own card, alerts at
 * 75 / 90 / 100 %, and four charts. Spending is the posted ledger's.
 */
class BudgetDashboardController extends LedgerController
{
    /** @var BudgetActualsService */
    protected $actuals;

    public function __construct(BudgetActualsService $actuals)
    {
        parent::__construct();
        $this->actuals = $actuals;
    }

    public function index()
    {
        $this->allow('budget-view');
        // Departmental budgets only: averaging the whole sheet with departmental
        // pots gives a utilisation that means nothing. The sheet has its own card.
        $activeBudgets = Budget::where('status', 'active')->where('is_institutional', false)->get();
        foreach ($activeBudgets as $b) {
            $this->actuals->refresh($b);
        }
        $activeBudgets = Budget::with('allocations')->where('status', 'active')->where('is_institutional', false)->orderBy('budget_code')->get();

        $annualBudget = Budget::where('is_institutional', true)
            ->orderByRaw("FIELD(status, 'active', 'approved', 'pending_approval', 'draft', 'closed', 'cancelled')")
            ->orderByDesc('start_date')->first();
        if ($annualBudget) {
            $this->actuals->refresh($annualBudget);
            $annualBudget->refresh();
        }

        return view('ledger.budget.dashboard', [
            'kpis' => $this->calculateKPIs($activeBudgets),
            'activeBudgets' => $activeBudgets,
            'utilizationData' => $this->getUtilizationData($activeBudgets),
            'departmentData' => $this->getDepartmentSpendingData(),
            'monthlyTrendData' => $this->getMonthlyTrendData($activeBudgets),
            'categoryData' => $this->getCategorySpendingData(),
            'alerts' => $this->getBudgetAlerts($activeBudgets),
            'annualBudget' => $annualBudget,
        ]);
    }

    private function calculateKPIs($budgets): array
    {
        $total = (float) $budgets->sum('total_amount');
        $allocated = (float) $budgets->sum('allocated_amount');
        $spent = (float) $budgets->sum('spent_amount');

        return [
            'total_budget' => $total,
            'total_allocated' => $allocated,
            'total_spent' => $spent,
            'total_remaining' => (float) $budgets->sum('remaining_amount'),
            'utilization_rate' => $total > 0 ? round($spent / $total * 100, 2) : 0,
            'allocation_rate' => $total > 0 ? round($allocated / $total * 100, 2) : 0,
            'active_budgets_count' => $budgets->count(),
        ];
    }

    private function getUtilizationData($budgets): array
    {
        return [
            'labels' => [__('ledger_budget.spent'), __('ledger_budget.remaining')],
            'values' => [(float) $budgets->sum('spent_amount'), max(0, (float) $budgets->sum('remaining_amount'))],
            'colors' => ['#dc3545', '#28a745'],
        ];
    }

    /** Spending by location (the department), from allocations, else from the budgets themselves. */
    private function getDepartmentSpendingData(): array
    {
        $bid = Ledger::businessId();
        $rows = DB::table('budget_allocations as a')
            ->join('budgets as b', 'b.id', '=', 'a.budget_id')
            ->join('business_locations as l', 'l.id', '=', DB::raw('COALESCE(a.location_id, b.location_id)'))
            ->where('a.business_id', $bid)->where('b.business_id', $bid)
            ->where('b.is_institutional', 0)->whereIn('b.status', ['active', 'approved'])
            ->groupBy('l.id', 'l.name')->havingRaw('SUM(a.spent_amount) > 0')
            ->orderByDesc(DB::raw('SUM(a.spent_amount)'))->limit(10)
            ->selectRaw('l.name as department, SUM(a.spent_amount) as total_spent')->get();
        if ($rows->isEmpty()) {
            $rows = DB::table('budgets as b')->join('business_locations as l', 'l.id', '=', 'b.location_id')
                ->where('b.business_id', $bid)->where('b.status', 'active')->where('b.spent_amount', '>', 0)
                ->groupBy('l.id', 'l.name')->orderByDesc(DB::raw('SUM(b.spent_amount)'))->limit(10)
                ->selectRaw('l.name as department, SUM(b.spent_amount) as total_spent')->get();
        }

        return ['labels' => $rows->pluck('department')->all(), 'values' => $rows->pluck('total_spent')->map(fn ($v) => (float) $v)->all()];
    }

    /** What the active budgets spent each month of this year, by the ledger. */
    private function getMonthlyTrendData($budgets): array
    {
        $year = Carbon::now()->year;
        $byMonth = array_fill(1, 12, 0.0);
        foreach ($budgets as $b) {
            $from = max($b->start_date->format('Y-m-d'), $year.'-01-01');
            $to = min($b->end_date->format('Y-m-d'), $year.'-12-31');
            if ($from > $to) {
                continue;
            }
            foreach ($this->actuals->allocationActuals($b, $b->allocations, $from, $to, true) as $months) {
                foreach ($months as $ym => $amount) {
                    $byMonth[(int) substr($ym, 5, 2)] += $amount;
                }
            }
        }
        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[] = Carbon::create($year, $m, 1)->locale(app()->getLocale())->translatedFormat('M');
        }

        return ['labels' => $labels, 'values' => array_values(array_map(fn ($v) => round($v, 2), $byMonth))];
    }

    private function getCategorySpendingData(): array
    {
        $bid = Ledger::businessId();
        $rows = DB::table('budget_allocations as a')
            ->join('budgets as b', 'b.id', '=', 'a.budget_id')
            ->join('expense_categories as c', 'c.id', '=', 'a.expense_category_id')
            ->where('a.business_id', $bid)->where('b.business_id', $bid)->where('b.status', 'active')
            ->groupBy('c.id', 'c.name')->orderByDesc(DB::raw('SUM(a.spent_amount)'))->limit(10)
            ->selectRaw('c.name as category, SUM(a.spent_amount) as total_spent, SUM(a.allocated_amount) as total_allocated')->get();

        return [
            'labels' => $rows->pluck('category')->all(),
            'allocated' => $rows->pluck('total_allocated')->map(fn ($v) => (float) $v)->all(),
            'spent' => $rows->pluck('total_spent')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    private function getBudgetAlerts($budgets): array
    {
        $alerts = [];
        foreach ($budgets as $budget) {
            $rate = (float) $budget->total_amount > 0 ? (float) $budget->spent_amount / (float) $budget->total_amount * 100 : 0;
            if ($rate > 100) {
                $alerts[] = ['type' => 'danger', 'icon' => 'fa fa-exclamation-triangle', 'title' => __('ledger_budget.alert_exceeded'),
                    'message' => __('ledger_budget.alert_exceeded_msg', ['title' => $budget->title, 'amount' => Ledger::money((float) $budget->spent_amount - (float) $budget->total_amount)]),
                    'budget_id' => $budget->id];
            } elseif ($rate > 90) {
                $alerts[] = ['type' => 'warning', 'icon' => 'fa fa-exclamation-triangle', 'title' => __('ledger_budget.alert_alert'),
                    'message' => __('ledger_budget.alert_at', ['title' => $budget->title, 'rate' => round($rate, 1)]), 'budget_id' => $budget->id];
            } elseif ($rate > 75) {
                $alerts[] = ['type' => 'info', 'icon' => 'fa fa-info-circle', 'title' => __('ledger_budget.alert_notice'),
                    'message' => __('ledger_budget.alert_at', ['title' => $budget->title, 'rate' => round($rate, 1)]), 'budget_id' => $budget->id];
            }
            foreach ($budget->allocations as $allocation) {
                if ((float) $allocation->spent_amount > (float) $allocation->allocated_amount) {
                    $alerts[] = ['type' => 'danger', 'icon' => 'fa fa-exclamation-triangle', 'title' => __('ledger_budget.alert_allocation_exceeded'),
                        'message' => __('ledger_budget.alert_allocation_exceeded_msg', ['allocation' => $allocation->title, 'title' => $budget->title,
                            'amount' => Ledger::money((float) $allocation->spent_amount - (float) $allocation->allocated_amount)]),
                        'budget_id' => $budget->id];
                }
            }
        }

        return $alerts;
    }

    /** One budget's summary, as JSON. */
    public function getBudgetSummary($id)
    {
        $this->allow('budget-view');
        $budget = Budget::findOrFail($id);
        $this->actuals->refresh($budget);
        $budget = Budget::with(['allocations.expenseCategory', 'allocations.account', 'allocations.budgetLine'])->findOrFail($id);

        return response()->json([
            'title' => $budget->title,
            'code' => $budget->budget_code,
            'status' => $budget->status,
            'status_label' => Budget::statusLabel($budget->status),
            'total_amount' => (float) $budget->total_amount,
            'allocated_amount' => (float) $budget->allocated_amount,
            'spent_amount' => (float) $budget->spent_amount,
            'remaining_amount' => (float) $budget->remaining_amount,
            'utilization_rate' => (float) $budget->total_amount > 0 ? (float) $budget->spent_amount / (float) $budget->total_amount * 100 : 0,
            'allocations' => $budget->allocations->map(fn (BudgetAllocation $a) => [
                'category' => $a->target_label,
                'allocated' => (float) $a->allocated_amount,
                'spent' => (float) $a->spent_amount,
                'remaining' => (float) $a->remaining_amount,
            ]),
        ]);
    }
}
