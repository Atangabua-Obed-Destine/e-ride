<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\ItemSupplier;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\SupplierPayment;
use App\Ledger\Services\DefaultAccount;
use App\Ledger\Services\PaymentBook;
use App\Ledger\Services\Preview;
use App\Ledger\Services\SupplierPayables;
use Illuminate\Http\Request;

/**
 * Paying suppliers (paxhitestbed Admin\SupplierPaymentController). Every payment
 * goes through SupplierPayables::pay(), which checks it against what is owed and
 * posts it. The pay form goes through the review dialog (its real request
 * replayed and rolled back); voiding has its own preview, as in paxhitestbed.
 */
class SupplierPaymentController extends LedgerController
{
    private $route = 'ledger.supplier-payment';

    protected function payables(): SupplierPayables
    {
        return app(SupplierPayables::class);
    }

    public function index(Request $request)
    {
        $this->allow('supplier-payment-view');
        $rows = SupplierPayment::with(['supplier', 'cashAccount', 'paymentAccount', 'allocations.bill'])
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->from, fn ($q) => $q->whereDate('payment_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('payment_date', '<=', $request->to))
            ->orderByDesc('payment_date')->orderByDesc('id')->get();

        return view('ledger.supplier-payment.index', ['title' => __('ledger_accounts.supplier_payments'), 'route' => $this->route,
            'rows' => $rows, 'suppliers' => ItemSupplier::orderBy('title')->get(['id', 'title'])]);
    }

    public function create(Request $request)
    {
        $this->allow('supplier-payment-create');
        $open = $this->payables()->openBills();
        $supplier = $request->supplier_id ? ItemSupplier::find($request->supplier_id) : null;
        $methods = PaymentBook::methods();
        $methodAccounts = [];
        foreach (array_keys($methods) as $m) {
            $methodAccounts[$m] = DefaultAccount::id('payment_method', $m);
        }

        return view('ledger.supplier-payment.create', [
            'title' => __('ledger_accounts.pay_a_supplier'), 'route' => $this->route,
            'suppliers' => ItemSupplier::whereIn('id', $open->pluck('supplier_id')->unique())->orderBy('title')->get(['id', 'title']),
            'supplier' => $supplier,
            'bills' => $supplier ? $open->where('supplier_id', $supplier->id)->values() : collect(),
            'preselect_bill' => (int) $request->bill_id,
            'cash_accounts' => $this->payables()->cashAccounts(),
            'payment_accounts' => PaymentAccount::where('status', 1)->whereNotNull('chart_of_account_id')->orderBy('title')->get(),
            'methods' => $methods,
            'method_accounts' => $methodAccounts,
        ]);
    }

    private function validatePayment(Request $request): void
    {
        $bid = Ledger::businessId();
        $request->validate([
            'supplier_id' => ['required', \Illuminate\Validation\Rule::exists('item_suppliers', 'id')->where('business_id', $bid)],
            'payment_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:1',
            'payment_method' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(PaymentBook::methods()))],
            'cash_account_id' => ['nullable', \Illuminate\Validation\Rule::exists('chart_of_accounts', 'id')->where('business_id', $bid)],
            'payment_account_id' => ['nullable', \Illuminate\Validation\Rule::exists('payment_accounts', 'id')->where('business_id', $bid)],
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:2000',
            'allocations' => 'nullable|array',
            'allocations.*' => 'nullable|numeric|min:0',
        ], [], ['amount' => __('ledger_accounts.amount_paid'), 'payment_date' => __('ledger_accounts.payment_date')]);
    }

    private function paymentInput(Request $request): array
    {
        $input = $request->only(['supplier_id', 'payment_date', 'amount', 'payment_method', 'cash_account_id', 'payment_account_id', 'reference', 'note']);
        $allocations = array_filter((array) $request->input('allocations', []), fn ($v) => $v !== null && $v !== '' && (float) $v != 0.0);
        $input['allocations'] = $allocations ?: null;

        return $input;
    }

    public function store(Request $request)
    {
        $this->allow('supplier-payment-create');
        $this->validatePayment($request);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        try {
            $payment = $this->payables()->pay($this->paymentInput($request), auth()->id());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route($this->route.'.show', $payment->id), __('ledger_accounts.payment_recorded'));
    }

    public function show($id)
    {
        $this->allow('supplier-payment-view');
        $payment = SupplierPayment::with(['supplier', 'cashAccount', 'paymentAccount', 'allocations.bill', 'journalEntry', 'voidJournalEntry', 'creator', 'voider'])->findOrFail($id);

        return view('ledger.supplier-payment.show', ['title' => __('ledger_accounts.supplier_payment').' '.$payment->number, 'route' => $this->route,
            'row' => $payment, 'print' => request()->boolean('print')]);
    }

    public function previewVoid($id)
    {
        $this->allow('supplier-payment-void');
        $payment = SupplierPayment::with(['supplier', 'allocations.bill'])->findOrFail($id);
        $p = Preview::blank();
        $p['summary'] = [[__('ledger_accounts.supplier_payment'), $payment->number], [__('ledger_accounts.amount'), Ledger::money($payment->amount)],
            [__('ledger_accounts.bills_paid'), $payment->allocations->map(fn ($a) => $a->bill->number ?? '')->implode(', ')]];
        Preview::dryRun($p, fn () => $this->payables()->voidPayment($payment, auth()->id(), 'preview'));
        $p['notes'][] = __('ledger_accounts.note_void_payment');

        return view('ledger.partials.preview', ['p' => $p]);
    }

    public function void(Request $request, $id)
    {
        $this->allow('supplier-payment-void');
        $request->merge(['void_reason' => $request->input('void_reason') ?: $request->input('reason')]);
        $request->validate(['void_reason' => 'required|string|max:1000'], [], ['void_reason' => __('ledger_accounts.reason')]);
        $payment = SupplierPayment::findOrFail($id);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        try {
            $this->payables()->voidPayment($payment, auth()->id(), $request->void_reason);
        } catch (\RuntimeException $e) {
            return $this->err(route($this->route.'.show', $id), $e->getMessage());
        }

        return $this->ok(route($this->route.'.show', $id), __('ledger_accounts.payment_voided_msg'));
    }
}
