<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\BudgetAllocation;
use App\Ledger\Models\BudgetLine;
use App\Ledger\Models\BudgetLineForecast;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Services\BudgetActualsService;
use App\Ledger\Services\BudgetLineChoices;
use App\Ledger\Services\BudgetReconciliationService;
use App\Ledger\Services\Preview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Managing the lines of the Income & Expenditure sheet (paxhitestbed
 * BudgetLineController).
 *
 * Same two rules: a line that money or figures already reference is never
 * destroyed, only retired; and the structure is two levels (headings and the
 * lines under them), ordered by dragging.
 *
 * PosInnova difference: paxhitestbed fed a line through category mappings
 * (and tuition through faculties), set from a "give this line a category"
 * dialog. Here a line TARGETS a ledger account of its section's class, with
 * an optional expense category and location, chosen on the line's own form.
 * "Standard lines" makes the sheet from the chart of accounts, as
 * paxhitestbed's lines came from the diocesan form.
 */
class BudgetLineController extends LedgerController
{
    /** @var BudgetReconciliationService */
    protected $reconciliation;

    public function __construct(BudgetReconciliationService $reconciliation)
    {
        parent::__construct();
        $this->reconciliation = $reconciliation;
    }

    public function index()
    {
        $this->allow('budget-line-view');
        $lines = BudgetLine::with(['account', 'expenseCategory', 'location'])
            ->orderByRaw("FIELD(section, 'income', 'expenditure', 'capital')")
            ->orderBy('sort_order')->orderBy('code')->get();

        $grouped = collect(BudgetLine::SECTIONS)->mapWithKeys(fn ($s) => [$s => $this->treeOrdered($lines->where('section', $s))]);

        return view('ledger.budget-line.index', [
            'lines' => $lines,
            'grouped' => $grouped,
            'headers' => $lines->where('is_header', true),
            'accountsByLine' => $this->reconciliation->accountsByLine(),
            'usage' => $this->usage(),
            'accountOptions' => BudgetLineChoices::accountsBySection(),
            'categories' => BudgetLineChoices::categories(),
            'locations' => BudgetLineChoices::locations(),
        ]);
    }

    /**
     * One section's lines as a heading-first tree: ['line' => ..., 'children' => ...].
     * A line whose heading is missing surfaces at the top level rather than dropping off the screen.
     */
    protected function treeOrdered($lines)
    {
        $lines = $lines->values();
        $ids = $lines->pluck('id')->all();
        $childrenOf = $lines->filter(fn ($l) => $l->parent_id && in_array($l->parent_id, $ids, true))
            ->sortBy([['sort_order', 'asc'], ['code', 'asc']])->groupBy('parent_id');

        return $lines->filter(fn ($l) => ! $l->parent_id || ! in_array($l->parent_id, $ids, true))
            ->sortBy([['sort_order', 'asc'], ['code', 'asc']])
            ->map(fn ($l) => ['line' => $l, 'children' => $childrenOf->get($l->id, collect())->values()])
            ->values();
    }

    /** Persist a new running order, as dragged: position and heading together, within one section. */
    public function reorder(Request $request)
    {
        $this->allow('budget-line-edit');
        $validated = $request->validate([
            'section' => ['required', Rule::in(BudgetLine::SECTIONS)],
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => ['required', 'integer'],
            'order.*.parent_id' => ['nullable', 'integer'],
        ]);
        $lines = BudgetLine::where('section', $validated['section'])->get()->keyBy('id');
        $seen = [];
        $skipped = [];
        $position = 0;

        DB::transaction(function () use ($validated, $lines, &$seen, &$skipped, &$position) {
            foreach ($validated['order'] as $entry) {
                $id = (int) $entry['id'];
                $parentId = isset($entry['parent_id']) && $entry['parent_id'] !== null && $entry['parent_id'] !== '' ? (int) $entry['parent_id'] : null;
                $line = $lines->get($id);
                // A line from another section (or another business) is never moved by a drag.
                if (! $line || in_array($id, $seen, true)) {
                    $skipped[] = $id;
                    continue;
                }
                $seen[] = $id;
                if ($parentId !== null) {
                    $parent = $lines->get($parentId);
                    if (! $parent || ! $parent->is_header || $parentId === $id) {
                        $parentId = null;
                    }
                }
                if ($line->is_header) {
                    $parentId = null; // two levels: a heading never files under another heading
                }
                $position += 10;
                if ((int) $line->sort_order !== $position || $line->parent_id !== $parentId) {
                    $line->sort_order = $position;
                    $line->parent_id = $parentId;
                    $line->updated_by = Auth::id();
                    $line->save();
                }
            }
        });

        return response()->json(['success' => true, 'message' => __('ledger_budget.order_saved'), 'reordered' => count($seen), 'skipped' => $skipped]);
    }

    /** Renumber a section by code, keeping the heading structure. */
    public function autoSort(Request $request)
    {
        $this->allow('budget-line-edit');
        $validated = $request->validate(['section' => ['required', Rule::in(BudgetLine::SECTIONS)]]);
        $lines = BudgetLine::where('section', $validated['section'])->get();
        $childrenOf = $lines->filter(fn ($l) => (bool) $l->parent_id)->sortBy('code', SORT_NATURAL)->groupBy('parent_id');
        $top = $lines->filter(fn ($l) => ! $l->parent_id)->sortBy('code', SORT_NATURAL);
        $position = 0;
        DB::transaction(function () use ($top, $childrenOf, &$position) {
            foreach ($top as $line) {
                $position += 10;
                $line->sort_order = $position;
                $line->save();
                foreach ($childrenOf->get($line->id, collect()) as $child) {
                    $position += 10;
                    $child->sort_order = $position;
                    $child->save();
                }
            }
        });

        return $this->ok(route('ledger.budget-line.index'), __('ledger_budget.section_sorted'));
    }

    /**
     * Make the standard lines from the chart of accounts: a heading for each
     * two-digit account of classes 7, 6 and 2 (20 to 27), and a line for each
     * three-digit account under it, reading that account. Codes already used
     * are left alone, so it can be run again after accounts are added.
     */
    public function standard()
    {
        $this->allow('budget-line-create');
        $existing = BudgetLine::pluck('id', 'code');
        $accounts = ChartOfAccount::whereIn('class_number', [2, 6, 7])->where('is_active', 1)
            ->where('account_code', 'not like', '28%')->where('account_code', 'not like', '29%')
            ->orderBy('account_code')->get()
            ->filter(fn ($a) => in_array(strlen((string) $a->account_code), [2, 3], true));
        $made = 0;
        $position = (int) BudgetLine::max('sort_order');

        DB::transaction(function () use ($accounts, $existing, &$made, &$position) {
            $headings = [];
            foreach ($accounts as $a) {
                $code = (string) $a->account_code;
                $section = BudgetActualsService::sectionForClass((int) $a->class_number);
                $isHeading = strlen($code) === 2;
                if ($existing->has($code)) {
                    if ($isHeading) {
                        $headings[$code] = $existing[$code];
                    }
                    continue;
                }
                $parentId = $isHeading ? null : ($headings[substr($code, 0, 2)] ?? null);
                $position += 10;
                $line = BudgetLine::create([
                    'code' => $code,
                    'name' => $a->account_name,
                    'name_fr' => $a->account_name_fr,
                    'section' => $section,
                    'is_header' => $isHeading,
                    'parent_id' => $parentId,
                    'account_id' => $isHeading ? null : $a->id,
                    'is_local' => false,
                    'sort_order' => $position,
                    'status' => true,
                    'created_by' => Auth::id(),
                ]);
                if ($isHeading) {
                    $headings[$code] = $line->id;
                }
                $made++;
            }
        });

        return $this->ok(route('ledger.budget-line.index'), $made
            ? __('ledger_budget.standard_made', ['count' => $made]) : __('ledger_budget.standard_none'));
    }

    public function store(Request $request)
    {
        $this->allow('budget-line-create');
        $data = $this->validated($request);
        if ($data instanceof \Illuminate\Http\RedirectResponse) {
            return $data;
        }
        $data['is_local'] = true; // anything added here is by definition not from the chart
        $data['status'] = $request->boolean('status', true);
        $data['created_by'] = Auth::id();
        BudgetLine::create($data);

        return $this->ok(route('ledger.budget-line.index'), __('ledger_budget.line_added'));
    }

    public function update(Request $request, $id)
    {
        $this->allow('budget-line-edit');
        $line = BudgetLine::findOrFail($id);
        $data = $this->validated($request, $line);
        if ($data instanceof \Illuminate\Http\RedirectResponse) {
            return $data;
        }
        $data['status'] = $request->boolean('status', true);
        $data['updated_by'] = Auth::id();
        if (($data['parent_id'] ?? null) && $this->wouldLoop($line, (int) $data['parent_id'])) {
            return back()->withInput()->withErrors(['parent_id' => __('ledger_budget.err_own_parent')]);
        }
        if ($data['is_header'] && ! $line->is_header && $this->carriesFigures($line)) {
            return back()->withInput()->withErrors(['is_header' => __('ledger_budget.err_header_has_figures')]);
        }
        $line->update($data);

        return $this->ok(route('ledger.budget-line.index'), __('ledger_budget.line_updated'));
    }

    /**
     * Take a line off the sheet, or put it back, without touching history. A
     * heading with lines still under it is refused; a line carrying money is
     * allowed, but said out loud: that money now falls to another line or to
     * "unallocated", and the reconciliation reports it.
     */
    public function toggle($id)
    {
        $this->allow('budget-line-edit');
        $line = BudgetLine::findOrFail($id);
        $retiring = (bool) $line->status;
        if ($retiring && $line->is_header && $line->children()->where('status', true)->exists()) {
            return $this->err(route('ledger.budget-line.index'), __('ledger_budget.err_retire_heading', ['line' => $line->label]));
        }
        $carried = 0.0;
        if ($retiring) {
            $carried = (float) (app(BudgetActualsService::class)->forPeriod()['lines'][$line->id] ?? 0);
        }
        $line->status = ! $line->status;
        $line->updated_by = Auth::id();
        $line->save();

        if (! $line->status && abs($carried) > 0.009) {
            return $this->ok(route('ledger.budget-line.index'), __('ledger_budget.retired_with_money', ['line' => $line->label, 'amount' => Ledger::money($carried)]));
        }

        return $this->ok(route('ledger.budget-line.index'), $line->status ? __('ledger_budget.line_restored') : __('ledger_budget.line_retired'));
    }

    public function destroy($id)
    {
        $this->allow('budget-line-delete');
        $line = BudgetLine::findOrFail($id);
        $used = $this->usage()[$line->id] ?? null;
        if ($used) {
            return $this->err(route('ledger.budget-line.index'), __('ledger_budget.err_line_in_use', ['line' => $line->label, 'reasons' => implode(', ', $used)]));
        }
        if ($line->children()->exists()) {
            return $this->err(route('ledger.budget-line.index'), __('ledger_budget.err_move_children'));
        }
        $line->delete();

        return $this->ok(route('ledger.budget-line.index'), __('ledger_budget.line_deleted'));
    }

    /** What references each line, so the screen can explain why one cannot be deleted. @return array<int, string[]> */
    protected function usage(): array
    {
        $usage = [];
        $bid = Ledger::businessId();
        $sources = [
            ['budget_allocations', __('ledger_budget.usage_figures')],
            ['budget_line_forecasts', __('ledger_budget.usage_workings')],
        ];
        foreach ($sources as [$table, $reason]) {
            $rows = DB::table($table)->where('business_id', $bid)->whereNotNull('budget_line_id')
                ->select('budget_line_id', DB::raw('COUNT(*) as c'))->groupBy('budget_line_id')->pluck('c', 'budget_line_id');
            foreach ($rows as $lineId => $count) {
                $usage[(int) $lineId][] = $reason.' ('.$count.')';
            }
        }
        // A line that ledger money already lands on keeps its history.
        foreach (app(BudgetActualsService::class)->forPeriod()['lines'] as $lineId => $amount) {
            if (abs($amount) > 0.009) {
                $usage[(int) $lineId][] = __('ledger_budget.usage_money', ['amount' => Ledger::money($amount)]);
            }
        }

        return $usage;
    }

    protected function carriesFigures(BudgetLine $line): bool
    {
        return BudgetAllocation::where('budget_line_id', $line->id)->exists() || BudgetLineForecast::where('budget_line_id', $line->id)->exists();
    }

    protected function wouldLoop(BudgetLine $line, int $parentId): bool
    {
        if ($parentId === (int) $line->id) {
            return true;
        }
        $seen = [];
        $cursor = BudgetLine::find($parentId);
        while ($cursor) {
            if ((int) $cursor->id === (int) $line->id || isset($seen[$cursor->id])) {
                return true;
            }
            $seen[$cursor->id] = true;
            $cursor = $cursor->parent_id ? BudgetLine::find($cursor->parent_id) : null;
        }

        return false;
    }

    /** @return array|\Illuminate\Http\RedirectResponse */
    protected function validated(Request $request, ?BudgetLine $line = null)
    {
        $bid = Ledger::businessId();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('budget_lines', 'code')->where('business_id', $bid)->ignore($line?->id)],
            'name' => ['required', 'string', 'max:191'],
            'name_fr' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'section' => ['required', Rule::in(BudgetLine::SECTIONS)],
            'parent_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'expense_category_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_header' => ['nullable', 'boolean'],
        ]);
        $data['is_header'] = $request->boolean('is_header');
        $data['sort_order'] = $data['sort_order'] ?? ($line ? $line->sort_order : ((int) BudgetLine::max('sort_order') + 10));

        // A heading totals its lines: it carries no money of its own, no target and no heading above it.
        if ($data['is_header']) {
            $data['parent_id'] = null;
            $data['account_id'] = null;
            $data['expense_category_id'] = null;
            $data['location_id'] = null;
        }
        $errors = [];
        if (! empty($data['parent_id'])) {
            $parent = BudgetLine::find($data['parent_id']);
            if (! $parent || ! $parent->is_header) {
                $errors['parent_id'] = __('ledger_budget.err_parent_not_heading');
            } elseif ($parent->section !== $data['section']) {
                $errors['parent_id'] = __('ledger_budget.err_parent_other_section');
            }
        }
        // Only an account of the section's own class: an expense account on an
        // income line would report money leaving as money arriving.
        if (! empty($data['account_id']) && ! BudgetLineChoices::accountFits($data['section'], $data['account_id'])) {
            $errors['account_id'] = __('ledger_budget.err_account_section', ['section' => BudgetLine::sectionLabel($data['section']),
                'class' => BudgetLine::SECTION_CLASS[$data['section']]]);
        }
        if (! empty($data['expense_category_id']) && ! BudgetLineChoices::categoryBelongs($data['expense_category_id'])) {
            $errors['expense_category_id'] = __('ledger_budget.err_not_found');
        }
        if (! empty($data['location_id']) && ! BudgetLineChoices::locationBelongs($data['location_id'])) {
            $errors['location_id'] = __('ledger_budget.err_not_found');
        }
        if ($errors) {
            return back()->withInput()->withErrors($errors);
        }
        foreach (['parent_id', 'account_id', 'expense_category_id', 'location_id'] as $k) {
            $data[$k] = ! empty($data[$k]) ? (int) $data[$k] : null;
        }

        return $data;
    }

    /** The label of an account, for messages. */
    public static function accountLabel(?ChartOfAccount $a): string
    {
        return $a ? $a->account_code.' '.Preview::accountName($a) : '';
    }
}
