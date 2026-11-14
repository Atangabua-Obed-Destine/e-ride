<?php

namespace Modules\MarketManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketOrder;
use Modules\MarketManagement\Entities\MarketReview;
use Modules\MarketManagement\Service\MarketOrderService;
use Modules\MarketManagement\Entities\MarketReport;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\MarketManagement\Service\ModerationService;
use Modules\MarketManagement\Service\ShopService;

/** Admin, E-Ride Market: the moderation queue, shops and verification, settings. */
class MarketAdminController extends Controller
{
    public const TABS = ['open' => 'Needs a decision', 'digital' => 'Digital only', 'blocked' => 'Blocked words', 'decided' => 'Decided'];

    public function __construct(protected ModerationService $moderation, protected ShopService $shops)
    {
    }

    /** Items that need a look: hidden or reported (scams first), or stopped by a banned word. */
    public function moderation(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'open';
        $query = MarketItem::withTrashed()->with(['shop.owner', 'photos']);
        $openReports = fn($q) => $q->where('status', MarketReport::OPEN);
        match ($tab) {
            'blocked' => $query->where('status', MarketItem::BLOCKED),
            'digital' => $query->where('condition', 'digital')->where(fn($q) => $q->where('status', MarketItem::HIDDEN)
                ->orWhereIn('id', MarketReport::where('status', MarketReport::OPEN)->select('item_id'))),
            'decided' => $query->whereIn('id', MarketReport::where('status', '!=', MarketReport::OPEN)->select('item_id')),
            default => $query->where(fn($q) => $q->where('status', MarketItem::HIDDEN)
                ->orWhereIn('id', MarketReport::where('status', MarketReport::OPEN)->select('item_id'))),
        };
        $items = $query->orderByDesc('report_count')->latest('updated_at')->paginate(paginationLimit())->withQueryString();
        $reports = MarketReport::whereIn('item_id', $items->pluck('id'))->latest()->get()->groupBy('item_id');
        $shopReports = MarketReport::with('shop')->whereNull('item_id')->where('status', MarketReport::OPEN)->latest()->limit(20)->get();
        $stats = [
            'open' => MarketReport::where('status', MarketReport::OPEN)->count(),
            'blocked' => MarketItem::where('status', MarketItem::BLOCKED)->count(),
            'hidden' => MarketItem::where('status', MarketItem::HIDDEN)->count(),
            'live' => MarketItem::whereIn('status', MarketItem::PUBLIC)->count(),
            'shops' => MarketShop::where('status', MarketShop::LIVE)->count(),
        ];
        return view('marketmanagement::admin.moderation', compact('items', 'reports', 'shopReports', 'stats', 'tab') + ['settings' => MarketSettings::all()]);
    }

    public function decide(Request $request, MarketItem $item): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(MarketReport::DECISIONS)], 'reason' => 'nullable|string|max:191']);
        $this->moderation->decide($item, $data['decision'], $request->user(), $data['reason'] ?? null);
        Toastr::success(translate('Decision saved'));
        return back();
    }

    public function decideShop(Request $request, MarketShop $shop): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['kept', 'closed'])], 'reason' => 'nullable|string|max:191']);
        $this->moderation->decideShop($shop, $data['decision'], $request->user(), $data['reason'] ?? null);
        Toastr::success(translate('Decision saved'));
        return back();
    }

    public function shops(Request $request): View
    {
        $search = trim((string)$request->query('search', ''));
        $filter = in_array($request->query('filter'), ['requests', 'verified', 'closed'], true) ? $request->query('filter') : null;
        $shops = MarketShop::with('owner')->withCount(['items' => fn($q) => $q->whereIn('status', MarketItem::PUBLIC)])
            ->when($search !== '', fn($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->when($filter === 'requests', fn($q) => $q->whereNotNull('verification_requested_at')->whereNull('verified_at'))
            ->when($filter === 'verified', fn($q) => $q->whereNotNull('verified_at'))
            ->when($filter === 'closed', fn($q) => $q->where('status', MarketShop::CLOSED))
            ->orderByRaw('verification_requested_at IS NULL')->latest()
            ->paginate(paginationLimit())->withQueryString();
        $requests = MarketShop::whereNotNull('verification_requested_at')->whereNull('verified_at')->count();
        return view('marketmanagement::admin.shops', compact('shops', 'search', 'filter', 'requests'));
    }

    public function verify(Request $request, MarketShop $shop): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['verify', 'decline', 'unverify'])], 'id_seen' => 'nullable|boolean', 'shop_checked' => 'nullable|boolean']);
        if ($data['decision'] === 'verify') {
            if (!$request->boolean('id_seen') || !$request->boolean('shop_checked')) {
                Toastr::error(translate('Tick both checks before verifying.'));
                return back();
            }
            $this->shops->verify($shop, $request->user());
        } elseif ($data['decision'] === 'decline') {
            $this->shops->declineVerification($shop);
        } else {
            $shop->update(['verified_at' => null, 'verified_by' => null]);
        }
        Toastr::success(translate('Saved'));
        return back();
    }

    public function reopen(MarketShop $shop): RedirectResponse
    {
        $this->shops->reopen($shop);
        Toastr::success(translate('Shop reopened'));
        return back();
    }

    /** Orders across all shops: numbers for the period, the list, and the order rules. */
    public function orders(Request $request): View
    {
        $days = in_array((int)$request->query('days'), [7, 30, 90], true) ? (int)$request->query('days') : 30;
        $status = in_array($request->query('status'), ['open', 'completed', 'declined', 'expired', 'cancelled'], true) ? $request->query('status') : '';
        $since = now()->subDays($days);
        $period = MarketOrder::where('created_at', '>=', $since);
        $requests = (clone $period)->count();
        $confirmed = (clone $period)->whereNotNull('confirmed_at')->count();
        $expired = (clone $period)->where('status', MarketOrder::EXPIRED)->count();
        $stats = [
            'requests' => $requests,
            'confirmed_rate' => $requests ? (int)round($confirmed * 100 / $requests) : 0,
            'expired_rate' => $requests ? (int)round($expired * 100 / $requests) : 0,
            'completed' => (clone $period)->where('status', MarketOrder::COMPLETED)->count(),
            'reply' => ($r = MarketShop::whereNotNull('reply_minutes')->avg('reply_minutes')) !== null ? (int)round($r) : null,
            'rating' => MarketReview::where('hidden', false)->where('created_at', '>=', $since)->avg('stars'),
        ];
        $query = MarketOrder::with(['shop', 'buyer', 'seller', 'lines', 'review'])->where('created_at', '>=', $since);
        if ($status === 'open') {
            $query->whereIn('status', MarketOrder::OPEN);
        } elseif ($status !== '') {
            $query->where('status', $status);
        }
        $orders = $query->latest()->paginate(paginationLimit())->withQueryString();
        return view('marketmanagement::admin.orders', compact('orders', 'stats', 'days', 'status') + ['settings' => MarketSettings::all()]);
    }

    public function saveOrderRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'answer_minutes' => 'required|integer|min:10|max:1440',
            'agree_minutes' => 'required|integer|min:10|max:1440',
            'open_orders_per_buyer' => 'required|integer|min:1|max:50',
            'demote_after_expired' => 'required|integer|min:1|max:50',
            'before_pay_verified_only' => 'nullable|boolean',
        ]);
        $data['before_pay_verified_only'] = $request->boolean('before_pay_verified_only') ? 1 : 0;
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: 'market_' . $key, settingType: MarketSettings::TYPE, value: (string)$value);
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Order rules saved'));
        return back();
    }

    /** An abusive or fake rating can be hidden; the shop's rating is recomputed. */
    public function hideReview(Request $request, MarketReview $review): RedirectResponse
    {
        $review->update(['hidden' => $request->boolean('hidden')]);
        app(MarketOrderService::class)->refreshRating($review->shop_id);
        Toastr::success(translate($review->hidden ? 'Rating hidden' : 'Rating shown again'));
        return back();
    }

    public function settings(): View
    {
        return view('marketmanagement::admin.settings', ['settings' => MarketSettings::all()]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'digital_allowed' => 'nullable|boolean',
            'digital_warning' => 'nullable|boolean',
            'hide_after_reports' => 'required|integer|min:1|max:20',
            'items_per_shop' => 'required|integer|min:1|max:500',
            'delivery_payer' => ['required', Rule::in(MarketSettings::DELIVERY_PAYERS)],
            'banned_words' => 'nullable|string|max:20000',
        ]);
        foreach (['enabled', 'digital_allowed', 'digital_warning'] as $switch) {
            $data[$switch] = $request->boolean($switch) ? 1 : 0;
        }
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: 'market_' . $key, settingType: MarketSettings::TYPE, value: (string)($value ?? ''));
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('E-Ride Market settings saved'));
        return back();
    }
}
