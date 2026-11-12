<?php

namespace Modules\PlaceManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\PlaceManagement\Entities\DropFare;
use Modules\PlaceManagement\Entities\Landmark;
use Modules\PlaceManagement\Lib\LocalSettings;
use Modules\VehicleManagement\Entities\VehicleCategory;

/** Drop fares: fixed prices between two landmarks (Fare management). */
class DropFareAdminController extends Controller
{
    public function index(Request $request): View
    {
        $routes = DropFare::with(['from', 'to', 'vehicleCategory'])->orderByDesc('is_active')->orderByDesc('uses')->latest()->get();
        $edit = $request->query('edit') ? DropFare::find($request->query('edit')) : null;
        $landmarks = Landmark::where('is_active', 1)->orderBy('name')->get(['id', 'name', 'area']);
        $categories = VehicleCategory::where('is_active', 1)->orderBy('name')->get()->reject(fn($c) => method_exists($c, 'isMove') && $c->isMove())->values();
        $settings = LocalSettings::all();
        return view('placemanagement::admin.drop-fares.index', compact('routes', 'edit', 'landmarks', 'categories', 'settings'));
    }

    public function store(Request $request): RedirectResponse
    {
        DropFare::create($this->validated($request) + ['is_active' => 1]);
        Toastr::success(translate('Drop fare added'));
        return redirect()->route('admin.drop-fare.index');
    }

    public function update(Request $request, DropFare $dropFare): RedirectResponse
    {
        $dropFare->update($this->validated($request));
        Toastr::success(translate('Drop fare saved'));
        return redirect()->route('admin.drop-fare.index');
    }

    public function toggle(DropFare $dropFare): RedirectResponse
    {
        $dropFare->update(['is_active' => !$dropFare->is_active]);
        Toastr::success($dropFare->is_active ? translate('Drop fare on') : translate('Drop fare off'));
        return back();
    }

    public function destroy(DropFare $dropFare): RedirectResponse
    {
        $dropFare->delete();
        Toastr::success(translate('Drop fare deleted'));
        return back();
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'drop_fares_enabled' => 'nullable|boolean',
            'drop_fares_radius_m' => 'required|integer|min:50|max:2000',
        ]);
        $data['drop_fares_enabled'] = $request->boolean('drop_fares_enabled') ? 1 : 0;
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: $key, settingType: LocalSettings::TYPE, value: (string)$value);
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Drop fare settings saved'));
        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'from_landmark_id' => 'required|uuid|exists:landmarks,id',
            'to_landmark_id' => 'required|uuid|exists:landmarks,id|different:from_landmark_id',
            'vehicle_category_id' => 'required|uuid|exists:vehicle_categories,id',
            'price' => 'required|numeric|min:1|max:1000000',
            'radius_m' => 'required|integer|min:50|max:2000',
            'hour_from' => 'nullable|integer|min:0|max:23',
            'hour_to' => 'nullable|integer|min:0|max:23',
        ]);
        $data['both_ways'] = $request->boolean('both_ways');
        // Hours only count as a pair.
        if (($data['hour_from'] ?? null) === null || ($data['hour_to'] ?? null) === null) {
            $data['hour_from'] = $data['hour_to'] = null;
        }
        return $data;
    }
}
