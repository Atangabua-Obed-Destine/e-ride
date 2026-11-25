<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Http\Controllers\Ledger\LedgerController;
use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;
use App\Ledger\Models\PaymentAccount;
use App\Ledger\Models\PayrollSetting;
use App\Ledger\Payroll\PayrollAccounts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * paxhitestbed Admin\PayrollSettingController (permission payroll-setting, the manager's in E-Ride):
 * approval before paying, carrying lines over, proration, the bank file layout, and the loan
 * accounts. The staff-advances account is the 'staff_advances' mapping (shown and set here, as in
 * Mapping settings); loans are paid out of a payment account. Payslip emails and the staff's own
 * payslips are not ported.
 */
class PayrollSettingController extends LedgerController
{
    public function index()
    {
        $this->allow('payroll-setting');

        return view('ledger.payroll.payroll-setting.index', [
            'title' => __('ledger_payroll.payroll_settings'),
            'settings' => PayrollSetting::current(),
            'accounts' => ChartOfAccount::postable()->where('account_code', 'like', '4%')->orderBy('account_code')->get(),
            'advance_account_id' => PayrollAccounts::id('staff_advances'),
            'payment_accounts' => PaymentAccount::where('status', 1)->orderBy('title')->get(),
            'columns' => PayrollSetting::BANK_COLUMNS,
            'bases' => PayrollSetting::PRORATION_BASES,
        ]);
    }

    public function update(Request $request)
    {
        $this->allow('payroll-setting');
        $bid = Ledger::businessId();
        $request->validate([
            'require_approval' => 'required|boolean',
            'carry_over_lines' => 'required|boolean',
            'prorate_partial_months' => 'required|boolean',
            'proration_basis' => ['required', Rule::in(PayrollSetting::PRORATION_BASES)],
            'bank_file_columns' => 'required|array|min:1',
            'bank_file_columns.*' => ['string', Rule::in(PayrollSetting::BANK_COLUMNS)],
            'bank_file_delimiter' => ['required', Rule::in([',', ';', 'tab', '|'])],
            'bank_file_header' => 'required|boolean',
            'bank_file_reference' => 'required|string|max:120',
            'bank_file_decimals' => 'required|integer|between:0,2',
            'advance_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('business_id', $bid)],
            'advance_payment_account_id' => ['nullable', 'integer', Rule::exists('payment_accounts', 'id')->where('business_id', $bid)],
        ], ['bank_file_columns.required' => __('ledger_payroll.err_bank_columns')]);

        $order = (array) $request->input('bank_file_order', []);
        $columns = collect($request->bank_file_columns)->unique()->sortBy(fn ($c) => (int) ($order[$c] ?? 99))->values()->all();
        $settings = PayrollSetting::current();
        $settings->fill([
            'require_approval' => $request->boolean('require_approval'),
            'carry_over_lines' => $request->boolean('carry_over_lines'),
            'prorate_partial_months' => $request->boolean('prorate_partial_months'),
            'proration_basis' => $request->proration_basis,
            'bank_file_columns' => $columns,
            'bank_file_delimiter' => $request->bank_file_delimiter,
            'bank_file_header' => $request->boolean('bank_file_header'),
            'bank_file_reference' => trim($request->bank_file_reference),
            'bank_file_decimals' => (int) $request->bank_file_decimals,
            'advance_payment_account_id' => $request->advance_payment_account_id ?: null,
            'updated_by' => auth()->id(),
        ])->save();
        if ($request->filled('advance_account_id')) {
            DefaultAccountMapping::updateOrCreate(['mapping_type' => 'staff_advances', 'category_key' => ''],
                ['debit_account_id' => (int) $request->advance_account_id, 'credit_account_id' => (int) $request->advance_account_id, 'status' => 1,
                    'updated_by' => auth()->id()]);
        }
        PayrollSetting::flush();

        return $this->ok(route('ledger.payroll-setting.index'), __('ledger_payroll.settings_saved'));
    }
}
