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
            'own_delivery_max_km' => 'required|numeric|min:0.1|max:20',
            'own_delivery_share_percent' => 'required|numeric|min:0|max:100',
            'my_riders_first_seconds' => 'required|integer|min:20|max:600',
            'reseller_min_percent' => 'required|numeric|min:0|max:100',
            'reseller_max_percent' => 'required|numeric|min:1|max:100|gte:reseller_min_percent',
            'reseller_window_days' => 'required|integer|min:1|max:60',
            'reseller_min_payout' => 'required|numeric|min:0|max:1000000',
        ]);
        updateBusinessSetting(keyName: 'store_ordering_status', settingType: STORE_SETTINGS, value: $request->boolean('status') ? '1' : '0');
        updateBusinessSetting(keyName: 'store_refund_status', settingType: STORE_SETTINGS, value: $request->boolean('refund_status') ? '1' : '0');
        updateBusinessSetting(keyName: 'store_service_label', settingType: STORE_SETTINGS, value: trim($data['service_label']));
        updateBusinessSetting(keyName: 'store_partner_response_minutes', settingType: STORE_SETTINGS, value: (string)$data['partner_response_minutes']);
        updateBusinessSetting(keyName: 'store_rider_lead_minutes', settingType: STORE_SETTINGS, value: (string)$data['rider_lead_minutes']);
        updateBusinessSetting(keyName: 'store_order_parcel_weight', settingType: STORE_SETTINGS, value: (string)$data['parcel_weight']);
        updateBusinessSetting(keyName: 'store_own_delivery_status', settingType: STORE_SETTINGS, value: $request->boolean('own_delivery_status') ? '1' : '0');
        updateBusinessSetting(keyName: 'store_own_delivery_max_km', settingType: STORE_SETTINGS, value: (string)$data['own_delivery_max_km']);
        updateBusinessSetting(keyName: 'store_own_delivery_share_percent', settingType: STORE_SETTINGS, value: (string)$data['own_delivery_share_percent']);
        updateBusinessSetting(keyName: 'store_my_riders_status', settingType: STORE_SETTINGS, value: $request->boolean('my_riders_status') ? '1' : '0');
        updateBusinessSetting(keyName: 'store_my_riders_first_seconds', settingType: STORE_SETTINGS, value: (string)$data['my_riders_first_seconds']);
        updateBusinessSetting(keyName: 'store_reseller_status', settingType: STORE_SETTINGS, value: $request->boolean('reseller_status') ? '1' : '0');
        foreach (['reseller_min_percent', 'reseller_max_percent', 'reseller_window_days', 'reseller_min_payout'] as $key) {
            updateBusinessSetting(keyName: 'store_' . $key, settingType: STORE_SETTINGS, value: (string)$data[$key]);
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Food & Shops settings saved'));
        return back();
    }
}
