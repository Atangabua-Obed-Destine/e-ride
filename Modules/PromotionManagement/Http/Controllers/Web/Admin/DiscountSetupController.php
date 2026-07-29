<?php

namespace Modules\PromotionManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\AdminModule\Service\Interfaces\ActivityLogServiceInterface;
use Modules\PromotionManagement\Entities\DiscountSetup;
use Modules\PromotionManagement\Http\Requests\DiscountStoreOrUpdateRequest;
use Modules\PromotionManagement\Service\Interfaces\DiscountSetupServiceInterface;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Lib\DiscountCalculationTrait;
use Modules\UserManagement\Service\Interfaces\CustomerLevelServiceInterface;
use Modules\UserManagement\Service\Interfaces\CustomerServiceInterface;
use Modules\VehicleManagement\Service\Interfaces\VehicleCategoryServiceInterface;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscountSetupController extends BaseController
{
    use AuthorizesRequests;
    use DiscountCalculationTrait;

    protected $discountSetupService;
    protected $zoneService;
    protected $customerLevelService;
    protected $customerService;
    protected $vehicleCategoryService;
    protected $activityLogService;

    public function __construct(DiscountSetupServiceInterface   $discountSetupService, ZoneServiceInterface $zoneService,
                                CustomerLevelServiceInterface   $customerLevelService, CustomerServiceInterface $customerService,
                                VehicleCategoryServiceInterface $vehicleCategoryService, ActivityLogServiceInterface $activityLogService)
    {
        parent::__construct($discountSetupService);
        $this->discountSetupService = $discountSetupService;
        $this->zoneService = $zoneService;
        $this->customerLevelService = $customerLevelService;
        $this->customerService = $customerService;
        $this->vehicleCategoryService = $vehicleCategoryService;
        $this->activityLogService = $activityLogService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('promotion_view');
        $dateRangeValue = $request?->query('date_range');
        $this->discountSetupService->updatedBy(criteria: [['end_date', '<', Carbon::today()]], data: ['is_active' => false]);
        $discounts = $this->discountSetupService->index(criteria: $request?->all(), orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request?->page ?? 1);

        return view('promotionmanagement::admin.discount-setup.index', compact('dateRangeValue', 'discounts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Renderable
    {
        $this->authorize('promotion_add');
        $zones = $this->zoneService->getAll();
        $levels = $this->customerLevelService->getBy(criteria: ['user_type' => CUSTOMER]);
        $vehicleCategories = $this->vehicleCategoryService->getAll();
        return view('promotionmanagement::admin.discount-setup.create', compact('zones', 'levels', 'vehicleCategories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DiscountStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('promotion_add');
        $this->discountSetupService->create(data: $request->validated());
        Toastr::success(DISCOUNT_STORE_200['message']);
        return redirect()->route('admin.promotion.discount-setup.index');
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('promotionmanagement::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $this->authorize('promotion_edit');
        $zones = $this->zoneService->getAll();
        $levels = $this->customerLevelService->getBy(criteria: ['user_type' => CUSTOMER]);
        $vehicleCategories = $this->vehicleCategoryService->getAll();
        $relations = ['vehicleCategories', 'zones', 'customers', 'customerLevels'];
        $discount = $this->discountSetupService->findOne(id: $id, relations: $relations);
        if (is_null($discount)) {
            Toastr::error(translate(DISCOUNT_RESOURCE_404['message']));
            return redirect()->back();
        }
        if ($discount?->customer_level_discount_type == ALL) {
            $customers = $this->customerService->getBy(criteria: ['user_type' => CUSTOMER]);
        } else {
            $customers = $this->customerService->getBy(criteria: ['user_type' => CUSTOMER], whereInCriteria: ['user_level_id' => $discount?->customerLevels->pluck('id')]);
        }
        return view('promotionmanagement::admin.discount-setup.edit', compact('discount', 'zones', 'levels', 'vehicleCategories', 'customers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DiscountStoreOrUpdateRequest $request, $id): RedirectResponse
    {
        $this->authorize('promotion_edit');
        $this->discountSetupService->update(id: $id, data: $request->validated());
        Toastr::success(DISCOUNT_UPDATE_200['message']);
        return redirect()->route('admin.promotion.discount-setup.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $this->authorize('promotion_view');
        $this->discountSetupService->delete(id: $id);
        Toastr::success(DISCOUNT_DESTROY_200['message']);
        return back();
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorize('promotion_edit');
        $request->validate([
            'status' => 'boolean'
        ]);
        $model = $this->discountSetupService->statusChange(id: $request->id, data: $request->all());
        return response()->json($model);
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('promotion_export');
        $discount = $this->discountSetupService->index(criteria: $request->all(), orderBy: ['created_at' => 'desc']);
        $date = Carbon::now()->startOfDay();


        $data = $discount->map(function ($item) use ($date) {

            if ($date->gt($item['end_date'])) {
                $discountStatus = ucwords(EXPIRED);
            } elseif (!$item['is_active']) {
                $discountStatus = ucwords(CURRENTLY_OFF);
            } elseif ($date->lt($item['start_date'])) {
                $discountStatus = ucwords(UPCOMING);
            } elseif ($date->lte($item['end_date'])) {
                $discountStatus = ucwords(RUNNING);
            } else {
                $discountStatus = ucwords(UPCOMING);
            }

            $startDate = Carbon::parse($item['start_date']);
            $endDate = Carbon::parse($item['end_date']);
            $duration = $startDate->diffInDays($endDate) + 1;

            return [
                'Discount Title' => $item['title'],
                'Zone' => $item['zone_discount_type'] ?? '-',
                'Customer Level' => $item['customer_level_discount_type'] ?? '-',
                'Customer' => $item['customer_discount_type'] ?? '-',
                'Category' => implode(',', $item['module_discount_type']) ?? '-',
                "Discount Amount" => $item['discount_amount_type'] == 'percentage' ? ($item['discount_amount'] ?? 0) . '%' : getCurrencyFormat($item['discount_amount'] ?? 0),
                'Duration' =>
                    'Start: ' . $startDate->format('Y-m-d') .
                    ', End: ' . $endDate->format('Y-m-d') .
                    ', Duration: ' . $duration . ' Days',
                'Total Time Used' => $item['total_used'] ?? 0,
                'Total Discount Amount' => getCurrencyFormat($item['total_amount'] ?? 0),
                "Discount Status" => $discountStatus,
                "Active Status" => $item['is_active'] == 1 ? "Active" : "Inactive",
            ];
        });
        $config = styledExportConfig(
            $data,
            title: 'Discount Setup',
            summary: [
                'Total Discounts'    => $data->count(),
                'Active Discounts'   => $data->where('Active Status', 'Active')->count(),
                'Inactive Discounts' => $data->where('Active Status', 'Inactive')->count(),
            ],
            filters: [
                'Status' => $request->status ?? translate('All'),
                'Search' => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Total Time Used'        => ColumnFormat::INTEGER,
                'Total Discount Amount'  => ColumnFormat::CURRENCY,
                'Discount Status'        => ColumnFormat::STATUS,
                'Active Status'          => ColumnFormat::STATUS,
            ],
            fileName: 'discount-setup-' . time() . '.xlsx',
            headings: ['Discount Title', 'Zone', 'Customer Level', 'Customer', 'Category', 'Discount Amount', 'Duration', 'Total Time Used', 'Total Discount Amount', 'Discount Status', 'Active Status'],
        );
        return exportData($data, $request['file'], '', $config);
    }

    public function log(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('promotion_log');
        $request->merge(['logable_type' => DiscountSetup::class]);
        $logs = $this->activityLogService->log($request->all());
        $file = array_key_exists('file', $request->all()) ? $request['file'] : '';
        return logViewerNew($logs,$file);
    }

    public function trashed(Request $request): View
    {
        $this->authorize('super-admin');
        $discounts = $this->discountSetupService->trashedData(criteria: $request->all(), limit: paginationLimit(), offset: $request['page'] ?? 1);
        return view('promotionmanagement::admin.discount-setup.trashed', compact('discounts'));
    }

    public function restore($id): RedirectResponse
    {
        $this->authorize('super-admin');
        $this->discountSetupService->restoreData($id);
        Toastr::success(DEFAULT_RESTORE_200['message']);
        return redirect()->route('admin.promotion.discount-setup.index');
    }

    public function permanentDelete($id)
    {
        $this->authorize('super-admin');
        $this->discountSetupService->permanentDelete(id: $id);
        Toastr::success(DISCOUNT_DESTROY_200['message']);
        return back();
    }

    public function test()
    {
        $user = $this->customerService->findOne(id: '1a9df870-f209-4d7f-aee9-71e7393f58ee');
        $trip = TripRequest::find(id: 'd471e033-12a9-479c-b14e-c1850ab74dac');
        return $this->getDiscount($user, $trip);
    }
}
