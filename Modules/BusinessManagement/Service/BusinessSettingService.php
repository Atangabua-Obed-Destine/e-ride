<?php

namespace Modules\BusinessManagement\Service;

use App\Jobs\SendPushNotificationForAllUserJob;
use App\Service\BaseService;
use App\Traits\UnloadedHelpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Modules\BusinessManagement\Lib\AdditionalDataFieldNormalizer;
use Modules\BusinessManagement\Repository\BusinessSettingRepositoryInterface;
use Modules\BusinessManagement\Repository\ExternalConfigurationRepositoryInterface;
use Modules\BusinessManagement\Repository\NotificationSettingRepositoryInterface;
use Modules\BusinessManagement\Repository\SettingRepositoryInterface;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\UserManagement\Repository\UserRepositoryInterface;
use Modules\VehicleManagement\Repository\VehicleCategoryRepositoryInterface;

class BusinessSettingService extends BaseService implements BusinessSettingServiceInterface
{
    protected $businessSettingRepository;
    protected $userRepository;
    protected $externalConfigurationRepository;

    protected $settingRepository;

    public function __construct(BusinessSettingRepositoryInterface     $businessSettingRepository, UserRepositoryInterface $userRepository,
                                ExternalConfigurationRepositoryInterface $externalConfigurationRepository, SettingRepositoryInterface $settingRepository)
    {
        parent::__construct($businessSettingRepository);
        $this->businessSettingRepository = $businessSettingRepository;
        $this->userRepository = $userRepository;
        $this->externalConfigurationRepository = $externalConfigurationRepository;
        $this->settingRepository = $settingRepository;
    }

    public function storeBusinessInfo(array $data)
    {
        $code = 'USD';
        $symbol = "$";

        if (array_key_exists('currency_code', $data)) {
            $code = $data['currency_code'];
        }
        foreach (CURRENCIES as $currency) {
            if ($currency['code'] == $code) {
                $symbol = $currency['symbol'];
                break;
            }
        }

        $data['currency_code'] = $code;
        $data['currency_symbol'] = $symbol;
        $session_keys = [
            'currency_decimal_point', 'currency_symbol_position', 'currency_code', 'header_logo', 'footer_logo', 'favicon',
            'preloader', 'copyright_text', 'time_format', 'currency_symbol', 'business_name', 'business_contact_email', 'business_contact_phone','business_support_email','business_support_phone'
        ];

        foreach ($data as $key => $value) {
            $businessInfo = $this->businessSettingRepository
                ->findOneBy(criteria: ['key_name' => $key, 'settings_type' => BUSINESS_INFORMATION]);
            $images = ['header_logo', 'favicon', 'preloader', 'footer_logo'];
            if (in_array($key, $images)) {
                $value = fileUploader('business/', APPLICATION_IMAGE_FORMAT, $data[$key], $businessInfo->value ?? '');
            }

            $value == 'on' ? $value = 1 : null;

            if (isset($value)) {
                if ($businessInfo) {
                    $this->businessSettingRepository->update(id: $businessInfo->id, data: [
                        'key_name' => $key,
                        'value' => $value,
                        'settings_type' => BUSINESS_INFORMATION
                    ]);
                } else {
                    $this->businessSettingRepository->create(data: [
                        'key_name' => $key,
                        'value' => $value,
                        'settings_type' => BUSINESS_INFORMATION
                    ]);
                }

            }

            if (in_array($key, $session_keys, true)) {
                Session::forget($key);
                Session::put($key, $value);
            }

        }
        $absent_keys = ['email_verification', 'otp_verification'];
        foreach ($absent_keys as $absent) {
            if (!array_key_exists($absent, $data)) {
                $businessInfo = $this->businessSettingRepository
                    ->findOneBy(criteria: ['key_name' => $absent, 'settings_type' => 'business_information']);
                if ($businessInfo) {
                    $this->businessSettingRepository->update(id: $businessInfo->id, data: [
                        'key_name' => $absent,
                        'value' => 0,
                        'settings_type' => BUSINESS_INFORMATION
                    ]);
                } else {
                    $this->businessSettingRepository->create(data: [
                        'key_name' => $absent,
                        'value' => 0,
                        'settings_type' => BUSINESS_INFORMATION
                    ]);
                }
            }
        }
    }

    public function updateSetting(array $data)
    {
        $envFile = app()->environmentFilePath();
        $envWritable = is_file($envFile) && is_readable($envFile) && is_writable($envFile);

        if (array_key_exists('websocket_url', $data)) {
            if ($envWritable) {
                UnloadedHelpers::setEnvironmentValue('PUSHER_HOST', getMainDomain(url('/')));
                UnloadedHelpers::setEnvironmentValue('REVERB_HOST', getMainDomain(url('/')));
            } else {
                Log::warning('Environment file is not writable; skipped updating PUSHER_HOST/REVERB_HOST.');
            }
            $data['websocket_url'] = getMainDomain(url('/'));

        }
        if (array_key_exists('websocket_port', $data)) {
            if ($envWritable) {
                UnloadedHelpers::setEnvironmentValue('PUSHER_PORT', (int)$data['websocket_port']);
                UnloadedHelpers::setEnvironmentValue('REVERB_PORT', (int)$data['websocket_port']);
            } else {
                Log::warning('Environment file is not writable; skipped updating PUSHER_PORT/REVERB_PORT.');
            }
        }
        if (array_key_exists('bid_on_fare', $data)) {
            $data['bid_on_fare'] = 1;
        } else {
            $data['bid_on_fare'] = 0;
        }
        $not_cached = ['pagination_limit'];
        foreach ($data as $key => $value) {
            if (!is_null($value)) {
                $businessSetting = $this->businessSettingRepository
                    ->findOneBy(criteria: ['key_name' => $key, 'settings_type' => BUSINESS_SETTINGS]);
                if ($businessSetting) {
                    $this->businessSettingRepository->update(id: $businessSetting->id, data: [
                        'key_name' => $key,
                        'value' => $value,
                        'settings_type' => BUSINESS_SETTINGS
                    ]);
                } else {
                    $this->businessSettingRepository->create(data: [
                        'key_name' => $key,
                        'value' => $value,
                        'settings_type' => BUSINESS_SETTINGS
                    ]);
                }

            }
            if (!in_array($key, $not_cached)) {
                //putting values on cache
                Cache::put($key, $value);
            }
            if ($key == 'pagination_limit') {
                Session::put('pagination_limit', $value ?? 10);
            }
        }
    }

    public function maintenance(array $data): ?Model
    {
        $maintenanceMode = $this->businessSettingRepository
            ->findOneBy(criteria: ['key_name' => 'maintenance_mode', 'settings_type' => BUSINESS_INFORMATION]);
        if ($maintenanceMode) {
            $maintenanceModeData = $this->businessSettingRepository->update(id: $maintenanceMode->id, data: [
                'key_name' => 'maintenance_mode',
                'value' => $data['status'],
                'settings_type' => BUSINESS_INFORMATION
            ]);
        } else {
            $maintenanceModeData = $this->businessSettingRepository->create(data: [
                'key_name' => 'maintenance_mode',
                'value' => $data['status'],
                'settings_type' => BUSINESS_INFORMATION
            ]);
        }

        return $maintenanceModeData;
    }

    public function advanceMaintenance(array $data)
    {
        $maintenanceMode = $this->businessSettingRepository
            ->findOneBy(criteria: ['key_name' => 'maintenance_mode', 'settings_type' => BUSINESS_INFORMATION]);
        $maintenanceModeData = [
            'key_name' => 'maintenance_mode',
            'value' => array_key_exists('maintenance_mode', $data) ? 1 : 0,
            'settings_type' => BUSINESS_INFORMATION
        ];
        if ($maintenanceMode) {
            $this->businessSettingRepository->update(id: $maintenanceMode->id, data: $maintenanceModeData);
        } else {
            $this->businessSettingRepository->create(data: $maintenanceModeData);
        }
        $selectedSystems = [];
        $systems = ['user_app', 'driver_app'];

        foreach ($systems as $system) {
            if (array_key_exists($system, $data)) {
                $selectedSystems[] = $system;
            }
        }
        $maintenanceMode1 = $this->businessSettingRepository
            ->findOneBy(criteria: ['key_name' => 'maintenance_system_setup', 'settings_type' => BUSINESS_INFORMATION]);
        $maintenanceModeData1 = [
            'key_name' => 'maintenance_system_setup',
            'value' => $selectedSystems,
            'settings_type' => BUSINESS_INFORMATION
        ];
        if ($maintenanceMode1) {
            $this->businessSettingRepository->update(id: $maintenanceMode1->id, data: $maintenanceModeData1);
        } else {
            $this->businessSettingRepository->create(data: $maintenanceModeData1);
        }

        $maintenanceMode2 = $this->businessSettingRepository
            ->findOneBy(criteria: ['key_name' => 'maintenance_duration_setup', 'settings_type' => BUSINESS_INFORMATION]);
        $maintenanceModeData2 = [
            'key_name' => 'maintenance_duration_setup',
            'value' => [
                'maintenance_duration' => $data['maintenance_duration'],
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
            ],
            'settings_type' => BUSINESS_INFORMATION
        ];
        if ($maintenanceMode2) {
            $this->businessSettingRepository->update(id: $maintenanceMode2->id, data: $maintenanceModeData2);
        } else {
            $this->businessSettingRepository->create(data: $maintenanceModeData2);
        }


        $maintenanceMode3 = $this->businessSettingRepository
            ->findOneBy(criteria: ['key_name' => 'maintenance_message_setup', 'settings_type' => BUSINESS_INFORMATION]);
        $maintenanceModeData3 = [
            'key_name' => 'maintenance_message_setup',
            'value' => [
                'business_number' => array_key_exists('business_number', $data) ? 1 : 0,
                'business_email' => array_key_exists('business_email', $data) ? 1 : 0,
                'maintenance_message' => $data['maintenance_message'],
                'message_body' => $data['message_body']
            ],
            'settings_type' => BUSINESS_INFORMATION
        ];
        if ($maintenanceMode3) {
            $this->businessSettingRepository->update(id: $maintenanceMode3->id, data: $maintenanceModeData3);
        } else {
            $this->businessSettingRepository->create(data: $maintenanceModeData3);
        }
    }

    public function storeDriverSetting(array $data)
    {
        if ($data['type'] == 'loyalty_point') {
            $storeData['type'] = 'loyalty_point';
            $storeData['loyalty_points'] = [
                'status' => ($data['loyalty_points']['status'] ?? 0) == 'on' ? 1 : 0,
                'points' => $data['loyalty_points']['value'] ?? 0,
            ];
        } else if ($data['type'] == 'maximum_parcel_request_accept_limit') {
            $storeData['type'] = 'maximum_parcel_request_accept_limit';
            $storeData['maximum_parcel_request_accept_limit'] = [
                'status' => ($data['maximum_parcel_request_accept_limit']['status'] ?? 0) == 'on' ? 1 : 0,
                'limit' => $data['maximum_parcel_request_accept_limit']['value'] ?? 0,
            ];
        }
        foreach ($storeData as $key => $value) {
            $driverSetting = $this->businessSettingRepository
                ->findOneBy(criteria: ['key_name' => $key, 'settings_type' => DRIVER_SETTINGS]);
            if ($driverSetting) {
                $this->businessSettingRepository
                    ->update(id: $driverSetting->id, data: ['key_name' => $key, 'settings_type' => DRIVER_SETTINGS, 'value' => $value]);
            } else {
                $this->businessSettingRepository
                    ->create(data: ['key_name' => $key, 'settings_type' => DRIVER_SETTINGS, 'value' => $value]);
            }
        }
    }

    public function storeVehicleUpdateDriverSetting(array $data)
    {
        if (array_key_exists('update_vehicle', $data)) {
            $value = $data['update_vehicle'];
            $driverSetting = $this->businessSettingRepository
                ->findOneBy(criteria: ['key_name' => 'update_vehicle', 'settings_type' => $data['type']]);
            if ($driverSetting) {
                $this->businessSettingRepository
                    ->update(id: $driverSetting->id, data: ['key_name' => 'update_vehicle', 'settings_type' => $data['type'], 'value' => $value]);
            } else {
                $this->businessSettingRepository
                    ->create(data: ['key_name' => 'update_vehicle', 'settings_type' => $data['type'], 'value' => $value]);
            }
        }

    }

    public function storeCustomerSetting(array $data)
    {
        if ($data['type'] == 'loyalty_point') {

            $storeData['type'] = 'loyalty_point';
            $storeData['loyalty_points'] = [
                'status' => ($data['loyalty_points']['status'] ?? 0) == 'on' ? 1 : 0,
                'points' => $data['loyalty_points']['value'] ?? 0,
            ];
        } else if ($data['type'] == 'wallet') {
            $storeData['customer_wallet'] = [
                'add_fund_status' => ($data['customer_wallet']['add_fund_status'] ?? 0) == 'on' ? 1 : 0,
                'min_deposit_limit' => $data['customer_wallet']['min_deposit_limit'] ?? 0,
            ];
        }

        foreach ($storeData as $key => $value) {
            $driverSetting = $this->businessSettingRepository
                ->findOneBy(criteria: ['key_name' => $key, 'settings_type' => CUSTOMER_SETTINGS]);
            if ($driverSetting) {
                $this->businessSettingRepository
                    ->update(id: $driverSetting->id, data: ['key_name' => $key, 'settings_type' => CUSTOMER_SETTINGS, 'value' => $value]);
            } else {
                $this->businessSettingRepository
                    ->create(data: ['key_name' => $key, 'settings_type' => 'customer_settings', 'value' => $value]);
            }

        }
    }

    public function storeTripFareSetting(array $data)
    {
        if ($data['type'] == TRIP_SETTINGS) {
            if (!array_key_exists('bidding_push_notification', $data)) {
                $data['bidding_push_notification'] = 0;
            }
            if (!array_key_exists('trip_push_notification', $data)) {
                $data['trip_push_notification'] = 0;
            }
            if (!array_key_exists('enable_real_time_location_sharing', $data)) {
                $data['enable_real_time_location_sharing'] = 0;
            } else {
                $data['enable_real_time_location_sharing'] = 1;
            }
            if (!array_key_exists('female_only_ride_service', $data)) {
                $data['female_only_ride_service'] = 0;
            } else {
                $data['female_only_ride_service'] = 1;
            }
        }
        foreach ($data as $key => $value) {
            $driverSetting = $this->businessSettingRepository
                ->findOneBy(criteria: ['key_name' => $key, 'settings_type' => TRIP_SETTINGS]);
            if ($driverSetting) {
                $this->businessSettingRepository
                    ->update(id: $driverSetting->id, data: ['key_name' => $key, 'settings_type' => TRIP_SETTINGS, 'value' => $value]);
            } else {
                $this->businessSettingRepository
                    ->create(data: ['key_name' => $key, 'settings_type' => TRIP_SETTINGS, 'value' => $value]);
            }
            Cache::put($key, $value);
        }
        $absent_keys = ['driver_otp_confirmation_for_trip', 'add_intermediate_points'];
        foreach ($absent_keys as $absent) {
            if (!array_key_exists($absent, $data)) {
                $businessInfo = $this->businessSettingRepository
                    ->findOneBy(criteria: ['key_name' => $absent, 'settings_type' => TRIP_SETTINGS]);
                if ($businessInfo) {
                    $this->businessSettingRepository->update(id: $businessInfo->id, data: [
                        'key_name' => $absent,
                        'value' => 0,
                        'settings_type' => TRIP_SETTINGS
                    ]);
                } else {
                    $this->businessSettingRepository->create(data: [
                        'key_name' => $absent,
                        'value' => 0,
                        'settings_type' => TRIP_SETTINGS
                    ]);
                }
            }
        }
    }

    public function storeParcelSetting(array $data)
    {
        if (array_key_exists('parcel_return_time_fee_status', $data)) {
            $parcelReturnTimeFeeStatus = 1;
        } else {
            $parcelReturnTimeFeeStatus = 0;
        }
        $driverSetting = $this->businessSettingRepository
            ->findOneBy(criteria: ['key_name' => 'parcel_return_time_fee_status', 'settings_type' => PARCEL_SETTINGS]);
        if ($driverSetting) {
            $this->businessSettingRepository
                ->update(id: $driverSetting->id, data: ['key_name' => 'parcel_return_time_fee_status', 'settings_type' => PARCEL_SETTINGS, 'value' => $parcelReturnTimeFeeStatus]);
        } else {
            $this->businessSettingRepository
                ->create(data: ['key_name' => 'parcel_return_time_fee_status', 'settings_type' => PARCEL_SETTINGS, 'value' => $parcelReturnTimeFeeStatus]);
        }
        if (array_key_exists('do_not_charge_customer_return_fee', $data))
        {
            $data['do_not_charge_customer_return_fee'] = '1';
        } else {
            $data['do_not_charge_customer_return_fee'] = '0';
        }
        foreach ($data as $key => $value) {
            if ($key != 'parcel_return_time_fee_status') {
                $driverSetting = $this->businessSettingRepository
                    ->findOneBy(criteria: ['key_name' => $key, 'settings_type' => PARCEL_SETTINGS]);
                if ($driverSetting) {
                    $this->businessSettingRepository
                        ->update(id: $driverSetting->id, data: ['key_name' => $key, 'settings_type' => PARCEL_SETTINGS, 'value' => $value]);
                } else {
                    $this->businessSettingRepository
                        ->create(data: ['key_name' => $key, 'settings_type' => PARCEL_SETTINGS, 'value' => $value]);
                }
                Cache::put($key, $value);
            }
        }
    }

    public function storeParcelTrackingSetting(array $data)
    {
        if (array_key_exists('parcel_tracking_status', $data)) {
            $parcelTrackingStatus = 1;
        } else {
            $parcelTrackingStatus = 0;
        }
        $parcelTrackingStatusSetting = $this->businessSettingRepository
            ->findOneBy(criteria: ['key_name' => 'parcel_tracking_status', 'settings_type' => PARCEL_SETTINGS]);
        if ($parcelTrackingStatusSetting) {
            $this->businessSettingRepository
                ->update(id: $parcelTrackingStatusSetting->id, data: ['key_name' => 'parcel_tracking_status', 'settings_type' => PARCEL_SETTINGS, 'value' => $parcelTrackingStatus]);
        } else {
            $this->businessSettingRepository
                ->create(data: ['key_name' => 'parcel_tracking_status', 'settings_type' => PARCEL_SETTINGS, 'value' => $parcelTrackingStatusSetting]);
        }
        $parcelTrackingMessageSetting = $this->businessSettingRepository
            ->findOneBy(criteria: ['key_name' => 'parcel_tracking_message', 'settings_type' => PARCEL_SETTINGS]);
        if ($parcelTrackingMessageSetting) {
            $this->businessSettingRepository
                ->update(id: $parcelTrackingMessageSetting->id, data: ['key_name' => 'parcel_tracking_message', 'settings_type' => PARCEL_SETTINGS, 'value' => $data['parcel_tracking_message']]);
        } else {
            $this->businessSettingRepository
                ->create(data: ['key_name' => 'parcel_tracking_message', 'settings_type' => PARCEL_SETTINGS, 'value' => $data['parcel_tracking_message']]);
        }
    }

    public function storeBusinessPage(array $data)
    {
        $value = [];
        $page = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => $data['type'],
            'settings_type' => PAGES_SETTINGS
        ]);
        if (array_key_exists('image', $data)) {
            $fileName = fileUploader('business/pages/', APPLICATION_IMAGE_FORMAT, $data['image'], $page->value['image'] ?? '');
            $value['image'] = $fileName;
        } else {
            $value['image'] = $page->value['image'] ?? '';
        }

        $value['name'] = $data['type'];
        $value['short_description'] = $data['short_description'];
        $value['long_description'] = $data['long_description'];
        if ($page) {
            $page = $this->businessSettingRepository->update(id: $page->id, data: ['key_name' => $data['type'], 'settings_type' => PAGES_SETTINGS, 'value' => $value]);
        } else {
            $page = $this->businessSettingRepository
                ->create(data: ['key_name' => $data['type'], 'settings_type' => PAGES_SETTINGS, 'value' => $value]);
        }
        //notify
        if (in_array($data['type'], ['privacy_policy', 'terms_and_conditions', 'legal'])) {
            $users = $this->userRepository->getBy(criteria: ['is_active' => 1], whereInCriteria: ['user_type' => [CUSTOMER, DRIVER]]);
            if (!empty($users)) {
                $notify = [];
                foreach ($users as $key => $user) {
                    if ($user?->fcm_token) {
                        $notify[$key]['user_id'] = $user->id;
                    }

                }
                if ($data['type'] == 'legal') {
                    $push = getNotification('legal_updated');
                    $notification = [
                        'title' => $push['title'],
                        'description' => $push['description'],
                        'status' => $push['status'],
                        'notification_type' => 'business_page',
                        'action' => $push['action']
                    ];
                    if (!empty($notify)) {
                        dispatch(new SendPushNotificationForAllUserJob($notification, $users))->onQueue('high');
                    }
                }
                if ($data['type'] == 'terms_and_conditions') {
                    $push = getNotification('terms_and_conditions_updated');
                    $notification = [
                        'title' => $push['title'],
                        'description' => $push['description'],
                        'notification_type' => 'business_page',
                        'status' => $push['status'],
                        'action' => $push['action']
                    ];
                    if (!empty($notify)) {
                        dispatch(new SendPushNotificationForAllUserJob($notification, $users))->onQueue('high');
                    }
                }
                if ($data['type'] == 'privacy_policy') {
                    $push = getNotification('privacy_policy_updated');

                    $notification = [
                        'title' => $push['title'],
                        'description' => $push['description'],
                        'notification_type' => 'business_page',
                        'status' => $push['status'],
                        'action' => $push['action']
                    ];
                    if (!empty($notify)) {
                        dispatch(new SendPushNotificationForAllUserJob($notification, $users))->onQueue('high');
                    }
                }

            }
        }
        //notify
    }

    public function storeLandingPageIntroSection(array $data)
    {
        $introSection = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => $data['type'],
            'settings_type' => LANDING_PAGES_SETTINGS
        ]);
        if ($data['type'] === INTRO_SECTION) {
            $value = ['title' => $data['title'], 'sub_title' => $data['sub_title']];
        }
        if ($data['type'] === INTRO_SECTION_IMAGE) {
            if (array_key_exists('background_image', $data)) {
                $fileName = fileUploader('business/landing-pages/intro-section/', $data['background_image']->extension(), $data['background_image'], $introSection->value['background_image'] ?? '');
                $value['background_image'] = $fileName;
            } else {
                $value['background_image'] = $introSection->value['background_image'] ?? '';
            }
        }
        if ($introSection) {
            $this->businessSettingRepository->update(id: $introSection->id, data: ['key_name' => $data['type'], 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => $data['type'], 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        }
    }

    public function storeLandingPageOurSolutionsSection(array $data): void
    {
        $ourSolutionsSection = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => $data['type'],
            'settings_type' => LANDING_PAGES_SETTINGS
        ]);
        if ($data['type'] === OUR_SOLUTIONS_SECTION) {
            $value = ['title' => $data['title'], 'sub_title' => $data['sub_title']];
        }
        if ($data['type'] === OUR_SOLUTIONS_DATA) {
            if (array_key_exists('background_image', $data)) {
                $fileName = fileUploader('business/landing-pages/intro-section/', $data['background_image']->extension(), $data['background_image'], $ourSolutionsSection->value['background_image'] ?? '');
                $value['background_image'] = $fileName;
            } else {
                $value['background_image'] = $ourSolutionsSection->value['background_image'] ?? '';
            }
        }

        if ($ourSolutionsSection) {
            $this->businessSettingRepository->update(id: $ourSolutionsSection['id'], data: ['key_name' => $data['type'], 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => $data['type'], 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        }
    }

    public function storeLandingPageOurSolutionsData(array $data): void
    {
        $value = [
            'title' => $data['title'],
            'description' => $data['description'],
            'status' => 1,
        ];
        if (array_key_exists('id', $data)) {
            $attributes = ['id' => $data['id'], 'key_name' => OUR_SOLUTIONS_DATA, 'settings_type' => LANDING_PAGES_SETTINGS];
            $ourSolutionData = $this->businessSettingRepository->findOneBy(criteria: $attributes);
        }

        if (array_key_exists('image', $data)) {
            $fileName = fileUploader('business/landing-pages/our-solutions/', $data['image']->extension(), $data['image'], (array_key_exists('id', $data) && $ourSolutionData?->value['image'] ? $ourSolutionData?->value['image'] : ''));
            $value['image'] = $fileName;
        }
        if (array_key_exists('id', $data)) {
            if (!array_key_exists('image', $data)) {
                $value['image'] = $ourSolutionData?->value['image'] ?? '';
            }
            $this->businessSettingRepository->update(id: $data['id'], data: ['key_name' => OUR_SOLUTIONS_DATA, 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);

        } else {
            $this->businessSettingRepository->create(data: ['key_name' => OUR_SOLUTIONS_DATA, 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        }
    }

    public function statusChangeOurSolutions(string|int $id, array $data): ?Model
    {
        $attributes = ['id' => $id, 'key_name' => OUR_SOLUTIONS_DATA, 'settings_type' => LANDING_PAGES_SETTINGS];
        $ourSolutions = $this->businessSettingRepository->findOneBy(criteria: $attributes);
        $value = [
            'title' => $ourSolutions?->value['title'],
            'description' => $ourSolutions?->value['description'],
            'image' => $ourSolutions?->value['image'] ?? '',
            'status' => $data['status'] == "0" ? $data['status'] : "1"
        ];
        return $this->businessSettingRepository->update(id: $id, data: ['key_name' => OUR_SOLUTIONS_DATA, 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
    }

    public function deleteOurSolutions(string|int $id): bool
    {
        $attributes = ['id' => $id, 'key_name' => OUR_SOLUTIONS_DATA, 'settings_type' => LANDING_PAGES_SETTINGS];
        $ourSolutions = $this->businessSettingRepository->findOneBy(criteria: $attributes);
        $image = $ourSolutions?->value['image'] ?? '';
        if ($image) {
            fileRemover('business/landing-pages/our-solutions/', $image);
        }
        return $this->businessSettingRepository->delete(id: $id);
    }

    public function storeLandingPageBusinessStatistics(array $data): void
    {
        $businessStatistic = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => BUSINESS_STATISTICS,
            'settings_type' => LANDING_PAGES_SETTINGS
        ]);
        $value = [];
        //start total download
        if (array_key_exists('total_download_image', $data)) {
            $fileName = fileUploader('business/landing-pages/business-statistics/total-download/', $data['total_download_image']->extension(), $data['total_download_image'], $businessStatistic?->value['total_download']['image'] ?? '');
            $value['total_download']['image'] = $fileName;
        } else {
            $value['total_download']['image'] = $businessStatistic?->value['total_download']['image'] ?? '';
        }
        $value['total_download']['count'] = $data['total_download_count'];
        $value['total_download']['content'] = $data['total_download_content'];
        //end total download

        //start complete ride
        if (array_key_exists('complete_ride_image', $data)) {
            $fileName = fileUploader('business/landing-pages/business-statistics/complete-ride/', $data['complete_ride_image']->extension(), $data['complete_ride_image'], $businessStatistic?->value['complete_ride']['image'] ?? '');
            $value['complete_ride']['image'] = $fileName;
        } else {
            $value['complete_ride']['image'] = $businessStatistic?->value['complete_ride']['image'] ?? '';
        }
        $value['complete_ride']['count'] = $data['complete_ride_count'];
        $value['complete_ride']['content'] = $data['complete_ride_content'];
        //end complete ride

        //start happy customer
        if (array_key_exists('happy_customer_image', $data)) {
            $fileName = fileUploader('business/landing-pages/business-statistics/happy-customer/', $data['happy_customer_image']->extension(), $data['happy_customer_image'], $businessStatistic?->value['happy_customer']['image'] ?? '');
            $value['happy_customer']['image'] = $fileName;
        } else {
            $value['happy_customer']['image'] = $businessStatistic?->value['happy_customer']['image'] ?? '';
        }
        $value['happy_customer']['count'] = $data['happy_customer_count'];
        $value['happy_customer']['content'] = $data['happy_customer_content'];
        //end happy customer

        //start support
        if (array_key_exists('support_image', $data)) {
            $fileName = fileUploader('business/landing-pages/business-statistics/support/', $data['support_image']->extension(), $data['support_image'], $businessStatistic?->value['support']['image'] ?? '');
            $value['support']['image'] = $fileName;
        } else {
            $value['support']['image'] = $businessStatistic?->value['support']['image'] ?? '';
        }
        $value['support']['title'] = $data['support_title'];
        $value['support']['content'] = $data['support_content'];
        //end support
        if ($businessStatistic) {
            $this->businessSettingRepository->update(id: $businessStatistic->id, data: ['key_name' => BUSINESS_STATISTICS, 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => BUSINESS_STATISTICS, 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        }
    }

    public function storeLandingPageEarnMoney(array $data)
    {
        $earnMoney = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => $data['type'],
            'settings_type' => LANDING_PAGES_SETTINGS
        ]);
        if ($data['type'] === EARN_MONEY) {
            $value = ['title' => $data['title'], 'sub_title' => $data['sub_title']];
        }
        if ($data['type'] === EARN_MONEY_IMAGE) {

            if (array_key_exists('image', $data)) {
                $fileName = fileUploader('business/landing-pages/earn-money/', $data['image']->extension(), $data['image'], $earnMoney->value['image'] ?? '');
                $value['image'] = $fileName;
            } else {
                $value['image'] = $earnMoney->value['image'] ?? '';
            }
        }
        if ($earnMoney) {
            $this->businessSettingRepository->update(id: $earnMoney->id, data: ['key_name' => $data['type'], 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => $data['type'], 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        }
    }

    public function storeLandingPageTestimonial(array $data)
    {
        $value = [];
        $value['reviewer_name'] = $data['reviewer_name'];
        $value['designation'] = $data['designation'];
        $value['rating'] = $data['rating'];
        $value['review'] = $data['review'];
        $value['status'] = "1";

        if (array_key_exists('id', $data)) {
            $attributes = ['id' => $data['id'], 'key_name' => TESTIMONIAL, 'settings_type' => LANDING_PAGES_SETTINGS];
            $testimonial = $this->businessSettingRepository->findOneBy(criteria: $attributes);
        }

        if (array_key_exists('reviewer_image', $data)) {
            $fileName = fileUploader('business/landing-pages/testimonial/', $data['reviewer_image']->extension(), $data['reviewer_image'], (array_key_exists('id', $data) && $testimonial?->value['reviewer_image'] ? $testimonial?->value['reviewer_image'] : ''));
            $value['reviewer_image'] = $fileName;
        }
        if (array_key_exists('id', $data)) {
            if (!array_key_exists('reviewer_image', $data)) {
                $value['reviewer_image'] = $testimonial->value['reviewer_image'] ?? '';
            }
            $this->businessSettingRepository->update(id: $data['id'], data: ['key_name' => TESTIMONIAL, 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);

        } else {
            $this->businessSettingRepository->create(data: ['key_name' => TESTIMONIAL, 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        }
    }

    public function statusChange(string|int $id, array $data): ?Model
    {
        $attributes = ['id' => $id, 'key_name' => TESTIMONIAL, 'settings_type' => LANDING_PAGES_SETTINGS];
        $testimonial = $this->businessSettingRepository->findOneBy(criteria: $attributes);
        $value = [];
        $value['reviewer_name'] = $testimonial?->value['reviewer_name'];
        $value['designation'] = $testimonial?->value['designation'];
        $value['rating'] = $testimonial?->value['rating'];
        $value['review'] = $testimonial?->value['review'];
        $value['reviewer_image'] = $testimonial?->value['reviewer_image'] ?? '';
        $value['status'] = $data['status'] == "0" ? $data['status'] : "1";
        return $this->businessSettingRepository->update(id: $id, data: ['key_name' => TESTIMONIAL, 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
    }

    public function storeLandingPageCTA(array $data)
    {
        $cta = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => $data['type'],
            'settings_type' => LANDING_PAGES_SETTINGS
        ]);
        if ($data['type'] === CTA) {
            $value['title'] = $data['title'];
            $value['sub_title'] = $data['sub_title'];
            $value['play_store']['user_download_link'] = $data['play_store_user_download_link'];
            $value['play_store']['driver_download_link'] = $data['play_store_driver_download_link'];
            $value['app_store']['user_download_link'] = $data['app_store_user_download_link'];
            $value['app_store']['driver_download_link'] = $data['app_store_driver_download_link'];
        } else {
            if (array_key_exists('image', $data)) {
                $fileName = fileUploader('business/landing-pages/cta/', $data['image']->extension(), $data['image'], $cta->value['image'] ?? '');
                $value['image'] = $fileName;
            } else {
                $value['image'] = $file->value['image'] ?? '';
            }
            if (array_key_exists('background_image', $data)) {
                $fileName = fileUploader('business/landing-pages/cta/', $data['background_image']->extension(), $data['background_image'], $cta->value['background_image'] ?? '');
                $value['background_image'] = $fileName;
            } else {
                $value['background_image'] = $cta->value['background_image'] ?? '';
            }
        }
        if ($cta) {
            $this->businessSettingRepository->update(id: $cta->id, data: ['key_name' => $data['type'], 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => $data['type'], 'settings_type' => LANDING_PAGES_SETTINGS, 'value' => $value]);
        }
    }

    public function storeEmailConfig(array $data)
    {
        $emailConfig = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => EMAIL_CONFIG,
            'settings_type' => EMAIL_CONFIG]);
        if ($emailConfig) {
            $this->businessSettingRepository->update(id: $emailConfig->id, data: ['key_name' => EMAIL_CONFIG, 'settings_type' => EMAIL_CONFIG, 'value' => $data]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => EMAIL_CONFIG, 'settings_type' => EMAIL_CONFIG, 'value' => $data]);
        }
    }

    public function storeGoogleMapApi(array $data)
    {
        $googleMapApi = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => GOOGLE_MAP_API,
            'settings_type' => GOOGLE_MAP_API
        ]);
        if ($googleMapApi) {
            $this->businessSettingRepository->update(id: $googleMapApi->id, data: ['key_name' => GOOGLE_MAP_API, 'settings_type' => GOOGLE_MAP_API, 'value' => $data]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => GOOGLE_MAP_API, 'settings_type' => GOOGLE_MAP_API, 'value' => $data]);
        }
    }

    public function storeRecaptha(array $data)
    {
        $recaptcha = $this->businessSettingRepository->findOneBy(criteria: [
            'settings_type' => RECAPTCHA,
            'key_name' => RECAPTCHA
        ]);
        if ($recaptcha) {
            $this->businessSettingRepository->update(id: $recaptcha->id, data: ['key_name' => RECAPTCHA, 'settings_type' => RECAPTCHA, 'value' => $data]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => RECAPTCHA, 'settings_type' => RECAPTCHA, 'value' => $data]);
        }
    }

    public function storeAppVersion(array $data)
    {
        $customerAppForAndroid = $this->businessSettingRepository->findOneBy(criteria: [
            'settings_type' => APP_VERSION,
            'key_name' => CUSTOMER_APP_VERSION_CONTROL_FOR_ANDROID
        ]);
        $customerAppForAndroidData = [
            'minimum_app_version' => $data['minimum_customer_app_version_for_android'],
            'app_url' => $data['customer_app_url_for_android'],
        ];
        if ($customerAppForAndroid) {
            $this->businessSettingRepository->update(id: $customerAppForAndroid->id, data: ['key_name' => CUSTOMER_APP_VERSION_CONTROL_FOR_ANDROID, 'settings_type' => APP_VERSION, 'value' => $customerAppForAndroidData]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => CUSTOMER_APP_VERSION_CONTROL_FOR_ANDROID, 'settings_type' => APP_VERSION, 'value' => $customerAppForAndroidData]);
        }
        $customerAppForIos = $this->businessSettingRepository->findOneBy(criteria: [
            'settings_type' => APP_VERSION,
            'key_name' => CUSTOMER_APP_VERSION_CONTROL_FOR_IOS
        ]);
        $customerAppForIosData = [
            'minimum_app_version' => $data['minimum_customer_app_version_for_ios'],
            'app_url' => $data['customer_app_url_for_ios'],
        ];
        if ($customerAppForIos) {
            $this->businessSettingRepository->update(id: $customerAppForIos->id, data: ['key_name' => CUSTOMER_APP_VERSION_CONTROL_FOR_IOS, 'settings_type' => APP_VERSION, 'value' => $customerAppForIosData]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => CUSTOMER_APP_VERSION_CONTROL_FOR_IOS, 'settings_type' => APP_VERSION, 'value' => $customerAppForIosData]);
        }
        $driverAppForAndroid = $this->businessSettingRepository->findOneBy(criteria: [
            'settings_type' => APP_VERSION,
            'key_name' => DRIVER_APP_VERSION_CONTROL_FOR_ANDROID
        ]);
        $driverAppForAndroidData = [
            'minimum_app_version' => $data['minimum_driver_app_version_for_android'],
            'app_url' => $data['driver_app_url_for_android'],
        ];
        if ($driverAppForAndroid) {
            $this->businessSettingRepository->update(id: $driverAppForAndroid->id, data: ['key_name' => DRIVER_APP_VERSION_CONTROL_FOR_ANDROID, 'settings_type' => APP_VERSION, 'value' => $driverAppForAndroidData]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => DRIVER_APP_VERSION_CONTROL_FOR_ANDROID, 'settings_type' => APP_VERSION, 'value' => $driverAppForAndroidData]);
        }
        $driverAppForIos = $this->businessSettingRepository->findOneBy(criteria: [
            'settings_type' => APP_VERSION,
            'key_name' => DRIVER_APP_VERSION_CONTROL_FOR_IOS
        ]);
        $driverAppForIosData = [
            'minimum_app_version' => $data['minimum_driver_app_version_for_ios'],
            'app_url' => $data['driver_app_url_for_ios'],
        ];
        if ($driverAppForIos) {
            $this->businessSettingRepository->update(id: $driverAppForIos->id, data: ['key_name' => DRIVER_APP_VERSION_CONTROL_FOR_IOS, 'settings_type' => APP_VERSION, 'value' => $driverAppForIosData]);
        } else {
            $this->businessSettingRepository->create(data: ['key_name' => DRIVER_APP_VERSION_CONTROL_FOR_IOS, 'settings_type' => APP_VERSION, 'value' => $driverAppForIosData]);
        }
    }

    public function storeAllZoneExtraFare(array $data)
    {
        if (array_key_exists('zone_edit', $data) && $data['zone_edit']) {
            $allZoneExtraFareStatus = $this->businessSettingRepository->findOneBy(criteria: [
                'settings_type' => ALL_ZONE_EXTRA_FARE,
                'key_name' => 'extra_fare_status'
            ]);
            $allZoneExtraFareStatusData = [
                'key_name' => 'extra_fare_status',
                'value' => 0,
                'settings_type' => ALL_ZONE_EXTRA_FARE
            ];

            if ($allZoneExtraFareStatus) {
                $this->businessSettingRepository->update(id: $allZoneExtraFareStatus->id, data: $allZoneExtraFareStatusData);
            } else {
                $this->businessSettingRepository->create(data: $allZoneExtraFareStatusData);
            }
        } else {
            $allZoneExtraFareStatus = $this->businessSettingRepository->findOneBy(criteria: [
                'settings_type' => ALL_ZONE_EXTRA_FARE,
                'key_name' => 'extra_fare_status'
            ]);
            $allZoneExtraFareStatusData = [
                'key_name' => 'extra_fare_status',
                'value' => array_key_exists('all_zone_extra_fare_status', $data) ? 1 : 0,
                'settings_type' => ALL_ZONE_EXTRA_FARE
            ];

            if ($allZoneExtraFareStatus) {
                $this->businessSettingRepository->update(id: $allZoneExtraFareStatus->id, data: $allZoneExtraFareStatusData);
            } else {
                $this->businessSettingRepository->create(data: $allZoneExtraFareStatusData);
            }
            $allZoneExtraFareFee = $this->businessSettingRepository->findOneBy(criteria: [
                'settings_type' => ALL_ZONE_EXTRA_FARE,
                'key_name' => 'extra_fare_fee'
            ]);
            $allZoneExtraFareFeeData = [
                'key_name' => 'extra_fare_fee',
                'value' => $data['all_zone_extra_fare_fee'],
                'settings_type' => ALL_ZONE_EXTRA_FARE
            ];

            if ($allZoneExtraFareFee) {
                $this->businessSettingRepository->update(id: $allZoneExtraFareFee->id, data: $allZoneExtraFareFeeData);
            } else {
                $this->businessSettingRepository->create(data: $allZoneExtraFareFeeData);
            }

            $allZoneExtraFareReason = $this->businessSettingRepository->findOneBy(criteria: [
                'settings_type' => ALL_ZONE_EXTRA_FARE,
                'key_name' => 'extra_fare_reason'
            ]);
            $allZoneExtraFareReasonData = [
                'key_name' => 'extra_fare_reason',
                'value' => $data['all_zone_extra_fare_reason'],
                'settings_type' => ALL_ZONE_EXTRA_FARE
            ];

            if ($allZoneExtraFareReason) {
                $this->businessSettingRepository->update(id: $allZoneExtraFareReason->id, data: $allZoneExtraFareReasonData);
            } else {
                $this->businessSettingRepository->create(data: $allZoneExtraFareReasonData);
            }
        }

    }

    public function storeParcelRefundSetting(array $data)
    {
        if (array_key_exists('parcel_refund_status', $data)) {
            $parcelRefundStatus = 1;
        } else {
            $parcelRefundStatus = 0;
        }
        $parcelRefundSetting = $this->businessSettingRepository
            ->findOneBy(criteria: ['key_name' => 'parcel_refund_status', 'settings_type' => PARCEL_SETTINGS]);
        if ($parcelRefundSetting) {
            $this->businessSettingRepository
                ->update(id: $parcelRefundSetting->id, data: ['key_name' => 'parcel_refund_status', 'settings_type' => PARCEL_SETTINGS, 'value' => $parcelRefundStatus]);
        } else {
            $this->businessSettingRepository
                ->create(data: ['key_name' => 'parcel_refund_status', 'settings_type' => PARCEL_SETTINGS, 'value' => $parcelRefundStatus]);
        }
        foreach ($data as $key => $value) {
            if ($key != 'parcel_refund_status') {
                $refundSetting = $this->businessSettingRepository
                    ->findOneBy(criteria: ['key_name' => $key, 'settings_type' => PARCEL_SETTINGS]);
                if ($refundSetting) {
                    $this->businessSettingRepository
                        ->update(id: $refundSetting->id, data: ['key_name' => $key, 'settings_type' => PARCEL_SETTINGS, 'value' => $value]);
                } else {
                    $this->businessSettingRepository
                        ->create(data: ['key_name' => $key, 'settings_type' => PARCEL_SETTINGS, 'value' => $value]);
                }
            }
        }
    }

    public function storeOrUpdateFirebaseOtpSetting(array $data)
    {
        if (array_key_exists('firebase_otp_verification_status', $data)) {
            $firebaseOtpVerificationStatusData = 1;
        } else {
            $firebaseOtpVerificationStatusData = 0;
        }
        $firebaseOtpVerificationStatus = $this->businessSettingRepository
            ->findOneBy(criteria: ['key_name' => 'firebase_otp_verification_status', 'settings_type' => FIREBASE_OTP]);
        if ($firebaseOtpVerificationStatus) {
            $this->businessSettingRepository
                ->update(id: $firebaseOtpVerificationStatus->id, data: ['key_name' => 'firebase_otp_verification_status', 'settings_type' => FIREBASE_OTP, 'value' => $firebaseOtpVerificationStatusData]);
        } else {
            $this->businessSettingRepository
                ->create(data: ['key_name' => 'firebase_otp_verification_status', 'settings_type' => FIREBASE_OTP, 'value' => $firebaseOtpVerificationStatusData]);
        }
        if ($data['firebase_otp_web_api_key']) {
            $firebaseOtpWebApiKey = $this->businessSettingRepository
                ->findOneBy(criteria: ['key_name' => 'firebase_otp_web_api_key', 'settings_type' => FIREBASE_OTP]);
            if ($firebaseOtpWebApiKey) {
                $this->businessSettingRepository
                    ->update(id: $firebaseOtpWebApiKey->id, data: ['key_name' => 'firebase_otp_web_api_key', 'settings_type' => FIREBASE_OTP, 'value' => $data['firebase_otp_web_api_key']]);
            } else {
                $this->businessSettingRepository
                    ->create(data: ['key_name' => 'firebase_otp_web_api_key', 'settings_type' => FIREBASE_OTP, 'value' => $data['firebase_otp_web_api_key']]);
            }
        }

        if (!isOtpEnabled()) {
            $this->updateUserLoginToDefaultOptions();
        }
    }

    public function storeMaximumParcelWeight(array $data)
    {
        $maxParcelWeight = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => 'max_parcel_weight',
            'settings_type' => PARCEL_SETTINGS
        ]);

        $maxParcelWeightValue = ($data['max_parcel_weight_status'] === 'on') ? $data['max_parcel_weight'] : null;
        if ($maxParcelWeight) {
            $this->businessSettingRepository->update(
                id: $maxParcelWeight->id,
                data: [
                    'key_name' => 'max_parcel_weight',
                    'settings_type' => PARCEL_SETTINGS,
                    'value' => $maxParcelWeightValue,
                ]
            );
        } else {
            $this->businessSettingRepository->create(
                data: [
                    'key_name' => 'max_parcel_weight',
                    'settings_type' => PARCEL_SETTINGS,
                    'value' => $maxParcelWeightValue,
                ]
            );
        }
    }

    public function storeSafetyFeature(array $data)
    {
        $forTripDelayData = [
            'minimum_delay_time' => (double)$data['minimum_delay_time'],
            'time_format' => $data['time_format'],
        ];
        $forTripDelay = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => 'for_trip_delay',
            'settings_type' => SAFETY_FEATURE_SETTINGS
        ]);

        if ($forTripDelay) {
            $this->businessSettingRepository->update(
                id: $forTripDelay->id,
                data: [
                    'key_name' => 'for_trip_delay',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $forTripDelayData,
                ]
            );
        } else {
            $this->businessSettingRepository->create(
                data: [
                    'key_name' => 'for_trip_delay',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $forTripDelayData,
                ]
            );
        }


        $afterTripCompleteData = [
            'safety_feature_active_status' => array_key_exists('safety_feature_active_status', $data) ? 1 : 0,
            'set_time' => (double)$data['set_time'],
        ];

        $afterTripComplete = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => 'after_trip_complete',
            'settings_type' => SAFETY_FEATURE_SETTINGS
        ]);

        if ($afterTripComplete) {
            $this->businessSettingRepository->update(
                id: $afterTripComplete->id,
                data: [
                    'key_name' => 'after_trip_complete',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $afterTripCompleteData,
                ]
            );
        } else {
            $this->businessSettingRepository->create(
                data: [
                    'key_name' => 'after_trip_complete',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $afterTripCompleteData,
                ]
            );
        }

        $afterTripCompleteTimeFormat = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => 'after_trip_complete_time_format',
            'settings_type' => SAFETY_FEATURE_SETTINGS
        ]);

        if ($afterTripCompleteTimeFormat) {
            $this->businessSettingRepository->update(
                id: $afterTripCompleteTimeFormat->id,
                data: [
                    'key_name' => 'after_trip_complete_time_format',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $data['after_trip_complete_time_format'],
                ]
            );
        } else {
            $this->businessSettingRepository->create(
                data: [
                    'key_name' => 'after_trip_complete_time_format',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $data['after_trip_complete_time_format'],
                ]
            );
        }

    }

    public function storeEmergencyNumberForCall(array $data)
    {
        $valueToStore = $data['choose_number_type'] == 'hotline' ? $data['emergency_govt_number_for_call_hotline'] : $data['emergency_govt_number_for_call'];

        $emergencyGovtNumber = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => 'emergency_govt_number_for_call',
            'settings_type' => SAFETY_FEATURE_SETTINGS
        ]);


        if ($emergencyGovtNumber) {
            $this->businessSettingRepository->update(
                id: $emergencyGovtNumber->id,
                data: [
                    'key_name' => 'emergency_govt_number_for_call',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $valueToStore,
                ]
            );
        } else {
            $this->businessSettingRepository->create(
                data: [
                    'key_name' => 'emergency_govt_number_for_call',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $valueToStore,
                ]
            );
        }


        $emergencyGovtNumberType = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => 'emergency_govt_number_type',
            'settings_type' => SAFETY_FEATURE_SETTINGS
        ]);


        if ($emergencyGovtNumberType) {
            $this->businessSettingRepository->update(
                id: $emergencyGovtNumberType->id,
                data: [
                    'key_name' => 'emergency_govt_number_type',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $data['choose_number_type'],
                ]
            );
        } else {
            $this->businessSettingRepository->create(
                data: [
                    'key_name' => 'emergency_govt_number_type',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $data['choose_number_type'],
                ]
            );
        }


        $emergencyOtherNumbersForCall = [];

        foreach ($data['emergency_other_number_title'] as $key => $title) {
            if ($title) {
                $emergencyOtherNumbersForCall[] = [
                    'title' => $title,
                    'number' => $data['emergency_other_number'][$key],
                ];
            }

        }

        $emergencyOtherNumber = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => 'emergency_other_numbers_for_call',
            'settings_type' => SAFETY_FEATURE_SETTINGS
        ]);

        if ($emergencyOtherNumber) {
            $this->businessSettingRepository->update(
                id: $emergencyOtherNumber->id,
                data: [
                    'key_name' => 'emergency_other_numbers_for_call',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $emergencyOtherNumbersForCall,
                ]
            );
        } else {
            $this->businessSettingRepository->create(
                data: [
                    'key_name' => 'emergency_other_numbers_for_call',
                    'settings_type' => SAFETY_FEATURE_SETTINGS,
                    'value' => $emergencyOtherNumbersForCall,
                ]
            );
        }
    }

    public function storeScheduleTrip(array $data): void
    {
        $keys = [];
        try {
            DB::beginTransaction();
            foreach ($data as $key => $value) {
                if (!is_null($value)){
                    $keys[$key] = $this->findOneBy(criteria: [
                        'key_name' => $key,
                        'settings_type' => SCHEDULE_TRIP_SETTINGS
                    ]);

                    if ($keys[$key]) {
                        $this->businessSettingRepository->update(id: $keys[$key]->id, data: ['key_name' => $key, 'settings_type' => SCHEDULE_TRIP_SETTINGS, 'value' => $value]);
                    } else {
                        $this->businessSettingRepository->create(data: ['key_name' => $key, 'settings_type' => SCHEDULE_TRIP_SETTINGS, 'value' => $value]);
                    }
                }
            }
            if (!array_key_exists('increase_fare', $data)) {
                $increaseFare = $this->businessSettingRepository->findOneBy(criteria: [
                    'key_name' => 'increase_fare',
                    'settings_type' => SCHEDULE_TRIP_SETTINGS
                ]);

                if ($increaseFare){
                    $this->businessSettingRepository->update(
                        id: $increaseFare->id,
                        data: ['key_name' => 'increase_fare', 'settings_type' => SCHEDULE_TRIP_SETTINGS, 'value' => 0]
                    );
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in storing schedule trip settings: ' . $e->getMessage());
        }
    }

    public function updateCashInHand(array $data): void
    {
        $maxAmountToHoldCash = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => 'max_amount_to_hold_cash',
            'settings_type' => DRIVER_SETTINGS
        ]);

        if ($maxAmountToHoldCash) {
            $this->businessSettingRepository->update(
                id: $maxAmountToHoldCash->id,
                data: [
                    'key_name' => 'max_amount_to_hold_cash',
                    'settings_type' => DRIVER_SETTINGS,
                    'value' => $data['max_amount_to_hold_cash'],
                ]
            );
        } else {
            $this->businessSettingRepository->create(
                data: [
                    'key_name' => 'max_amount_to_hold_cash',
                    'settings_type' => DRIVER_SETTINGS,
                    'value' => $data['max_amount_to_hold_cash'],
                ]
            );
        }

        $minAmountToPay = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => 'min_amount_to_pay',
            'settings_type' => DRIVER_SETTINGS
        ]);

        if ($minAmountToPay) {
            $this->businessSettingRepository->update(
                id: $minAmountToPay->id,
                data: [
                    'key_name' => 'min_amount_to_pay',
                    'settings_type' => DRIVER_SETTINGS,
                    'value' => $data['min_amount_to_pay'],
                ]
            );
        } else {
            $this->businessSettingRepository->create(
                data: [
                    'key_name' => 'min_amount_to_pay',
                    'settings_type' => DRIVER_SETTINGS,
                    'value' => $data['min_amount_to_pay'],
                ]
            );
        }
    }
    public function updateIdentityVerification(array $data): void
    {
        foreach ($data as $key => $value)
        {
            $businessSetting = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => $key]);
            $businessSettingData = [
                'key_name' => $key,
                'value' => $value,
                'settings_type' => FACE_VERIFICATION_SETTINGS
            ];
            if ($businessSetting)
            {
                $this->businessSettingRepository->update(id: $businessSetting->id, data: $businessSettingData);
            } else{
                $this->businessSettingRepository->create(data: $businessSettingData);
            }
        }
    }

    public function updateOtpLoginAttemptsData(array $data): void
    {
        foreach ($data as $key => $value) {
            if (!is_null($value)) {
                $businessSetting = $this->businessSettingRepository
                    ->findOneBy(criteria: ['key_name' => $key, 'settings_type' => BUSINESS_SETTINGS]);
                if ($businessSetting) {
                    $this->businessSettingRepository->update(id: $businessSetting->id, data: [
                        'key_name' => $key,
                        'value' => $value,
                        'settings_type' => BUSINESS_SETTINGS
                    ]);
                } else {
                    $this->businessSettingRepository->create(data: [
                        'key_name' => $key,
                        'value' => $value,
                        'settings_type' => BUSINESS_SETTINGS
                    ]);
                }

            }
        }
    }

    public function updateLoginSettingsData(array $data): void {
        $otpEnabled = isOtpEnabled();

        foreach ([CUSTOMER, DRIVER] as $user) {
            $value = $data[$user] ?? [];
            $storeData = $otpEnabled
                ? [
                    'manual_login' => array_key_exists('manual_login', $value) ? 1 : 0,
                    'otp_login' => array_key_exists('otp_login', $value) ? 1 : 0,
                ]
                : [
                    'manual_login' => 1,
                    'otp_login' => 0,
                ];

            if ($user === DRIVER) {
                $storeData['biometric_login'] = array_key_exists('biometric_login', $value) ? 1 : 0;
            }

            $businessSetting = $this->businessSettingRepository
                ->findOneBy(criteria: ['key_name' => $user . '_login_options', 'settings_type' => LOGIN_SETTINGS]);
            if ($businessSetting) {
                $this->businessSettingRepository->update(id: $businessSetting->id, data: [
                    'key_name' => $user . '_login_options',
                    'value' => $storeData,
                    'settings_type' => LOGIN_SETTINGS
                ]);
            } else {
                $this->businessSettingRepository->create(data: [
                    'key_name' => $user . '_login_options',
                    'value' => $storeData,
                    'settings_type' => LOGIN_SETTINGS
                ]);
            }
        }

        foreach (['customer_verification', 'driver_verification'] as $key) {
            $isVerificationEnabled = $otpEnabled && array_key_exists($key, $data) ? 1 : 0;
            $businessSetting = $this->businessSettingRepository
                ->findOneBy(criteria: ['key_name' => $key, 'settings_type' => BUSINESS_INFORMATION]);
            if ($businessSetting) {
                $this->businessSettingRepository->update(id: $businessSetting->id, data: [
                    'key_name' => $key,
                    'value' => $isVerificationEnabled,
                    'settings_type' => BUSINESS_INFORMATION
                ]);
            } else {
                $this->businessSettingRepository->create(data: [
                    'key_name' => $key,
                    'value' => $isVerificationEnabled,
                    'settings_type' => BUSINESS_INFORMATION
                ]);
            }
        }
    }

    public function updateUserLoginToDefaultOptions(): void
    {
            foreach ([CUSTOMER, DRIVER] as $user)
            {
                $storeData = [
                    'manual_login' => 1,
                    'otp_login' => 0,
                ];
                if ($user === DRIVER) {
                    $storeData['biometric_login'] = 0;
                }
                $businessSetting = $this->businessSettingRepository
                    ->findOneBy(criteria: ['key_name' => $user . '_login_options', 'settings_type' => LOGIN_SETTINGS]);
                if ($businessSetting) {
                    $this->businessSettingRepository->update(id: $businessSetting->id, data: [
                        'key_name' => $user . '_login_options',
                        'value' => $storeData,
                        'settings_type' => LOGIN_SETTINGS
                    ]);
                } else {
                    $this->businessSettingRepository->create(data: [
                        'key_name' => $user . '_login_options',
                        'value' => $storeData,
                        'settings_type' => LOGIN_SETTINGS
                    ]);
                }
            }

        foreach (['customer_verification', 'driver_verification'] as $value)
        {
            $businessSetting = $this->businessSettingRepository
                ->findOneBy(criteria: ['key_name' => $value, 'settings_type' => BUSINESS_INFORMATION]);

            if ($businessSetting) {
                $this->businessSettingRepository->update(id: $businessSetting->id, data: [
                    'key_name' => $value,
                    'value' => 0,
                    'settings_type' => BUSINESS_INFORMATION
                ]);
            } else {
                $this->businessSettingRepository->create(data: [
                    'key_name' => $value,
                    'value' => 0,
                    'settings_type' => BUSINESS_INFORMATION
                ]);
            }
        }
    }

    public function storeAdditionalDataSetup(array $data): void
    {
        $userType = $data['user_type'];
        $keyName = $userType . '_additional_registration_form_fields';

        $fields = [];
        $nextFieldNumber = 1;
        $usedFieldIds = [];
        foreach ($data['fields'] ?? [] as $field) {
            $type = AdditionalDataFieldNormalizer::normalizeType($field['type'] ?? null);
            $title = AdditionalDataFieldNormalizer::toFieldKey($field['title'] ?? '');

            if ($type === null || $title === '') {
                continue;
            }

            $fieldId = $this->additionalDataFieldId($field['id'] ?? null, $nextFieldNumber, $usedFieldIds);
            $normalized = [
                'id' => $fieldId,
                'type' => $type,
                'title' => $title,
                'is_required' => !empty($field['is_required']) ? 1 : 0,
            ];

            if (in_array($type, AdditionalDataFieldNormalizer::PLACEHOLDER_TYPES, true)) {
                $normalized['placeholder'] = $field['placeholder'] ?? '';
            }

            if (in_array($type, AdditionalDataFieldNormalizer::OPTION_TYPES, true)) {
                $normalized['options'] = array_values(array_filter(
                    array_map(
                        fn ($option) => is_string($option) ? AdditionalDataFieldNormalizer::toFieldKey($option) : '',
                        $field['options'] ?? []
                    ),
                    fn ($option) => $option !== ''
                ));
            }

            if ($type === 'file') {
                $normalized['file_format'] = array_values($field['file_format'] ?? []);
                $normalized['quantity'] = max(1, min(5, (int) ($field['quantity'] ?? 1)));
            }

            $fields[] = $normalized;
        }
        usort($fields, fn ($first, $second) => $this->compareAdditionalDataFieldIds($first['id'], $second['id']));
        $totalFields = count($fields);
        foreach ($fields as $index => &$field) {
            $field['id'] = 'field_' . ($totalFields - $index);
        }
        unset($field);

        $payload = [
            'key_name' => $keyName,
            'value' => $fields,
            'settings_type' => ADDITIONAL_DATA_SETUP,
        ];

        $existing = $this->businessSettingRepository->findOneBy(criteria: [
            'key_name' => $keyName,
            'settings_type' => ADDITIONAL_DATA_SETUP,
        ]);

        if ($existing) {
            $this->businessSettingRepository->update(id: $existing->id, data: $payload);
        } else {
            $this->businessSettingRepository->create(data: $payload);
        }
    }

    private function additionalDataFieldId(mixed $id, int &$nextFieldNumber, array &$usedFieldIds): string
    {
        $id = is_string($id) ? trim($id) : '';
        if (preg_match('/^field_\d+$/', $id) && !isset($usedFieldIds[$id])) {
            $usedFieldIds[$id] = true;
            $fieldNumber = (int) substr($id, 6);
            if ($fieldNumber >= $nextFieldNumber) {
                $nextFieldNumber = $fieldNumber + 1;
            }

            return $id;
        }

        do {
            $id = 'field_' . $nextFieldNumber;
            $nextFieldNumber++;
        } while (isset($usedFieldIds[$id]));

        $usedFieldIds[$id] = true;

        return $id;
    }

    private function compareAdditionalDataFieldIds(string $firstId, string $secondId): int
    {
        $firstNumber = preg_match('/^field_(\d+)$/', $firstId, $firstMatch) ? (int) $firstMatch[1] : null;
        $secondNumber = preg_match('/^field_(\d+)$/', $secondId, $secondMatch) ? (int) $secondMatch[1] : null;

        if ($firstNumber !== null && $secondNumber !== null) {
            return $secondNumber <=> $firstNumber;
        }

        return $secondId <=> $firstId;
    }

    public function storeDeepLinkingInfo(array $data): void
    {
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                $keyName = $key . '_deep_linking_data';
                $payload = [
                    'key_name' => $keyName,
                    'value' => $value,
                    'settings_type' => DEEP_LINKING_SETTINGS
                ];
                $businessSetting = $this->businessSettingRepository
                    ->findOneBy(criteria: ['key_name' => $keyName, 'settings_type' => DEEP_LINKING_SETTINGS]);

                if ($businessSetting) {
                    $this->businessSettingRepository->update(id: $businessSetting->id, data: $payload);
                } else {
                    $this->businessSettingRepository->create(data: $payload);
                }
            }

            $this->generateAssetLinks($data);
            $this->generateAppleAssociation($data);
        });
    }

    private function generateAssetLinks(array $data): void
    {
        $assetLinks = [];

        foreach ($data as $app => $appData) {

            if (
                empty($appData['package_name']) ||
                empty($appData['sha256_fingerprint'])
            ) {
                continue;
            }

            $assetLinks[] = [
                "relation" => ["delegate_permission/common.handle_all_urls"],
                "target" => [
                    "namespace" => "android_app",
                    "package_name" => $appData['package_name'],
                    "sha256_cert_fingerprints" => [
                        $appData['sha256_fingerprint']
                    ]
                ]
            ];
        }

        $path = deepLinkWellKnownPath('assetlinks.json');

        file_put_contents(
            $path,
            json_encode($assetLinks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    private function generateAppleAssociation(array $data): void
    {
        $details = [];

        foreach ($data as $app => $appData) {

            if (
                empty($appData['bundle_id']) ||
                empty($appData['team_id'])
            ) {
                continue;
            }

            $path = match ($app) {
                'customer_app' => '/locate-user/*',
                'driver_app' => '/locate-driver/*',
                default => '/*'
            };

            $details[] = [
                "appID" => $appData['team_id'] . "." . $appData['bundle_id'],
                "paths" => [$path]
            ];
        }

        $apple = [
            "applinks" => [
                "apps" => [],
                "details" => $details
            ]
        ];

        $path = deepLinkWellKnownPath('apple-app-site-association');

        file_put_contents(
            $path,
            json_encode($apple, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}
