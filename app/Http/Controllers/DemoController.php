<?php

namespace App\Http\Controllers;

use App\Traits\ActivationClass;
use App\Traits\UnloadedHelpers;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\ExternalConfigurationServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\FirebasePushNotificationServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\NotificationSettingServiceInterface;
use Modules\Gateways\Traits\SmsGateway;
use Modules\PromotionManagement\Entities\CouponSetup;
use Modules\PromotionManagement\Service\Interfaces\CouponSetupServiceInterface;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\WithdrawRequest;
use Modules\UserManagement\Service\Interfaces\EmployeeRoleServiceInterface;
use Modules\UserManagement\Service\Interfaces\EmployeeServiceInterface;

class DemoController extends Controller
{
    use UnloadedHelpers;
    use ActivationClass;
    use SmsGateway;

    protected $businessSetting;
    protected $notificationSettingService;
    protected $firebasePushNotificationService;
    protected $couponSetupService;

    public function __construct(BusinessSettingServiceInterface          $businessSetting, NotificationSettingServiceInterface $notificationSettingService,
                                FirebasePushNotificationServiceInterface $firebasePushNotificationService, CouponSetupServiceInterface $couponSetupService)
    {
        $this->businessSetting = $businessSetting;
        $this->notificationSettingService = $notificationSettingService;
        $this->firebasePushNotificationService = $firebasePushNotificationService;
        $this->couponSetupService = $couponSetupService;
    }

    public function demo()
    {
        $withdrawRequests = WithdrawRequest::get();
        foreach ($withdrawRequests as $withdrawRequest) {
            if ($withdrawRequest->is_approved == null) {
                $withdrawRequest->status = PENDING;
            } elseif ($withdrawRequest->is_approved == 1) {
                $withdrawRequest->status = SETTLED;
            } else {
                $withdrawRequest->status = DENIED;
            }
            $withdrawRequest->save();
        }
        $users = User::withTrashed()->get();
        foreach ($users as $user) {
            if (is_null($user->full_name)) {
                $user->full_name = $user->first_name . ' ' . $user->last_name;
                $user->save();
            }
        }
        if (Schema::hasColumns('coupon_setups', ['user_id', 'user_level_id', 'rules'])) {
            $couponSetups = CouponSetup::withTrashed()->get();
            if (count((array)$couponSetups) > 0) {
                foreach ($couponSetups as $couponSetup) {
                    $couponSetup->zone_coupon_type = ALL;
                    $couponSetup->save();
                    if ($couponSetup->user_id == ALL) {
                        $couponSetup->customer_coupon_type = ALL;
                        $couponSetup->save();
                    } else {
                        $couponSetup->customer_coupon_type = CUSTOM;
                        $couponSetup->save();
                        $couponSetup?->customers()->attach($couponSetup->user_id);
                    }
                    if ($couponSetup->user_level_id == ALL || $couponSetup->user_level_id == null) {
                        $couponSetup->customer_level_coupon_type = ALL;
                        $couponSetup->save();
                    } else {
                        $couponSetup->customer_level_coupon_type = CUSTOM;
                        $couponSetup->save();
                        $couponSetup?->customerLevels()->attach($couponSetup->user_level_id);
                    }
                    if ($couponSetup->rules == "default") {
                        $couponSetup->category_coupon_type = [ALL];
                        $couponSetup->save();
                    } else {
                        $couponSetup->category_coupon_type = [CUSTOM];
                        $couponSetup->save();
                    }
                }

            }
            Schema::table('coupon_setups', function (Blueprint $table) {
                $table->dropColumn(['user_id', 'user_level_id', 'rules']); // Replace 'column_name' with the actual column name
            });
        }

        $notificationSettings = $this->notificationSettingService->getAll();
        foreach ($notificationSettings as $setting) {
            if (in_array($setting->name, ['trip', 'rating_and_review'])) {
                $this->notificationSettingService->delete($setting->id);
            }
        }
        $emptyRefCodeUsers = User::withTrashed()->whereNull('ref_code')->get();
        foreach ($emptyRefCodeUsers as $user) {
            generateReferralCode($user);
        }
        insertBusinessSetting(keyName: 'return_time_for_driver', settingType: PARCEL_SETTINGS, value: 24);
        insertBusinessSetting(keyName: 'return_time_type_for_driver', settingType: PARCEL_SETTINGS, value: "hour");
        insertBusinessSetting(keyName: 'return_fee_for_driver_time_exceed', settingType: PARCEL_SETTINGS, value: 0);
        insertBusinessSetting(keyName: 'parcel_refund_validity', settingType: PARCEL_SETTINGS, value: 2);
        insertBusinessSetting(keyName: 'parcel_refund_validity_type', settingType: PARCEL_SETTINGS, value: 'day');
        $parcel_tracking_message = "Dear {CustomerName}
Parcel ID is {ParcelId} You can track this parcel from this link {TrackingLink}";
        insertBusinessSetting(keyName: 'parcel_tracking_message', settingType: PARCEL_SETTINGS, value: $parcel_tracking_message);

        #version 2.1
        $firebasePushNotifications = $this->firebasePushNotificationService->getBy(whereInCriteria: ['id' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]]);
        if (count($firebasePushNotifications) > 0) {
            foreach ($firebasePushNotifications as $firebasePushNotification) {
                if ($firebasePushNotification) {
                    $this->firebasePushNotificationService->delete(id: $firebasePushNotification->id);
                }
            }
        }

        if ($this->firebasePushNotificationService->findOneBy(criteria: ['name' => 'ride_is_started']) == false) {
            $this->firebasePushNotificationService->create(data: ['name' => 'ride_is_started',
                'value' => "Another driver already accept the trip request.",
                'status' => 1
            ]);
        }


        #version 2.2
        insertBusinessSetting(keyName: 'parcel_weight_unit', settingType: PARCEL_SETTINGS, value: 'kg');


        return redirect()->route("admin.dashboard");
    }

    public function smsGatewayTest(Request $request)
    {
        try {
            self::send("+8801740128172", "1234");
            dd("done");
        } catch (\Exception $exception) {
            dd($exception->getMessage());
        }
    }

    public function firebaseMessageConfigFileGen()
    {
        $apiKey = businessConfig(key: 'api_key',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $authDomain = businessConfig(key: 'auth_domain',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $projectId = businessConfig(key: 'project_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $storageBucket = businessConfig(key: 'storage_bucket',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $messagingSenderId = businessConfig(key: 'messaging_sender_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $appId = businessConfig(key: 'app_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $measurementId = businessConfig(key: 'measurement_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '';

        $filePath = DOMAIN_POINTED_DIRECTORY == 'root' ? base_path('firebase-messaging-sw.js') : base_path('public/firebase-messaging-sw.js');

        try {
            if (file_exists($filePath) && !is_writable($filePath)) {
                if (!chmod($filePath, 0644)) {
                    throw new \Exception('File is not writable and permission change failed: ' . $filePath);
                }
            }

            $fileContent = <<<JS
                importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
                importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

                firebase.initializeApp({
                    apiKey: "$apiKey",
                    authDomain: "$authDomain",
                    projectId: "$projectId",
                    storageBucket: "$storageBucket",
                    messagingSenderId: "$messagingSenderId",
                    appId: "$appId",
                    measurementId: "$measurementId"
                });

                const messaging = firebase.messaging();
                messaging.setBackgroundMessageHandler(function (payload) {
                    return self.registration.showNotification(payload.data.title, {
                        body: payload.data.body ? payload.data.body : '',
                        icon: payload.data.icon ? payload.data.icon : ''
                    });
                });
                JS;


            if (file_put_contents($filePath, $fileContent) === false) {
                throw new \Exception('Failed to write to file: ' . $filePath);
            }

        } catch (\Exception $e) {
            //
        }
    }


}
