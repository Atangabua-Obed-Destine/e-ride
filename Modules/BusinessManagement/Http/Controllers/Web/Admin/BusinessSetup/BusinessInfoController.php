<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\BusinessInfoStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\BusinessSettingStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\ExternalConfigurationServiceInterface;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;

class BusinessInfoController extends BaseController
{
    use AuthorizesRequests;

    protected $businessSettingService;
    protected $externalConfigurationService;
    protected $zoneService;

    public function __construct(BusinessSettingServiceInterface $businessSettingService, ExternalConfigurationServiceInterface $externalConfigurationService,
                                ZoneServiceInterface            $zoneService)
    {
        parent::__construct($businessSettingService);
        $this->businessSettingService = $businessSettingService;
        $this->externalConfigurationService = $externalConfigurationService;
        $this->zoneService = $zoneService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('business_view');
        $settings = $this->businessSettingService
            ->getBy(criteria: ['settings_type' => BUSINESS_INFORMATION]);

        return view('businessmanagement::admin.business-setup.index', compact('settings'));
    }

    public function store(BusinessInfoStoreOrUpdateRequest $request)
    {
        $this->authorize('business_edit');
        $this->businessSettingService->storeBusinessInfo($request->validated());
        $activationMode = externalConfig('activation_mode');
        $martBaseUrl = externalConfig('mart_base_url');
        if ($activationMode && $activationMode->value == 1 && $martBaseUrl && $martBaseUrl->value != null) {
            $name = businessConfig('business_name', BUSINESS_INFORMATION)?->value ?? "DriveMond";
            $logo = businessConfig('header_logo', BUSINESS_INFORMATION)?->value ? asset(businessConfig('header_logo', BUSINESS_INFORMATION)?->value) : dynamicAsset('public/assets/admin-module/img/logo.png');
            $cta = $this->businessSettingService->findOneBy(criteria: ['key_name' => CTA, 'settings_type' => LANDING_PAGES_SETTINGS]);

            try {
                $response = Http::post($martBaseUrl->value . '/api/v1/configurations/store', [
                    'drivemond_business_name' => $name,
                    'drivemond_business_logo' => $logo,
                    'drivemond_app_url_android' => $cta?->value && $cta?->value['play_store']['user_download_link'] ? $cta?->value['play_store']['user_download_link'] : "",
                    'drivemond_app_url_ios' => $cta?->value && $cta?->value['app_store']['user_download_link'] ? $cta?->value['app_store']['user_download_link'] : "",
                ]);
            } catch (\Exception $exception) {

            }
        }
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }

    public function updateBusinessSetting(Request $request): JsonResponse
    {
        $this->authorize('business_edit');
        $businessInfo = $this->businessSettingService->findOneBy(criteria: ['key_name' => $request['name'], 'settings_type' => $request['type']]);
        if ($businessInfo) {
            $data = $this->businessSettingService
                ->update(id: $businessInfo->id, data: ['key_name' => $request['name'], 'settings_type' => $request['type'], 'value' => $request['value']]);
        } else {
            $data = $this->businessSettingService
                ->create(data: ['key_name' => $request['name'], 'settings_type' => $request['type'], 'value' => $request['value']]);
        }
        if ($request['type'] == ALL_ZONE_EXTRA_FARE && $request['value'] == 0) {
            $allZones = $this->zoneService->getAll(withTrashed: true);
            if ($allZones) {
                $whereInCriteria = [
                    'id' => $allZones->pluck('id')->toArray(),
                ];
                $this->zoneService->updatedBy(whereInCriteria: $whereInCriteria, data: ['extra_fare_status' => false]);
            }
        }

        if ($request['name'] == 'schedule_trip_status' && $request['value'] == 1) {
            enableCronJobs(commands: ['app:process-scheduled-trips']);
        }

        return response()->json($data);
    }

    public function settings()
    {
        $settings = $this->businessSettingService
            ->getBy(criteria: ['settings_type' => 'business_settings']);
        return view('businessmanagement::admin.business-setup.settings', compact('settings'));
    }

    public function updateSettings(BusinessSettingStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('business_edit');
        $this->businessSettingService->updateSetting($request->validated());
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }

    public function maintenance(Request $request)
    {
        $this->authorize('super-admin');
        if ($request->has('status')) {
            $data = $this->businessSettingService->maintenance(data: $request->all());
            $checkMaintenanceMode = checkMaintenanceMode();
            if ($checkMaintenanceMode['maintenance_status'] == 0) {
                $topic = "driver_maintenance_mode_off";
                $topic1 = "customer_maintenance_mode_off";
                $title = "Server running now";
                $description = "You can work now";
                $type = "maintenance_mode_off";
                sendTopicNotification(topic: $topic, title: $title, description: $description, type: $type);
                sendTopicNotification(topic: $topic1, title: $title, description: $description, type: $type);
            }

            return response()->json($data);
        }
        if (array_key_exists('maintenance_mode', $request->all()) && !(array_key_exists('driver_app', $request->all()) || array_key_exists('user_app', $request->all())))
        {
            Toastr::error('Please select at least one app for maintenance mode.');
            return back();
        }
        $this->businessSettingService->advanceMaintenance(data: $request->all());
        $checkMaintenanceMode = checkMaintenanceMode();

        if (array_key_exists('maintenance_status', $checkMaintenanceMode) && $checkMaintenanceMode['maintenance_status'] == 1) {
            if (array_key_exists('selected_maintenance_system', $checkMaintenanceMode) && array_key_exists('user_app', $checkMaintenanceMode['selected_maintenance_system'])) {
                if ($checkMaintenanceMode['selected_maintenance_system']['user_app'] == 1) {
                    $topicUser = "customer_maintenance_mode_on";
                    $titleUser = "Server is under maintenance";
                    $descriptionUser = "You can not work now";
                    $typeUser = "maintenance_mode_on";
                } else {
                    $topicUser = "customer_maintenance_mode_off";
                    $titleUser = "Server running now";
                    $descriptionUser = "You can work now";
                    $typeUser = "maintenance_mode_off";
                }
                sendTopicNotification(topic: $topicUser, title: $titleUser, description: $descriptionUser, type: $typeUser);
                if ($checkMaintenanceMode['selected_maintenance_system']['driver_app'] == 1) {
                    $topic = "driver_maintenance_mode_on";
                    $title = "Server is under maintenance";
                    $description = "You can not work now";
                    $type = "maintenance_mode_on";
                } else {
                    $topic = "driver_maintenance_mode_off";
                    $title = "Server running now";
                    $description = "You can work now";
                    $type = "maintenance_mode_off";
                }
                sendTopicNotification(topic: $topic, title: $title, description: $description, type: $type);
            }
        }
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }
}
