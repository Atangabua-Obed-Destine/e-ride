<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Http\Controllers\Ledger\LedgerController;
use App\Ledger\Ledger;
use App\Ledger\Models\TaxGroup;
use App\Ledger\Models\TaxSetting;
use App\Ledger\Payroll\TaxDefaults;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * paxhitestbed Admin\TaxGroupController (permissions shared with tax-setting-*): the groups, a group
 * with its bands, and adding / editing / removing a band (no overlapping ranges within a group).
 */
class TaxGroupController extends LedgerController
{
    private function base(): array
    {
        return ['title' => __('ledger_payroll.tax_group'), 'route' => 'ledger.tax-group', 'access' => 'tax-setting'];
    }

    public function index()
    {
        $this->allow('tax-setting-view', 'tax-setting-create', 'tax-setting-edit', 'tax-setting-delete');
        TaxDefaults::ensure();

        return view('ledger.payroll.tax-group.index', $this->base() + ['title' => __('ledger_payroll.tax_groups'),
            'rows' => TaxGroup::with(['brackets', 'liabilityAccount'])->ordered()->get()]);
    }

    public function create()
    {
        $this->allow('tax-setting-create');

        return view('ledger.payroll.tax-group.form', $this->base() + ['row' => null, 'accounts' => TaxSettingController::liabilityAccounts()]);
    }

    public function store(Request $request)
    {
        $this->allow('tax-setting-create');
        $this->check($request);
        $group = TaxGroup::create($this->values($request) + ['status' => 1,
            'display_order' => $request->display_order ?? ((TaxGroup::max('display_order') ?? 0) + 1)]);

        return $this->ok(route('ledger.tax-group.show', $group->id), __('ledger_payroll.created'));
    }

    public function show($id)
    {
        $this->allow('tax-setting-view', 'tax-setting-create', 'tax-setting-edit', 'tax-setting-delete');
        $row = TaxGroup::with(['brackets' => fn ($q) => $q->orderBy('bracket_order')->orderBy('min_amount'), 'liabilityAccount'])->findOrFail($id);

        return view('ledger.payroll.tax-group.show', $this->base() + ['row' => $row, 'coverage' => $row->coverageGaps()]);
    }

    public function edit($id)
    {
        $this->allow('tax-setting-edit');

        return view('ledger.payroll.tax-group.form', $this->base() + ['row' => TaxGroup::findOrFail($id), 'accounts' => TaxSettingController::liabilityAccounts()]);
    }

    public function update(Request $request, $id)
    {
        $this->allow('tax-setting-edit');
        $group = TaxGroup::findOrFail($id);
        $this->check($request, true);
        $group->update($this->values($request) + ['display_order' => $request->display_order ?? $group->display_order, 'status' => $request->boolean('status')]);

        return $this->ok(route('ledger.tax-group.show', $group->id), __('ledger_payroll.updated'));
    }

    public function destroy($id)
    {
        $this->allow('tax-setting-delete');
        $group = TaxGroup::findOrFail($id);
        if (\App\Ledger\Models\PayrollTaxLine::where('tax_group_id', $group->id)->exists()) {
            $group->forceFill(['status' => 0])->save();

            return $this->ok(route('ledger.tax-group.index'), __('ledger_payroll.switched_off_in_use'));
        }
        TaxSetting::where('tax_group_id', $group->id)->delete();
        $group->delete();

        return $this->ok(route('ledger.tax-group.index'), __('ledger_payroll.deleted'));
    }

    public function storeBracket(Request $request, $id)
    {
        $this->allow('tax-setting-create', 'tax-setting-edit');
        $group = TaxGroup::findOrFail($id);
        $this->checkBracket($request);
        if ($this->overlaps($group->id, $request)) {
            return $this->err(back(), __('ledger_payroll.bracket_range_overlap'));
        }
        TaxSetting::create($this->bracketValues($request) + ['tax_group_id' => $group->id, 'status' => 1,
            'bracket_order' => $request->bracket_order ?? ((TaxSetting::where('tax_group_id', $group->id)->max('bracket_order') ?? 0) + 1)]);

        return $this->ok(route('ledger.tax-group.show', $group->id), __('ledger_payroll.bracket_added'));
    }

    public function updateBracket(Request $request, $groupId, $bracketId)
    {
        $this->allow('tax-setting-edit');
        $group = TaxGroup::findOrFail($groupId);
        $bracket = TaxSetting::where('tax_group_id', $group->id)->findOrFail($bracketId);
        $this->checkBracket($request, true);
        if ($this->overlaps($group->id, $request, $bracket->id)) {
            return $this->err(back(), __('ledger_payroll.bracket_range_overlap'));
        }
        $bracket->update($this->bracketValues($request) + ['bracket_order' => $request->bracket_order ?? $bracket->bracket_order, 'status' => $request->boolean('status')]);

        return $this->ok(route('ledger.tax-group.show', $group->id), __('ledger_payroll.updated'));
    }

    public function destroyBracket($groupId, $bracketId)
    {
        $this->allow('tax-setting-delete');
        $bracket = TaxSetting::where('tax_group_id', $groupId)->findOrFail($bracketId);
        if (\App\Ledger\Models\PayrollTaxLine::where('tax_setting_id', $bracket->id)->exists()) {
            $bracket->forceFill(['status' => 0])->save();

            return $this->ok(back(), __('ledger_payroll.switched_off_in_use'));
        }
        $bracket->delete();

        return $this->ok(route('ledger.tax-group.show', $groupId), __('ledger_payroll.deleted'));
    }

    // ------------------------------------------------------------------

    private function check(Request $request, bool $update = false): void
    {
        $request->validate([
            'title' => 'required|string|max:191',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_progressive' => 'required|boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'display_order' => 'nullable|integer|min:0',
            'liability_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('business_id', Ledger::businessId())],
            'status' => $update ? 'required|boolean' : 'nullable|boolean',
        ]);
    }

    private function values(Request $request): array
    {
        return ['title' => $request->title, 'code' => $request->code, 'description' => $request->description, 'is_progressive' => $request->boolean('is_progressive'),
            'effective_from' => $request->effective_from ?: null, 'effective_to' => $request->effective_to ?: null,
            'liability_account_id' => $request->liability_account_id ?: null];
    }

    private function checkBracket(Request $request, bool $update = false): void
    {
        $request->validate([
            'title' => 'required|string|max:191',
            'bracket_order' => 'nullable|integer|min:0',
            'tax_type' => 'required|in:1,2',
            'paid_by' => 'nullable|in:employee,employer,both',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'percentange' => 'required_if:tax_type,1|nullable|numeric|min:0|max:100',
            'employer_percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'required_if:tax_type,2|nullable|numeric|min:0',
            'employer_fixed_amount' => 'nullable|numeric|min:0',
            'max_no_taxable_amount' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'status' => $update ? 'required|boolean' : 'nullable|boolean',
        ]);
    }

    private function bracketValues(Request $request): array
    {
        $paidBy = $request->paid_by ?: 'employee';

        return [
            'title' => $request->title,
            'tax_type' => $request->tax_type,
            'paid_by' => $paidBy,
            'is_shared' => $paidBy === 'both',
            'min_amount' => $request->min_amount,
            'max_amount' => $request->max_amount,
            'percentange' => $request->tax_type == 1 ? ($request->percentange ?? 0) : 0,
            'employer_percentage' => $request->tax_type == 1 ? ($request->employer_percentage ?? 0) : 0,
            'fixed_amount' => $request->tax_type == 2 ? ($request->fixed_amount ?? 0) : 0,
            'employer_fixed_amount' => $request->tax_type == 2 ? ($request->employer_fixed_amount ?? 0) : 0,
            'max_no_taxable_amount' => $request->max_no_taxable_amount ?? 0,
            'effective_from' => $request->effective_from ?: null,
            'effective_to' => $request->effective_to ?: null,
        ];
    }

    private function overlaps(int $groupId, Request $request, ?int $except = null): bool
    {
        $min = (float) $request->min_amount;
        $max = (float) $request->max_amount;

        return TaxSetting::where('tax_group_id', $groupId)->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->where('min_amount', '<=', $max)->where('max_amount', '>=', $min)->exists();
    }
}
