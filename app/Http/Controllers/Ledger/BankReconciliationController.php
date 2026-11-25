<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Models\BankReconciliation;
use App\Ledger\Models\BankReconciliationItem;
use App\Ledger\Services\BankReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Bank reconciliation screens (paxhitestbed Admin\BankReconciliationController).
 * Every rule is in BankReconciliationService; posting a charge or interest and
 * completing go through the review dialog, and are refused without it.
 */
class BankReconciliationController extends LedgerController
{
    protected $service;

    public function __construct(BankReconciliationService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $this->allow('bank-reconciliation-list');
        $rows = BankReconciliation::with('chartOfAccount')
            ->when($request->filled('account_id'), fn ($q) => $q->where('chart_of_account_id', $request->account_id))
            ->when($request->filled('status'), fn ($q) => $request->status === 'completed'
                ? $q->whereIn('status', [BankReconciliation::STATUS_COMPLETED, BankReconciliation::STATUS_APPROVED])
                : $q->where('status', $request->status))
            ->orderByDesc('statement_date')->orderByDesc('id')
            ->paginate(20)->appends($request->query());

        $accounts = $this->service->accounts();
        $last = $accounts->mapWithKeys(fn ($a) => [$a->id => [
            'last' => $this->service->lastCompleted($a->id),
            'book' => $this->service->bookBalance($a->id, now()->toDateString()),
        ]]);

        return view('ledger.bank-reconciliation.index', ['rows' => $rows, 'accounts' => $accounts, 'last' => $last]);
    }

    public function summary(Request $request)
    {
        return redirect()->route('ledger.bank-reconciliation.index', $request->query());
    }

    public function create(Request $request)
    {
        $this->allow('bank-reconciliation-create');

        return view('ledger.bank-reconciliation.create', [
            'accounts' => $this->service->accounts(),
            'selected' => (int) $request->input('account_id'),
        ]);
    }

    public function store(Request $request)
    {
        $this->allow('bank-reconciliation-create');
        $data = $request->validate([
            'chart_of_account_id' => 'required|integer',
            'statement_date' => 'required|date|before_or_equal:today',
            'statement_closing_balance' => 'required|numeric',
            'statement_opening_balance' => 'nullable|numeric',
            'statement_reference' => 'nullable|string|max:190',
            'notes' => 'nullable|string|max:2000',
        ], [], $this->attributes());

        try {
            $rec = $this->service->create($data, Auth::id());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.bank-reconciliation.show', $rec->id), __('ledger_periodend.br_started'));
    }

    public function show($id)
    {
        $this->allow('bank-reconciliation-list');
        $rec = BankReconciliation::with(['chartOfAccount', 'reconciledBy', 'items' => fn ($q) => $q->orderBy('transaction_date')->orderBy('id')])->findOrFail($id);

        return view('ledger.bank-reconciliation.show', [
            'rec' => $rec,
            'position' => $this->service->position($rec),
            'editable' => $rec->isEditable(),
            'adjustmentAccounts' => $this->service->adjustmentAccounts(),
        ]);
    }

    public function saveTicks(Request $request, $id)
    {
        $this->allow('bank-reconciliation-edit');
        $rec = BankReconciliation::findOrFail($id);
        $ids = array_map('intval', (array) $request->input('cleared', []));

        return $this->attempt(fn () => $this->service->saveTicks($rec, $ids), $rec, __('ledger_periodend.br_ticks_saved'));
    }

    public function clearItem($item)
    {
        $this->allow('bank-reconciliation-edit');
        $item = BankReconciliationItem::findOrFail($item);
        $rec = BankReconciliation::findOrFail($item->bank_reconciliation_id);

        return $this->attempt(fn () => $this->service->setCleared($rec, [$item->id], true), $rec);
    }

    public function unclearItem($item)
    {
        $this->allow('bank-reconciliation-edit');
        $item = BankReconciliationItem::findOrFail($item);
        $rec = BankReconciliation::findOrFail($item->bank_reconciliation_id);

        return $this->attempt(fn () => $this->service->setCleared($rec, [$item->id], false), $rec);
    }

    public function updateStatementBalance(Request $request, $id)
    {
        $this->allow('bank-reconciliation-edit');
        $rec = BankReconciliation::findOrFail($id);
        $data = $request->validate([
            'statement_closing_balance' => 'required|numeric',
            'statement_reference' => 'nullable|string|max:190',
        ], [], $this->attributes());

        return $this->attempt(fn () => $this->service->updateStatement($rec, (float) $data['statement_closing_balance'], $data['statement_reference'] ?? null),
            $rec, __('ledger_periodend.br_balance_updated'));
    }

    public function recalculate($id)
    {
        $this->allow('bank-reconciliation-edit');
        $rec = BankReconciliation::findOrFail($id);

        return $this->attempt(fn () => $this->service->recalculate($rec), $rec);
    }

    public function previewAdjustment(Request $request, $id)
    {
        $this->allow('bank-reconciliation-edit');
        $rec = BankReconciliation::findOrFail($id);
        $data = $this->adjustmentInput($request);

        return view('ledger.partials.preview', ['p' => $this->service->previewAdjustment($rec, $data, Auth::id())]);
    }

    public function addAdjustment(Request $request, $id)
    {
        $this->allow('bank-reconciliation-edit');
        $rec = BankReconciliation::findOrFail($id);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $data = $this->adjustmentInput($request);

        return $this->attempt(fn () => $this->service->addAdjustment($rec, $data, Auth::id()), $rec, __('ledger_periodend.br_posted_ticked'));
    }

    public function previewComplete($id)
    {
        $this->allow('bank-reconciliation-complete');
        $rec = BankReconciliation::findOrFail($id);

        return view('ledger.partials.preview', ['p' => $this->service->previewComplete($rec, Auth::id())]);
    }

    public function complete(Request $request, $id)
    {
        $this->allow('bank-reconciliation-complete');
        $rec = BankReconciliation::findOrFail($id);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }

        return $this->attempt(fn () => $this->service->complete($rec, Auth::id()), $rec, __('ledger_periodend.br_completed'));
    }

    public function destroy($id)
    {
        $this->allow('bank-reconciliation-delete');
        $rec = BankReconciliation::findOrFail($id);
        try {
            $this->service->delete($rec);
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.bank-reconciliation.index'), __('ledger_periodend.br_deleted'));
    }

    /* ------------------------------------------------------------------ */

    protected function adjustmentInput(Request $request): array
    {
        return $request->validate([
            'kind' => 'required|in:bank_charge,bank_interest',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'account_id' => 'required|integer',
            'description' => 'nullable|string|max:150',
        ], [], $this->attributes());
    }

    protected function attempt(callable $action, BankReconciliation $rec, ?string $success = null)
    {
        $to = route('ledger.bank-reconciliation.show', $rec->id);
        try {
            $action();
        } catch (\RuntimeException $e) {
            return $this->err($to, $e->getMessage());
        }

        return $success ? $this->ok($to, $success) : redirect($to);
    }

    private function attributes(): array
    {
        return [
            'chart_of_account_id' => __('ledger_periodend.account'),
            'statement_date' => __('ledger_periodend.statement_date'),
            'statement_closing_balance' => __('ledger_periodend.closing_balance_on_statement'),
            'statement_opening_balance' => __('ledger_periodend.opening_balance_on_statement'),
            'statement_reference' => __('ledger_periodend.statement_reference'),
            'kind' => __('ledger_periodend.what'),
            'amount' => __('ledger_periodend.amount'),
            'date' => __('ledger_periodend.date'),
            'account_id' => __('ledger_periodend.account'),
        ];
    }
}
