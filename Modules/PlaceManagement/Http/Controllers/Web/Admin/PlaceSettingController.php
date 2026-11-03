<?php

namespace Modules\PlaceManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\PlaceManagement\Lib\PlaceSettings;

class PlaceSettingController extends Controller
{
    public function edit(): View
    {
        $settings = PlaceSettings::all();
        $usage = GooglePlaceController::usage();
        return view('placemanagement::admin.settings', compact('settings', 'usage'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'nullable|boolean',
            'label' => 'required|string|max:40',
            'google_status' => 'nullable|boolean',
            'radius_km' => 'required|numeric|min:1|max:50',
            'min_reviews' => 'required|integer|min:0|max:500',
            'search_cap' => 'required|integer|min:0|max:1000000',
            'photo_cap' => 'required|integer|min:0|max:1000000',
            'explore_label' => 'required|string|max:40',
            'emergency_note' => 'nullable|string|max:200',
        ]);
        updateBusinessSetting(keyName: 'stay_status', settingType: PLACE_SETTINGS, value: $request->boolean('status') ? '1' : '0');
        updateBusinessSetting(keyName: 'stay_label', settingType: PLACE_SETTINGS, value: trim($data['label']));
        updateBusinessSetting(keyName: 'stay_google_status', settingType: PLACE_SETTINGS, value: $request->boolean('google_status') ? '1' : '0');
        updateBusinessSetting(keyName: 'stay_radius_km', settingType: PLACE_SETTINGS, value: (string)$data['radius_km']);
        updateBusinessSetting(keyName: 'stay_min_reviews', settingType: PLACE_SETTINGS, value: (string)$data['min_reviews']);
        updateBusinessSetting(keyName: 'stay_google_search_cap', settingType: PLACE_SETTINGS, value: (string)$data['search_cap']);
        updateBusinessSetting(keyName: 'stay_google_photo_cap', settingType: PLACE_SETTINGS, value: (string)$data['photo_cap']);
        updateBusinessSetting(keyName: 'explore_label', settingType: PLACE_SETTINGS, value: trim($data['explore_label']));
        updateBusinessSetting(keyName: 'explore_emergency_note', settingType: PLACE_SETTINGS, value: trim((string)($data['emergency_note'] ?? '')));
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Explore Bamenda settings saved'));
        return back();
    }
}
