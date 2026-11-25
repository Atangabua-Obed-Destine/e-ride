<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Http\Controllers\Ledger\LedgerController;
use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\TaxRemittance;
use App\Ledger\Payroll\TaxRemittanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * paxhitestbed Admin\TaxRemittanceController: what is held for the CNPS and the State, by authority
 * and salary month, and paying it over (with the receipt number) from a payment account. Recording
 * and voiding go through the review dialog; voiding reverses the entry and keeps the row.
 */
class TaxRemittanceController extends LedgerController
{
    public function index(TaxRemittanceService $service)
    {
        $this->allow('tax-remittance-view', 'tax-remittance-create', 'tax-remittance-void');
        $rows = $service->outstanding();
        $breakdowns = [];
        foreach ($rows as $row) {
            $breakdowns[$row['account_id'].'|'.$row['month']] = $service->breakdownFor($row['account_id'], $row['month']);
        }
        $owing = array_values(array_filter($rows, fn ($r) => $r['outstanding'] > 0.005));

        return view('ledger.payroll.tax-remittance.index', [
            'title' => __('ledger_payroll.tax_remittance'),
            'route' => 'ledger.tax-remittance',
            'rows' => $rows,
            'reconciliation' => $service->reconciliation($rows),
            'breakdowns' => $breakdowns,
            'oldest_owing' => $owing[0] ?? null,
            'total_owing' => round(array_sum(array_column($owing, 'outstanding')), 2),
            'authorities' => ChartOfAccount::whereIn('id', $service->liabilityAccountIds())->orderBy('account_code')->get(),
            'payment_accounts' => PaymentAccount::where('status', 1)->orderBy('title')->get(),
            'remittances' => TaxRemittance::with(['liabilityAccount', 'paymentAccount', 'creator'])->orderByDesc('payment_date')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function store(Request $request, TaxRemittanceService $service)
    {
        $this->allow('tax-remittance-create');
        $bid = Ledger::businessId();
        $request->validate([
            'liability_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('business_id', $bid)],
            'salary_month' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_account_id' => ['required', Rule::exists('payment_accounts', 'id')->where('business_id', $bid)->where('status', 1)],
            'reference' => 'nullable|string|max:191',
            'note' => 'nullable|string|max:2000',
        ]);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        try {
            $r = $service->record($request->only(['liability_account_id', 'salary_month', 'amount', 'payment_date', 'payment_account_id', 'reference', 'note'])
                + ['allow_overpayment' => $request->boolean('allow_overpayment'), 'allow_additional' => $request->boolean('allow_additional')], auth()->id());
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(route('ledger.tax-remittance.index'), __('ledger_payroll.remittance_posted', ['account' => $r->liabilityAccount->account_name ?? '']));
    }

    public function void(Request $request, $id, TaxRemittanceService $service)
    {
        $this->allow('tax-remittance-void');
        $request->validate(['void_reason' => 'nullable|string|max:1000', 'reason' => 'nullable|string|max:1000']);
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        try {
            $service->void(TaxRemittance::findOrFail($id), auth()->id(), $request->void_reason ?: $request->reason);
        } catch (\RuntimeException $e) {
            return $this->err(back(), $e->getMessage());
        }

        return $this->ok(back(), __('ledger_payroll.remittance_voided'));
    }
}
