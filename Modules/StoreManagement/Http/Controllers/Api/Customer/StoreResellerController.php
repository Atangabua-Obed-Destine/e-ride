<?php

namespace Modules\StoreManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreReseller;
use Modules\StoreManagement\Lib\StoreResellers;
use Modules\StoreManagement\Lib\StoreSettings;

/**
 * "Sell for shops": the stores that pay resellers, my links, what I brought and earned;
 * joining with a Mobile Money number; and the tap when a customer opens a reseller's link.
 */
class StoreResellerController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $settings = StoreSettings::all();
        $reseller = StoreResellers::mine($request->user());
        return response()->json(responseFormatter(DEFAULT_200, [
            'enabled' => $settings['reseller_status'],
            'window_days' => $settings['reseller_window_days'],
            'min_payout' => $settings['reseller_min_payout'],
            'reseller' => $reseller ? $this->reseller($reseller) : null,
            'stores' => StoreResellers::stores()->map(fn(Store $s) => [
                'slug' => $s->slug,
                'name' => $s->name,
                'logo' => $s->logoUrl(),
                'description' => $s->description,
                'rate' => StoreResellers::rate($s),
                'link' => $reseller ? StoreResellers::link($s, $reseller) : null,
            ])->values()->all(),
            'summary' => $reseller ? StoreResellers::summary($reseller) : null,
            'orders' => $reseller ? StoreResellers::orders($reseller)->all() : [],
        ]));
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate([
            'momo_phone' => 'required|string|max:20',
            'momo_name' => 'required|string|min:2|max:80',
        ]);
        $reseller = StoreResellers::join($request->user(), $data['momo_phone'], $data['momo_name']);
        return response()->json(responseFormatter(DEFAULT_200, $this->reseller($reseller)));
    }

    /** A customer opened a store through a reseller's link. */
    public function tap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:12',
            'slug' => 'required|string|max:140',
        ]);
        return response()->json(responseFormatter(DEFAULT_200, ['counted' => StoreResellers::tap($request->user(), $data['code'], $data['slug'])]));
    }

    private function reseller(StoreReseller $r): array
    {
        return ['code' => $r->code, 'momo_phone' => $r->momo_phone, 'momo_name' => $r->momo_name, 'status' => $r->status, 'pause_reason' => $r->pause_reason];
    }
}
