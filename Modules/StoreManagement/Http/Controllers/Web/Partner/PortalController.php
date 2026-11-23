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
        $available = $brand->items()->where('is_available', true)->get();
        $look = \App\Lib\ShareLooks::present('store', $brand->id, true);
        // The item shown big: the one chosen, else the first with a photo.
        $auto = $available->sortByDesc(fn($i) => $i->image ? 1 : 0)->first();
        $big = $available->firstWhere('id', $look['item']) ?? $auto;
        $items = $available->sortByDesc(fn($i) => $i->image ? 1 : 0)->take(6)->values();
        $bigOf = fn($i) => ['name' => $i->name, 'price' => getCurrencyFormat((float)$i->price), 'image' => $i->imageUrl(), 'about' => mb_substr((string)$i->description, 0, 160)];
        $hours = $kitchen->hoursFor(now());
        $ways = array_map(fn($w) => translate(match ($w) { 'delivery' => 'Delivery', 'dine_in' => 'Eat there', default => 'Collect' }), $kitchen->ways());
        $shareText = translate(key: 'Order from {store} on E-Ride: {link}', replace: ['store' => $brand->name, 'link' => $url]);
        $card = [
            'kind' => $brand->type === 'shop' ? 'SHOPS' : 'FOOD',
            'name' => $brand->name,
            'line' => implode('  ·  ', array_filter([
                $hours ? ($hours === ['00:00', '23:59'] ? translate('Open all day') : $hours[0] . ' - ' . $hours[1]) : null,
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
            // The link as a QR code: rows of 1 (dark) and 0, drawn by the page.
            'qr' => self::qrRows($url),
            'scan' => translate('Scan to see the menu and order'),
            'scan_short' => translate('Scan or tap the link'),
            'no_app' => translate('No app needed: the menu opens in the browser.'),
            'ways' => array_values($ways),
            'type' => $brand->type === 'shop' ? translate('Shop') : translate('Restaurant'),
            'big' => $big ? $bigOf($big) : null,
            'big_default' => $auto ? $bigOf($auto) : null,
            'items_by_id' => $available->take(300)->mapWithKeys(fn($i) => [$i->id => $bigOf($i)])->all(),
            'rows' => ['list' => $available->take(8)->map(fn($i) => ['l' => $i->name, 'r' => getCurrencyFormat((float)$i->price)])->values()->all()],
            'poster_line' => ($brand->type === 'shop' ? translate('Shop') : translate('Restaurant')) . '  ·  ' . implode(', ', $ways),
            'poster_hint' => translate('Print it and put it on your counter or your door: customers scan it to see your menu and order.'),
            'about' => (string)$brand->description,
            'look' => \Illuminate\Support\Arr::except($look, ['options', 'removed']),
        ];
        $staff = $request->attributes->get('partnerStaff');
        $canEdit = !$staff || \Modules\StoreManagement\Lib\StoreTeam::allows($staff, 'partner.share.look');
        $pickItems = $available->take(300)->map(fn($i) => ['id' => $i->id, 'name' => $i->name])->sortBy('name')->values();
        return view('storemanagement::partner.share', compact('brand', 'url', 'card', 'shareText', 'look', 'canEdit', 'pickItems'));
    }

    /** The look of the share pictures: colour, layout, the item shown big, the E-Ride strip. */
    public function saveLook(Request $request): JsonResponse
    {
        $data = $request->validate([
            'color' => 'nullable|string|max:7',
            'layout' => 'nullable|string|max:20',
            'item' => 'nullable|string|max:64',
            'strip' => 'nullable|boolean',
        ]);
        $brand = $this->menuStore($request)->brand();
        if (!empty($data['item']) && !$brand->items()->whereKey($data['item'])->exists()) {
            $data['item'] = null;
        }
        \App\Lib\ShareLooks::save('store', $brand->id, $data);
        return response()->json(['ok' => true, 'look' => \App\Lib\ShareLooks::present('store', $brand->id, true)]);
    }

    /** A picture the partner designed, shared instead of the one E-Ride draws. */
    public function saveDesign(Request $request): JsonResponse
    {
        $request->validate(['design' => 'required|file']);
        $brand = $this->menuStore($request)->brand();
        \App\Lib\ShareLooks::saveDesign('store', $brand->id, $request->file('design'));
        return response()->json(['ok' => true, 'look' => \App\Lib\ShareLooks::present('store', $brand->id, true)]);
    }

    public function removeDesign(Request $request): JsonResponse
    {
        $brand = $this->menuStore($request)->brand();
        \App\Lib\ShareLooks::removeDesign('store', $brand->id);
        return response()->json(['ok' => true, 'look' => \App\Lib\ShareLooks::present('store', $brand->id, true)]);
    }

    /** A QR code of $text as rows of "0"/"1". */
    private static function qrRows(string $text): array
    {
        $matrix = \BaconQrCode\Encoder\Encoder::encode($text, \BaconQrCode\Common\ErrorCorrectionLevel::M())->getMatrix();
        $rows = [];
        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            $row = '';
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                $row .= $matrix->get($x, $y) === 1 ? '1' : '0';
            }
            $rows[] = $row;
        }
        return $rows;
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
