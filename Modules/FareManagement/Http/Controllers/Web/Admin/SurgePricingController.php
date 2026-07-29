<?php

namespace Modules\FareManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\FareManagement\Http\Requests\SurgePricingStoreOrUpdateRequest;
use Modules\FareManagement\Service\Interfaces\SurgePricingServiceInterface;
use Modules\VehicleManagement\Service\Interfaces\VehicleCategoryServiceInterface;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SurgePricingController extends BaseController
{
    use AuthorizesRequests;

    protected $surgePricingService;
    protected $zoneService;
    protected $vehicleCategories;

    public function __construct(SurgePricingServiceInterface $surgePricingService, ZoneServiceInterface $zoneService, VehicleCategoryServiceInterface $vehicleCategories)
    {
        parent::__construct($surgePricingService);
        $this->surgePricingService = $surgePricingService;
        $this->zoneService = $zoneService;
        $this->vehicleCategories = $vehicleCategories;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('fare_view');

        $criteria = $request->all();
        $relations = ['surgePricingZones', 'surgePricingServiceCategories', 'surgePricingTimeSlot'];
        $orderBy = ['readable_id' => 'desc'];
        $surgePricing = $this->surgePricingService->index(criteria: $criteria, relations: $relations, orderBy: $orderBy, limit: paginationLimit(), offset: $request['page'] ?? 1);


        return view('faremanagement::admin.surge-pricing.index', compact('surgePricing'));
    }

    public function create(): JsonResponse
    {
        $this->authorize('fare_add');
        $zones = $this->zoneService->getAll()->pluck('name', 'id')->toArray();
        $vehicleCategories = $this->vehicleCategories->getAll();
        $isCreateBlade = true;

        return response()->json(view('faremanagement::admin.surge-pricing.partials.offcanvas-create-edit', compact('zones', 'vehicleCategories', 'isCreateBlade'))->render());
    }

    public function store(SurgePricingStoreOrUpdateRequest $request): JsonResponse
    {
        $this->authorize('fare_add');
        $data = $request->validated();
        $this->surgePricingService->create(data: $data);

        return response()->json(SURGE_PRICING_STORE_200['message']);
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorize('fare_add');
        $model = $this->surgePricingService->statusChange(id: $request->id, data: $request->all());

        return response()->json($model);
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorize('fare_view');
        $this->surgePricingService->delete(id: $id);
        Toastr::success(translate(SURGE_PRICING_DESTROY_200['message']));
        return redirect()->route('admin.fare.surge-pricing.index');
    }

    public function show(string $id): View
    {
        $this->authorize('fare_view');
        $surgePrice = $this->surgePricingService->findOne(id: $id,relations: ['surgePricingTimeSlot','surgePricingZones', 'surgePricingServiceCategories']);
        $zones = $this->zoneService->getAll()->pluck('name', 'id')->toArray();
        return view('faremanagement::admin.surge-pricing.details', compact('surgePrice', 'zones'));
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('fare_view');
        $criteria = array_merge($request->all());
        $data = $this->surgePricingService->export(criteria: $criteria, relations: ['surgePricingZones', 'surgePricingServiceCategories', 'surgePricingTimeSlot'], orderBy: ['readable_id' => 'desc']);

        $config = styledExportConfig(
            $data,
            title: 'Surge Pricing',
            summary: ['Total Records' => $data->count()],
            filters: [
                'Status' => $request->status ?? translate('All'),
                'Search' => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Status' => ColumnFormat::STATUS,
            ],
            fileName: 'surge-pricing-' . time() . '.xlsx',
            headings: ['Name', 'Zones', 'Extra Price', 'Schedule', 'Date Range', 'Selected Days', 'Time Slots', 'Statistics', 'Status', 'Note'],
        );
        return exportData($data, $request['file'], '', $config);
    }

    public function edit(string $id): JsonResponse
    {
        $this->authorize('fare_add');
        $relations = ['surgePricingZones', 'surgePricingServiceCategories', 'surgePricingTimeSlot'];
        $surgePricing = $this->surgePricingService->findOne(id: $id, relations: $relations);
        $zones = $this->zoneService->getAll()->pluck('name', 'id')->toArray();
        $vehicleCategories = $this->vehicleCategories->getAll();
        $isCreateBlade = false;
        $formatDateRangeForCustomSchedule = $surgePricing->schedule == 'custom' ? collect($surgePricing->surgePricingTimeSlot->slots)->map(function ($slot, $index){
            return [
                'id' => ($index + 1) + rand(1111, 9999),
                'date' => Carbon::parse($slot['date'])->format('D M d Y'),
                'time' => date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time']))
            ];
        })->toArray() : [];
        return response()
            ->json(view('faremanagement::admin.surge-pricing.partials.offcanvas-create-edit', compact('surgePricing', 'zones', 'vehicleCategories', 'isCreateBlade', 'formatDateRangeForCustomSchedule'))
                ->render());
    }

    public function update(SurgePricingStoreOrUpdateRequest $request, string $id): JsonResponse
    {
        $this->authorize('fare_add');
        $data = $request->validated();
        $data['id'] = $id;
        $surgePricing = $this->surgePricingService->updatesSurgePricing(id: $id, data: $data);

        if (!$surgePricing) {
            return response()->json(['error' => 'Failed to update surge pricing'], 500);
        }

        return response()->json(SURGE_PRICING_UPDATE_200['message']);
    }

    public function getZones($id): JsonResponse
    {
        $this->authorize('fare_view');
        $surgePricing = $this->surgePricingService->findOne(id: $id, relations: ['surgePricingZones']);
        if ($surgePricing->zone_setup_type = 'all')
        {
            $zones = $this->zoneService->getAll()->pluck('name', 'id')->toArray();
        } else {
            $zones = $surgePricing->surgePricingZones->pluck('name', 'id')->toArray();
        }

        return response()
            ->json(view('faremanagement::admin.surge-pricing.partials.offcanvas-zone-list', compact('zones'))
                ->render());
    }

    public function getCustomDateList($id): JsonResponse
    {
        $this->authorize('fare_view');
        $surge = $this->surgePricingService->findOne(id: $id, relations: ['surgePricingTimeSlot']);
        $data['date_time_slots'] = $surge->schedule === 'custom' ? collect($surge->surgePricingTimeSlot->slots)->map(function ($slot) {
            return [
                'date' => date('D, M d Y', strtotime($slot['date'])),
                'time_slot' => date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time'])),

            ];
        })->toArray() : [
            'date' => '',
            'time_slot' => date('h:i A', strtotime($surge->surgePricingTimeSlot->slots[0]['start_time'])) . ' - ' . date('h:i A', strtotime($surge->surgePricingTimeSlot->slots[0]['end_time'])),
        ];
        return response()
            ->json(view('faremanagement::admin.surge-pricing.partials.offcanvas-custom-date-list', compact('data'))
                ->render());
    }

    public function getCustomDateListInDetails($id): JsonResponse
    {
        $this->authorize('fare_view');
        $surge = $this->surgePricingService->findOne(id: $id, relations: ['surgePricingTimeSlot']);
        $data['date_time_slots'] = $surge->schedule === 'custom' ? collect($surge->surgePricingTimeSlot->slots)->map(function ($slot) {
            return [
                'date' => date('D, M d Y', strtotime($slot['date'])),
                'time_slot' => date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time'])),

            ];
        })->toArray() : [
            'date' => '',
            'time_slot' => date('h:i A', strtotime($surge->surgePricingTimeSlot->slots[0]['start_time'])) . ' - ' . date('h:i A', strtotime($surge->surgePricingTimeSlot->slots[0]['end_time'])),
        ];
        $isDetailsPage= true;
        return response()
            ->json(view('faremanagement::admin.surge-pricing.partials.offcanvas-custom-date-list', compact('data', 'isDetailsPage'))
                ->render());
    }

    public function getStatisticsData(Request $request, $id): JsonResponse
    {
        $data = $this->surgePricingService->getRidesByDateAndTimeRange(data: $request->all(), id: $id);
        return response()->json($data);
    }

    public function updateCustomerNote(Request $request, $id): JsonResponse
    {
        $request->validate([
            'customer_note' => 'nullable|string|max:31'
        ]);
        $this->surgePricingService->update(id: $id, data: ['customer_note' => $request->customer_note]);

        return response()->json(SURGE_PRICING_UPDATE_200['message']);
    }

    public function updateZoneList(Request $request, $id): JsonResponse
    {
        $request->validate([
            'zones' => 'required|array',
            'zones.*' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value !== 'all' && !\DB::table('zones')->where('id', $value)->exists()) {
                        $fail("The selected zone ($value) is invalid.");
                    }
                }
            ],
        ]);

        $this->surgePricingService->updateZone(id: $id, data: $request->all());

        return response()->json(SURGE_PRICING_UPDATE_200['message']);
    }

    public function editSchedule(string $id): JsonResponse
    {
        $this->authorize('fare_add');
        $relations = ['surgePricingZones', 'surgePricingServiceCategories', 'surgePricingTimeSlot'];
        $surgePricing = $this->surgePricingService->findOne(id: $id, relations: $relations);
        $zones = $this->zoneService->getAll()->pluck('name', 'id')->toArray();
        $vehicleCategories = $this->vehicleCategories->getAll();
        $isCreateBlade = false;
        $isOnlySchedule = true;
        $formatDateRangeForCustomSchedule = $surgePricing->schedule == 'custom' ? collect($surgePricing->surgePricingTimeSlot->slots)->map(function ($slot, $index){
            return [
                'id' => ($index + 1) + rand(1111, 9999),
                'date' => Carbon::parse($slot['date'])->format('D M d Y'),
                'time' => date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time']))
            ];
        })->toArray() : [];
        return response()
            ->json(view('faremanagement::admin.surge-pricing.partials.offcanvas-create-edit', compact('surgePricing', 'zones', 'vehicleCategories', 'isCreateBlade', 'formatDateRangeForCustomSchedule', 'isOnlySchedule'))
                ->render());
    }

    public function editPriceApplicableFor(string $id): JsonResponse
    {
        $this->authorize('fare_add');
        $relations = ['surgePricingZones', 'surgePricingServiceCategories', 'surgePricingTimeSlot'];
        $surgePricing = $this->surgePricingService->findOne(id: $id, relations: $relations);
        $zones = $this->zoneService->getAll()->pluck('name', 'id')->toArray();
        $vehicleCategories = $this->vehicleCategories->getAll();
        $isCreateBlade = false;
        $isOnlyPriceApplicableFor = true;
        $formatDateRangeForCustomSchedule = $surgePricing->schedule == 'custom' ? collect($surgePricing->surgePricingTimeSlot->slots)->map(function ($slot, $index){
            return [
                'id' => ($index + 1) + rand(1111, 9999),
                'date' => Carbon::parse($slot['date'])->format('D M d Y'),
                'time' => date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time']))
            ];
        })->toArray() : [];
        return response()
            ->json(view('faremanagement::admin.surge-pricing.partials.offcanvas-create-edit', compact('surgePricing', 'zones', 'vehicleCategories', 'isCreateBlade', 'formatDateRangeForCustomSchedule', 'isOnlyPriceApplicableFor'))
                ->render());
    }
}
