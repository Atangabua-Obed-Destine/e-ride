<?php

namespace Modules\MarketManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketReport;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\MarketManagement\Service\FeedService;
use Modules\MarketManagement\Service\MarketChatService;
use Modules\MarketManagement\Service\ModerationService;
use Modules\MarketManagement\Service\ShopService;
use Modules\MarketManagement\Transformers\MarketPresenter;

/** Customer app, E-Ride Market for buyers: browsing, an item, a shop, reports, follows, blocks, referrals. */
class MarketController extends Controller
{
    public function __construct(
        protected ShopService $shops,
        protected FeedService $feed,
        protected ModerationService $moderation,
        protected MarketChatService $chats,
    ) {
    }

    /** What the screens need first: the choices, the rules, and the viewer's own shop. */
    public function meta(Request $request): JsonResponse
    {
        $settings = MarketSettings::all();
        $user = $request->user();
        $shop = $this->shops->mine($user);
        return response()->json(responseFormatter(DEFAULT_200, [
            'enabled' => $settings['enabled'],
            'categories' => MarketSettings::CATEGORIES,
            'digital_kinds' => MarketSettings::DIGITAL_KINDS,
            'digital_allowed' => $settings['digital_allowed'],
            'report_reasons' => MarketReport::REASONS,
            'max_photos' => MarketItem::MAX_PHOTOS,
            'max_previews' => MarketItem::MAX_PREVIEWS,
            'items_per_shop' => $settings['items_per_shop'],
            'delivery_payer' => $settings['delivery_payer'],
            'my_shop' => $shop ? MarketPresenter::shop($shop, $user->id) : null,
            'cannot_open_reason' => $this->shops->cannotOpenReason($user),
            'unread' => $this->chats->unread($user),
            'orders' => app(\Modules\MarketManagement\Service\MarketOrderService::class)->counts($user),
            'order_rules' => [
                'answer_minutes' => $settings['answer_minutes'],
                'agree_minutes' => $settings['agree_minutes'],
                'open_orders_per_buyer' => $settings['open_orders_per_buyer'],
            ],
            'decline_reasons' => \Modules\MarketManagement\Entities\MarketOrder::DECLINE_REASONS,
            'review_tags' => \Modules\MarketManagement\Entities\MarketReview::TAGS,
        ]));
    }

    public function feed(Request $request): JsonResponse
    {
        $request->validate([
            'category' => ['nullable', Rule::in(MarketSettings::CATEGORIES)],
            'q' => 'nullable|string|max:80',
            'following' => 'nullable|boolean',
            'shop' => 'nullable|string|max:90',
            'kind' => 'nullable|in:retail,wholesale',
            'verified' => 'nullable|boolean',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'limit' => 'nullable|integer|min:1|max:50',
            'offset' => 'nullable|integer|min:1',
        ]);
        if (!MarketSettings::enabled()) {
            return response()->json(responseFormatter(DEFAULT_200, ['total' => 0, 'items' => [], 'following' => []]));
        }
        $limit = (int)$request->query('limit', 20);
        $offset = ((int)$request->query('offset', 1) - 1) * $limit;
        $viewer = $request->user()->id;
        $filters = $request->only(['category', 'q', 'shop', 'kind']) + ['following' => $request->boolean('following'), 'verified' => $request->boolean('verified')];
        if ($request->filled(['lat', 'lng'])) {
            $filters['lat'] = (float)$request->query('lat');
            $filters['lng'] = (float)$request->query('lng');
        }
        $result = $this->feed->items($viewer, $filters, $offset, $limit);
        return response()->json(responseFormatter(DEFAULT_200, [
            'total' => $result['total'],
            'items' => $result['items']->map(fn(MarketItem $i) => MarketPresenter::item($i, $viewer))->all(),
            // The strip of followed shops, on the first page only.
            'following' => $offset === 0 ? $this->feed->followedShops($viewer)->map(fn(MarketShop $s) => MarketPresenter::shop($s, $viewer))->all() : [],
        ]));
    }

    public function shop(Request $request, string $slug): JsonResponse
    {
        $shop = MarketShop::where('slug', $slug)->first();
        $viewer = $request->user()->id;
        if (!$shop || (!$shop->isLive() && $shop->user_id !== $viewer)) {
            return $this->fail('shop', translate('This shop is no longer on E-Ride Market.'), 404);
        }
        if ($shop->user_id !== $viewer) {
            $this->shops->visit($shop, $viewer, false);
        }
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::shop($shop, $viewer) + [
            'following' => $this->shops->follows($viewer, $shop->id),
            'blocked' => $this->shops->blocked($viewer, $shop->id),
        ]));
    }

    public function item(Request $request, string $code): JsonResponse
    {
        $item = MarketItem::with(['photos', 'shop'])->where('code', $code)->first();
        $viewer = $request->user()->id;
        $mine = $item && $item->shop?->user_id === $viewer;
        if (!$item || (!$mine && !$item->isPublic())) {
            return $this->fail('item', translate('This item is no longer available.'), 404);
        }
        if (!$mine) {
            $this->shops->visit($item, $viewer, false);
        }
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::item($item, $viewer, true) + [
            'following' => $this->shops->follows($viewer, $item->shop_id),
        ]));
    }

    public function reportItem(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', Rule::in(MarketReport::REASONS)], 'note' => 'nullable|string|max:600',
            'block' => 'nullable|boolean']);
        $item = MarketItem::with('shop')->where('code', $code)->first();
        if (!$item || !$item->shop) {
            return $this->fail('item', translate('This item is no longer available.'), 404);
        }
        $this->moderation->report($request->user(), $item, $item->shop, $data['reason'], $data['note'] ?? null);
        if ($request->boolean('block')) {
            $this->shops->block($request->user(), $item->shop, true);
        }
        return response()->json(responseFormatter(DEFAULT_200, ['message' => translate('Thank you. E-Ride will check this report.')]));
    }

    public function reportShop(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', Rule::in(MarketReport::REASONS)], 'note' => 'nullable|string|max:600',
            'block' => 'nullable|boolean']);
        $shop = MarketShop::where('slug', $slug)->first();
        if (!$shop) {
            return $this->fail('shop', translate('This shop is no longer on E-Ride Market.'), 404);
        }
        $this->moderation->report($request->user(), null, $shop, $data['reason'], $data['note'] ?? null);
        if ($request->boolean('block')) {
            $this->shops->block($request->user(), $shop, true);
        }
        return response()->json(responseFormatter(DEFAULT_200, ['message' => translate('Thank you. E-Ride will check this report.')]));
    }

    public function follow(Request $request, string $slug): JsonResponse
    {
        $shop = MarketShop::where('slug', $slug)->where('status', MarketShop::LIVE)->first();
        if (!$shop) {
            return $this->fail('shop', translate('This shop is no longer on E-Ride Market.'), 404);
        }
        $this->shops->follow($request->user(), $shop, $request->boolean('on', true));
        return response()->json(responseFormatter(DEFAULT_200, ['following' => $this->shops->follows($request->user()->id, $shop->id)]));
    }

    public function block(Request $request, string $slug): JsonResponse
    {
        $shop = MarketShop::where('slug', $slug)->first();
        if (!$shop) {
            return $this->fail('shop', translate('This shop is no longer on E-Ride Market.'), 404);
        }
        $this->shops->block($request->user(), $shop, $request->boolean('on', true));
        return response()->json(responseFormatter(DEFAULT_200, ['blocked' => $this->shops->blocked($request->user()->id, $shop->id)]));
    }

    /** After a first sign-in from a shared link: "i:<code>" or "s:<slug>" from the install referrer. */
    public function referral(Request $request): JsonResponse
    {
        $data = $request->validate(['target' => ['required', 'string', 'max:100', 'regex:/^[is]:[a-z0-9-]+$/']]);
        return response()->json(responseFormatter(DEFAULT_200, ['counted' => $this->shops->claimReferral($request->user(), $data['target'])]));
    }

    /** 403 with errors[0]: what the app shows to the rider (it reads errors only on 403). */
    private function fail(string $code, string $message, int $status = 403): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), 403);
    }
}
