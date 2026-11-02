<?php

namespace Modules\StoreManagement\Http\Controllers\Web\Partner;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Http\Controllers\HandlesStoreMenu;
use Modules\StoreManagement\Http\Requests\StoreProfileRequest;
use Modules\StoreManagement\Service\Interfaces\StoreServiceInterface;

/** The partner portal: menu, store profile, open/closed and earnings. Orders live in PartnerOrderController. */
class PortalController extends Controller
{
    use HandlesStoreMenu;

    public function __construct(protected StoreServiceInterface $stores)
    {
    }

    protected function storeService(): StoreServiceInterface
    {
        return $this->stores;
    }

    protected function menuStore(Request $request): Store
    {
        return $request->attributes->get('partnerStore');
    }

    public function menu(Request $request): View
    {
        $store = $this->menuStore($request)->load(['categories', 'items']);
        return view('storemanagement::partner.menu', compact('store'));
    }

    public function profile(Request $request): View
    {
        return view('storemanagement::partner.store', ['store' => $this->menuStore($request)]);
    }

    public function updateProfile(StoreProfileRequest $request): RedirectResponse
    {
        $this->stores->updateProfile($this->menuStore($request), $request->validated() + $request->only('logo', 'cover'));
        Toastr::success(translate('Store details saved'));
        return back();
    }

    /** The big open/closed switch at the top of every portal page. */
    public function toggleOpen(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['is_open' => 'required|boolean']);
        $store = $this->menuStore($request);
        $store->is_open = $request->boolean('is_open');
        $store->save();
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'is_open' => $store->is_open, 'open_now' => $store->isOpenNow()]);
        }
        return back();
    }

    public function earnings(Request $request): View
    {
        $store = $this->menuStore($request);
        $orders = $store->orders()->where('status', StoreOrder::DELIVERED)->latest('delivered_at')->paginate(20);
        $payouts = $store->payouts()->latest()->limit(20)->get();
        $balance = $store->balance();
        $today = (float)$store->orders()->where('status', StoreOrder::DELIVERED)->whereDate('delivered_at', today())->sum('partner_earning');
        $week = (float)$store->orders()->where('status', StoreOrder::DELIVERED)->where('delivered_at', '>=', now()->startOfWeek())->sum('partner_earning');
        return view('storemanagement::partner.earnings', compact('store', 'orders', 'payouts', 'balance', 'today', 'week'));
    }
}
