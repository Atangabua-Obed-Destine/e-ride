<?php

namespace Modules\ZoneManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Modules\ZoneManagement\Entities\DangerReport;
use Modules\ZoneManagement\Entities\SafetyArea;
use Modules\ZoneManagement\Entities\Zone;
use Modules\TripManagement\Entities\TripCheckin;
use Modules\TripManagement\Lib\CheckinSettings;
use Modules\ZoneManagement\Lib\SafetySettings;
use Modules\ZoneManagement\Service\SafetyService;

/** Admin, Zone management > Safety map: danger reports, pauses and no-go areas. */
class SafetyAdminController extends Controller
{
    public function __construct(protected SafetyService $safety)
    {
    }

    public function index(): View
    {
        $now = now();
        $areas = SafetyArea::with('zone')->whereNull('lifted_at')->where('ends_at', '>', $now)->orderBy('starts_at')->get();
        $decide = $areas->filter(fn(SafetyArea $a) => !$a->confirmed && $a->isActive($now))->values();
        $inForce = $areas->filter(fn(SafetyArea $a) => $a->confirmed && $a->isActive($now))->values();
        $planned = $areas->filter(fn(SafetyArea $a) => $a->starts_at->gt($now))->values();
        $reports = DangerReport::with(['user', 'zone'])->where('created_at', '>=', $now->copy()->subDay())->latest()->limit(100)->get();
        $past = SafetyArea::with('zone')->where(fn($q) => $q->whereNotNull('lifted_at')->orWhere('ends_at', '<=', $now))
            ->latest('updated_at')->limit(15)->get();
        $zones = Zone::where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        $settings = SafetySettings::all();
        $checkinSettings = CheckinSettings::all();
        $checkins = TripCheckin::with('trip:id,ref_id')->where('asked_at', '>=', $now->copy()->subDay())->latest('asked_at')->limit(30)->get();
        $mapAreas = $areas->map(fn(SafetyArea $a) => $this->safety->presentArea($a) + [
            'state' => !$a->isActive($now) ? 'planned' : (!$a->confirmed ? 'decide' : $a->kind),
            'zone_name' => $a->zone?->name,
        ])->values();
        $mapReports = $reports->map(fn(DangerReport $r) => [
            'lat' => $r->latitude, 'lng' => $r->longitude, 'type' => DangerReport::label($r->type), 'role' => $r->role,
            'status' => $r->status, 'at' => $r->created_at->format('H:i'),
        ])->values();
        return view('zonemanagement::admin.safety.index', compact('decide', 'inForce', 'planned', 'reports', 'past', 'zones', 'settings', 'mapAreas', 'mapReports', 'checkinSettings', 'checkins'));
    }

    public function pause(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in([SafetyArea::PAUSE, SafetyArea::NO_GO])],
            'where' => ['required', Rule::in(['zone', 'circle'])],
            'zone_id' => 'required_if:where,zone|nullable|uuid',
            'latitude' => 'required_if:where,circle|nullable|numeric|between:-90,90',
            'longitude' => 'required_if:where,circle|nullable|numeric|between:-180,180',
            'radius_m' => 'required_if:where,circle|nullable|integer|min:100|max:20000',
            'title' => 'required|string|max:120',
            'reason' => 'required|string|max:191',
            'starts_at' => 'nullable|date',
            'ends_at' => 'required|date|after:now',
        ]);
        if ($data['where'] === 'zone') {
            $data['latitude'] = $data['longitude'] = $data['radius_m'] = null;
        } else {
            $data['zone_id'] = null;
        }
        $data['starts_at'] = ($data['starts_at'] ?? null) ?: now()->toDateTimeString();
        $area = $this->safety->pause(auth()->user(), $data);
        Toastr::success($area->starts_at->isFuture()
            ? translate('Pause planned. People near it are told 12 hours before.')
            : translate('Area paused. People in and near it were told.'));
        return back();
    }

    public function confirm(Request $request, SafetyArea $area): RedirectResponse
    {
        $data = $request->validate([
            'minutes' => 'nullable|integer|min:15|max:10080',
            'radius_m' => 'nullable|integer|min:100|max:20000',
            'reason' => 'nullable|string|max:191',
        ]);
        $this->safety->confirm($area, auth()->user(), $data['minutes'] ?? null, $data['radius_m'] ?? null, $data['reason'] ?? null);
        Toastr::success(translate('Confirmed.'));
        return back();
    }

    public function lift(SafetyArea $area): RedirectResponse
    {
        $this->safety->lift($area, auth()->user());
        Toastr::success(translate('Lifted. Trips can be booked there again.'));
        return back();
    }

    public function report(Request $request, DangerReport $report): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in([DangerReport::CONFIRMED, DangerReport::FALSE])]]);
        $this->safety->decideReport($report, auth()->user(), $data['status']);
        Toastr::success($data['status'] === DangerReport::FALSE ? translate('Marked as a false report.') : translate('Report confirmed. Riders nearby now see it too.'));
        return back();
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'auto_reports' => 'required|integer|min:2|max:10',
            'auto_radius_m' => 'required|integer|min:100|max:5000',
            'auto_window_min' => 'required|integer|min:5|max:240',
            'auto_minutes' => 'required|integer|min:15|max:1440',
            'caution_radius_km' => 'required|numeric|min:0.5|max:50',
            'reports_per_hour' => 'required|integer|min:1|max:20',
            'report_fade_min' => 'required|integer|min:15|max:1440',
            'banner_radius_km' => 'required|numeric|min:0.5|max:50',
        ]);
        $data['enabled'] = $request->boolean('enabled') ? 1 : 0;
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: 'safety_' . $key, settingType: SafetySettings::TYPE, value: (string)$value);
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Safety settings saved'));
        return back();
    }

    /** Trip check-ins and night sharing (TripCheckinService). */
    public function checkinSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'stop_minutes' => 'required|integer|min:2|max:60',
            'stop_m' => 'required|integer|min:10|max:500',
            'off_route_m' => 'required|integer|min:200|max:10000',
            'off_route_times' => 'required|integer|min:1|max:10',
            'long_factor' => 'required|numeric|min:1.2|max:5',
            'answer_minutes' => 'required|integer|min:1|max:15',
            'quiet_minutes' => 'required|integer|min:1|max:60',
            'night_from' => 'required|integer|min:0|max:23',
            'night_to' => 'required|integer|min:0|max:23',
        ]);
        $data['enabled'] = $request->boolean('enabled') ? 1 : 0;
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: 'checkin_' . $key, settingType: CheckinSettings::TYPE, value: (string)$value);
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Check-in settings saved'));
        return back();
    }
}
