<?php

namespace Modules\TripManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\TripManagement\Lib\PaymentWays;

/** Trip management > Ways to pay: turn cash and the E-Ride wallet on or off. */
class PaymentWaysController extends Controller
{
    public function index(): View
    {
        $this->authorize('trip_view');
        return view('tripmanagement::admin.payment-ways', ['cash' => PaymentWays::cash(), 'wallet' => PaymentWays::wallet()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('trip_edit');
        $save = fn(string $key, bool $on) => updateBusinessSetting(keyName: $key, settingType: TRIP_SETTINGS, value: $on ? '1' : '0');
        $save('payment_cash_status', $request->boolean('cash'));
        $save('payment_wallet_status', $request->boolean('wallet'));
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Ways to pay saved'));
        return back();
    }
}
