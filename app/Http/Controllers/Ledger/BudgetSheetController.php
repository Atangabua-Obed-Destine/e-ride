<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Exports\BudgetSheetExport;
use App\Ledger\Ledger;
use App\Ledger\Models\Budget;
use App\Ledger\Models\BudgetAllocation;
use App\Ledger\Models\BudgetLine;
use App\Ledger\Models\BudgetLineForecast;
use App\Ledger\Services\BudgetActualsService;
use App\Ledger\Services\BudgetPeriod;
use App\Ledger\Services\BudgetReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The Income & Expenditure sheet (paxhitestbed BudgetSheetController): budget
 * figures beside what the posted ledger holds for the same period. One screen
 * for entry and reporting, as the paper form is one sheet.
 *
 * PosInnova differences: actuals come from the ledger (BudgetActualsService);
 * no tuition forecast from student numbers (school-only); approving goes
 * through the review dialog.
 */
class BudgetSheetController extends LedgerController
{
    /** The steps a sheet moves through, and what may follow each one. */
    protected const TRANSITIONS = [
        'draft' => 'pending_approval',
        'pending_approval' => 'approved',
        'approved' => 'active',
        'active' => 'closed',
    ];

    /** @var BudgetActualsService */
    protected $actuals;

    /** @var BudgetReconciliationService */
    protected $reconciliation;

    public function __construct(BudgetActualsService $actuals, BudgetReconciliationService $reconciliation)
    {
        parent::__construct();
        $this->actuals = $actuals;
        $this->reconciliation = $reconciliation;
    }

    /** Figures may only be edited while the sheet is still a draft. */
    public static function isEditable(Budget $budget): bool
    {
        return $budget->status === 'draft';
    }

    public function submit($id)
    {
        $this->allow('budget-edit');

        return $this->advance($id, 'draft', __('ledger_budget.sheet_submitted'));
    }

    public function approve(Request $request, $id)
    {
        $this->allow('budget-approve');
        $budget = $this->sheet($id);
        $budgeted = $this->budgetedByLine($budget);
        $message = __('ledger_budget.sheet_approved', [
            'income' => Ledger::money($this->actuals->sectionTotal($budgeted, 'income')),
            'expenditure' => Ledger::money($this->actuals->sectionTotal($budgeted, 'expenditure')),
            'capital' => Ledger::money($this->actuals->sectionTotal($budgeted, 'capital')),
        ]);

        return $this->advance($id, 'pending_approval', $message, function (Budget $b) {
            $b->approved_by = Auth::id();
            $b->approved_at = now();
        });
    }

    public function activate($id)
    {
        $this->allow('budget-activate');

        return $this->advance($id, 'approved', __('ledger_budget.sheet_activated'));
    }

    public function close($id)
    {
        $this->allow('budget-close');

        return $this->advance($id, 'active', __('ledger_budget.sheet_closed'));
    }

    /** Move a sheet one step along, refusing anything out of order. */
    protected function advance($id, string $from, string $message, ?callable $before = null)
    {
        $budget = $this->sheet($id);
        if ($budget->status !== $from) {
            return $this->err(back(), __('ledger_budget.step_not_available', ['status' => Budget::statusLabel($budget->status)]));
        }
        if ($before) {
            $before($budget);
        }
        $budget->status = static::TRANSITIONS[$from];
        $budget->updated_by = Auth::id();
        $budget->save();

        return $this->ok(route('ledger.budget-sheet.show', $budget->id), $message);
    }

    /** Correct the period a sheet covers (draft only: it changes which actuals the sheet reports). */
    public function updatePeriod(Request $request, $id)
    {
        $this->allow('budget-edit');
        $budget = $this->sheet($id);
        if (! static::isEditable($budget)) {
            return $this->err(route('ledger.budget-sheet.show', $budget->id), __('ledger_budget.period_locked'));
        }
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date', BudgetPeriod::noOverlapRule($request->input('start_date'), $budget->id)],
        ]);
        $validated['fiscal_year'] = BudgetPeriod::labelFor($validated['start_date'], $validated['end_date']);
        $validated['updated_by'] = Auth::id();
        $budget->update($validated);

        return $this->ok(route('ledger.budget-sheet.show', $budget->id), __('ledger_budget.period_updated'))
            ->with('budget_warnings', BudgetPeriod::alignmentWarnings($validated['start_date'], $validated['end_date']));
    }

    /** Remove a sheet created by mistake, only while it is a draft. */
    public function destroy($id)
    {
        $this->allow('budget-delete');
        $budget = $this->sheet($id);
        if ($budget->status !== 'draft') {
            return $this->err(back(), __('ledger_budget.sheet_only_draft_delete'));
        }
        DB::transaction(function () use ($budget) {
            BudgetLineForecast::where('budget_id', $budget->id)->delete();
            BudgetAllocation::where('budget_id', $budget->id)->delete();
            $budget->delete();
        });

        return $this->ok(route('ledger.budget-sheet.index'), __('ledger_budget.sheet_deleted'));
    }

    /** The sheets, newest first. */
    public function index()
    {
        $this->allow('budget-view');

        return view('ledger.budget-sheet.index', [
            'budgets' => Budget::where('is_institutional', true)->orderByDesc('start_date')->get(),
        ]);
    }

    /** Start a sheet for a period, optionally from last year's actual figures or budget. */
    public function store(Request $request)
    {
        $this->allow('budget-edit', 'budget-create');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date', BudgetPeriod::noOverlapRule($request->input('start_date'))],
            'opening_balance' => ['nullable', 'numeric'],
            'seed_from' => ['nullable', 'in:actual,budget'],
            'uplift_percent' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
        ]);

        $seeded = 0;
        $budget = DB::transaction(function () use ($validated, &$seeded) {
            $budget = Budget::create([
                'title' => $validated['title'],
                'type' => 'annual',
                'is_institutional' => true,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'opening_balance' => ($validated['opening_balance'] ?? null) !== null && $validated['opening_balance'] !== ''
                    ? $validated['opening_balance'] : $this->previousClosingBalance($validated['start_date']),
                'total_amount' => 0,
                'status' => 'draft',
                'fiscal_year' => BudgetPeriod::labelFor($validated['start_date'], $validated['end_date']),
                'created_by' => Auth::id(),
            ]);
            $seeded = ! empty($validated['seed_from'])
                ? $this->seedFromPreviousSheet($budget, $validated['seed_from'], (float) ($validated['uplift_percent'] ?? 0)) : 0;

            return $budget;
        });

        return $this->ok(route('ledger.budget-sheet.show', $budget->id),
            $seeded ? __('ledger_budget.sheet_seeded', ['count' => $seeded]) : __('ledger_budget.sheet_created'))
            ->with('budget_warnings', BudgetPeriod::alignmentWarnings($validated['start_date'], $validated['end_date']));
    }

    /** Fill a new sheet from the one before it: what really happened, or what was planned, plus an uplift. */
    protected function seedFromPreviousSheet(Budget $budget, string $basis, float $upliftPercent): int
    {
        $previous = $this->previousSheet($budget);
        if (! $previous) {
            return 0;
        }
        $source = $basis === 'actual'
            ? $this->actuals->forPeriod($previous->start_date->format('Y-m-d'), $previous->end_date->format('Y-m-d'))['lines']
            : $this->budgetedByLine($previous);
        $multiplier = 1 + ($upliftPercent / 100);
        $postable = BudgetLine::active()->postable()->get()->keyBy('id');
        $count = 0;
        foreach ($source as $lineId => $amount) {
            $line = $postable->get((int) $lineId);
            $amount = round($amount * $multiplier, 2);
            if (! $line || $amount <= 0) {
                continue;
            }
            BudgetAllocation::updateOrCreate(
                ['budget_id' => $budget->id, 'budget_line_id' => (int) $lineId],
                ['allocated_amount' => $amount, 'title' => $line->name, 'is_active' => true,
                    'created_by' => Auth::id(), 'updated_by' => Auth::id()]
            );
            $count++;
        }
        $this->storeTotal($budget);

        return $count;
    }

    /** Everything the sheet shows, assembled once, for the screen, the PDF and the workbook. */
    protected function sheetData($id): array
    {
        $budget = $this->sheet($id);
        $from = $budget->start_date->format('Y-m-d');
        $to = $budget->end_date->format('Y-m-d');
        $lines = BudgetLine::sheet();
        $budgeted = $this->budgetedByLine($budget);
        $result = $this->actuals->forPeriod($from, $to);

        // The ledger's actuals written onto the allocations, so the dashboard,
        // reports and alerts see the sheet as a budget like any other.
        $this->actuals->refresh($budget);
        $budget->refresh();

        $previous = $this->previousSheet($budget);
        $priorActual = $previous ? $this->actuals->withHeaderTotals(
            $this->actuals->forPeriod($previous->start_date->format('Y-m-d'), $previous->end_date->format('Y-m-d'))['lines']) : [];
        $priorTotals = [
            'opening' => $previous ? (float) $previous->opening_balance : 0.0,
            'income' => $previous ? $this->actuals->sectionTotal($priorActual, 'income') : 0.0,
            'expenditure' => $previous ? $this->actuals->sectionTotal($priorActual, 'expenditure') : 0.0,
            'capital' => $previous ? $this->actuals->sectionTotal($priorActual, 'capital') : 0.0,
        ];
        $priorTotals['closing'] = $priorTotals['opening'] + $priorTotals['income'] - $priorTotals['expenditure'];

        return [
            'budget' => $budget,
            'lines' => $lines,
            'budgeted' => $this->actuals->withHeaderTotals($budgeted),
            'actual' => $this->actuals->withHeaderTotals($result['lines']),
            'unallocated' => $result['unallocated'],
            'totals' => $this->totals($budget, $budgeted, $result['lines'], $lines),
            'editable' => static::isEditable($budget),
            'delegated' => $this->delegatedByLine($budget),
            'previous' => $previous,
            'priorActual' => $priorActual,
            'priorTotals' => $priorTotals,
            'reconciliation' => $this->reconciliation->reconcile($from, $to),
            'accountsByLine' => $this->reconciliation->accountsByLine(),
            'forecasts' => BudgetLineForecast::where('budget_id', $budget->id)->get()->keyBy('budget_line_id'),
            'groupEnds' => BudgetLine::groupEnds($lines),
        ];
    }

    /** The sheet that covers a calendar month (YYYY-MM): paxhitestbed MonthLinkController@budgetSheet. */
    public function month($month)
    {
        $this->allow('budget-view');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $month)) {
            return $this->err(route('ledger.budget-sheet.index'), __('ledger_budget.not_a_month', ['month' => $month]));
        }
        $from = $month.'-01';
        $to = date('Y-m-t', strtotime($from));
        $budget = Budget::where('is_institutional', true)->where('status', '!=', 'cancelled')
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->orderByDesc('start_date')->first();
        if (! $budget) {
            return $this->err(route('ledger.budget-sheet.index'), __('ledger_budget.no_sheet_for_month', ['month' => $month]));
        }

        return redirect()->route('ledger.budget-sheet.show', $budget->id);
    }

    public function show($id)
    {
        $this->allow('budget-view');

        return view('ledger.budget-sheet.show', $this->sheetData($id));
    }

    /** The sheet as a PDF, for signing and filing (mpdf, as PosInnova's other reports). */
    public function exportPdf($id)
    {
        $this->allow('budget-view');
        $data = $this->sheetData($id);
        $data['businessName'] = optional(session('business'))->name ?: DB::table('ledger_businesses')->where('id', Ledger::businessId())->value('name');
        $html = view('ledger.budget-sheet.pdf', $data)->render();
        $mpdf = $this->getMpdf('P');
        $mpdf->SetTitle(__('ledger_budget.ie').' - '.$data['budget']->title);
        $mpdf->WriteHTML($html);
        $name = 'income-and-expenditure-'.Str::slug($data['budget']->title).'.pdf';

        return response($mpdf->Output($name, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /** The sheet as a workbook: figures as numbers, to be summed and modelled. */
    public function exportExcel($id)
    {
        $this->allow('budget-view');
        $data = $this->sheetData($id);
        $data['businessName'] = optional(session('business'))->name ?: DB::table('ledger_businesses')->where('id', Ledger::businessId())->value('name');

        return Excel::download(new BudgetSheetExport($data), 'income-and-expenditure-'.Str::slug($data['budget']->title).'.xlsx');
    }

    /** Save the figures typed into the sheet (draft only, enforced here and not only in the view). */
    public function saveFigures(Request $request, $id)
    {
        $this->allow('budget-edit');
        $budget = $this->sheet($id);
        if (! static::isEditable($budget)) {
            return $this->err(route('ledger.budget-sheet.show', $budget->id), __('ledger_budget.figures_locked', ['status' => Budget::statusLabel($budget->status)]));
        }
        $request->validate([
            'opening_balance' => ['nullable', 'numeric'],
            'amounts' => ['nullable', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
            'workings' => ['nullable', 'array'],
            'workings.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'workings.*.rate' => ['nullable', 'numeric', 'min:0'],
            'workings.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        // Only this business's lines that can carry a figure: a heading totals its
        // children, so a figure typed against one would be counted twice.
        $postable = BudgetLine::postable()->get()->keyBy('id');
        $workings = BudgetLineForecast::where('budget_id', $budget->id)->get()->keyBy('budget_line_id');
        $submitted = (array) $request->input('workings', []);

        DB::transaction(function () use ($request, $budget, $postable, $workings, $submitted) {
            $budget->opening_balance = $request->input('opening_balance', 0) ?: 0;
            foreach ((array) $request->input('amounts', []) as $lineId => $amount) {
                $line = $postable->get((int) $lineId);
                if (! $line) {
                    continue;
                }
                $amount = $amount === null || $amount === '' ? null : round((float) $amount, 2);
                $working = $workings[(int) $lineId] ?? null;
                $unit = $this->unitWorking($submitted[$lineId] ?? null);

                // A working that still explains the figure is kept (or written);
                // one that no longer matches the figure in the box goes. A sheet
                // must never print "12 x 85,000" under a number that is not 1,020,000.
                if ($unit && $amount !== null && abs($unit['amount'] - $amount) <= 0.5) {
                    BudgetLineForecast::updateOrCreate(
                        ['budget_id' => $budget->id, 'budget_line_id' => $line->id],
                        ['quantity' => $unit['quantity'], 'rate' => $unit['rate'], 'rate_basis' => BudgetLineForecast::BASIS_UNIT,
                            'computed_amount' => $unit['amount'], 'note' => $unit['note'], 'updated_by' => Auth::id(), 'created_by' => Auth::id()]
                    );
                } elseif ($working && ($unit || abs((float) $working->computed_amount - (float) $amount) > 0.5)) {
                    $working->delete();
                }

                if ($amount === null || $amount == 0.0) {
                    BudgetAllocation::where('budget_id', $budget->id)->where('budget_line_id', $line->id)->delete();
                    continue;
                }
                BudgetAllocation::updateOrCreate(
                    ['budget_id' => $budget->id, 'budget_line_id' => $line->id],
                    ['allocated_amount' => $amount, 'title' => $line->name, 'is_active' => true, 'updated_by' => Auth::id()]
                );
            }
            $budget->updated_by = Auth::id();
            $this->storeTotal($budget);
        });

        return $this->ok(route('ledger.budget-sheet.show', $budget->id), __('ledger_budget.figures_saved'));
    }

    /** Save one line as a quantity at a unit amount, from the dialog, without leaving the page (JSON). */
    public function unitBasis(Request $request, $id)
    {
        $this->allow('budget-edit');
        $budget = $this->sheet($id);
        if (! static::isEditable($budget)) {
            return response()->json(['ok' => false, 'message' => __('ledger_budget.figures_locked', ['status' => Budget::statusLabel($budget->status)])], 422);
        }
        $v = Validator::make($request->all(), [
            'budget_line_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'rate' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()->all()], 422);
        }
        $data = $v->validated();
        $line = BudgetLine::find($data['budget_line_id']);
        if (! $line || $line->is_header) {
            return response()->json(['ok' => false, 'message' => __('ledger_budget.unit_not_line')], 422);
        }
        $unit = $this->unitWorking($data);

        DB::transaction(function () use ($budget, $line, $unit) {
            BudgetLineForecast::updateOrCreate(
                ['budget_id' => $budget->id, 'budget_line_id' => $line->id],
                ['quantity' => $unit['quantity'], 'rate' => $unit['rate'], 'rate_basis' => BudgetLineForecast::BASIS_UNIT,
                    'computed_amount' => $unit['amount'], 'note' => $unit['note'], 'updated_by' => Auth::id(), 'created_by' => Auth::id()]
            );
            if ($unit['amount'] > 0) {
                BudgetAllocation::updateOrCreate(
                    ['budget_id' => $budget->id, 'budget_line_id' => $line->id],
                    ['allocated_amount' => $unit['amount'], 'title' => $line->name, 'is_active' => true, 'updated_by' => Auth::id()]
                );
            } else {
                BudgetAllocation::where('budget_id', $budget->id)->where('budget_line_id', $line->id)->delete();
            }
            $this->storeTotal($budget);
        });

        return response()->json(['ok' => true, 'line_id' => $line->id] + $unit);
    }

    /** One line's submitted quantity x amount, or null when the boxes were left empty. */
    protected function unitWorking($input): ?array
    {
        if (! is_array($input) || ($input['quantity'] ?? '') === '' || ($input['rate'] ?? '') === '') {
            return null;
        }
        $quantity = round((float) $input['quantity'], 2);
        $rate = round((float) $input['rate'], 2);
        $note = trim((string) ($input['note'] ?? ''));

        return ['quantity' => $quantity, 'rate' => $rate, 'amount' => round($quantity * $rate, 2), 'note' => $note === '' ? null : $note];
    }

    /**
     * How much of each sheet line has been handed to a departmental budget,
     * derived from where each delegated allocation's money would land.
     *
     * @return array<int,float>
     */
    protected function delegatedByLine(Budget $budget): array
    {
        $children = Budget::where('parent_id', $budget->id)->where('status', '!=', 'cancelled')->get();
        $out = [];
        foreach ($children as $child) {
            foreach (BudgetAllocation::with('account')->where('budget_id', $child->id)->get() as $a) {
                $lineId = $this->actuals->lineForAllocation($a, $child->location_id ? (int) $child->location_id : null);
                if ($lineId) {
                    $out[$lineId] = round(($out[$lineId] ?? 0) + (float) $a->allocated_amount, 2);
                }
            }
        }

        return $out;
    }

    /** Opening, income, expenditure, closing and the reconciliation to cash. */
    protected function totals(Budget $budget, array $budgeted, array $actual, $lines): array
    {
        $opening = (float) $budget->opening_balance;
        // Depreciation (68) never leaves the bank: the lines reading 68 accounts.
        $depLines = $lines->filter(fn ($l) => ! $l->is_header && $l->account && strpos((string) $l->account->account_code, '68') === 0)->pluck('id');
        $out = [];
        foreach (['budget' => $budgeted, 'actual' => $actual] as $key => $figures) {
            $income = $this->actuals->sectionTotal($figures, 'income');
            $expenditure = $this->actuals->sectionTotal($figures, 'expenditure');
            $capital = $this->actuals->sectionTotal($figures, 'capital');
            // As the paper does it: capital sits below the closing balance and is not deducted from it.
            $closing = $opening + $income - $expenditure;
            $depreciation = 0.0;
            foreach ($depLines as $id) {
                $depreciation += $figures[$id] ?? 0;
            }
            $out[$key] = [
                'opening' => $opening, 'income' => $income, 'expenditure' => $expenditure, 'capital' => $capital,
                'closing' => round($closing, 2), 'depreciation' => round($depreciation, 2),
                'cash' => round($closing + $depreciation - $capital, 2),
            ];
        }

        return $out;
    }

    /** The stored total is what is being spent (expenditure and capital), not what is hoped to come in. */
    protected function storeTotal(Budget $budget): void
    {
        $b = $this->budgetedByLine($budget);
        $budget->total_amount = $this->actuals->sectionTotal($b, 'expenditure') + $this->actuals->sectionTotal($b, 'capital');
        $budget->allocated_amount = $budget->total_amount;
        $budget->remaining_amount = (float) $budget->total_amount - (float) $budget->spent_amount;
        $budget->save();
    }

    /** @return array<int,float> */
    protected function budgetedByLine(Budget $budget): array
    {
        return BudgetAllocation::where('budget_id', $budget->id)->whereNotNull('budget_line_id')
            ->pluck('allocated_amount', 'budget_line_id')->map(fn ($v) => (float) $v)->toArray();
    }

    /** The sheet whose period ran immediately before this one. */
    protected function previousSheet(Budget $budget): ?Budget
    {
        return Budget::where('is_institutional', true)->where('id', '!=', $budget->id)->where('status', '!=', 'cancelled')
            ->where('end_date', '<', $budget->start_date->format('Y-m-d'))->orderByDesc('end_date')->first();
    }

    /** The budgeted closing balance of the sheet that ended before this one starts. */
    protected function previousClosingBalance(string $startDate): float
    {
        $previous = Budget::where('is_institutional', true)->where('status', '!=', 'cancelled')
            ->where('end_date', '<', $startDate)->orderByDesc('end_date')->first();
        if (! $previous) {
            return 0.0;
        }
        $b = $this->budgetedByLine($previous);

        return (float) $previous->opening_balance + $this->actuals->sectionTotal($b, 'income') - $this->actuals->sectionTotal($b, 'expenditure');
    }

    protected function sheet($id): Budget
    {
        return Budget::where('is_institutional', true)->findOrFail($id);
    }
}
