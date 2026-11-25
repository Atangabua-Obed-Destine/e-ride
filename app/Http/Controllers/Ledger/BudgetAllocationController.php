<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\Budget;
use App\Ledger\Models\BudgetAllocation;
use App\Ledger\Services\BudgetActualsService;
use App\Ledger\Services\BudgetLineChoices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Allocations of a departmental budget (paxhitestbed BudgetAllocationController):
 * only while the budget is not active, closed or cancelled, and never more
 * than the budget's total. paxhitestbed earmarked an allocation for an
 * expense category; PosInnova also lets it name a ledger account (classes 6
 * or 2), and at least one of the two is needed so the ledger can say what was
 * spent against it. paxhitestbed's budget-allocation-* permissions do not
 * exist here: changing allocations is budget-edit.
 */
class BudgetAllocationController extends LedgerController
{
    /** @var BudgetActualsService */
    protected $actuals;

    public function __construct(BudgetActualsService $actuals)
    {
        parent::__construct();
        $this->actuals = $actuals;
    }

    protected function redirectInstitutional(Budget $budget)
    {
        if (! $budget->is_institutional) {
            return null;
        }

        return $this->err(route('ledger.budget-sheet.show', $budget->id), __('ledger_budget.is_sheet_figures_there'));
    }

    public function index($budgetId)
    {
        $this->allow('budget-view');
        $budget = Budget::findOrFail($budgetId);
        if ($redirect = $this->redirectInstitutional($budget)) {
            return $redirect;
        }
        $this->actuals->refresh($budget);
        $budget = Budget::with(['allocations.expenseCategory', 'allocations.account', 'allocations.location', 'location'])->findOrFail($budgetId);

        return view('ledger.budget.allocations', [
            'budget' => $budget,
            'expenseCategories' => BudgetLineChoices::categories(),
            'accounts' => BudgetLineChoices::spendingAccounts(),
            'locations' => BudgetLineChoices::locations(),
        ]);
    }

    public function store(Request $request, $budgetId)
    {
        $this->allow('budget-edit');
        $budget = Budget::findOrFail($budgetId);
        if ($redirect = $this->redirectInstitutional($budget)) {
            return $redirect;
        }
        if (in_array($budget->status, ['active', 'closed', 'cancelled'])) {
            return $this->err(back(), __('ledger_budget.msg_cannot_modify_budget'));
        }
        $data = $this->validated($request);
        $unallocated = (float) $budget->total_amount - (float) $budget->allocations()->sum('allocated_amount');
        if ((float) $data['allocated_amount'] > $unallocated + 0.004) {
            return $this->err(back(), __('ledger_budget.err_allocation_exceeds', ['amount' => Ledger::money($data['allocated_amount']), 'available' => Ledger::money($unallocated)]));
        }
        DB::transaction(function () use ($budget, $data) {
            $a = new BudgetAllocation($data);
            $a->budget_id = $budget->id;
            $a->created_by = Auth::id();
            $a->save();
            $budget->calculateAllocatedAmount();
        });

        return $this->ok(route('ledger.budget.allocations', $budgetId), __('ledger_budget.msg_allocation_created_successfully'));
    }

    public function update(Request $request, $budgetId, $id)
    {
        $this->allow('budget-edit');
        $budget = Budget::findOrFail($budgetId);
        if ($redirect = $this->redirectInstitutional($budget)) {
            return $redirect;
        }
        $allocation = BudgetAllocation::where('budget_id', $budgetId)->findOrFail($id);
        if (in_array($budget->status, ['active', 'closed', 'cancelled'])) {
            return $this->err(back(), __('ledger_budget.msg_cannot_modify_budget'));
        }
        $data = $this->validated($request);
        $available = (float) $budget->total_amount - (float) $budget->allocations()->where('id', '!=', $id)->sum('allocated_amount');
        if ((float) $data['allocated_amount'] > $available + 0.004) {
            return $this->err(back(), __('ledger_budget.err_allocation_exceeds_available', ['amount' => Ledger::money($data['allocated_amount']), 'available' => Ledger::money($available)]));
        }
        DB::transaction(function () use ($budget, $allocation, $data) {
            $allocation->fill($data);
            $allocation->updated_by = Auth::id();
            $allocation->save();
            $budget->calculateAllocatedAmount();
        });

        return $this->ok(route('ledger.budget.allocations', $budgetId), __('ledger_budget.msg_allocation_updated_successfully'));
    }

    public function destroy($budgetId, $id)
    {
        $this->allow('budget-edit');
        $budget = Budget::findOrFail($budgetId);
        if ($redirect = $this->redirectInstitutional($budget)) {
            return $redirect;
        }
        $allocation = BudgetAllocation::where('budget_id', $budgetId)->findOrFail($id);
        if (in_array($budget->status, ['active', 'closed', 'cancelled'])) {
            return $this->err(back(), __('ledger_budget.msg_cannot_modify_budget'));
        }
        DB::transaction(function () use ($budget, $allocation) {
            $allocation->delete();
            $budget->calculateAllocatedAmount();
        });

        return $this->ok(route('ledger.budget.allocations', $budgetId), __('ledger_budget.msg_allocation_deleted_successfully'));
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'expense_category_id' => 'nullable|integer|required_without:account_id',
            'account_id' => 'nullable|integer|required_without:expense_category_id',
            'title' => 'required|max:191',
            'allocated_amount' => 'required|numeric|min:0',
            'period' => ['required', Rule::in(BudgetAllocation::PERIODS)],
            'location_id' => 'nullable|integer',
            'description' => 'nullable|string',
        ], ['expense_category_id.required_without' => __('ledger_budget.err_allocation_target'),
            'account_id.required_without' => __('ledger_budget.err_allocation_target')]);
        $errors = [];
        if (! empty($data['expense_category_id']) && ! BudgetLineChoices::categoryBelongs($data['expense_category_id'])) {
            $errors['expense_category_id'] = __('ledger_budget.err_not_found');
        }
        if (! empty($data['account_id']) && ! BudgetLineChoices::accountFits('expenditure', $data['account_id']) && ! BudgetLineChoices::accountFits('capital', $data['account_id'])) {
            $errors['account_id'] = __('ledger_budget.err_allocation_account');
        }
        if (! empty($data['location_id']) && ! BudgetLineChoices::locationBelongs($data['location_id'])) {
            $errors['location_id'] = __('ledger_budget.err_not_found');
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
        foreach (['expense_category_id', 'account_id', 'location_id'] as $k) {
            $data[$k] = ! empty($data[$k]) ? (int) $data[$k] : null;
        }

        return $data;
    }
}
