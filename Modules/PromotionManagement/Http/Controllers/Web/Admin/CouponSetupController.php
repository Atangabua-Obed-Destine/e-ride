<?php

namespace Modules\PromotionManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\AdminModule\Service\Interfaces\ActivityLogServiceInterface;
use Modules\PromotionManagement\Entities\CouponSetup;
use Modules\PromotionManagement\Http\Requests\CouponSetupStoreUpdateRequest;
use Modules\PromotionManagement\Service\Interfaces\CouponSetupServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\UserManagement\Service\Interfaces\CustomerLevelServiceInterface;
use Modules\UserManagement\Service\Interfaces\CustomerServiceInterface;
use Modules\VehicleManagement\Service\Interfaces\VehicleCategoryServiceInterface;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CouponSetupController extends BaseController
{
    use AuthorizesRequests;

    protected $couponSetupService;
    protected $tripRequestService;
    protected $zoneService;
    protected $customerLevelService;
    protected $customerService;
    protected $vehicleCategoryService;
    protected $activityLogService;

    public function __construct(CouponSetupServiceInterface $couponSetupService, TripRequestServiceInterface $tripRequestService,
                                ZoneServiceInterface        $zoneService, CustomerLevelServiceInterface $customerLevelService,
                                CustomerServiceInterface    $customerService, VehicleCategoryServiceInterface $vehicleCategoryService,
                                ActivityLogServiceInterface $activityLogService)
    {
        parent::__construct($couponSetupService);
        $this->couponSetupService = $couponSetupService;
        $this->tripRequestService = $tripRequestService;
        $this->zoneService = $zoneService;
        $this->customerLevelService = $customerLevelService;
        $this->customerService = $customerService;
        $this->vehicleCategoryService = $vehicleCategoryService;
        $this->activityLogService = $activityLogService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('promotion_view');
        if (Schema::hasColumns('coupon_setups', ['user_id', 'user_level_id', 'rules'])) {
            $couponSetups = $this->couponSetupService->getBy(withTrashed: true,);
            DB::beginTransaction();
            if (count((array)$couponSetups) > 0) {
                foreach ($couponSetups as $couponSetup) {
                    $this->couponSetupService->updatedBy(criteria: ['id' => $couponSetup->id], data: ['zone_coupon_type' => ALL]);
                    if ($couponSetup->user_id == ALL) {
                        $this->couponSetupService->updatedBy(criteria: ['id' => $couponSetup->id], data: ['customer_coupon_type' => ALL]);
                    } else {
                        $this->couponSetupService->updatedBy(criteria: ['id' => $couponSetup->id], data: ['customer_coupon_type' => CUSTOM]);
                        $couponSetup?->customers()->attach($couponSetup->user_id);
                    }
                    if ($couponSetup->user_level_id == ALL || $couponSetup->user_level_id == null) {
                        $this->couponSetupService->updatedBy(criteria: ['id' => $couponSetup->id], data: ['customer_level_coupon_type' => ALL]);
                    } else {
                        $this->couponSetupService->updatedBy(criteria: ['id' => $couponSetup->id], data: ['customer_level_coupon_type' => CUSTOM]);
                        $couponSetup?->customerLevels()->attach($couponSetup->user_level_id);
                    }
                    if ($couponSetup->rules == "default") {
                        $this->couponSetupService->updatedBy(criteria: ['id' => $couponSetup->id], data: ['category_coupon_type' => [ALL]]);
                    } else {
                        $this->couponSetupService->updatedBy(criteria: ['id' => $couponSetup->id], data: ['category_coupon_type' => CUSTOM]);
                    }
                }

            }
            DB::commit();
            Schema::table('coupon_setups', function (Blueprint $table) {
                $table->dropColumn(['user_id', 'user_level_id', 'rules']); // Replace 'column_name' with the actual column name
            });
        }

        $dateRange = $request->query('date_range');
        $data = $request?->date_range;
        $this->couponSetupService->updatedBy(criteria: [['end_date', '<', Carbon::today()]], data: ['is_active' => false]);
        $cardValues = $this->couponSetupService->getCardValues($data);
        $analytics = $this->tripRequestService->getAnalytics($data);
        $coupons = $this->couponSetupService->index(criteria: $request?->all(), orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1);

        return view('promotionmanagement::admin.coupon-setup.index', [
            'coupons' => $coupons,
            'cardValues' => $cardValues,
            'label' => $analytics[0],
            'data' => $analytics[1],
            'dateRangeValue' => $dateRange
        ]);
    }

    public function create(): Renderable
    {
        $this->authorize('promotion_add');
        $zones = $this->zoneService->getAll();
        $levels = $this->customerLevelService->getBy(criteria: ['user_type' => CUSTOMER]);
        $vehicleCategories = $this->vehicleCategoryService->getAll();
        return view('promotionmanagement::admin.coupon-setup.create', compact('zones', 'levels', 'vehicleCategories'));
    }

    public function store(CouponSetupStoreUpdateRequest $request): RedirectResponse
    {
        $this->authorize('promotion_add');
        $this->couponSetupService->create(data: $request->validated());
        Toastr::success(COUPON_STORE_200['message']);
        return redirect()->route('admin.promotion.coupon-setup.index');
    }

    public function edit(string $id): Renderable
    {
        $this->authorize('promotion_edit');
        $zones = $this->zoneService->getAll();
        $levels = $this->customerLevelService->getBy(criteria: ['user_type' => CUSTOMER]);
        $vehicleCategories = $this->vehicleCategoryService->getAll();
        $relations = ['vehicleCategories', 'zones', 'customers', 'customerLevels'];
        $coupon = $this->couponSetupService->findOne(id: $id, relations: $relations);
        if ($coupon?->customer_level_coupon_type == ALL) {
            $customers = $this->customerService->getBy(criteria: ['user_type' => CUSTOMER]);
        } else {
            $customers = $this->customerService->getBy(criteria: ['user_type' => CUSTOMER], whereInCriteria: ['user_level_id' => $coupon?->customerLevels->pluck('id')]);
        }
        return view('promotionmanagement::admin.coupon-setup.edit', compact('coupon','zones', 'levels', 'vehicleCategories', 'customers'));
    }

    public function update(CouponSetupStoreUpdateRequest $request, $id)
    {
        $this->authorize('promotion_edit');
        $this->couponSetupService->update(id: $id, data: $request->validated());
        Toastr::success(COUPON_UPDATE_200['message']);
        return redirect()->route('admin.promotion.coupon-setup.index');
    }

    public function destroy($id)
    {
        $this->authorize('promotion_view');
        $this->couponSetupService->delete(id: $id);
        Toastr::success(COUPON_DESTROY_200['message']);
        return back();
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorize('promotion_edit');
        $request->validate([
            'status' => 'boolean'
        ]);
        $model = $this->couponSetupService->statusChange(id: $request->id, data: $request->all());
        return response()->json($model);
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('promotion_export');
        $coupon = $this->couponSetupService->index(criteria: $request->all(), orderBy: ['created_at' => 'desc']);

        $date = Carbon::now()->startOfDay();


        $data = $coupon->map(function ($item) use ($date) {

            if ($date->gt($item['end_date'])) {
                $couponStatus = ucwords(EXPIRED);
            } elseif (!$item['is_active']) {
                $couponStatus = ucwords(CURRENTLY_OFF);
            } elseif ($date->lt($item['start_date'])) {
                $couponStatus = ucwords(UPCOMING);
            } elseif ($date->lte($item['end_date'])) {
                $couponStatus = ucwords(RUNNING);
            } else {
                $couponStatus = ucwords(UPCOMING);
            }

            return [
                'id' => $item['id'],
                'Name' => $item['name'],
                'Description' => $item['description'],
                'Zone' => $item['zone_coupon_type'] ?? '-',
                'Level' => $item['customer_level_coupon_type'] ?? '-',
                'Customer' => $item['customer_coupon_type'] ?? '-',
                'Category' => implode(',',$item['category_coupon_type']) ?? '-',
                'Min Trip Amount' => getCurrencyFormat($item['min_trip_amount'] ?? 0),
                "Max Coupon Amount" => getCurrencyFormat($item['max_coupon_amount'] ?? 0),
                "Coupon" => getCurrencyFormat($item['coupon'] ?? 0),
                "Amount Type" => ucwords($item['amount_type']),
                "Coupon Type" => ucwords($item['coupon_type']),
                "Coupon Code" => $item['coupon_code'],
                "Limit" => $item['limit'],
                "Start Date" => $item['start_date'],
                "End Date" => $item['end_date'],
                "Total Used" => $item['total_used'],
                "Total Amount" => getCurrencyFormat($item['total_amount'] ?? 0),
                "Duration In Days" => $item['start_date'] && $item['end_date'] ? Carbon::parse($item['end_date'])->diffInDays($item['start_date'])+1 . ' days' : '-',
                "Avg Amount" => set_currency_symbol(round($item['total_used'] > 0 ? ($item['total_amount'] / $item['total_used']) : 0, 2)),
                "Coupon Status" => $couponStatus,
                "Active Status" => $item['is_active'] == 1 ? "Active" : "Inactive",
            ];
        });
        $config = styledExportConfig(
            $data,
            title: 'Coupon Setup',
            summary: [
                'Total Coupons'    => $data->count(),
                'Active Coupons'   => $data->where('Active Status', 'Active')->count(),
                'Inactive Coupons' => $data->where('Active Status', 'Inactive')->count(),
            ],
            filters: [
                'Status' => $request->status ?? translate('All'),
                'Search' => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Min Trip Amount'    => ColumnFormat::CURRENCY,
                'Max Coupon Amount'  => ColumnFormat::CURRENCY,
                'Coupon'             => ColumnFormat::CURRENCY,
                'Limit'              => ColumnFormat::INTEGER,
                'Start Date'         => ColumnFormat::DATE,
                'End Date'           => ColumnFormat::DATE,
                'Total Used'         => ColumnFormat::INTEGER,
                'Total Amount'       => ColumnFormat::CURRENCY,
                'Avg Amount'         => ColumnFormat::CURRENCY,
                'Coupon Status'      => ColumnFormat::STATUS,
                'Active Status'      => ColumnFormat::STATUS,
            ],
            fileName: 'coupons-' . time() . '.xlsx',
            headings: ['Name', 'Description', 'Zone', 'Level', 'Customer', 'Category', 'Min Trip Amount', 'Max Coupon Amount', 'Coupon', 'Amount Type', 'Coupon Type', 'Coupon Code', 'Limit', 'Start Date', 'End Date', 'Total Used', 'Total Amount', 'Duration In Days', 'Avg Amount', 'Coupon Status', 'Active Status'],
        );
        return exportData($data, $request['file'], 'promotionmanagement::admin.coupon-setup.print', $config);
    }

    public function log(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('promotion_log');
        $request->merge(['logable_type' => CouponSetup::class]);
        $logs = $this->activityLogService->log($request->all());
        $file = array_key_exists('file', $request->all()) ? $request['file'] : '';
        return logViewerNew($logs,$file);
    }

    public function trashed(Request $request): View
    {
        $this->authorize('super-admin');
        $coupons = $this->couponSetupService->trashedData(criteria: $request->all(), limit: paginationLimit(), offset: $request['page'] ?? 1);
        return view('promotionmanagement::admin.coupon-setup.trashed', compact('coupons'));
    }

    public function restore($id): RedirectResponse
    {
        $this->authorize('super-admin');
        $this->couponSetupService->restoreData($id);
        Toastr::success(DEFAULT_RESTORE_200['message']);
        return redirect()->route('admin.promotion.coupon-setup.index');
    }

    public function permanentDelete($id)
    {
        $this->authorize('super-admin');
        $this->couponSetupService->permanentDelete(id: $id);
        Toastr::success(COUPON_DESTROY_200['message']);
        return back();
    }
}
