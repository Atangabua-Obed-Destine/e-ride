<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\Budget;
use App\Ledger\Models\BudgetAllocation;
use App\Ledger\Models\BudgetRevision;
use App\Ledger\Services\BudgetActualsService;
use App\Ledger\Services\BudgetLineChoices;
use App\Ledger\Services\BudgetPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Departmental and project budgets (paxhitestbed BudgetController): the
 * register, create / edit, the lifecycle draft -> pending approval -> approved
 * -> active -> closed (or cancelled), and revisions of a locked total.
 *
 * PosInnova: the department is a business location; what a budget has spent
 * is what the posted ledger holds against its allocations in its period
 * (BudgetActualsService::refresh), so there is no expense tagging and nothing
 * to drift. Approving and revising go through the review dialog.
 */
class BudgetController extends LedgerController
{
    /** @var BudgetActualsService */
    protected $actuals;

    public function __construct(BudgetActualsService $actuals)
    {
        parent::__construct();
        $this->actuals = $actuals;
    }

    public function index(Request $request)
    {
        $this->allow('budget-view');
        // Every budget, annual and departmental alike: hiding the annual sheet
        // made the register look empty while one existed. Editing screens are
        // guarded instead (redirectInstitutional).
        $query = Budget::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('fiscal_year'), fn ($q) => $q->where('fiscal_year', $request->fiscal_year))
            ->when($request->filled('location_id'), fn ($q) => $q->where('location_id', $request->location_id));
        $rows = $query->with(['location', 'createdBy'])->orderBy('id', 'desc')->paginate(25)->withQueryString();
        foreach ($rows as $row) {
            $this->actuals->refresh($row);
        }

        return view('ledger.budget.index', [
            'rows' => $rows,
            'locations' => BudgetLineChoices::locations(),
            'fiscal_years' => Budget::select('fiscal_year')->distinct()->orderBy('fiscal_year', 'desc')->pluck('fiscal_year')->filter(),
        ]);
    }

    public function create()
    {
        $this->allow('budget-create');

        return view('ledger.budget.create', [
            'row' => null,
            'locations' => BudgetLineChoices::locations(),
            'annualBudgets' => $this->annualBudgets(),
        ]);
    }

    public function store(Request $request)
    {
        $this->allow('budget-create');
        $data = $this->validated($request);
        $budget = new Budget();
        $budget->fill($data);
        $budget->status = 'draft';
        $budget->is_institutional = false;
        $budget->created_by = Auth::id();
        $budget->save();

        return $this->ok(route('ledger.budget.show', $budget->id), __('ledger_budget.msg_created'));
    }

    public function show($id)
    {
        $this->allow('budget-view');
        $row = Budget::findOrFail($id);
        $this->actuals->refresh($row);
        $row = Budget::with(['location', 'allocations.expenseCategory', 'allocations.account', 'allocations.location',
            'allocations.budgetLine', 'revisions.requestedBy', 'children.location', 'parent', 'approvedBy'])->findOrFail($id);
        foreach ($row->children as $child) {
            $this->actuals->refresh($child);
        }

        return view('ledger.budget.show', ['row' => $row]);
    }

    /**
     * Send the annual sheet back to its own editor: the departmental screens
     * know nothing of budget lines or the opening balance. Enforced here, not
     * by hiding buttons.
     */
    protected function redirectInstitutional(?Budget $budget)
    {
        if (! $budget || ! $budget->is_institutional) {
            return null;
        }

        return $this->err(route('ledger.budget-sheet.show', $budget->id), __('ledger_budget.is_sheet_edit_there'));
    }

    public function edit($id)
    {
        $this->allow('budget-edit');
        $row = Budget::findOrFail($id);
        if ($redirect = $this->redirectInstitutional($row)) {
            return $redirect;
        }
        if (! in_array($row->status, ['draft', 'pending_approval'])) {
            return $this->err(route('ledger.budget.show', $id), __('ledger_budget.msg_cannot_edit_active_budget'));
        }

        return view('ledger.budget.edit', [
            'row' => $row,
            'locations' => BudgetLineChoices::locations(),
            'annualBudgets' => $this->annualBudgets(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->allow('budget-edit');
        $budget = Budget::findOrFail($id);
        if ($redirect = $this->redirectInstitutional($budget)) {
            return $redirect;
        }
        if (! in_array($budget->status, ['draft', 'pending_approval'])) {
            return $this->err(route('ledger.budget.show', $id), __('ledger_budget.msg_cannot_edit_active_budget'));
        }
        $data = $this->validated($request);
        if ((float) $data['total_amount'] < (float) $budget->allocations()->sum('allocated_amount')) {
            return back()->withInput()->withErrors(['total_amount' => __('ledger_budget.err_total_below_allocated',
                ['amount' => Ledger::money($budget->allocations()->sum('allocated_amount'))])]);
        }
        $budget->fill($data);
        $budget->updated_by = Auth::id();
        $budget->save();

        return $this->ok(route('ledger.budget.show', $id), __('ledger_budget.msg_updated'));
    }

    public function destroy($id)
    {
        $this->allow('budget-delete');
        $budget = Budget::findOrFail($id);
        if ($budget->is_institutional) {
            return $this->redirectInstitutional($budget);
        }
        if ($budget->status !== 'draft') {
            return $this->err(back(), __('ledger_budget.msg_cannot_delete_active_budget'));
        }
        DB::transaction(function () use ($budget) {
            $budget->allocations()->delete();
            $budget->delete();
        });

        return $this->ok(route('ledger.budget.index'), __('ledger_budget.msg_deleted'));
    }

    public function submitForApproval($id)
    {
        $this->allow('budget-edit');

        return $this->step($id, ['draft'], 'pending_approval', __('ledger_budget.msg_budget_submitted_for_approval'));
    }

    public function approve(Request $request, $id)
    {
        $this->allow('budget-approve');
        $budget = Budget::findOrFail($id);
        $message = __('ledger_budget.msg_budget_approved_figures', [
            'total' => Ledger::money($budget->total_amount),
            'allocated' => Ledger::money($budget->allocations()->sum('allocated_amount')),
        ]);

        return $this->step($id, ['pending_approval'], 'approved', $message, function (Budget $b) {
            $b->approved_by = Auth::id();
            $b->approved_at = now();
        });
    }

    public function activate($id)
    {
        $this->allow('budget-activate');

        return $this->step($id, ['approved'], 'active', __('ledger_budget.msg_budget_activated'));
    }

    public function close($id)
    {
        $this->allow('budget-close');

        return $this->step($id, ['active'], 'closed', __('ledger_budget.msg_budget_closed'));
    }

    public function cancel($id)
    {
        $this->allow('budget-cancel');

        return $this->step($id, ['draft', 'pending_approval', 'approved'], 'cancelled', __('ledger_budget.msg_budget_cancelled'));
    }

    /** A guarded transition. The annual sheet has its own steps on its own screen. */
    protected function step($id, array $from, string $to, string $message, ?callable $before = null)
    {
        $budget = Budget::findOrFail($id);
        if ($budget->is_institutional) {
            return $this->redirectInstitutional($budget);
        }
        if (! in_array($budget->status, $from, true)) {
            return $this->err(back(), __('ledger_budget.msg_invalid_budget_status'));
        }
        if ($before) {
            $before($budget);
        }
        $budget->status = $to;
        $budget->updated_by = Auth::id();
        $budget->save();

        return $this->ok(route('ledger.budget.show', $id), $message);
    }

    /**
     * Revise an approved or active budget's total (logs a BudgetRevision and
     * applies it at once): the supported way to adjust a budget that is locked.
     */
    public function revise(Request $request, $id)
    {
        $this->allow('budget-edit');
        $budget = Budget::findOrFail($id);
        if ($budget->is_institutional) {
            return $this->redirectInstitutional($budget);
        }
        if (! in_array($budget->status, ['approved', 'active'])) {
            return $this->err(back(), __('ledger_budget.msg_invalid_budget_status'));
        }
        $request->validate([
            'new_amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:1000',
        ]);
        $this->actuals->refresh($budget);
        $budget->refresh();
        $new = round((float) $request->new_amount, 2);
        $allocated = (float) $budget->allocations()->sum('allocated_amount');
        if ($new < $allocated) {
            return $this->err(back(), __('ledger_budget.err_revise_below_allocated', ['new' => Ledger::money($new), 'allocated' => Ledger::money($allocated)]));
        }
        if ($new < (float) $budget->spent_amount) {
            return $this->err(back(), __('ledger_budget.err_revise_below_spent', ['new' => Ledger::money($new), 'spent' => Ledger::money($budget->spent_amount)]));
        }
        if (abs($new - (float) $budget->total_amount) < 0.005) {
            return $this->err(back(), __('ledger_budget.err_revise_unchanged'));
        }
        DB::transaction(function () use ($budget, $new, $request) {
            $revision = BudgetRevision::create([
                'budget_id' => $budget->id,
                'previous_amount' => $budget->total_amount,
                'new_amount' => $new,
                'reason' => $request->reason,
                'status' => 'pending',
                'requested_by' => Auth::id(),
            ]);
            $revision->approve(Auth::id());
        });

        return $this->ok(route('ledger.budget.show', $id), __('ledger_budget.msg_revised', [
            'from' => Ledger::money($budget->getOriginal('total_amount')), 'to' => Ledger::money($new)]));
    }

    /** The allocations of a budget, as JSON. */
    public function getAllocationsData($id)
    {
        $this->allow('budget-view');
        $budget = Budget::findOrFail($id);
        $this->actuals->refresh($budget);

        return response()->json($budget->allocations()->with(['expenseCategory', 'account', 'budgetLine'])->get()->map(fn ($a) => [
            'id' => $a->id,
            'expense_category_id' => $a->expense_category_id,
            'account_id' => $a->account_id,
            'category_title' => $a->target_label,
            'allocated_amount' => (float) $a->allocated_amount,
            'spent_amount' => (float) $a->spent_amount,
            'remaining_amount' => (float) $a->remaining_amount,
            'remaining_formatted' => Ledger::money($a->remaining_amount),
        ]));
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|max:191',
            'type' => ['required', Rule::in(['departmental', 'project', 'annual'])],
            'fiscal_year' => 'required|max:10',
            'start_date' => 'required|date',
            // On end_date: a closure rule is skipped when its own attribute is absent.
            'end_date' => ['required', 'date', 'after:start_date', BudgetPeriod::withinParentRule($request->input('start_date'), $request->input('parent_id'))],
            'total_amount' => 'required|numeric|min:0',
            'location_id' => 'required_if:type,departmental|nullable|integer',
            'parent_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'note' => 'nullable|string',
        ]);
        if (! empty($data['location_id']) && ! BudgetLineChoices::locationBelongs($data['location_id'])) {
            abort(422);
        }
        if (! empty($data['parent_id']) && ! Budget::where('is_institutional', true)->where('id', $data['parent_id'])->exists()) {
            $data['parent_id'] = null;
        }
        $data['location_id'] = $data['location_id'] ?? null ?: null;
        $data['parent_id'] = $data['parent_id'] ?? null ?: null;

        return $data;
    }

    /** Annual sheets a budget can be delegated out of. */
    protected function annualBudgets()
    {
        return Budget::where('is_institutional', true)->whereIn('status', ['draft', 'pending_approval', 'approved', 'active'])
            ->orderByDesc('start_date')->get();
    }
}
