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
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Modules\AdminModule\Service\Interfaces\ActivityLogServiceInterface;
use Modules\VehicleManagement\Entities\Vehicle;
use Modules\VehicleManagement\Http\Requests\VehicleStoreUpdateRequest;
use Modules\VehicleManagement\Mail\VehicleRequestApprovedMail;
use Modules\VehicleManagement\Mail\VehicleRequestDeniedMail;
use Modules\VehicleManagement\Service\Interfaces\VehicleCategoryServiceInterface;
use Modules\VehicleManagement\Service\Interfaces\VehicleServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleController extends BaseController
{
    use AuthorizesRequests;

    protected $vehicleService;
    protected $vehicleCategoryService;
    protected $activityLogService;

    public function __construct(VehicleServiceInterface     $vehicleService, VehicleCategoryServiceInterface $vehicleCategoryService,
                                ActivityLogServiceInterface $activityLogService)
    {
        parent::__construct($vehicleService);
        $this->vehicleService = $vehicleService;
        $this->vehicleCategoryService = $vehicleCategoryService;
        $this->activityLogService = $activityLogService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('vehicle_view');
        $criteria = array_merge($request->all(), ['vehicle_request_status' => APPROVED]);

        $vehicles = $this->vehicleService->index(criteria: $criteria, relations: ['model', 'brand', 'driver', 'category'], orderBy: ['updated_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1);
        $categories = $this->vehicleCategoryService->getAll(relations: ['vehicles']);
        return view('vehiclemanagement::admin.vehicle.index', compact('vehicles', 'categories'));
    }

    public function create(): Renderable
    {
        $this->authorize('vehicle_add');

        return view('vehiclemanagement::admin.vehicle.create');
    }

    public function store(VehicleStoreUpdateRequest $request): RedirectResponse
    {
        $this->authorize('vehicle_add');
        $data = array_merge($request->validated(), ['vehicle_request_status' => APPROVED]);
        $this->vehicleService->create(data: $data);
        Toastr::success(ucfirst(VEHICLE_CREATE_200['message']));
        return redirect()->route('admin.vehicle.index');
    }

    public function show(string $id): Renderable
    {
        $this->authorize('vehicle_view');
        $relations = ['brand', 'model', 'category', 'driver'];
        $vehicle = $this->vehicleService->findOne(id: $id, relations: $relations);
        return view('vehiclemanagement::admin.vehicle.show', compact('vehicle'));
    }

    public function edit(string $id): Renderable
    {

        $this->authorize('vehicle_edit');
        $relations = ['brand', 'model', 'category', 'driver'];
        $vehicle = $this->vehicleService->findOne(id: $id, relations: $relations);
        return view('vehiclemanagement::admin.vehicle.edit', compact('vehicle'));
    }

    public function update(VehicleStoreUpdateRequest $request, string $id): RedirectResponse
    {
        $this->authorize('vehicle_edit');
        $this->vehicleService->updatedByAdmin(id: $id, data: $request->validated());
        Toastr::success(VEHICLE_UPDATE_200['message']);
        return redirect()->route('admin.vehicle.index');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorize('vehicle_delete');
        $this->vehicleService->delete(id: $id);
        Toastr::success(DEFAULT_DELETE_200['message']);
        return redirect()->route('admin.vehicle.index');
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorize('vehicle_edit');
        $model = $this->vehicleService->statusChange(id: $request->id, data: $request->all());
        $sentTime = pushSentTime($model->updated_at);
        $push = getNotification('vehicle_active');
        if ($model && $request->status && $model?->driver->fcm_token) {
            sendDeviceNotification(
                fcm_token: $model?->driver->fcm_token,
                title: translate(key: $push['title'], locale: $model?->driver?->current_language_key),
                description: textVariableDataFormat(value: $push['description'], userName: $model->driver->first_name . ' ' . $model->driver->last_name, sentTime: $sentTime, vehicleCategory: $model->category->name, locale: $model?->driver?->current_language_key),
                status: $push['status'],
                notification_type: $push['action'],
                action: $push['action'],
                user_id: $model?->driver_id
            );
        }
        return response()->json($model);
    }

    public function trashed(Request $request): View
    {
        $this->authorize('super-admin');
        $vehicles = $this->vehicleService->getBy(criteria: $request->all(), limit: paginationLimit(), offset: $request['page'] ?? 1, onlyTrashed: true);
        return view('vehiclemanagement::admin.vehicle.trashed', compact('vehicles'));
    }

    public function restore(string $id): RedirectResponse
    {
        $this->authorize('super-admin');
        $this->vehicleService->restoreData(id: $id);
        Toastr::success(DEFAULT_RESTORE_200['message']);
        return redirect()->route('admin.vehicle.index');

    }

    public function permanentDelete($id)
    {
        $this->authorize('super-admin');
        $this->vehicleService->permanentDelete(id: $id);
        Toastr::success(DEFAULT_DELETE_200['message']);
        return back();
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('vehicle_export');
        $criteria = array_merge($request->all(), ['vehicle_request_status' => APPROVED]);
        $data = $this->vehicleService->export(criteria: $criteria, relations: ['category', 'model', 'brand', 'driver'], orderBy: ['created_at' => 'desc']);
        $config = styledExportConfig(
            $data,
            title: 'Vehicle List',
            summary: ['Total Vehicles' => $data->count()],
            filters: [
                'Status'   => $request->status   ?? translate('All'),
                'Category' => $request->category ?? translate('All'),
                'Search'   => $request->search   ?? translate('N/A'),
            ],
            columnFormats: [
                'Status' => ColumnFormat::STATUS,
            ],
            fileName: 'vehicles-' . time() . '.xlsx',
            headings: ['Driver Name', 'Type', 'Brand', 'Model', 'License', 'Owner', 'Seat Capacity', 'Hatch Bag Capacity', 'Fuel', 'Mileage', 'Status'],
        );
        return exportData($data, $request['file'], 'vehiclemanagement::admin.vehicle.print', $config);
    }

    public function log(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $request->merge([
            'logable_type' => Vehicle::class,
        ]);
        $logs = $this->activityLogService->log($request->all());
        $file = array_key_exists('file', $request->all()) ? $request['file'] : '';
        return logViewerNew($logs, $file);
    }


    public function newVehicleRequestList(Request $request): View
    {
        $this->authorize('vehicle_view');
        $criteria = array_merge($request->all(), ['vehicle_request_status' => $request->input('vehicle_request_status', PENDING)]);
        $vehicles = $this->vehicleService->index(criteria: $criteria, relations: ['model', 'brand', 'category', 'driver'], orderBy: ['updated_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1);
        return view('vehiclemanagement::admin.vehicle.request.list', compact('vehicles'));

    }

    public function requestedVehicleInfo($id)
    {
        $this->authorize('vehicle_view');
        $vehicle = $this->vehicleService->findOne(id: $id, relations: ['model', 'brand', 'category', 'driver']);
        return view('vehiclemanagement::admin.vehicle.request.details', compact('vehicle'));
    }


    public function editVehicleRequest($id)
    {
        $this->authorize('vehicle_edit');
        $vehicle = $this->vehicleService->findOne(id: $id, relations: ['model', 'brand', 'category', 'driver']);
        return view('vehiclemanagement::admin.vehicle.request.edit', compact('vehicle'));
    }

    public function approvedVehicleRequest($id)
    {
        $this->authorize('vehicle_edit');
        $this->vehicleService->update(id: $id, data: ['vehicle_request_status' => APPROVED, 'is_active' => 1]);
        $model = $this->vehicleService->findOne(id: $id, relations: ['driver']);
        $isMailEnabled = businessConfig(key: EMAIL_CONFIG, settingsType: EMAIL_CONFIG);
        if ($model->driver?->email && $isMailEnabled) {
            try {
                Mail::to($model->driver?->email)->send(new VehicleRequestApprovedMail());
            } catch (\Exception $exception) {
            }
        }
        $sentTime = pushSentTime($model?->updated_at);
        $push = getNotification('vehicle_request_approved');
        if ($model?->driver?->fcm_token) {
            sendDeviceNotification(
                fcm_token: $model->driver->fcm_token,
                title: translate(key: $push['title'], locale: $model->driver?->current_language_key),
                description: textVariableDataFormat(value: $push['description'], userName: $model->driver->first_name . ' ' . $model->driver->last_name, sentTime: $sentTime, vehicleCategory: $model->category?->name, locale: $model->driver?->current_language_key),
                status: $push['status'],
                notification_type: $push['action'],
                action: $push['action'],
                user_id: $model->driver_id
            );
        }

        Toastr::success('Vehicle request approved successfully');
        return redirect()->route('admin.vehicle.request.list');
    }

    public function deniedVehicleRequest(Request $request, $id)
    {
        $request->validate([
            'deny_note' => 'required|max:151'
        ]);
        $this->authorize('vehicle_edit');
        $this->vehicleService->update(id: $id, data: ['vehicle_request_status' => DENIED, 'deny_note' => $request->deny_note]);
        $model = $this->vehicleService->findOne(id: $id, relations: ['driver']);
        $isMailEnabled = businessConfig(key: EMAIL_CONFIG, settingsType: EMAIL_CONFIG);
        if ($model->driver?->email && $isMailEnabled) {
            try {
                Mail::to($model->driver?->email)->send(new VehicleRequestDeniedMail());
            } catch (\Exception $exception) {
            }
        }
        $sentTime = pushSentTime($model?->updated_at);
        $push = getNotification('vehicle_request_denied');
        if ($model?->driver?->fcm_token) {
            sendDeviceNotification(
                fcm_token: $model->driver->fcm_token,
                title: translate(key: $push['title'], locale: $model->driver?->current_language_key),
                description: textVariableDataFormat(value: $push['description'], userName: $model->driver->first_name . ' ' . $model->driver->last_name, sentTime: $sentTime, vehicleCategory: $model->category?->name, reason: $model->deny_note, locale: $model->driver?->current_language_key),
                status: $push['status'],
                notification_type: $push['action'],
                action: $push['action'],
                user_id: $model->driver_id
            );
        }

        Toastr::success('Vehicle request denied successfully');
        return redirect()->route('admin.vehicle.request.list');
    }


    public function exportVehicleRequest(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('vehicle_export');
        $criteria = array_merge($request->all(), ['vehicle_request_status' => $request->input('vehicle_request_status', PENDING)]);
        $data = $this->vehicleService->export(criteria: $criteria, relations: ['category', 'model', 'brand', 'driver'], orderBy: ['created_at' => 'desc']);
        $config = styledExportConfig(
            $data,
            title: 'Vehicle Requests',
            summary: ['Total Requests' => $data->count()],
            filters: [
                'Request Status' => $request->vehicle_request_status ?? translate('Pending'),
                'Search'         => $request->search                 ?? translate('N/A'),
            ],
            columnFormats: [
                'Status' => ColumnFormat::STATUS,
            ],
            fileName: 'vehicle-requests-' . time() . '.xlsx',
            headings: ['Driver Name', 'Type', 'Brand', 'Model', 'License', 'Owner', 'Seat Capacity', 'Hatch Bag Capacity', 'Fuel', 'Mileage', 'Status'],
        );
        return exportData($data, $request['file'], 'vehiclemanagement::admin.vehicle.print', $config);
    }


    public function newVehicleUpdateList(Request $request): View
    {
        $this->authorize('vehicle_view');
        $criteria = array_merge($request->all(), ['draft' => true]);
        $vehicles = $this->vehicleService->index(criteria: $criteria, relations: ['model', 'brand', 'category', 'driver'], orderBy: ['updated_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1);
        return view('vehiclemanagement::admin.vehicle.update.list', compact('vehicles'));

    }

    public function updatedVehicleInfo($id)
    {
        $this->authorize('vehicle_view');
        $vehicle = $this->vehicleService->findOne(id: $id, relations: ['model', 'brand', 'category', 'driver']);
        return view('vehiclemanagement::admin.vehicle.update.details', compact('vehicle'));
    }


    public function editVehicleUpdate($id)
    {
        $this->authorize('vehicle_edit');
        $vehicle = $this->vehicleService->findOne(id: $id, relations: ['model', 'brand', 'category', 'driver']);
        return view('vehiclemanagement::admin.vehicle.update.edit', compact('vehicle'));
    }

    public function approvedVehicleUpdate($id)
    {
        $this->authorize('vehicle_edit');
        $vehicle = $this->vehicleService->findOne(id: $id);
        $attributes = [];
        $data = $vehicle->draft;
        foreach ($data as $key => $row) {
            if (in_array($key, array_keys($vehicle->toArray()))) {
                $attributes[$key] = $row;
            }
        }
        $attributes = array_merge($attributes, ['draft' => null]);
        $this->vehicleService->update(id: $id, data: $attributes);
        $model = $this->vehicleService->findOneBy(criteria: ['id' => $id], relations: ['driver']);
        $isMailEnabled = businessConfig(key: EMAIL_CONFIG, settingsType: EMAIL_CONFIG);
        if ($model->driver?->email && $isMailEnabled) {
            try {
                Mail::to($model->driver?->email)->send(new VehicleRequestApprovedMail());
            } catch (\Exception $exception) {
            }
        }
        $sentTime = pushSentTime($model?->updated_at);
        $push = getNotification('vehicle_request_approved');
        if ($model?->driver?->fcm_token) {
            sendDeviceNotification(
                fcm_token: $model->driver->fcm_token,
                title: translate(key: $push['title'], locale: $model->driver?->current_language_key),
                description: textVariableDataFormat(value: $push['description'], userName: $model->driver->first_name . ' ' . $model->driver->last_name, sentTime: $sentTime, vehicleCategory: $model->category?->name, locale: $model->driver?->current_language_key),
                status: $push['status'],
                notification_type: $push['action'],
                action: $push['action'],
                user_id: $model->driver_id
            );
        }
        Toastr::success('Vehicle update approved successfully');
        return redirect()->route('admin.vehicle.update.list');
    }

    public function deniedVehicleUpdate(Request $request, $id)
    {
        $this->authorize('vehicle_edit');
        $this->vehicleService->update(id: $id, data: ['draft' => null]);
        $model = $this->vehicleService->findOne(id: $id, relations: ['driver']);
        $isMailEnabled = businessConfig(key: EMAIL_CONFIG, settingsType: EMAIL_CONFIG);
        if ($model->driver?->email && $isMailEnabled) {
            try {
                Mail::to($model->driver?->email)->send(new VehicleRequestDeniedMail());
            } catch (\Exception $exception) {
            }
        }
        $sentTime = pushSentTime($model?->updated_at);
        $push = getNotification('vehicle_request_denied');
        if ($model?->driver?->fcm_token) {
            sendDeviceNotification(
                fcm_token: $model->driver->fcm_token,
                title: translate(key: $push['title'], locale: $model->driver?->current_language_key),
                description: textVariableDataFormat(value: $push['description'], userName: $model->driver->first_name . ' ' . $model->driver->last_name, sentTime: $sentTime, vehicleCategory: $model->category?->name, reason: $model->deny_note, locale: $model->driver?->current_language_key),
                status: $push['status'],
                notification_type: $push['action'],
                action: $push['action'],
                user_id: $model->driver_id
            );
        }

        Toastr::success('Vehicle request denied successfully');
        return redirect()->route('admin.vehicle.update.list');
    }

    public function exportVehicleUpdate(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('vehicle_export');
        $criteria = array_merge($request->all(), ['draft' => true]);
        $data = $this->vehicleService->exportUpdateVehicle(criteria: $criteria, relations: ['category', 'model', 'brand', 'driver'], orderBy: ['created_at' => 'desc']);
        $config = styledExportConfig(
            $data,
            title: 'Vehicle Updates',
            summary: ['Total Updates' => $data->count()],
            filters: [
                'Search' => $request->search ?? translate('N/A'),
            ],
            fileName: 'vehicle-updates-' . time() . '.xlsx',
            headings: ['Vehicle ID', 'Date & Time', 'Before Edit', 'After Edit'],
        );
        return exportData($data, $request['file'], 'vehiclemanagement::admin.vehicle.print', $config);
    }
}
