<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\FirebaseConfigurationStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\FirebasePushNotificationsUpdateRequest;
use Modules\BusinessManagement\Http\Requests\NotificationSetupStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\FirebasePushNotificationServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\NotificationSettingServiceInterface;

class NotificationController extends BaseController
{
    use AuthorizesRequests;

    protected $notificationSettingService;
    protected $firebasePushNotificationService;
    protected $businessSettingService;

    public function __construct(NotificationSettingServiceInterface $notificationSettingService, FirebasePushNotificationServiceInterface $firebasePushNotificationService, BusinessSettingServiceInterface $businessSettingService)
    {
        parent::__construct($notificationSettingService);
        $this->notificationSettingService = $notificationSettingService;
        $this->firebasePushNotificationService = $firebasePushNotificationService;
        $this->businessSettingService = $businessSettingService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('business_view');
        if (!in_array($type, ['regular-trip', 'schedule-trip', 'parcel', 'driver-registration', 'others'])) {
            abort(404);
        }
        $notifications = $this->firebasePushNotificationService->getBy(criteria: ['type' => convertToSnakeCaseIfNeeded($type)])->groupBy('group');
        return view('businessmanagement::admin.configuration.new-notification', compact('type', 'notifications'));
    }

    public function firebasePushNotificationFields(string $type = null): JsonResponse
    {
        $notifications = $this->firebasePushNotificationService->getBy(criteria: ['type' => convertToSnakeCaseIfNeeded($type)])->groupBy('group');
        return response()->json(view('businessmanagement::admin.configuration.partials._firebase-notification-fields', compact('notifications', 'type'))->render());
    }

    public function firebaseConfiguration(): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('business_view');
        $settings = $this->businessSettingService
            ->getBy(criteria: ['settings_type' => NOTIFICATION_SETTINGS]);

        return view('businessmanagement::admin.configuration.firebase-configuration',
            compact('settings'));
    }

    public function store(FirebaseConfigurationStoreOrUpdateRequest $request): Renderable|RedirectResponse
    {
        $this->authorize('business_edit');
        Cache::forget('server_key');
        Cache::forget('firebase_access_token');
        foreach ($request->validated() as $key => $value) {
            if ($value) {
                $notificationKey = $this->businessSettingService->findOneBy(criteria: ['key_name' => $key,
                    'settings_type' => NOTIFICATION_SETTINGS]);
                $data = ['key_name' => $key,
                    'settings_type' => NOTIFICATION_SETTINGS,
                    'value' => $value];

                if ($notificationKey) {
                    $this->businessSettingService->update(id: $notificationKey->id, data: $data);
                } else {
                    $this->businessSettingService->create(data: $data);
                }
            }
        }
        Cache::rememberForever('server_key', function () {
            return json_decode(businessConfig('server_key')->value);
        });
        $this->firebaseMessageConfigFileGen();
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }

    public function pushStore(FirebasePushNotificationsUpdateRequest $request, $type): JsonResponse
    {
        try {
            foreach ($request['notification'] as $notification) {
                $status = array_key_exists('status', $notification) ? 1 : 0;
                $notification['status'] = $status;
                $firebaseNotification = $this->firebasePushNotificationService->findOneBy(criteria: ['group' => $notification['group'], 'name' => $notification['name'], 'type' => convertToSnakeCaseIfNeeded($type)]);
                $this->firebasePushNotificationService->update(id: $firebaseNotification?->id, data: $notification);
            }
            $notifications = $this->firebasePushNotificationService->getBy(criteria: ['type' => convertToSnakeCaseIfNeeded($type)])->groupBy('group');

            return response()->json([
                'success' => BUSINESS_SETTING_UPDATE_200['message'],
                'view' => view('businessmanagement::admin.configuration.partials._firebase-notification-fields', compact('notifications', 'type'))->render()
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return response()->json([
                'errors' => $exception->validator->errors(),
            ], 422);
        }
    }

    public function updateNotificationSettings(NotificationSetupStoreOrUpdateRequest $request): JsonResponse
    {
        $this->authorize('business_edit');
        $notification = $this->notificationSettingService
            ->update(id: $request['id'], data: $request->validated());
        return response()->json($notification);
    }

    private function firebaseMessageConfigFileGen()
    {
        $apiKey = businessConfig(key: 'api_key', settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $authDomain = businessConfig(key: 'auth_domain', settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $projectId = businessConfig(key: 'project_id', settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $storageBucket = businessConfig(key: 'storage_bucket', settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $messagingSenderId = businessConfig(key: 'messaging_sender_id', settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $appId = businessConfig(key: 'app_id', settingsType: NOTIFICATION_SETTINGS)?->value ?? '';
        $measurementId = businessConfig(key: 'measurement_id', settingsType: NOTIFICATION_SETTINGS)?->value ?? '';

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
