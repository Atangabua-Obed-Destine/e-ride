<?php

namespace Modules\StoreManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreReseller;
use Modules\StoreManagement\Entities\StoreResellerPayout;
use Modules\StoreManagement\Lib\StoreResellers;
use Modules\StoreManagement\Lib\StoreSettings;

/** Admin: resellers of partner stores, what E-Ride owes them, and recording a Mobile Money payout. */
class StoreResellerAdminController extends Controller
{
    public function index(): View
    {
        return view('storemanagement::admin.resellers', [
            'owed' => StoreResellers::owed(),
            'minPayout' => StoreSettings::all()['reseller_min_payout'],
            'payouts' => StoreResellerPayout::with('reseller.user')->latest('paid_at')->limit(30)->get(),
            'resellers' => StoreReseller::with('user')->latest()->limit(100)->get(),
            'stores' => Store::whereNull('parent_id')->whereNotNull('reseller_pct')->where('reseller_pct', '>', 0)->orderBy('name')->get(),
        ]);
    }

    public function pay(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['reference' => 'nullable|string|max:120']);
        $reseller = StoreReseller::findOrFail($id);
        $payout = StoreResellers::markPaid($reseller, $data['reference'] ?? null, auth()->id());
        Toastr::success(translate(key: 'Recorded: {amount} paid to {name}', replace: ['amount' => getCurrencyFormat($payout->amount), 'name' => $reseller->momo_name]));
        return back();
    }

    public function status(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:active,paused', 'reason' => 'nullable|string|max:191']);
        StoreReseller::findOrFail($id)->update(['status' => $data['status'], 'pause_reason' => $data['status'] === 'paused' ? ($data['reason'] ?? null) : null]);
        Toastr::success(translate('Saved'));
        return back();
    }
}
