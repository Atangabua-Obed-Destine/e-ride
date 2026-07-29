<?php

namespace Modules\VehicleManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
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
use Modules\VehicleManagement\Entities\VehicleBrand;
use Modules\VehicleManagement\Http\Requests\VehicleBrandStoreUpdateRequest;
use Modules\VehicleManagement\Service\Interfaces\VehicleBrandServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleBrandController extends BaseController
{
    use AuthorizesRequests;

    protected $vehicleBrandService;
    protected $activityLogService;

    public function __construct(VehicleBrandServiceInterface $vehicleBrandService, ActivityLogServiceInterface $activityLogService)
    {
        parent::__construct($vehicleBrandService);
        $this->vehicleBrandService = $vehicleBrandService;
        $this->activityLogService = $activityLogService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('vehicle_view');
        $brands = $this->vehicleBrandService->index(criteria: $request?->all(), relations: ['vehicles'], orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page']??1);
        return view('vehiclemanagement::admin.brand.index', compact('brands'));
    }

    public function store(VehicleBrandStoreUpdateRequest $request): RedirectResponse
    {
        $this->authorize('vehicle_add');
        $this->vehicleBrandService->create(data: $request->validated());
        Toastr::success(ucfirst(BRAND_CREATE_200['message']));
        return back();

    }

    public function edit(string $id): Renderable
    {
        $this->authorize('vehicle_edit');
        $brand = $this->vehicleBrandService->findOne(id: $id);
        return view('vehiclemanagement::admin.brand.edit', compact('brand'));
    }

    public function update(VehicleBrandStoreUpdateRequest $request, string $id): RedirectResponse
    {
        $this->authorize('vehicle_edit');
        $this->vehicleBrandService->update(id: $id, data: $request->validated());
        Toastr::success(BRAND_UPDATE_200['message']);
        return redirect()->route('admin.vehicle.attribute-setup.brand.index');

    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorize('vehicle_delete');
        $this->vehicleBrandService->delete(id: $id);
        Toastr::success(BRAND_DELETE_200['message']);
        return redirect()->route('admin.vehicle.attribute-setup.brand.index');
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorize('vehicle_edit');
        $request->validate([
            'status' => 'boolean'
        ]);
        $model = $this->vehicleBrandService->statusChange(id: $request->id, data: $request->all());
        return response()->json($model);
    }

    public function trashed(Request $request): View
    {
        $this->authorize('super-admin');
        $brands = $this->vehicleBrandService->getBy(criteria: $request->all(), limit: paginationLimit(), onlyTrashed: true);
        return view('vehiclemanagement::admin.brand.trashed', compact('brands'));
    }

    public function restore($id): RedirectResponse
    {
        $this->authorize('super-admin');
        $this->vehicleBrandService->restoreData(id: $id);
        Toastr::success(DEFAULT_RESTORE_200['message']);
        return redirect()->route('admin.vehicle.attribute-setup.brand.index');
    }

    public function permanentDelete($id)
    {
        $this->authorize('super-admin');
        $this->vehicleBrandService->permanentDelete(id: $id);
        Toastr::success(BRAND_DELETE_200['message']);
        return back();
    }

    public function getAllAjax(Request $request): JsonResponse
    {
        $brands = $this->vehicleBrandService->index(criteria: $request->all());
        $selectBrands = $brands->map(function ($items, $key) {
            return [
                'text' => $items->name,
                'id' => $items->id
            ];
        });
        return response()->json($selectBrands);
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('vehicle_export');
        $data = $this->vehicleBrandService->export(criteria: $request->all(), relations: ['vehicles'], orderBy: ['created_at' => 'desc']);
        $config = styledExportConfig(
            $data,
            title: 'Vehicle Brands',
            summary: ['Total Brands' => $data->count()],
            filters: [
                'Status' => ucfirst($request->status ?? 'all'),
                'Search' => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Status' => ColumnFormat::STATUS,
            ],
            fileName: 'vehicle-brands-' . time() . '.xlsx',
            headings: ['Brand Name', 'Description', 'Total Vehicles', 'Status'],
        );
        return exportData($data, $request['file'], 'vehiclemanagement::admin.brand.print', $config);
    }

    public function log(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('vehicle_log');
        $request->merge([
            'logable_type' => VehicleBrand::class,
        ]);
        $logs = $this->activityLogService->log($request->all());
        $file = array_key_exists('file', $request->all()) ? $request['file'] : '';
        return logViewerNew($logs,$file);

    }
}
