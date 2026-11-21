<?php

namespace Modules\StoreManagement\Transformers;

use Illuminate\Support\Carbon;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Entities\StoreOrder;

/** JSON shapes for the customer app (mirrored by its Dart models). */
class StorePresenter
{
    /** A kitchen as the app shows it: the brand's name, photos and rating, this kitchen's place and hours. */
    public static function store(Store $store, ?float $lat = null, ?float $lng = null): array
    {
        $now = now();
        $hours = $store->hoursFor($now);
        $brand = $store->brand();
        return [
            'id' => $store->id,
            'brand_id' => $brand->id,
            'branch_name' => $store->branch_name,
            'name' => $brand->name,
            'type' => $brand->type,
            'description' => $brand->description ?: $store->description,
            'logo' => $store->logoUrl(),
            'cover' => $store->coverUrl(),
            'address' => $store->address,
            'latitude' => $store->latitude,
            'longitude' => $store->longitude,
            'phone' => $store->phone,
            'open_now' => $store->isOpenNow($now),
            'today_hours' => $hours ? ['open' => $hours[0], 'close' => $hours[1]] : null,
            'prep_minutes' => $store->default_prep_minutes,
            'min_order_amount' => $store->min_order_amount,
            // How an order can be had here: delivery, dine_in (eat there), pickup (collect).
            'ways' => $store->ways(),
            ...$brand->brandRatingSummary(),
            'distance_km' => ($lat !== null && $lng !== null) ? round(self::distanceKm($lat, $lng, $store->latitude, $store->longitude), 1) : null,
        ];
    }

    /**
     * Today's menu. Hidden items (and hidden sections) are left out; items not
     * served right now (another day, or finished for today) move to a last
     * section, kind "not_today", so customers know when to come back.
     * is_available = can be ordered right now (older apps grey out the rest).
     */
    /**
     * @param Store $store the brand (owns the menu), with categories and items
     * @param Store|null $kitchen the kitchen serving the customer (defaults to the brand)
     * @param callable|null $elsewhere StoreBranches::elsewhereFinder, for dishes this kitchen can't serve now
     */
    public static function menu(Store $store, ?Carbon $at = null, ?Store $kitchen = null, ?callable $elsewhere = null): array
    {
        $at = $at ?? now();
        $store->linkMenu();
        $states = ($kitchen ?? $store)->stateMap();
        $stateOf = fn(StoreItem $i) => $i->menuState($at, $states->get($i->id));
        $item = function (StoreItem $i) use ($at, $stateOf, $elsewhere) {
            $state = $stateOf($i);
            $window = $i->effectiveWindow();
            return [
                'id' => $i->id,
                'name' => $i->name,
                'description' => $i->description,
                'price' => $i->price,
                'image' => $i->imageUrl(),
                'is_available' => $state === StoreItem::AVAILABLE,
                'state' => $state,
                'served_days' => StoreItem::maskToDays($i->effectiveDayMask()),
                'served_from' => $window[0] ?? null,
                'served_until' => $window[1] ?? null,
                'category_id' => $i->store_menu_category_id,
                // Sold out here or not made here, but available now at another open branch.
                'elsewhere' => $elsewhere && in_array($state, [StoreItem::SOLD_OUT, StoreItem::NOT_HERE], true) ? $elsewhere($i) : null,
            ];
        };
        $itemStates = $store->items->mapWithKeys(fn(StoreItem $i) => [$i->id => $stateOf($i)]);
        // A dish this branch doesn't make is listed only when another branch has it now.
        $notHereShown = $elsewhere
            ? $store->items->filter(fn($i) => $itemStates[$i->id] === StoreItem::NOT_HERE && $elsewhere($i))->pluck('id')->all()
            : [];
        $today = $store->items->filter(fn($i) => !in_array($itemStates[$i->id], [StoreItem::HIDDEN, StoreItem::NOT_TODAY, StoreItem::NOT_HERE], true)
            || in_array($i->id, $notHereShown, true));
        $later = $store->items->filter(fn($i) => $itemStates[$i->id] === StoreItem::NOT_TODAY);

        $categories = $store->categories->where('is_active', true)->values();
        $known = $categories->pluck('id')->all();
        $sections = $categories->map(fn($c) => [
            'id' => $c->id,
            'kind' => 'section',
            'name' => $c->name,
            'items' => $today->where('store_menu_category_id', $c->id)->map($item)->values(),
        ])->filter(fn($s) => count($s['items']) > 0)->values()->all();
        $loose = $today->filter(fn($i) => !$i->store_menu_category_id || !in_array($i->store_menu_category_id, $known))->map($item)->values();
        if ($loose->isNotEmpty()) {
            $sections[] = ['id' => null, 'kind' => 'section', 'name' => count($sections) ? translate('Other') : translate('Menu'), 'items' => $loose];
        }
        if ($later->isNotEmpty()) {
            $sections[] = ['id' => null, 'kind' => 'not_today', 'name' => translate('Not served right now'), 'items' => $later->map($item)->values()];
        }
        return $sections;
    }

    public static function order(StoreOrder $order, bool $withItems = true): array
    {
        $trip = $order->relationLoaded('trip') ? $order->trip : null;
        $driver = $trip?->driver;
        $review = $order->relationLoaded('review') ? $order->review : null;
        $riderReview = $review && $withItems && $trip
            ? \Modules\ReviewModule\Entities\Review::where('trip_request_id', $trip->id)->where('given_by', $order->customer_id)->first()
            : null;
        return [
            'id' => $order->id,
            'ref' => $order->ref_id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'store' => $order->store ? [
                'id' => $order->store->id,
                'name' => $order->store->displayName(),
                'logo' => $order->store->logoUrl(),
                'phone' => $order->store->phone,
            ] : null,
            'items' => $withItems ? $order->items->map(fn($i) => [
                'name' => $i->name,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
                'line_total' => $i->line_total,
            ])->values() : [],
            'items_count' => (int)$order->items->sum('quantity'),
            'items_subtotal' => $order->items_subtotal,
            'delivery_fee' => $order->delivery_fee,
            'total' => $order->total,
            'refunded_amount' => $order->refunded_amount,
            'drop_address' => $order->drop_address,
            'receiver_name' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'note' => $order->note,
            'prep_minutes' => $order->prep_minutes,
            'ready_at' => $order->ready_at?->toIso8601String(),
            'trip_request_id' => $order->trip_request_id,
            'trip_status' => $trip?->current_status,
            'rider' => $driver && in_array($trip->current_status, [ACCEPTED, OUT_FOR_PICKUP, ONGOING], true) ? [
                'id' => $driver->id,
                'name' => trim($driver->first_name . ' ' . $driver->last_name),
                'phone' => $driver->phone,
                'image' => $driver->profile_image ? dynamicStorage('storage/app/public/driver/profile') . '/' . $driver->profile_image : null,
            ] : null,
            // First name only, kept after delivery so the customer can rate the rider.
            'rider_first_name' => $driver?->first_name,
            'can_review' => $order->relationLoaded('review') && !$review && $order->status === StoreOrder::DELIVERED
                && $order->delivered_at?->gt(now()->subDays(\Modules\StoreManagement\Entities\StoreReview::MAX_DAYS_TO_REVIEW)),
            'review' => $review ? [
                'store_rating' => $review->rating,
                'store_comment' => $review->comment,
                'rider_rating' => $riderReview?->rating,
            ] : null,
            // delivery | dine_in (eat there) | pickup (collect), the time they come and for how many.
            'fulfilment' => $order->fulfilment ?? StoreOrder::DELIVERY,
            'arrive_at' => $order->arrive_at?->toIso8601String(),
            'guests' => $order->guests,
            // Shown at the restaurant once paid, until the order is handed over.
            'collect_code' => !$order->isDelivery() && $order->status === StoreOrder::ACCEPTED ? $order->collect_code : null,
            'ready_told' => $order->ready_told_at !== null,
            'store_place' => $order->store ? ['address' => $order->store->address, 'latitude' => $order->store->latitude, 'longitude' => $order->store->longitude] : null,
            // "Deliver it instead", while the order is still open and not a delivery.
            'can_deliver' => !$order->isDelivery() && in_array($order->status, [StoreOrder::AWAITING_CONFIRMATION, StoreOrder::AWAITING_PAYMENT, StoreOrder::ACCEPTED], true),
            // A change to delivery waiting for its fee.
            'delivery_change' => $order->switch && !$order->isDelivery() && \Illuminate\Support\Carbon::parse($order->switch['pay_by'] ?? now())->isFuture()
                ? ['fee' => (float)$order->switch['fee'], 'address' => $order->switch['drop_address'], 'pay_by' => $order->switch['pay_by'],
                    // To open the fee's page again (left without paying).
                    'pay_url' => route('store-order.pay-delivery', ['order' => $order->id, 'payment_method' => $order->switch['method'] ?? ''])] : null,
            'cancel_reason' => $order->cancel_reason,
            // A paid order is cancelled (and refunded) by the customer only when refunds are on.
            'can_cancel' => in_array($order->status, [StoreOrder::AWAITING_CONFIRMATION, StoreOrder::CHANGES_PROPOSED, StoreOrder::AWAITING_PAYMENT, StoreOrder::PLACED], true)
                && ($order->payment_status !== StoreOrder::PAID || \Modules\StoreManagement\Lib\StoreSettings::refunds()),
            // Confirm before payment: who has to act, and by when.
            'respond_by' => in_array($order->status, [StoreOrder::AWAITING_CONFIRMATION, StoreOrder::PLACED], true) && $order->placed_at
                ? $order->placed_at->copy()->addMinutes(\Modules\StoreManagement\Lib\StoreSettings::all()['partner_response_minutes'])->toIso8601String() : null,
            'answer_by' => $order->status === StoreOrder::CHANGES_PROPOSED && $order->proposed_at
                ? $order->proposed_at->copy()->addMinutes(\Modules\StoreManagement\Service\StoreOrderService::ANSWER_MINUTES)->toIso8601String() : null,
            'pay_by' => $order->isConfirmedUnpaid() ? $order->pay_by?->toIso8601String() : null,
            'confirmed_at' => $order->confirmed_at?->toIso8601String(),
            'proposal' => $order->status === StoreOrder::CHANGES_PROPOSED ? $order->proposal : null,
            'created_at' => $order->created_at?->toIso8601String(),
            'timeline' => [
                'placed_at' => $order->placed_at?->toIso8601String(),
                'accepted_at' => $order->accepted_at?->toIso8601String(),
                'rider_requested_at' => $order->rider_requested_at?->toIso8601String(),
                'picked_up_at' => $order->picked_up_at?->toIso8601String(),
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            ],
        ];
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
