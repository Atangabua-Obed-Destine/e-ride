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
        return view('storemanagement::partner.menu', $this->menuPage($request));
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

    /** Share the store: the public page's link, a WhatsApp Status picture, and what the link brought. */
    public function share(Request $request): View
    {
        $brand = $this->menuStore($request)->brand();
        $kitchen = $this->menuStore($request);
        $url = \Modules\StoreManagement\Lib\StoreLinks::url($brand);
        $items = $brand->items()->where('is_available', true)->get()
            ->sortByDesc(fn($i) => $i->image ? 1 : 0)->take(4)->values();
        $hours = $kitchen->hoursFor(now());
        $ways = array_map(fn($w) => translate(match ($w) { 'delivery' => 'Delivery', 'dine_in' => 'Eat there', default => 'Collect' }), $kitchen->ways());
        $shareText = translate(key: 'Order from {store} on E-Ride: {link}', replace: ['store' => $brand->name, 'link' => $url]);
        $card = [
            'kind' => $brand->type === 'shop' ? 'SHOPS' : 'FOOD',
            'name' => $brand->name,
            'line' => implode('  ·  ', array_filter([
                $hours ? $hours[0] . ' - ' . $hours[1] : null,
                implode(', ', $ways),
            ])),
            'cover' => $brand->coverUrl(),
            'logo' => $brand->logoUrl(),
            'items' => $items->map(fn($i) => ['name' => $i->name, 'price' => getCurrencyFormat((float)$i->price), 'image' => $i->imageUrl()])->all(),
            'cta' => translate('Order in the E-Ride app'),
            'link' => preg_replace('#^https?://#', '', $url),
            'page' => $url,
            'share_text' => $shareText,
            'file' => 'eride-' . $brand->slug . '.png',
        ];
        return view('storemanagement::partner.share', compact('brand', 'url', 'card', 'shareText'));
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

    /** The owner sees every branch and the balance (payouts go to the owner); a branch sees its own orders. */
    public function earnings(Request $request): View
    {
        $store = $this->menuStore($request);
        $ids = $store->isBranch() ? [$store->id] : $store->kitchens()->pluck('id')->all();
        $delivered = fn() => StoreOrder::with('store.parent')->whereIn('store_id', $ids)->where('status', StoreOrder::DELIVERED);
        $orders = $delivered()->latest('delivered_at')->paginate(20);
        $payouts = $store->isBranch() ? collect() : $store->payouts()->latest()->limit(20)->get();
        $balance = $store->isBranch() ? null : $store->balance();
        $today = (float)$delivered()->whereDate('delivered_at', today())->sum('partner_earning');
        $week = (float)$delivered()->where('delivered_at', '>=', now()->startOfWeek())->sum('partner_earning');
        $showBranch = count($ids) > 1;
        return view('storemanagement::partner.earnings', compact('store', 'orders', 'payouts', 'balance', 'today', 'week', 'showBranch'));
    }
}
