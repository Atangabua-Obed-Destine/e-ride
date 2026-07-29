<?php

namespace App\Http\Controllers;

use App\Traits\ActivationClass;
use App\Traits\InstallUpdateTrait;
use App\Traits\UnloadedHelpers;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery\Exception;
use Modules\BusinessManagement\Entities\FirebasePushNotification;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\FirebasePushNotificationServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\NotificationSettingServiceInterface;
use Modules\PromotionManagement\Entities\CouponSetup;
use Modules\PromotionManagement\Service\Interfaces\CouponSetupServiceInterface;
use Modules\UserManagement\Entities\User;
use Illuminate\Support\Facades\Schema;
use Modules\UserManagement\Entities\WithdrawRequest;

class UpdateController extends Controller
{
    use UnloadedHelpers, ActivationClass, InstallUpdateTrait;

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

    public function update_software_index()
    {
        $modules = ['AdminModule', 'AuthManagement', 'BusinessManagement', 'ChattingManagement', 'FareManagement',
            'Gateways', 'ParcelManagement', 'PromotionManagement', 'ReviewModule', 'TransactionManagement', 'TripManagement',
            'UserManagement', 'VehicleManagement', 'ZoneManagement',
        ];
        foreach ($modules as $module) {
            Artisan::call('module:enable', ['module' => $module]);
        }
        return view('update.update-software');
    }

    public function update_software(Request $request)
    {
        $this->setEnvironmentValue('SOFTWARE_ID', 'MTAwMDAwMDA=');
        $this->setEnvironmentValue('BUYER_USERNAME', $request['username']);
        $this->setEnvironmentValue('PURCHASE_CODE', $request['purchase_key']);
        $this->setEnvironmentValue('SOFTWARE_VERSION', '3.2');
        $this->setEnvironmentValue('APP_ENV', 'local');
        $this->setEnvironmentValue('APP_MODE', 'live');
        $this->setEnvironmentValue('APP_URL', url('/'));
        $this->setEnvironmentValue('PUSHER_APP_ID', 'drivemond');
        $this->setEnvironmentValue('PUSHER_APP_KEY', 'drivemond');
        $this->setEnvironmentValue('PUSHER_APP_SECRET', 'drivemond');
        $this->setEnvironmentValue('PUSHER_HOST', getMainDomain(url('/')));
        $this->setEnvironmentValue('PUSHER_PORT', 6001);
        $this->setEnvironmentValue('PUSHER_APP_CLUSTER', 'mt1');
        $this->setEnvironmentValue('PUSHER_SCHEME', 'http');
        $this->setEnvironmentValue('REVERB_APP_ID', 'drivemond');
        $this->setEnvironmentValue('REVERB_APP_KEY', 'drivemond');
        $this->setEnvironmentValue('REVERB_APP_SECRET', 'drivemond');
        $this->setEnvironmentValue('REVERB_HOST', getMainDomain(url('/')));
        $this->setEnvironmentValue('REVERB_PORT', 6001);
        $this->setEnvironmentValue('REVERB_SCHEME', 'http');
        $this->setEnvironmentValue('REVERB_SSL_CERT_PATH', "");
        $this->setEnvironmentValue('REVERB_SSL_KEY_PATH', "");

        $data = $this->actch();
        try {
            if (!$data->getData()->active) {
                $remove = array("http://", "https://", "www.");
                $url = str_replace($remove, "", url('/'));

                $activation_url = base64_decode('aHR0cHM6Ly9hY3RpdmF0aW9uLmRyaXZlbW9uZC5hcHAv');
                $activation_url .= '?username=' . $request['username'];
                $activation_url .= '&purchase_code=' . $request['purchase_key'];
                $activation_url .= '&domain=' . $url . '&';

                return redirect($activation_url);
            }
        } catch (Exception $exception) {
            Toastr::error('verification failed! try again');
            return back();
        }


        Artisan::call('migrate', ['--force' => true]);

        $previousRouteServiceProvider = base_path('app/Providers/RouteServiceProvider.php');
        $newRouteServiceProvider = base_path('app/Providers/RouteServiceProvider.txt');
        copy($newRouteServiceProvider, $previousRouteServiceProvider);

        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('config:cache');
        Artisan::call('config:clear');
        Artisan::call('optimize:clear');
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
        if ($this->notificationSettingService->findOneBy(criteria: ['name' => 'legal']) == false) {
            $this->notificationSettingService->create(data: [
                'name' => 'legal',
                'push' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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
        #version 2.2
        insertBusinessSetting(keyName: 'parcel_weight_unit', settingType: PARCEL_SETTINGS, value: 'kg');

        #version 2.3
        $this->firebaseMessageConfigFileGen();

        #version 2.5
        $this->setupScheduleTripSettingsData();

        #version 2.7
        $this->setupVersion2Point7Data();

        #version 3.0
        $this->setupDriverFaceVerificationData();
        $this->setupThreePointZeroDataForUpdate();

        #version3.1
        $this->setupThreePointOneData();


        return redirect(env('APP_URL'));
    }

    private function firebaseMessageConfigFileGen()
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
