<?php

namespace Modules\StoreManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\ParcelManagement\Entities\ParcelCategory;
use Modules\StoreManagement\Lib\StoreSettings;

class StoreSettingController extends Controller
{
    public function edit(): View
    {
        $settings = StoreSettings::all();
        $category = ParcelCategory::find($settings['parcel_category_id']);
        return view('storemanagement::admin.settings', compact('settings', 'category'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'nullable|boolean',
            'service_label' => 'required|string|max:40',
            'partner_response_minutes' => 'required|integer|min:2|max:60',
            'rider_lead_minutes' => 'required|integer|min:0|max:60',
            'parcel_weight' => 'required|numeric|min:0.1|max:100',
        ]);
        updateBusinessSetting(keyName: 'store_ordering_status', settingType: STORE_SETTINGS, value: $request->boolean('status') ? '1' : '0');
        updateBusinessSetting(keyName: 'store_service_label', settingType: STORE_SETTINGS, value: trim($data['service_label']));
        updateBusinessSetting(keyName: 'store_partner_response_minutes', settingType: STORE_SETTINGS, value: (string)$data['partner_response_minutes']);
        updateBusinessSetting(keyName: 'store_rider_lead_minutes', settingType: STORE_SETTINGS, value: (string)$data['rider_lead_minutes']);
        updateBusinessSetting(keyName: 'store_order_parcel_weight', settingType: STORE_SETTINGS, value: (string)$data['parcel_weight']);
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Food & Shops settings saved'));
        return back();
    }
}
