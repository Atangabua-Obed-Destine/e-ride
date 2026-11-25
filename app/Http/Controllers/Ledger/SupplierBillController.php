<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\ExpenseCategory;
use App\Ledger\Models\ItemSupplier;
use App\Ledger\Models\SupplierBill;
use App\Ledger\Services\PaymentBook;
use App\Ledger\Services\Preview;
use App\Ledger\Services\SupplierPayables;
use Illuminate\Http\Request;

/**
 * Supplier bills (paxhitestbed Admin\SupplierBillController): enter, approve, void;
 * the payables aging and the supplier statement. Every rule lives in
 * SupplierPayables; this reads the form and shows the answer. Approving and voiding
 * go through the review dialog (their own preview routes, as in paxhitestbed, run
 * the real action and roll it back).
 */
class SupplierBillController extends LedgerController
{
    private $route = 'ledger.supplier-bill';

    private $path = 'supplier-bill';

    protected function payables(): SupplierPayables
    {
        return app(SupplierPayables::class);
    }

    private function base(string $title): array
    {
        return ['title' => $title, 'route' => $this->route, 'path' => $this->path];
    }

    public function index(Request $request)
    {
        $this->allow('supplier-bill-view');
        PaymentBook::ensureDefaults();
        $data = $this->base(__('ledger_accounts.supplier_bills'));
        $bills = SupplierBill::with('supplier')
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->from, fn ($q) => $q->whereDate('bill_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('bill_date', '<=', $request->to))
            ->orderByDesc('bill_date')->orderByDesc('id')->get();
        $owed = $this->payables()->openBills()->keyBy('id');
        $today = now()->toDateString();
        foreach ($bills as $bill) {
            $bill->owed = $bill->isApproved() ? (float) ($owed[$bill->id]->owed ?? 0) : 0.0;
            $bill->state = $this->payables()->paymentState($bill, $bill->owed);
            $bill->overdue = $bill->owed > 0.005 && $bill->due_date->toDateString() < $today;
        }
        if ($status = $request->status) {
            $bills = $bills->filter(fn ($b) => $status === 'overdue' ? $b->overdue : $b->state === $status)->values();
        }
        $data['rows'] = $bills;
        $data['suppliers'] = ItemSupplier::orderBy('title')->get(['id', 'title']);
        $data['total_owed'] = round($owed->sum('owed'), 2);
        $data['overdue_owed'] = round($owed->filter(fn ($b) => $b->due_date < $today)->sum('owed'), 2);
        $data['drafts'] = SupplierBill::where('status', SupplierBill::DRAFT)->count();
        $data['payable_account_id'] = $this->payables()->payableAccountId();
        $data['payable_accounts'] = $this->payables()->payableAccounts();

        return view('ledger.supplier-bill.index', $data);
    }

    public function create(Request $request)
    {
        $this->allow('supplier-bill-create');

        return view('ledger.supplier-bill.form', $this->base(__('ledger_accounts.new_supplier_bill')) + ['row' => null] + $this->formData($request->supplier_id));
    }

    public function store(Request $request)
    {
        $this->allow('supplier-bill-create');

        return $this->save($request, null);
    }

    public function show($id)
    {
        $this->allow('supplier-bill-view');
        $bill = SupplierBill::with(['supplier', 'lines.category', 'lines.account', 'journalEntry', 'voidJournalEntry',
            'creator', 'approver', 'voider', 'allocations.payment', 'payableAccount'])->findOrFail($id);
        $owed = $this->payables()->owedOn($bill);

        return view('ledger.supplier-bill.show', $this->base(__('ledger_accounts.supplier_bill').' '.$bill->number) + [
            'row' => $bill, 'owed' => $owed, 'state' => $this->payables()->paymentState($bill, $owed),
            'duplicate' => $bill->isDraft() ? $this->payables()->duplicateOf($bill) : null,
        ]);
    }

    public function edit($id)
    {
        $this->allow('supplier-bill-create');
        $bill = SupplierBill::with('lines')->findOrFail($id);
        if (! $bill->isDraft()) {
            return $this->err(route($this->route.'.show', $bill->id), __('ledger_accounts.err_only_draft_change'));
        }

        return view('ledger.supplier-bill.form', $this->base(__('ledger_accounts.edit_supplier_bill').' '.$bill->number) + ['row' => $bill] + $this->formData($bill->supplier_id, $bill));
    }

    public function update(Request $request, $id)
    {
        $this->allow('supplier-bill-create');

        return $this->save($request, SupplierBill::findOrFail($id));
    }

    public function destroy($id)
    {
        $this->allow('supplier-bill-create');
        try {
            $this->payables()->deleteDraft(SupplierBill::findOrFail($id));
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route($this->route.'.index'), __('ledger_accounts.draft_deleted'));
    }

    public function approve(Request $request, $id)
    {
        $this->allow('supplier-bill-approve');
        $bill = SupplierBill::findOrFail($id);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        try {
            $this->payables()->approve($bill, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->err(route($this->route.'.show', $id), $e->getMessage());
        }

        return $this->ok(route($this->route.'.show', $id), __('ledger_accounts.bill_approved'));
    }

    /** What approving will post, before it is approved: the real approval, rolled back. */
    public function previewApprove($id)
    {
        $this->allow('supplier-bill-approve');
        $bill = SupplierBill::with(['supplier', 'lines.category'])->findOrFail($id);
        $p = Preview::blank();
        $p['summary'] = [[__('ledger_accounts.supplier'), $bill->supplier->title ?? '-'], [__('ledger_accounts.their_invoice'), $bill->supplier_reference ?: '-'],
            [__('ledger_accounts.bill_date'), $bill->bill_date->format('d/m/Y')], [__('ledger_accounts.total'), Ledger::money($bill->total)]];
        if ($dup = $this->payables()->duplicateOf($bill)) {
            $p['warnings'][] = __('ledger_accounts.err_duplicate', ['ref' => $bill->supplier_reference, 'number' => $dup->number]);
        }
        Preview::dryRun($p, fn () => $this->payables()->approve($bill, auth()->id()));
        $p['notes'][] = __('ledger_accounts.note_approve');

        return view('ledger.partials.preview', ['p' => $p]);
    }

    public function previewVoid($id)
    {
        $this->allow('supplier-bill-void');
        $bill = SupplierBill::with('supplier')->findOrFail($id);
        $p = Preview::blank();
        $p['summary'] = [[__('ledger_accounts.supplier_bill'), $bill->number], [__('ledger_accounts.total'), Ledger::money($bill->total)]];
        Preview::dryRun($p, fn () => $this->payables()->voidBill($bill, auth()->id(), 'preview'));
        $p['notes'][] = __('ledger_accounts.note_void_bill');

        return view('ledger.partials.preview', ['p' => $p]);
    }

    public function void(Request $request, $id)
    {
        $this->allow('supplier-bill-void');
        $request->merge(['void_reason' => $request->input('void_reason') ?: $request->input('reason')]);
        $request->validate(['void_reason' => 'required|string|max:1000'], [], ['void_reason' => __('ledger_accounts.reason')]);
        $bill = SupplierBill::findOrFail($id);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        try {
            $this->payables()->voidBill($bill, auth()->id(), $request->void_reason);
        } catch (\RuntimeException $e) {
            return $this->err(route($this->route.'.show', $id), $e->getMessage());
        }

        return $this->ok(route($this->route.'.show', $id), __('ledger_accounts.bill_voided_msg'));
    }

    public function settings(Request $request)
    {
        $this->allow('supplier-bill-approve');
        $request->validate(['payable_account_id' => ['required', \Illuminate\Validation\Rule::exists('chart_of_accounts', 'id')->where('business_id', Ledger::businessId())]]);
        try {
            $this->payables()->setPayableAccount((int) $request->payable_account_id, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(back(), __('ledger_accounts.payable_saved'));
    }

    /** E-Ride: a supplier added from the bill form (paxhitestbed sends to its inventory's supplier screen). */
    public function storeSupplier(Request $request)
    {
        $this->allow('supplier-bill-create');
        $data = $request->validate([
            'title' => 'required|string|max:191', 'contact_person' => 'nullable|string|max:191', 'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:191', 'address' => 'nullable|string|max:191', 'tax_number' => 'nullable|string|max:40',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
        ], [], ['title' => __('ledger_accounts.supplier')]);
        $s = ItemSupplier::create($data + ['payment_terms_days' => (int) ($data['payment_terms_days'] ?? 0), 'status' => 1, 'created_by' => auth()->id()]);
        if ($request->wantsJson()) {
            return response()->json(['id' => $s->id, 'title' => $s->title, 'terms' => $s->payment_terms_days]);
        }

        return $this->ok(back(), __('ledger_accounts.supplier_added'))->withInput(['supplier_id' => $s->id]);
    }

    public function aging(Request $request)
    {
        $this->allow('supplier-bill-view');
        $request->validate(['as_of' => 'nullable|date']);

        return view('ledger.supplier-bill.aging', $this->base(__('ledger_accounts.payables_aging')) + [
            'report' => $this->payables()->aging($request->as_of), 'print' => $request->boolean('print')]);
    }

    public function statement(Request $request)
    {
        $this->allow('supplier-bill-view');
        $request->validate(['supplier_id' => 'nullable|integer', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $supplier = $request->supplier_id ? ItemSupplier::find($request->supplier_id) : null;

        return view('ledger.supplier-bill.statement', $this->base(__('ledger_accounts.supplier_statement')) + [
            'suppliers' => ItemSupplier::orderBy('title')->get(['id', 'title']),
            'supplier' => $supplier,
            'statement' => $supplier ? $this->payables()->statement($supplier->id, $request->from, $request->to) : null,
            'print' => $request->boolean('print'),
        ]);
    }

    private function save(Request $request, ?SupplierBill $bill)
    {
        $bid = Ledger::businessId();
        $request->validate([
            'supplier_id' => ['required', \Illuminate\Validation\Rule::exists('item_suppliers', 'id')->where('business_id', $bid)],
            'kind' => 'nullable|in:bill,opening_balance',
            'supplier_reference' => 'nullable|string|max:100',
            'bill_date' => 'required|date|before_or_equal:today',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'description' => 'nullable|string|max:255',
            'vat_amount' => 'nullable|numeric|min:0',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:20480',
            'lines' => 'nullable|array',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.expense_category_id' => 'nullable|integer',
            'lines.*.amount' => 'nullable|numeric',
            'opening_account_id' => ['nullable', \Illuminate\Validation\Rule::exists('chart_of_accounts', 'id')->where('business_id', $bid)],
            'opening_amount' => 'nullable|numeric',
        ], [], ['supplier_id' => __('ledger_accounts.supplier'), 'bill_date' => __('ledger_accounts.bill_date')]);
        $input = $request->only(['supplier_id', 'kind', 'supplier_reference', 'bill_date', 'due_date', 'description', 'opening_account_id', 'vat_amount']);
        $input['lines'] = $request->input('kind') === SupplierBill::KIND_OPENING
            ? [['description' => $request->description, 'amount' => $request->opening_amount]]
            : array_values($request->input('lines', []));
        $input['attach'] = $request->hasFile('attach') ? $request->file('attach')->store('ledger/supplier-bill/'.$bid, 'public') : null;
        try {
            $bill = $this->payables()->saveDraft($input, auth()->id(), $bill);
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }
        if ($request->input('after') === 'approve') {
            if (! auth()->user()->can('supplier-bill-approve')) {
                return $this->ok(route($this->route.'.show', $bill->id), __('ledger_accounts.saved_not_allowed_approve'));
            }

            // saved; approving is a separate, reviewed step: the bill page opens with the review
            return $this->ok(route($this->route.'.show', ['id' => $bill->id, 'review' => 'approve']), __('ledger_accounts.saved_review_approval'));
        }

        return $this->ok(route($this->route.'.show', $bill->id), __('ledger_accounts.saved_draft'));
    }

    private function formData($supplierId = null, ?SupplierBill $bill = null): array
    {
        $categories = ExpenseCategory::where('status', 1)->orderBy('name')->get();
        $kept = $bill ? $bill->lines->pluck('expense_category_id')->filter()->reject(fn ($id) => $categories->contains('id', (int) $id))
            ->map(fn ($id) => ExpenseCategory::withTrashed()->find($id))->filter()->values() : collect();

        return [
            'suppliers' => ItemSupplier::where('status', 1)->orderBy('title')->get(['id', 'title', 'payment_terms_days']),
            'selected_supplier' => $supplierId,
            'categories' => $categories,
            'kept_categories' => $kept,
            'opening_accounts' => $this->payables()->openingBalanceAccounts(),
        ];
    }
}
