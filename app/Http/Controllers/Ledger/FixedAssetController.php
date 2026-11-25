<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Exports\LedgerRowsExport;
use App\Ledger\Ledger;
use App\Ledger\Models\DepreciationSchedule;
use App\Ledger\Models\FiscalYear;
use App\Ledger\Models\FixedAsset;
use App\Ledger\Models\FixedAssetCategory;
use App\Ledger\Services\DepreciationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Fixed assets (paxhitestbed Admin\FixedAssetController): the asset list with
 * the month's depreciation actions and the dispose dialog, the asset form,
 * the asset page, the asset report (register), the depreciation report and
 * the summary by category. Same screens, fields and buttons.
 *
 * PosInnova differences: one business's assets (BelongsToBusiness); no
 * departments (the register filters by location, as the list does);
 * "Paid into" lists the business's payment accounts that have a ledger
 * account; the purchase can be posted from the asset page; permissions are
 * fixed-asset-list / create / edit / delete (paxhitestbed's
 * fixed-asset-depreciation and fixed-asset-dispose are fixed-asset-edit here).
 *
 * Every posting (the month's depreciation, a disposal, the purchase) goes
 * through the review dialog: the routes carry ledger.review.
 */
class FixedAssetController extends LedgerController
{
    private function service(): DepreciationService
    {
        return app(DepreciationService::class);
    }

    public function index(Request $request)
    {
        $this->allow('fixed-asset-list');
        $query = FixedAsset::with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // paxhitestbed's screen has a location filter its controller ignored
        if ($request->filled('location')) {
            $query->where('location', 'like', '%'.$request->location.'%');
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('asset_code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")->orWhere('serial_number', 'like', "%{$s}%"));
        }

        $assets = $query->orderBy('asset_code')->paginate(20);
        $held = FixedAsset::where('status', '!=', FixedAsset::STATUS_DISPOSED);

        // The money figures are of the assets still held: a disposed asset has left the books.
        $statistics = [
            'total_assets' => FixedAsset::count(),
            'active_assets' => FixedAsset::active()->count(),
            'total_cost' => (clone $held)->sum('acquisition_cost'),
            'total_depreciation' => (clone $held)->sum('accumulated_depreciation'),
            'total_book_value' => (clone $held)->sum('book_value'),
        ];

        return view('ledger.fixed-assets.index', [
            'assets' => $assets,
            'categories' => FixedAssetCategory::active()->orderBy('name')->get(),
            'statistics' => $statistics,
            'paymentAccounts' => DepreciationService::paymentAccounts(),
        ]);
    }

    public function create()
    {
        $this->allow('fixed-asset-create');

        return view('ledger.fixed-assets.create', [
            'fleet' => \App\Ledger\ERide\Books::fleet(), // E-Ride: a bus of the fleet
            'categories' => FixedAssetCategory::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->allow('fixed-asset-create');
        $bid = Ledger::businessId();
        // the form sends years; months are still accepted
        if (! $request->filled('useful_life_months') && $request->filled('useful_life_years')) {
            $request->merge(['useful_life_months' => (int) round((float) $request->useful_life_years * 12)]);
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'name_fr' => 'nullable|string|max:255',
            'category_id' => ['required', Rule::exists('fixed_asset_categories', 'id')->where('business_id', $bid)->whereNull('deleted_at')],
            'serial_number' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'acquisition_date' => 'required|date',
            'acquisition_cost' => 'required|numeric|gt:0',
            'salvage_value' => 'nullable|numeric|min:0|lt:acquisition_cost',
            'useful_life_years' => 'required_without:useful_life_months|nullable|numeric|min:1',
            'useful_life_months' => 'required|integer|min:1',
            'depreciation_method' => ['required', Rule::in(array_merge(FixedAssetCategory::METHODS, ['none']))],
            'depreciation_start_date' => 'nullable|date|after_or_equal:acquisition_date',
            'vehicle_id' => ['nullable', Rule::in(array_keys(\App\Ledger\ERide\Books::fleet()))], // E-Ride: a bus of the fleet
            'vendor' => 'nullable|string|max:255',
            'warranty_expiry_date' => 'nullable|date',
            'insurance_value' => 'nullable|numeric|min:0',
            'insurance_policy_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $asset = DB::transaction(fn () => FixedAsset::create([
                'vehicle_id' => $request->vehicle_id ?: null, // E-Ride
                'name' => $request->name,
                'name_fr' => $request->name_fr ?: $request->name,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'serial_number' => $request->serial_number,
                'model' => $request->model,
                'manufacturer' => $request->manufacturer,
                'location' => $request->location,
                'vendor' => $request->vendor,
                'acquisition_date' => $request->acquisition_date,
                'acquisition_cost' => $request->acquisition_cost,
                'salvage_value' => $request->salvage_value ?: 0,
                'useful_life_months' => $request->useful_life_months,
                'depreciation_method' => $request->depreciation_method,
                'declining_balance_rate' => $request->declining_balance_rate ?: null,
                // "Leave blank to start from acquisition"
                'depreciation_start_date' => $request->depreciation_start_date ?: $request->acquisition_date,
                'accumulated_depreciation' => 0,
                'book_value' => $request->acquisition_cost,
                'status' => FixedAsset::STATUS_ACTIVE,
                'warranty_expiry' => $request->warranty_expiry_date ?: null,
                'insurance_value' => $request->insurance_value ?: null,
                'insurance_policy_number' => $request->insurance_policy_number,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]));
        } catch (\Throwable $e) {
            Log::error('Error creating fixed asset: '.$e->getMessage());

            return $this->err(back(), __('ledger_assets.error_creating_asset').': '.$e->getMessage());
        }

        return $this->ok(route('ledger.fixed-assets.show', $asset->id), __('ledger_assets.asset_created_successfully'));
    }

    public function show($id)
    {
        $this->allow('fixed-asset-list');
        $asset = FixedAsset::with(['category.assetAccount', 'depreciationSchedules.journalEntry', 'purchaseJournalEntry', 'disposalJournalEntry'])->findOrFail($id);

        return view('ledger.fixed-assets.show', [
            'asset' => $asset,
            'paymentAccounts' => DepreciationService::paymentAccounts(),
        ]);
    }

    public function edit($id)
    {
        $this->allow('fixed-asset-edit');
        $asset = FixedAsset::findOrFail($id);
        if ($asset->status === FixedAsset::STATUS_DISPOSED) {
            return $this->err(route('ledger.fixed-assets.show', $asset->id), __('ledger_assets.disposed_locked'));
        }

        return view('ledger.fixed-assets.edit', [
            'asset' => $asset,
            'categories' => FixedAssetCategory::active()->orWhere('id', $asset->category_id)->orderBy('name')->get(),
            'inLedger' => $asset->isInLedger(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->allow('fixed-asset-edit');
        $asset = FixedAsset::findOrFail($id);
        if ($asset->status === FixedAsset::STATUS_DISPOSED) {
            return $this->err(route('ledger.fixed-assets.show', $asset->id), __('ledger_assets.disposed_locked'));
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'name_fr' => 'nullable|string|max:255',
            'category_id' => ['required', Rule::exists('fixed_asset_categories', 'id')->where('business_id', Ledger::businessId())->whereNull('deleted_at')],
            'serial_number' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'salvage_value' => 'nullable|numeric|min:0|lt:'.(float) $asset->acquisition_cost,
            'vendor' => 'nullable|string|max:255',
            'warranty_expiry_date' => 'nullable|date',
            'insurance_value' => 'nullable|numeric|min:0',
            'insurance_policy_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $inLedger = $asset->isInLedger();
        // Once its figures are in the ledger, the category (its accounts) stays: the
        // disposal must credit the account the purchase and depreciation went to.
        if ($inLedger && (int) $request->category_id !== (int) $asset->category_id) {
            return $this->err(back(), __('ledger_assets.category_locked'));
        }

        $values = [
            'name' => $request->name,
            'name_fr' => $request->name_fr ?: $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'serial_number' => $request->serial_number,
            'location' => $request->location,
            'vendor' => $request->vendor,
            'warranty_expiry' => $request->warranty_expiry_date ?: null,
            'insurance_value' => $request->insurance_value ?: null,
            'insurance_policy_number' => $request->insurance_policy_number,
            'updated_by' => auth()->id(),
        ];
        // the salvage value changes the monthly charge: only before anything is charged
        if ((float) $asset->accumulated_depreciation == 0 && $request->has('salvage_value')) {
            $values['salvage_value'] = $request->salvage_value ?: 0;
        }
        $asset->update($values);

        return $this->ok(route('ledger.fixed-assets.show', $asset->id), __('ledger_assets.asset_updated_successfully'));
    }

    public function destroy($id)
    {
        $this->allow('fixed-asset-delete');
        $asset = FixedAsset::findOrFail($id);

        // A disposed asset, or one whose figures never reached the ledger (paxhitestbed's
        // list offered Delete for an untouched asset its controller then refused).
        if ($asset->status !== FixedAsset::STATUS_DISPOSED && $asset->isInLedger()) {
            return $this->err(route('ledger.fixed-assets.index'), __('ledger_assets.dispose_asset_before_deleting'));
        }
        DB::transaction(function () use ($asset) {
            DepreciationSchedule::where('fixed_asset_id', $asset->id)->where('status', DepreciationSchedule::STATUS_PENDING)->delete();
            $asset->delete();
        });

        return $this->ok(route('ledger.fixed-assets.index'), __('ledger_assets.asset_deleted_successfully'));
    }

    public function dispose(Request $request, $id)
    {
        $this->allow('fixed-asset-edit');
        $asset = FixedAsset::findOrFail($id);
        // The screen's form sends disposal_proceeds; disposal_value is still accepted.
        $request->merge(['disposal_value' => $request->input('disposal_value', $request->input('disposal_proceeds', 0)) ?: 0]);
        $request->validate([
            'disposal_date' => 'required|date',
            'disposal_value' => 'required|numeric|min:0',
            'disposal_method' => ['nullable', Rule::in(FixedAsset::DISPOSAL_METHODS)],
            'disposal_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
            'payment_account_id' => ['nullable', Rule::exists('payment_accounts', 'id')->where('business_id', Ledger::businessId())],
        ]);
        $reason = $request->disposal_reason
            ?? trim(($request->disposal_method ? __('ledger_assets.'.$request->disposal_method) : '').($request->notes ? ' — '.$request->notes : ''), ' —');

        try {
            $entry = $this->service()->disposeAsset($asset, $request->disposal_date, (float) $request->disposal_value, $reason ?: null,
                $request->filled('payment_account_id') ? (int) $request->payment_account_id : null, $request->disposal_method, auth()->id());
        } catch (\Throwable $e) {
            return $this->err(route('ledger.fixed-assets.index'), $e->getMessage());
        }

        return $this->ok(route('ledger.fixed-assets.index'), __('ledger_assets.asset_disposed_msg', ['number' => $entry->entry_number]));
    }

    /** Record the purchase in the ledger (Dr 2x, Cr the cash box or bank, or 401). */
    public function postPurchase(Request $request, $id)
    {
        $this->allow('fixed-asset-edit');
        $asset = FixedAsset::findOrFail($id);
        $request->validate(['paid_from' => 'required|alpha_num']);
        $show = route('ledger.fixed-assets.show', $asset->id);

        try {
            $entry = $this->service()->postPurchase($asset, $request->paid_from === 'supplier' ? 'supplier' : (int) $request->paid_from, auth()->id());
        } catch (\Throwable $e) {
            return $this->err($show, $e->getMessage());
        }

        return $this->ok($show, __('ledger_assets.purchase_posted', ['number' => $entry->entry_number]));
    }

    /** Calculate the month's depreciation (pending, not posted). */
    public function calculateDepreciation(Request $request)
    {
        $this->allow('fixed-asset-edit');
        $request->validate(['depreciation_date' => 'nullable|date', 'month' => 'nullable|date_format:Y-m', 'auto_post' => 'nullable|boolean']);

        return $this->runDepreciation($request, $request->boolean('auto_post'));
    }

    /**
     * Charge the month's depreciation and post it: one action on the screen.
     * Assets already charged for the month are left alone; months waiting to
     * be posted are posted.
     */
    public function postDepreciation(Request $request)
    {
        $this->allow('fixed-asset-edit');
        $request->validate(['depreciation_date' => 'nullable|date', 'month' => 'nullable|date_format:Y-m']);

        return $this->runDepreciation($request, true);
    }

    private function runDepreciation(Request $request, bool $post)
    {
        $date = $request->filled('depreciation_date')
            ? Carbon::parse($request->depreciation_date)->endOfMonth()
            : ($request->filled('month') ? Carbon::createFromFormat('!Y-m', $request->month)->endOfMonth() : now()->endOfMonth());
        // An ordinary form gets the screen back, never JSON (paxhitestbed's users saw raw JSON).
        $json = $request->ajax() && $request->wantsJson();

        try {
            $results = DB::transaction(function () use ($date, $post) {
                $results = $this->service()->calculateMonthlyDepreciation($date, $post, auth()->id());
                if ($post && $results['errors']) {
                    // a month is charged whole or not at all
                    throw new \RuntimeException(collect($results['errors'])->map(fn ($e) => ($e['asset_code'] ?? '').': '.($e['error'] ?? ''))->implode('; '));
                }

                return $results;
            });
        } catch (\Throwable $e) {
            if ($json) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return $this->err(route('ledger.fixed-assets.index'), $e->getMessage());
        }

        if ($json) {
            return response()->json(['success' => true, 'data' => collect($results)->except('schedules')]);
        }

        $month = $date->translatedFormat('F Y');
        $message = $post
            ? trans_choice('ledger_assets.run_posted', $results['posted'], ['count' => $results['posted'], 'month' => $month])
            : trans_choice('ledger_assets.run_calculated', $results['processed'], ['count' => $results['processed'], 'month' => $month]);
        if (! $post && $results['errors']) {
            $message .= ' '.__('ledger_assets.run_some_failed', ['list' => collect($results['errors'])->map(fn ($e) => $e['asset_code'].': '.$e['error'])->implode('; ')]);
        }

        return $this->ok(route('ledger.fixed-assets.index'), $message);
    }

    /** Asset report (the register), with its Excel export. */
    public function assetRegister(Request $request)
    {
        $this->allow('fixed-asset-list');
        $assets = $this->service()->getAssetRegister($request->all());
        $totals = [
            'acquisition_cost' => $assets->sum('acquisition_cost'),
            'accumulated_depreciation' => $assets->sum('accumulated_depreciation'),
            'book_value' => $assets->sum('book_value'),
        ];

        if ($request->input('export') === 'excel') {
            $rows = [[__('ledger_assets.asset_code'), __('ledger_assets.asset_name'), __('ledger_assets.category'), __('ledger_assets.acquisition_date'),
                __('ledger_assets.acquisition_cost'), __('ledger_assets.accumulated_depreciation'), __('ledger_assets.net_book_value'), __('ledger_assets.status_title')]];
            foreach ($assets as $a) {
                $rows[] = [$a->asset_code, $a->name, $a->category->name ?? '', $a->acquisition_date->format('d/m/Y'),
                    round((float) $a->acquisition_cost, 2), round((float) $a->accumulated_depreciation, 2), round((float) $a->book_value, 2), $a->getStatusLabel()];
            }
            $rows[] = [__('ledger_assets.total'), '', '', '', round((float) $totals['acquisition_cost'], 2), round((float) $totals['accumulated_depreciation'], 2), round((float) $totals['book_value'], 2), ''];
            $export = new class($rows) extends LedgerRowsExport {
                public function __construct(array $rows) { $this->rows = $rows; }
                public function title(): string { return __('ledger_assets.asset_register'); }
            };

            return \Maatwebsite\Excel\Facades\Excel::download($export, 'asset-register-'.now()->format('Y-m-d').'.xlsx');
        }

        return view('ledger.fixed-assets.register', [
            'assets' => $assets,
            'categories' => FixedAssetCategory::active()->orderBy('name')->get(),
            'totals' => $totals,
        ]);
    }

    public function depreciationScheduleReport(Request $request)
    {
        $this->allow('fixed-asset-list');
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
        // The year list has no "all": with nothing chosen, the year of today (what the list shows).
        $filters = $request->all();
        if (! $request->has('fiscal_year_id')) {
            $filters['fiscal_year_id'] = optional(FiscalYear::forDate(now()->toDateString()) ?? $fiscalYears->first())->id;
        }
        $schedules = $this->service()->getDepreciationScheduleReport($filters);

        return view('ledger.fixed-assets.depreciation-report', [
            'schedules' => $schedules,
            'fiscalYears' => $fiscalYears,
            'fiscalYearId' => $filters['fiscal_year_id'] ?? null,
            'categories' => FixedAssetCategory::active()->orderBy('name')->get(),
            'totals' => [
                'total_depreciation' => $schedules->where('status', '!=', DepreciationSchedule::STATUS_REVERSED)->sum('depreciation_amount'),
                'pending_count' => $schedules->where('status', DepreciationSchedule::STATUS_PENDING)->count(),
                'posted_count' => $schedules->where('status', DepreciationSchedule::STATUS_POSTED)->count(),
            ],
        ]);
    }

    public function depreciationSummary(Request $request)
    {
        $this->allow('fixed-asset-list');
        $fiscalYearId = $request->fiscal_year_id ?? optional(FiscalYear::forDate(now()->toDateString()) ?? FiscalYear::getActiveFiscalYear())->id;
        if (! $fiscalYearId) {
            return $this->err(route('ledger.fixed-assets.index'), __('ledger_assets.no_active_fiscal_year'));
        }
        $current = FiscalYear::findOrFail($fiscalYearId);

        return view('ledger.fixed-assets.depreciation-summary', [
            'summary' => $this->service()->getSummaryByCategory($current->id),
            'fiscalYears' => FiscalYear::orderBy('start_date', 'desc')->get(),
            'currentFiscalYear' => $current,
        ]);
    }
}
