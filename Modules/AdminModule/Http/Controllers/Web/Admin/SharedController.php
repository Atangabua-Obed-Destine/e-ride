<?php

namespace Modules\AdminModule\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Modules\AdminModule\Service\Interfaces\AdminNotificationServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\TripManagement\Service\Interfaces\SafetyAlertServiceInterface;

class SharedController extends BaseController
{
    protected $adminNotificationService;
    protected $businessSettingService;
    protected $safetyAlertService;

    public function __construct(AdminNotificationServiceInterface $adminNotificationService, BusinessSettingServiceInterface $businessSettingService, SafetyAlertServiceInterface $safetyAlertService)
    {
        parent::__construct($adminNotificationService);
        $this->adminNotificationService = $adminNotificationService;
        $this->businessSettingService = $businessSettingService;
        $this->safetyAlertService = $safetyAlertService;
    }

    public function getNotifications()
    {
        $notification = $this->adminNotificationService->getBy(criteria: ['is_seen' => false], orderBy: ['created_at' => 'desc']);
        return response()->json(view('adminmodule::partials._notifications', compact('notification'))->render());
    }

    public function seenNotification(Request $request)
    {
        $notification = $this->adminNotificationService->update(id: $request->id, data: ['is_seen' => true]);
        return response()->json($notification);
    }

    public function lang($locale)
    {
        $direction = 'ltr';
        $languages = $this->businessSettingService->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE])?->value ?? [['code' => 'en', 'direction' => 'ltr']];
        foreach ($languages as $data) {
            if ($data['code'] == $locale) {
                $direction = $data['direction'] ?? 'ltr';
            }
        }
        session()->put('locale', $locale);
        Session::put('direction', $direction);
        return redirect()->back();
    }

    public function getSafetyAlert()
    {
        $safetyAlerts = $this->safetyAlertService->getBy(criteria: ['status' => PENDING], orderBy: ['created_at' => 'desc']);
        $route = count($safetyAlerts) > 0 ? $this->safetyAlertService->safetyAlertLatestUserRoute() : 'javascript:void(0)';
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['status' => PENDING], relations: ['sentBy', 'trip', 'lastLocation'], orderBy: ['created_at' => 'desc']);
        $safetyAlertUserId = $safetyAlert?->sentBy?->id ?? null;

        return response()->json(view('adminmodule::partials._safety_alerts', compact('safetyAlerts', 'route', 'safetyAlertUserId'))->render());
    }
}
