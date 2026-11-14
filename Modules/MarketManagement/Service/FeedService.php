<?php

namespace Modules\MarketManagement\Service;

use Illuminate\Support\Collection;
use Modules\MarketManagement\Entities\MarketBlock;
use Modules\MarketManagement\Entities\MarketFollow;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketShop;

/** Browsing: newest public items, by category, search, followed shops, nearest first. Blocked shops never show. */
class FeedService
{
    /**
     * @param array{category?:string, q?:string, following?:bool, lat?:float, lng?:float, shop?:string} $filters
     * @return array{total:int, items:Collection}
     */
    public function items(?string $viewer, array $filters, int $offset, int $limit): array
    {
        $query = MarketItem::with(['photos', 'shop'])
            ->whereIn('market_items.status', MarketItem::PUBLIC)
            ->whereHas('shop', fn($s) => $s->where('status', MarketShop::LIVE));
        if ($viewer) {
            $blocked = MarketBlock::where('user_id', $viewer)->pluck('shop_id');
            if ($blocked->isNotEmpty()) {
                $query->whereNotIn('market_items.shop_id', $blocked);
            }
            if (!empty($filters['following'])) {
                $query->whereIn('market_items.shop_id', MarketFollow::where('user_id', $viewer)->pluck('shop_id'));
            }
        }
        if (!empty($filters['category'])) {
            $query->where('market_items.category', $filters['category']);
        }
        if (!empty($filters['shop'])) {
            $query->whereHas('shop', fn($s) => $s->where('slug', $filters['shop']));
        }
        if (($q = trim((string)($filters['q'] ?? ''))) !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(fn($w) => $w->where('market_items.title', 'like', $like)->orWhere('market_items.description', 'like', $like)
                ->orWhereHas('shop', fn($s) => $s->where('name', 'like', $like)));
        }
        $total = (clone $query)->count();
        if (isset($filters['lat'], $filters['lng'])) {
            // Nearest shops first (their pickup point), then newest.
            $query->join('market_shops as near', 'near.id', '=', 'market_items.shop_id')
                ->select('market_items.*')
                ->orderByRaw('CASE WHEN near.pickup_lat IS NULL THEN 1 ELSE 0 END')
                ->orderByRaw('POW(near.pickup_lat - ?, 2) + POW((near.pickup_lng - ?) * COS(RADIANS(?)), 2)',
                    [(float)$filters['lat'], (float)$filters['lng'], (float)$filters['lat']]);
        }
        $items = $query->orderByDesc('market_items.created_at')->skip($offset)->take($limit)->get();
        return ['total' => $total, 'items' => $items];
    }

    /** The followed shops for the strip on the Market screen. */
    public function followedShops(string $viewer, int $limit = 20): Collection
    {
        return MarketShop::where('status', MarketShop::LIVE)
            ->whereIn('id', MarketFollow::where('user_id', $viewer)->pluck('shop_id'))
            ->latest('updated_at')->limit($limit)->get();
    }
}
