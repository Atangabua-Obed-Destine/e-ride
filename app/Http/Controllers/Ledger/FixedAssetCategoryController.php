<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\FixedAssetCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fixed asset categories (paxhitestbed Admin\FixedAssetCategoryController):
 * the list, and the form with code, names, method, default useful life and
 * the linked GL accounts.
 *
 * paxhitestbed's form, controller and table disagreed on the field names
 * (code / default_useful_life / expense_account_id on the form,
 * default_useful_life_months / depreciation_expense_account_id in the
 * controller, neither in the table), so no category could be saved from its
 * screen. The same fields are kept, in the same order, each saved to the
 * column the posting reads. Permissions: paxhitestbed's
 * fixed-asset-category-* are the fixed-asset-* ones here.
 */
class FixedAssetCategoryController extends LedgerController
{
    public function index()
    {
        $this->allow('fixed-asset-list');
        $categories = FixedAssetCategory::with(['assetAccount', 'accumulatedDepreciationAccount', 'depreciationAccount'])
            ->withCount('assets')->orderBy('name')->paginate(15);

        return view('ledger.fixed-asset-categories.index', compact('categories'));
    }

    public function create()
    {
        $this->allow('fixed-asset-create');

        return view('ledger.fixed-asset-categories.create', $this->accountLists());
    }

    public function store(Request $request)
    {
        $this->allow('fixed-asset-create');
        $data = $this->validated($request);
        $category = FixedAssetCategory::create($data + ['is_active' => true, 'created_by' => auth()->id()]);

        return $this->ok(route('ledger.fixed-asset-categories.index'), __('ledger_assets.category_created_successfully'));
    }

    public function show($id)
    {
        return redirect()->route('ledger.fixed-asset-categories.edit', $id);
    }

    public function edit($id)
    {
        $this->allow('fixed-asset-edit');
        $category = FixedAssetCategory::findOrFail($id);

        return view('ledger.fixed-asset-categories.edit', ['category' => $category] + $this->accountLists());
    }

    public function update(Request $request, $id)
    {
        $this->allow('fixed-asset-edit');
        $category = FixedAssetCategory::findOrFail($id);
        $data = $this->validated($request, $category);
        $category->update($data + ['is_active' => $request->has('is_active') ? $request->boolean('is_active') : $category->is_active, 'updated_by' => auth()->id()]);

        return $this->ok(route('ledger.fixed-asset-categories.index'), __('ledger_assets.category_updated_successfully'));
    }

    public function destroy($id)
    {
        $this->allow('fixed-asset-delete');
        $category = FixedAssetCategory::findOrFail($id);
        if ($category->assets()->withTrashed()->exists()) {
            return $this->err(route('ledger.fixed-asset-categories.index'), __('ledger_assets.cannot_delete_category_with_assets'));
        }
        $category->delete();

        return $this->ok(route('ledger.fixed-asset-categories.index'), __('ledger_assets.category_deleted_successfully'));
    }

    private function validated(Request $request, ?FixedAssetCategory $category = null): array
    {
        $bid = Ledger::businessId();
        $account = fn (string $prefix) => ['bail', 'integer', function ($attr, $value, $fail) use ($prefix) {
            $ok = ChartOfAccount::postable()->whereKey($value)
                ->where(fn ($q) => collect(explode(',', $prefix))->each(fn ($p) => $q->orWhere('account_code', 'like', $p.'%')))->exists();
            if (! $ok) {
                $fail(__('ledger_assets.account_not_in_class', ['class' => $prefix]));
            }
        }];
        $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('fixed_asset_categories', 'code')->where('business_id', $bid)->whereNull('deleted_at')->ignore($category->id ?? null)],
            'name' => 'required|string|max:255',
            'name_fr' => 'nullable|string|max:255',
            'depreciation_method' => ['required', Rule::in(FixedAssetCategory::METHODS)],
            'default_useful_life' => 'required|integer|min:1|max:100',
            'asset_account_id' => array_merge(['required'], $account('2')),
            'accumulated_depreciation_account_id' => array_merge(['required'], $account('28')),
            'depreciation_account_id' => array_merge(['required'], $account('68')),
            'disposal_account_id' => array_merge(['nullable'], $account('65,75,81,82')),
            'description' => 'nullable|string',
        ], [], [
            'default_useful_life' => __('ledger_assets.default_useful_life'),
            'asset_account_id' => __('ledger_assets.asset_account'),
            'accumulated_depreciation_account_id' => __('ledger_assets.accumulated_depreciation_account'),
            'depreciation_account_id' => __('ledger_assets.depreciation_expense_account'),
            'disposal_account_id' => __('ledger_assets.disposal_gain_loss_account'),
        ]);
        if (str_starts_with((string) ChartOfAccount::whereKey($request->asset_account_id)->value('account_code'), '28')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['asset_account_id' => __('ledger_assets.asset_account_not_28')]);
        }

        return [
            'code' => $request->code,
            'name' => $request->name,
            'name_fr' => $request->name_fr ?: $request->name,
            'description' => $request->description,
            'depreciation_method' => $request->depreciation_method,
            'useful_life_years' => (int) $request->default_useful_life,
            'asset_account_id' => (int) $request->asset_account_id,
            'accumulated_depreciation_account_id' => (int) $request->accumulated_depreciation_account_id,
            'depreciation_account_id' => (int) $request->depreciation_account_id,
            'disposal_account_id' => $request->disposal_account_id ? (int) $request->disposal_account_id : null,
        ];
    }

    /** The account lists of the form: detail accounts of this business, by code. */
    private function accountLists(): array
    {
        $list = fn (array $prefixes, array $not = []) => ChartOfAccount::postable()
            ->where(fn ($q) => collect($prefixes)->each(fn ($p) => $q->orWhere('account_code', 'like', $p.'%')))
            ->when($not, function ($q) use ($not) { foreach ($not as $p) { $q->where('account_code', 'not like', $p.'%'); } })
            ->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_name_fr']);

        return [
            'assetAccounts' => $list(['2'], ['28', '29']),
            'accumulatedDepreciationAccounts' => $list(['28']),
            'depreciationExpenseAccounts' => $list(['68']),
            'disposalAccounts' => $list(['65', '75', '81', '82']),
            'methods' => FixedAssetCategory::METHODS,
        ];
    }
}
