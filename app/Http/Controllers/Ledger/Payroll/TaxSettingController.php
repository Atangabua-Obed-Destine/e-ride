<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Http\Controllers\Ledger\LedgerController;
use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\Staff;
use App\Ledger\Models\StaffTaxExemption;
use App\Ledger\Models\TaxGroup;
use App\Ledger\Models\TaxSetting;
use App\Ledger\Payroll\TaxDefaults;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * paxhitestbed Admin\TaxSettingController (permissions tax-setting-*): every band and standalone
 * tax, the add form, the coverage warnings (holes between bands, salaries above the last band,
 * dependent taxes filed inside a group) and each tax's staff exemptions. The Cameroon defaults are
 * installed the first time (App\Ledger\Payroll\TaxDefaults).
 */
class TaxSettingController extends LedgerController
{
    public function index()
    {
        $this->allow('tax-setting-view', 'tax-setting-create', 'tax-setting-edit', 'tax-setting-delete');
        TaxDefaults::ensure();
        $groups = TaxGroup::active()->ordered()->get();
        $highest = (float) (Staff::where('status', 1)->max('basic_salary') ?? 0);
        $coverage = [];
        foreach ($groups as $group) {
            $c = $group->coverageGaps();
            $c['biting_gaps'] = array_values(array_filter($c['gaps'], fn ($gap) => $gap['from'] <= $highest));
            $c['top_exceeded'] = $c['open_top'] && $c['open_top']['from'] <= $highest;
            $coverage[$group->id] = $c;
        }

        return view('ledger.payroll.tax-setting.index', [
            'title' => __('ledger_payroll.tax_settings'),
            'route' => 'ledger.tax-setting',
            'access' => 'tax-setting',
            'rows' => TaxSetting::with(['taxGroup', 'liabilityAccount', 'exemptions.staff'])->orderBy('bracket_order')->orderBy('min_amount')->get(),
            'tax_groups' => $groups,
            'all_groups' => TaxGroup::ordered()->get(),
            'standalone_taxes' => TaxSetting::active()->standalone()->ordered()->get(),
            'highest_salary' => $highest,
            'coverage' => $coverage,
            'misfiled_dependents' => TaxSetting::active()->where('is_dependent', true)->whereNotNull('tax_group_id')->get(),
            'accounts' => self::liabilityAccounts(),
            'staff' => Staff::where('status', 1)->orderBy('first_name')->get(),
        ]);
    }

    public static function liabilityAccounts()
    {
        return ChartOfAccount::postable()->where('account_code', 'like', '4%')->orderBy('account_code')->get();
    }

    public function store(Request $request)
    {
        $this->allow('tax-setting-create');
        $this->check($request);
        if ($request->is_dependent && $request->tax_group_id) {
            return $this->err(back(), __('ledger_payroll.err_dependent_in_group'));
        }
        if ($overlap = $this->overlap($request)) {
            return $this->err(back(), $overlap);
        }
        $tax = new TaxSetting();
        $this->fill($tax, $request);
        $tax->bracket_order = $request->bracket_order ?? ((TaxSetting::max('bracket_order') ?? 0) + 1);
        $tax->status = 1;
        $tax->save();

        return $this->ok(back(), __('ledger_payroll.created'));
    }

    public function update(Request $request, $id)
    {
        $this->allow('tax-setting-edit');
        $tax = TaxSetting::findOrFail($id);
        $this->check($request, true);
        if ($request->is_dependent && $request->tax_group_id) {
            return $this->err(back(), __('ledger_payroll.err_dependent_in_group'));
        }
        if ($overlap = $this->overlap($request, $tax->id)) {
            return $this->err(back(), $overlap);
        }
        $this->fill($tax, $request);
        $tax->bracket_order = $request->bracket_order ?? $tax->bracket_order;
        $tax->status = $request->boolean('status');
        $tax->save();

        return $this->ok(back(), __('ledger_payroll.updated'));
    }

    public function destroy($id)
    {
        $this->allow('tax-setting-delete');
        $tax = TaxSetting::findOrFail($id);
        if (\App\Ledger\Models\PayrollTaxLine::where('tax_setting_id', $tax->id)->exists()) {
            $tax->forceFill(['status' => 0])->save();

            return $this->ok(back(), __('ledger_payroll.switched_off_in_use'));
        }
        StaffTaxExemption::where('tax_setting_id', $tax->id)->delete();
        $tax->delete();

        return $this->ok(back(), __('ledger_payroll.deleted'));
    }

    public function storeExemption(Request $request)
    {
        $this->allow('tax-setting-edit', 'tax-setting-create');
        $bid = Ledger::businessId();
        $request->validate([
            'tax_setting_id' => ['required', Rule::exists('tax_settings', 'id')->where('business_id', $bid)],
            'staff_id' => ['required', Rule::exists('ledger_staff', 'id')->where('business_id', $bid)],
            'reason' => 'nullable|string|max:500',
            'custom_percentage' => 'nullable|numeric|min:0|max:100',
            'custom_fixed_amount' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date|after_or_equal:today',
        ]);
        if (StaffTaxExemption::where('tax_setting_id', $request->tax_setting_id)->where('staff_id', $request->staff_id)->exists()) {
            return $this->err(back(), __('ledger_payroll.err_exists'));
        }
        StaffTaxExemption::create($request->only(['tax_setting_id', 'staff_id', 'reason', 'custom_percentage', 'custom_fixed_amount', 'expires_at']));

        return $this->ok(back(), __('ledger_payroll.exemption_added'));
    }

    public function destroyExemption($taxSettingId, $staffId)
    {
        $this->allow('tax-setting-edit', 'tax-setting-delete');
        $deleted = StaffTaxExemption::where('tax_setting_id', $taxSettingId)->where('staff_id', $staffId)->delete();

        return $deleted ? $this->ok(back(), __('ledger_payroll.exemption_removed')) : $this->err(back(), __('ledger_payroll.not_found'));
    }

    // ------------------------------------------------------------------

    private function check(Request $request, bool $update = false): void
    {
        $bid = Ledger::businessId();
        $employeePays = in_array($request->paid_by, ['employee', 'both'], true);
        $employerPays = in_array($request->paid_by, ['employer', 'both'], true);
        $isPercent = (string) $request->tax_type === '1';
        $isFixed = (string) $request->tax_type === '2';
        $request->validate([
            'title' => 'required|string|max:191',
            'tax_group_id' => ['nullable', Rule::exists('tax_groups', 'id')->where('business_id', $bid)],
            'is_dependent' => 'nullable|boolean',
            'depends_on_type' => 'required_if:is_dependent,1|nullable|in:tax_group,tax_setting',
            'depends_on_id' => 'required_if:is_dependent,1|nullable|integer',
            'bracket_order' => 'nullable|integer|min:0',
            'tax_type' => 'required|in:1,2',
            'paid_by' => 'required|in:employee,employer,both',
            'liability_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('business_id', $bid)],
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'percentange' => [Rule::requiredIf($isPercent && $employeePays), 'nullable', 'numeric', 'min:0', 'max:100'],
            'employer_percentage' => [Rule::requiredIf($isPercent && $employerPays), 'nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => [Rule::requiredIf($isFixed && $employeePays), 'nullable', 'numeric'],
            'employer_fixed_amount' => [Rule::requiredIf($isFixed && $employerPays), 'nullable', 'numeric'],
            'max_no_taxable_amount' => 'nullable|numeric',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'status' => $update ? 'required|boolean' : 'nullable|boolean',
        ]);
    }

    /** Bands of one group must not overlap (a shared boundary counts: both would claim that salary). */
    private function overlap(Request $request, ?int $except = null): ?string
    {
        if (! $request->tax_group_id) {
            return null;
        }
        $newMin = (float) $request->min_amount;
        $newMax = (float) $request->max_amount;
        foreach (TaxSetting::where('tax_group_id', $request->tax_group_id)->when($except, fn ($q) => $q->where('id', '!=', $except))->get() as $t) {
            if ($newMin <= $t->max_amount && $newMax >= $t->min_amount) {
                return __('ledger_payroll.err_overlap', ['title' => $t->title ?? '#'.$t->id, 'from' => Ledger::money($t->min_amount), 'to' => Ledger::money($t->max_amount)]);
            }
        }

        return null;
    }

    private function fill(TaxSetting $tax, Request $request): void
    {
        $tax->title = $request->title;
        $tax->tax_group_id = $request->tax_group_id ?: null;
        $tax->is_dependent = (bool) $request->is_dependent;
        $tax->depends_on_type = $request->is_dependent ? $request->depends_on_type : null;
        $tax->depends_on_id = $request->is_dependent ? $request->depends_on_id : null;
        $tax->tax_type = $request->tax_type;
        $tax->paid_by = $request->paid_by;
        $tax->is_shared = $request->paid_by === 'both';
        $tax->liability_account_id = $request->liability_account_id ?: null;
        $tax->min_amount = $request->min_amount;
        $tax->max_amount = $request->max_amount;
        $tax->percentange = $request->tax_type == 1 ? ($request->percentange ?? 0) : 0;
        $tax->employer_percentage = $request->tax_type == 1 ? ($request->employer_percentage ?? 0) : 0;
        $tax->fixed_amount = $request->tax_type == 2 ? ($request->fixed_amount ?? 0) : 0;
        $tax->employer_fixed_amount = $request->tax_type == 2 ? ($request->employer_fixed_amount ?? 0) : 0;
        $tax->max_no_taxable_amount = $request->max_no_taxable_amount ?? 0;
        $tax->effective_from = $request->effective_from ?: null;
        $tax->effective_to = $request->effective_to ?: null;
    }
}
