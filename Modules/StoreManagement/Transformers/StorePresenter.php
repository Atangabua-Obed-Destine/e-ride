<?php

namespace Modules\StoreManagement\Transformers;

use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Entities\StoreOrder;

/** JSON shapes for the customer app (mirrored by its Dart models). */
class StorePresenter
{
    public static function store(Store $store, ?float $lat = null, ?float $lng = null): array
    {
        $now = now();
        $hours = $store->hoursFor($now);
        return [
            'id' => $store->id,
            'name' => $store->name,
            'type' => $store->type,
            'description' => $store->description,
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
            'distance_km' => ($lat !== null && $lng !== null) ? round(self::distanceKm($lat, $lng, $store->latitude, $store->longitude), 1) : null,
        ];
    }

    public static function menu(Store $store): array
    {
        $item = fn(StoreItem $i) => [
            'id' => $i->id,
            'name' => $i->name,
            'description' => $i->description,
            'price' => $i->price,
            'image' => $i->imageUrl(),
            'is_available' => $i->is_available,
            'category_id' => $i->store_menu_category_id,
        ];
        $categories = $store->categories->where('is_active', true)->values();
        $known = $categories->pluck('id')->all();
        $sections = $categories->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'items' => $store->items->where('store_menu_category_id', $c->id)->map($item)->values(),
        ])->filter(fn($s) => count($s['items']) > 0)->values()->all();
        $loose = $store->items->filter(fn($i) => !$i->store_menu_category_id || !in_array($i->store_menu_category_id, $known))->map($item)->values();
        if ($loose->isNotEmpty()) {
            $sections[] = ['id' => null, 'name' => count($sections) ? translate('Other') : translate('Menu'), 'items' => $loose];
        }
        return $sections;
    }

    public static function order(StoreOrder $order, bool $withItems = true): array
    {
        $trip = $order->relationLoaded('trip') ? $order->trip : null;
        $driver = $trip?->driver;
        return [
            'id' => $order->id,
            'ref' => $order->ref_id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'store' => $order->store ? [
                'id' => $order->store->id,
                'name' => $order->store->name,
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
                'name' => trim($driver->first_name . ' ' . $driver->last_name),
                'phone' => $driver->phone,
                'image' => $driver->profile_image ? dynamicStorage('storage/app/public/driver/profile') . '/' . $driver->profile_image : null,
            ] : null,
            'cancel_reason' => $order->cancel_reason,
            'can_cancel' => in_array($order->status, [StoreOrder::AWAITING_PAYMENT, StoreOrder::PLACED], true),
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
