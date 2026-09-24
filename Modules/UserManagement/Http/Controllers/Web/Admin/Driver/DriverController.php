<?php

namespace Modules\UserManagement\Http\Controllers\Web\Admin\Driver;

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
use Modules\TransactionManagement\Service\Interfaces\TransactionServiceInterface;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Http\Requests\DriverAvailabilityScheduleRequest;
use Modules\UserManagement\Http\Requests\DriverSameTimeForEveryDayRequest;
use Modules\UserManagement\Http\Requests\DriverStatusUpdateRequest;
use Modules\UserManagement\Http\Requests\DriverStoreOrUpdateRequest;
use Modules\UserManagement\Lib\AdditionalDataForm;
use Modules\UserManagement\Service\Interfaces\DriverAvailabilityScheduleServiceInterface;
use Modules\UserManagement\Service\Interfaces\DriverDetailServiceInterface;
use Modules\UserManagement\Service\Interfaces\DriverLevelServiceInterface;
use Modules\UserManagement\Service\Interfaces\DriverServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverController extends BaseController
{
    use AuthorizesRequests;

    protected $driverService;
    protected $driverLevelService;
    protected $transactionService;
    protected $activityLogService;
    protected $driverDetailService;
    protected $driverAvailabilityScheduleService;

    public function __construct(
        DriverServiceInterface          $driverService,
        DriverLevelServiceInterface     $driverLevelService,
        TransactionServiceInterface     $transactionService,
        ActivityLogServiceInterface $activityLogService,
        DriverDetailServiceInterface $driverDetailService,
        DriverAvailabilityScheduleServiceInterface $driverAvailabilityScheduleService
    )
    {
        parent::__construct($driverService);
        $this->driverService = $driverService;
        $this->driverLevelService = $driverLevelService;
        $this->transactionService = $transactionService;
        $this->activityLogService = $activityLogService;
        $this->driverDetailService = $driverDetailService;
        $this->driverAvailabilityScheduleService = $driverAvailabilityScheduleService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('user_view');
        $drivers = $this->driverService->index(criteria: $request?->all(), relations: ['level', 'driverTrips', 'driverTripsStatus', 'lastLocations.zone', 'driverDetails'], orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1);
        $statusCounts = $this->driverService->getStatusCounts();
        return view('usermanagement::admin.driver.index', compact('drivers', 'statusCounts'));
    }

    public function create(): Renderable
    {
        $this->authorize('user_add');
        $additionalDataFields = AdditionalDataForm::fields(DRIVER);
        return view('usermanagement::admin.driver.create', compact('additionalDataFields'));
    }

    public function store(DriverStoreOrUpdateRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('user_add');
        $firstLevel = $this->driverLevelService->findOneBy(criteria: ['user_type' => DRIVER, 'sequence' => 1]);
        if (!$firstLevel) {
            if ($request->ajax()) {
                return response()->json([
                    'errors' => [
                        ['error_code' => 'user_level_id', 'message' => LEVEL_403['message']],
                    ],
                ]);
            }

            Toastr::error(LEVEL_403['message']);
            return back();
        }
        $request->merge([
            'user_level_id' => $firstLevel->id
        ]);
        $this->driverService->create(data: $request->validated());
        if ($request->ajax()) {
            return response()->json([
                'successMessage' => DRIVER_STORE_200['message'],
                'redirectUrl' => route('admin.driver.index'),
            ]);
        }

        Toastr::success(DRIVER_STORE_200['message']);
        return redirect(route('admin.driver.index'));

    }

    public function show($id, Request $request): Renderable|RedirectResponse
    {
        $this->authorize('user_view');
        $driver = $this->driverService->findOne(id: $id, relations: ['userAccount', 'receivedReviews', 'driverTrips', 'driverDetails', 'driverTrips']);
        if (!$driver) {
            Toastr::warning(translate("Driver not found"));
            return back();
        }
        AdditionalDataForm::pruneRemovedFields($driver, DRIVER);
        $data = $this->driverService->show(id: $id, data: $request->all());
        $commonData = $data['commonData'];
        $otherData = $data['otherData'];

        return view('usermanagement::admin.driver.details', compact('driver', 'commonData', 'otherData'));

    }

    public function edit($id): Renderable
    {
        $this->authorize('user_edit');
        $driver = $this->driverService
            ->findOneBy(criteria: ['id' => $id, 'user_type' => DRIVER], relations: ['additionalInfo']);
        $additionalDataFields = AdditionalDataForm::fields(DRIVER);
        $additionalData = AdditionalDataForm::pruneRemovedFields($driver, DRIVER, $additionalDataFields);
        return view('usermanagement::admin.driver.edit', compact('driver', 'additionalDataFields', 'additionalData'));
    }

    public function update(DriverStoreOrUpdateRequest $request, $id): RedirectResponse|JsonResponse
    {
        $this->authorize('user_edit');
        $data = array_merge($request->validated(), ['type' => 'web']);
        $this->driverService->update(id: $id, data: $data);
        if ($request->ajax()) {
            return response()->json([
                'successMessage' => DRIVER_UPDATE_200['message'],
                'redirectUrl' => route('admin.driver.edit', ['id' => $id]),
            ]);
        }

        Toastr::success(DRIVER_UPDATE_200['message']);
        return back();
    }

    public function destroy($id): RedirectResponse
    {
        $this->authorize('user_delete');
        $driver = $this->driverService->findOne($id);
        if(count($driver->getDriverLastTrip())!=0|| $driver?->userAccount->payable_balance>0 || $driver?->userAccount->pending_balance>0 || $driver?->userAccount->receivable_balance>0){
            Toastr::success(translate("Sorry you can't delete this driver, because there are ongoing rides or payment due this driver."));
            return back();
        }
        $this->driverService->delete(id: $id);
        $driver->driverIdentityVerification()->delete();
        Toastr::success(DRIVER_DELETE_200['message']);
        return back();
    }

    public function updateStatus(DriverStatusUpdateRequest $request): RedirectResponse
    {
        $this->authorize('user_edit');

        $driver = $this->driverService->findOneBy(criteria: ['id' => $request->id, 'user_type' => DRIVER], relations: ['driverDetails']);
        if (!$driver) {
            Toastr::error(translate('Driver not found'));
            return back();
        }

        if (!$driver->is_active) {
            Toastr::error(translate('You cannot change the status of a suspended driver'));
            return back();
        }

        if ($driver->driverDetails?->isSystemPaused()) {
            Toastr::error($driver->driverDetails->systemPauseMessage());
            return back();
        }

        if ($request->status) {
            $this->driverService->resumeStatus(driver: $driver);
            Toastr::success(translate('Driver status resumed successfully'));
            return back();
        }

        $this->driverService->pauseStatus(driver: $driver, data: $request->validated());
        Toastr::success(translate('Driver status paused successfully'));

        return back();
    }

    public function getAllAjax(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'sometimes'
        ]);
        $drivers = $this->driverService->getDriverWithoutVehicle(criteria:$request->all(),limit: 100, offset:$request['page']??1);
        $mapped = $drivers->map(function ($items) {
            return [
                'text' => $items['first_name'] . ' ' . $items['last_name'] . ' ' . '(' . $items['phone'] . ')',
                'id' => $items['id']
            ];
        });
        if ($request->all_driver) {
            $all_driver = (object)['id' => 0, 'text' => translate('all_driver')];
            $mapped->prepend($all_driver);
        }

        return response()->json($mapped);
    }

    public function getAllAjaxVehicle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'sometimes'
        ]);

        $drivers = $this->driverService->getDriverWithoutVehicle(criteria:$request->all(),limit: 100, offset:$request['page']??1);
        $mapped = $drivers->map(function ($items) {
            return [
                'text' => $items['first_name'] . ' ' . $items['last_name'] . ' ' . '(' . $items['phone'] . ')',
                'id' => $items['id']
            ];
        });
        if ($request->all_driver) {
            $all_driver = (object)['id' => 0, 'text' => translate('all_driver')];
            $mapped->prepend($all_driver);
        }

        return response()->json($mapped);
    }

    public function statistics(Request $request)
    {
        $analytics = $this->driverService->getStatisticsData($request->all());
        $total = $analytics['total'];
        $active = $analytics['active'];
        $inactive = $analytics['inactive'];
        $suspended = $analytics['suspended'];
        $car = $analytics['car'];
        $motor_bike = $analytics['motor_bike'];
        return response()->json(view('usermanagement::admin.driver._statistics',
            compact('total', 'active', 'inactive', 'suspended', 'car', 'motor_bike'))->render());
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('user_export');
        $attributes = [
            'relations' => ['level'],
            // 'query' => $request['query'],
            // 'value' => $request['value'],
        ];

        !is_null($request['search']) ? $attributes['search'] = $request['search'] : '';
        !is_null($request['query']) ? $attributes['query'] = $request['query'] : '';
        !is_null($request['value']) ? $attributes['value'] = $request['value'] : '';

        $request->merge(['relations' => ['level', 'driverTrips', 'driverTripsStatus', 'lastLocations.zone']]);

        $data = $this->driverService->export(criteria: $request->all(), relations: ['level', 'driverTrips', 'driverTripsStatus', 'lastLocations.zone'], orderBy: ['created_at' => 'desc']);
        $config = styledExportConfig(
            $data,
            title: 'Driver List',
            summary: [
                'Total Driver'    => $data->count(),
                'Active Driver'   => $data->where('Status', 'Active')->count(),
                'Inactive Driver' => $data->where('Status', 'Inactive')->count(),
            ],
            filters: [
                'Status'  => $request->status   ?? translate('All'),
                'Search'  => $request->search   ?? translate('N/A'),
            ],
            columnFormats: [
                'Total Trip' => ColumnFormat::INTEGER,
                'Earning'    => ColumnFormat::CURRENCY,
                'Status'     => ColumnFormat::STATUS,
            ],
            fileName: 'drivers-' . time() . '.xlsx',
            headings: ['Name', 'Email', 'Phone', 'Profile Status', 'Level', 'Total Trip', 'Earning', 'Status'],
        );
        return exportData($data, $request['file'], 'usermanagement::admin.driver.print', $config);
    }

    public function driverTransactionExport(Request $request)
    {
        $request->merge([
            'driver_id' => $request['id']
        ]);
        $exportData = $this->transactionService->export(criteria: $request->all(), orderBy: ['created_at' => 'desc']);
        $config = styledExportConfig(
            $exportData,
            title: 'Driver Transactions',
            summary: ['Total Records' => $exportData->count()],
            filters: [
                'Type'   => $request->type    ?? translate('All'),
                'From'   => $request->from    ?? translate('N/A'),
                'To'     => $request->to      ?? translate('N/A'),
                'Search' => $request->search  ?? translate('N/A'),
            ],
            columnFormats: [
                'Credit'           => ColumnFormat::CURRENCY,
                'Debit'            => ColumnFormat::CURRENCY,
                'Balance'          => ColumnFormat::CURRENCY,
                'Added Bonus'      => ColumnFormat::CURRENCY,
                'Transaction Date' => ColumnFormat::DATETIME,
            ],
            fileName: 'driver-transactions-' . time() . '.xlsx',
            headings: ['Transaction Id', 'Reference', 'Type', 'Transaction Date', 'Transaction To', 'Credit', 'Debit', 'Balance'],
        );
        return exportData($exportData, $request['file'], 'usermanagement::admin.driver.transaction.print', $config);
    }

    public function log(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('user_log');
        $request->merge([
            'logable_type' => User::class,
            'user_type' => DRIVER
        ]);
        $logs = $this->activityLogService->log($request->all());
        $file = array_key_exists('file', $request->all()) ? $request['file'] : '';
        return logViewerNew($logs,$file);
    }

    public function trash(Request $request)
    {
        $this->authorize('super-admin');
        $drivers = $this->driverService->trashedData(criteria: $request->all(), relations: ['level', 'lastLocations.zone', 'driverTrips', 'driverTripsStatus'], limit: paginationLimit(), offset:$request['page']??1);
        return view('usermanagement::admin.driver.trashed', compact('drivers'));
    }

    public function restore($id): RedirectResponse
    {
        $this->authorize('super-admin');
        $this->driverService->restoreData(id: $id);
        Toastr::success(DEFAULT_RESTORE_200['message']);
        return redirect()->route('admin.driver.index');
    }

    public function permanentDelete($id)
    {
        $this->authorize('super-admin');
        $this->driverService->permanentDelete(id: $id);
        Toastr::success(DRIVER_DELETE_200['message']);
        return back();
    }

    //identity image change
    public function profileUpdateRequestList(Request $request): Renderable
    {
        $this->authorize('user_edit');
        $request->merge(['pending' => true]);
        $drivers = $this->driverService->index(criteria: $request?->all(), relations: ['level', 'driverTrips', 'driverTripsStatus', 'lastLocations.zone', 'driverDetails'], orderBy : ['created_at' => 'desc'], limit: paginationLimit(), offset:$request['page'] ?? 1);
        return view('usermanagement::admin.driver.profile-update-request', compact('drivers'));
    }

    public function profileUpdateRequestListExport(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('user_edit');
        $request->merge(['pending' => true]);

        $attributes = [
            'relations' => ['level'],
        ];

        !is_null($request['search']) ? $attributes['search'] = $request['search'] : '';
        !is_null($request['query']) ? $attributes['query'] = $request['query'] : '';
        !is_null($request['value']) ? $attributes['value'] = $request['value'] : '';

        $request->merge(['relations' => ['level', 'driverTrips', 'driverTripsStatus', 'lastLocations.zone']]);

        $data = $this->driverService->export(criteria: $request->all(), relations: ['level', 'driverTrips', 'driverTripsStatus', 'lastLocations.zone'],orderBy : ['created_at' => 'desc']);
        $config = styledExportConfig(
            $data,
            title: 'Driver Profile Update Requests',
            summary: [
                'Total Pending'   => $data->count(),
            ],
            filters: [
                'Status' => $request->status   ?? translate('Pending'),
                'Search' => $request->search   ?? translate('N/A'),
            ],
            columnFormats: [
                'Total Trip' => ColumnFormat::INTEGER,
                'Earning'    => ColumnFormat::CURRENCY,
                'Status'     => ColumnFormat::STATUS,
            ],
            fileName: 'driver-profile-update-requests-' . time() . '.xlsx',
            headings: ['Name', 'Email', 'Phone', 'Profile Status', 'Level', 'Total Trip', 'Earning', 'Status'],
        );
        return exportData($data, $request['file'], 'usermanagement::admin.driver.print', $config);
    }

    public function profileUpdateRequestApprovedOrRejected($id,Request $request)
    {
        $this->authorize('user_edit');
        $this->driverService->updateIdentityImage(id: $id,data: $request->all());
        $driver = $this->driverService->findOne(id: $id);
        $sentTime = pushSentTime($driver->updated_at);
        if ($request->status=='approved'){
            $push = getNotification('identity_image_approved');
            sendDeviceNotification(
                fcm_token: $driver?->fcm_token,
                title: translate(key: $push['title'], locale: $driver?->current_language_key),
                description: textVariableDataFormat(value: $push['description'], userName: $driver->first_name . ' ' . $driver->last_name, sentTime: $sentTime, locale: $driver?->current_language_key),
                status: $push['status'],
                notification_type: 'driver',
                action: $push['action'],
                user_id: $driver?->id
            );
            Toastr::success(translate('driver_identity_image_approved_successfully'));
        }else{
            $push = getNotification('identity_image_rejected');
            sendDeviceNotification(
                fcm_token: $driver?->fcm_token,
                title: translate(key: $push['title'], locale: $driver?->current_language_key),
                description: textVariableDataFormat(value: $push['description'], userName: $driver->first_name . ' ' . $driver->last_name, sentTime: $sentTime, locale: $driver?->current_language_key),
                status: $push['status'],
                notification_type: 'driver',
                action: $push['action'],
                user_id: $driver?->id
            );
            Toastr::success(translate('driver_identity_image_rejected_successfully'));
        }
        return redirect()->back();
    }

    public function updateSuspensionStatus(Request $request, $id)
    {
        $this->authorize('user_edit');

        if (!in_array($request->action, [SUSPEND, REACTIVATE])) {
            return back();
        }

        $driver = $this->driverService
            ->findOneBy(criteria: ['id' => $id, 'user_type' => DRIVER], relations: ['driverDetails']);

        if ($request->action == SUSPEND && !$driver->is_active)
        {
            Toastr::error(DRIVER_ALREADY_SUSPENDED['message']);

            return back();
        }

        $this->driverService->changeSuspensionStatus(driver: $driver, action: $request->action);

        Toastr::success($request->action == REACTIVATE ? DRIVER_MARK_AS_UN_SUSPENDED['message'] :DRIVER_MARK_AS_SUSPENDED['message']);

        return back();
    }

    public function markAsVerified($id)
    {
        $this->authorize('user_edit');
        $driver = $this->driverService->findOne(id: $id, relations: ['driverDetails']);
        $this->driverDetailService->updatedBy(criteria: ['user_id' => $driver->id], data: ['is_verified' => 1]);

        Toastr::success(DRIVER_MARK_AS_VERIFIED['message']);

        return back();
    }

    public function storeAvailabilitySchedule($id, DriverAvailabilityScheduleRequest $request): JsonResponse
    {
        $this->authorize('user_edit');
        $this->driverAvailabilityScheduleService->storeSlot(userId: $id, data: $request->validated());

        return response()->json(['message' => translate(DEFAULT_STORE_200['message'])]);
    }

    public function destroyAvailabilitySchedule($id, $scheduleId): JsonResponse
    {
        $this->authorize('user_edit');
        $this->driverAvailabilityScheduleService->deleteSlot(userId: $id, scheduleId: $scheduleId);

        return response()->json(['message' => translate(DEFAULT_DELETE_200['message'])]);
    }

    public function updateSameTimeForEveryDay($id, DriverSameTimeForEveryDayRequest $request): JsonResponse
    {
        $this->authorize('user_edit');
        $this->driverAvailabilityScheduleService->setSameTime(userId: $id, sameTime: (bool)$request->input('same_time_for_every_day'));

        return response()->json(['message' => translate(DEFAULT_UPDATE_200['message'])]);
    }

}
