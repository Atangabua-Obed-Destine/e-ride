<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\YearEndClosing;
use App\Ledger\Services\YearEndClosingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Year-end closing (paxhitestbed Admin\YearEndClosingController): create →
 * start → checklist (and, in PosInnova, the closing stock) → generate the
 * closing entries → approve → reverse. Generating, approving and reversing go
 * through the review dialog (route middleware ledger.review). Every rule is in
 * YearEndClosingService.
 */
class YearEndClosingController extends LedgerController
{
    protected $service;

    public function __construct(YearEndClosingService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $this->allow('year-end-closing-list');
        $query = YearEndClosing::with(['fiscalYear', 'creator', 'approver']);
        if ($request->filled('fiscal_year_id')) {
            $query->where('fiscal_year_id', $request->fiscal_year_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('ledger.year-end-closing.index', [
            'closings' => $query->orderBy('closing_date', 'desc')->orderBy('id', 'desc')->paginate(20)->appends($request->query()),
            'fiscalYears' => FiscalYear::orderBy('start_date', 'desc')->get(),
            'activeFiscalYear' => FiscalYear::getActiveFiscalYear(),
        ]);
    }

    public function create()
    {
        $this->allow('year-end-closing-create');
        $fiscalYears = FiscalYear::where('is_closed', false)->orderBy('start_date', 'desc')->get();
        if ($fiscalYears->isEmpty()) {
            return $this->err(route('ledger.year-end-closing.index'), __('ledger_periodend.no_open_fiscal_years'));
        }

        return view('ledger.year-end-closing.create', [
            'fiscalYears' => $fiscalYears,
            'retainedEarningsAccounts' => ChartOfAccount::postable()->where('account_code', 'like', '12%')->orderBy('account_code')->get(),
            'incomeSummaryAccounts' => ChartOfAccount::postable()->where('account_code', 'like', '13%')->orderBy('account_code')->get(),
            'defaultRetained' => DefaultAccountMapping::accountFor('retained_earnings'),
            'defaultSummary' => DefaultAccountMapping::accountFor('income_summary'),
        ]);
    }

    public function store(Request $request)
    {
        $this->allow('year-end-closing-create');
        $bid = \App\Ledger\Ledger::businessId();
        $data = $request->validate([
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->where('business_id', $bid)],
            'closing_date' => 'required|date',
            'retained_earnings_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('business_id', $bid)],
            'income_summary_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('business_id', $bid)],
            'notes' => 'nullable|string|max:2000',
        ], [], [
            'fiscal_year_id' => __('ledger_periodend.fiscal_year_to_close'),
            'closing_date' => __('ledger_periodend.closing_date'),
            'retained_earnings_account_id' => __('ledger_periodend.retained_earnings_account'),
            'income_summary_account_id' => __('ledger_periodend.income_summary_account'),
        ]);

        try {
            $closing = $this->service->create($data, Auth::id());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.year-end-closing.show', $closing->id), __('ledger_periodend.year_end_closing_created_successfully'));
    }

    public function show($id)
    {
        $this->allow('year-end-closing-list');
        $closing = YearEndClosing::with(['fiscalYear', 'retainedEarningsAccount', 'incomeSummaryAccount',
            'closingJournalEntry.reversingEntry', 'stockJournalEntry.reversingEntry', 'creator', 'approver'])->findOrFail($id);
        $working = in_array($closing->status, [YearEndClosing::STATUS_DRAFT, YearEndClosing::STATUS_IN_PROGRESS], true);

        return view('ledger.year-end-closing.show', [
            'closing' => $closing,
            'checklistCompletion' => $closing->getChecklistCompletionPercentage(),
            'checks' => $closing->status === YearEndClosing::STATUS_IN_PROGRESS ? $this->service->checks($closing) : [],
            'projection' => $this->service->projection($closing),
            'valuation' => $working ? $this->service->stockValuation($closing->closing_date->toDateString()) : (float) $closing->stock_valuation,
        ]);
    }

    public function start($id)
    {
        $this->allow('year-end-closing-edit');
        $closing = YearEndClosing::findOrFail($id);
        try {
            $this->service->start($closing);
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.year-end-closing.show', $closing->id), __('ledger_periodend.closing_process_started'));
    }

    /** The checklist form (paxhitestbed's screen posted checklist[key][completed]; its controller expected item / value and answered JSON). */
    public function updateChecklist(Request $request, $id)
    {
        $this->allow('year-end-closing-edit');
        $closing = YearEndClosing::findOrFail($id);
        $ticked = [];
        foreach ((array) $request->input('checklist', []) as $key => $row) {
            if (is_array($row) && ! empty($row['completed'])) {
                $ticked[] = (string) $key;
            }
        }
        if ($request->filled('item')) { // paxhitestbed's one-item form: item + value
            $ticked = array_keys(array_filter(array_map(fn ($i) => ! empty($i['completed']), $closing->checklistItems())));
            $ticked = $request->boolean('value') ? array_unique(array_merge($ticked, [$request->item])) : array_values(array_diff($ticked, [$request->item]));
        }
        try {
            $this->service->saveChecklist($closing, $ticked, Auth::user());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.year-end-closing.show', $closing->id), __('ledger_periodend.checklist_updated'));
    }

    public function updateStock(Request $request, $id)
    {
        $this->allow('year-end-closing-edit');
        $closing = YearEndClosing::findOrFail($id);
        $data = $request->validate(['closing_stock_value' => 'required|numeric|min:0'], [], ['closing_stock_value' => __('ledger_periodend.closing_stock_counted')]);
        try {
            $this->service->setStock($closing, (float) $data['closing_stock_value'], Auth::id());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.year-end-closing.show', $closing->id), __('ledger_periodend.stock_saved'));
    }

    public function generateClosingEntries($id)
    {
        $this->allow('year-end-closing-edit');
        $closing = YearEndClosing::with('fiscalYear')->findOrFail($id);
        $to = route('ledger.year-end-closing.show', $closing->id);
        try {
            $this->service->generateClosingEntries($closing, Auth::id());
        } catch (\RuntimeException $e) {
            return $this->err($to, __('ledger_periodend.error_generating_entries').': '.$e->getMessage());
        }

        return $this->ok($to, __('ledger_periodend.closing_entries_generated'));
    }

    public function approve($id)
    {
        $this->allow('year-end-closing-approve');
        $closing = YearEndClosing::with('fiscalYear')->findOrFail($id);
        $to = route('ledger.year-end-closing.show', $closing->id);
        try {
            $this->service->approve($closing, Auth::id());
        } catch (\RuntimeException $e) {
            return $this->err($to, __('ledger_periodend.error_approving_closing').': '.$e->getMessage());
        }

        return $this->ok($to, __('ledger_periodend.closing_approved_successfully'));
    }

    /** The review of reversing (the reason is typed in the dialog, so it has its own preview). */
    public function previewReverse($id)
    {
        $this->allow('year-end-closing-approve');
        $closing = YearEndClosing::with('fiscalYear')->findOrFail($id);

        return view('ledger.partials.preview', ['p' => $this->service->previewReverse($closing, Auth::id())]);
    }

    public function reverse(Request $request, $id)
    {
        $this->allow('year-end-closing-approve');
        $closing = YearEndClosing::with('fiscalYear')->findOrFail($id);
        $to = route('ledger.year-end-closing.show', $closing->id);
        $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => __('ledger.reason')]);
        try {
            $this->service->reverse($closing, Auth::id(), (string) $request->reason);
        } catch (\RuntimeException $e) {
            return $this->err($to, __('ledger_periodend.error_reversing_closing').': '.$e->getMessage());
        }

        return $this->ok($to, __('ledger_periodend.closing_reversed_successfully'));
    }

    public function destroy($id)
    {
        $this->allow('year-end-closing-delete');
        try {
            $this->service->delete(YearEndClosing::findOrFail($id));
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.year-end-closing.index'), __('ledger_periodend.closing_deleted_successfully'));
    }
}
