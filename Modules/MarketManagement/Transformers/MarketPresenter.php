<?php

namespace Modules\MarketManagement\Transformers;

use Modules\MarketManagement\Entities\MarketConversation;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketItemPhoto;
use Modules\MarketManagement\Entities\MarketMessage;
use Modules\MarketManagement\Entities\MarketOrder;
use Modules\MarketManagement\Entities\MarketOrderLine;
use Modules\MarketManagement\Entities\MarketReview;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Lib\MarketLinks;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\TripManagement\Entities\TripRequest;

/**
 * What the app sees. No phone numbers: buyers and sellers talk in the chat. The only
 * exception is the seller seeing the buyer's name and phone once the buyer shared a
 * delivery point, because the seller needs them to book the parcel.
 */
class MarketPresenter
{
    public static function shop(MarketShop $shop, ?string $viewer = null, bool $stats = false): array
    {
        $mine = $viewer !== null && $viewer === $shop->user_id;
        $data = [
            'id' => $shop->id,
            'slug' => $shop->slug,
            'name' => $shop->name,
            'category' => $shop->category,
            'categories' => $shop->categoryList(),
            'about' => $shop->about,
            'area' => $shop->area_label,
            'logo' => $shop->logoUrl(),
            'verified' => $shop->isVerified(),
            'is_new' => $shop->isNew(),
            'live' => $shop->isLive(),
            'since' => $shop->created_at?->toDateString(),
            'link' => MarketLinks::shop($shop),
            'items_count' => $shop->items_count ?? $shop->items()->whereIn('status', MarketItem::PUBLIC)->count(),
            'mine' => $mine,
            // Orders: what buyers see before sending one.
            'taking_orders' => $shop->acceptsOrders(),
            'fulfilment' => $shop->fulfilmentList(),
            'pay_methods' => $shop->payMethodList(),
            'open_from' => $shop->open_from,
            'open_to' => $shop->open_to,
            'open_now' => $shop->isOpenAt(now()),
            'min_order' => $shop->min_order,
            'rating' => $shop->rating,
            'rating_count' => (int)$shop->rating_count,
            'orders_completed' => (int)$shop->orders_completed,
            'reply_minutes' => $shop->reply_minutes,
        ];
        if ($mine) {
            $data += [
                'status' => $shop->status,
                'closed_reason' => $shop->closed_reason,
                'verification_requested' => (bool)$shop->verification_requested_at,
                'pickup_lat' => $shop->pickup_lat,
                'pickup_lng' => $shop->pickup_lng,
                'pickup_address' => $shop->pickup_address,
            ];
        }
        if ($mine && $stats) {
            $data['stats'] = ['visits' => $shop->visits, 'chats' => $shop->chats, 'new_users' => $shop->new_users,
                'followers' => $shop->follows()->count()];
            $data['taking_orders_switch'] = (bool)$shop->taking_orders;
            $today = MarketOrder::where('shop_id', $shop->id)->where('created_at', '>=', now()->startOfDay());
            $data['today'] = [
                'orders' => (clone $today)->count(),
                'confirmed_total' => (float)(clone $today)->whereIn('status', array_merge(MarketOrder::HELD, [MarketOrder::COMPLETED]))->sum('confirmed_total'),
            ];
        }
        return $data;
    }

    public static function item(MarketItem $item, ?string $viewer = null, bool $full = false): array
    {
        $shop = $item->shop;
        $photos = $item->relationLoaded('photos') ? $item->photos : $item->photos()->get();
        $cover = $photos->firstWhere('preview', false) ?? $photos->first();
        $mine = $viewer !== null && $shop && $viewer === $shop->user_id;
        $settings = MarketSettings::all();
        $data = [
            'id' => $item->id,
            'code' => $item->code,
            'title' => $item->title,
            'price' => $item->price,
            'negotiable' => $item->negotiable,
            'condition' => $item->condition,
            'digital_kind' => $item->digital_kind,
            'category' => $item->category,
            'quantity' => $item->quantity,
            'status' => $item->status,
            'cover' => $cover?->url(),
            'link' => MarketLinks::item($item),
            'shop' => $shop ? ['slug' => $shop->slug, 'name' => $shop->name, 'area' => $shop->area_label, 'verified' => $shop->isVerified(),
                'logo' => $shop->logoUrl()] : null,
            'mine' => $mine,
            'created_at' => $item->created_at?->toIso8601String(),
            'wholesale_price' => $item->wholesale_price,
            'wholesale_min' => $item->wholesale_min,
            'orderable' => $item->orderable() && (bool)$shop?->acceptsOrders(),
        ];
        if ($full) {
            $data += [
                'description' => $item->description,
                'photos' => $photos->where('preview', false)->values()->map(fn(MarketItemPhoto $p) => ['id' => $p->id, 'url' => $p->url()])->all(),
                'previews' => $photos->where('preview', true)->values()->map(fn(MarketItemPhoto $p) => ['id' => $p->id, 'url' => $p->url()])->all(),
                'pickup' => $item->pickup,
                'eride_delivery' => $item->eride_delivery,
                'seller_delivers' => $item->seller_delivers,
                // Digital products of new, unverified shops get the strong red warning.
                'strong_warning' => $item->isDigital() && $settings['digital_warning'] && $shop && !$shop->isVerified(),
                'options' => [
                    'colours' => array_map(fn($c) => $c + ['photo' => ($p = $photos->firstWhere('id', (int)($c['photo_id'] ?? 0))) ? $p->url() : null], $item->colours()),
                    'sizes' => $item->sizes(),
                ],
                // Pieces left per colour and size, so sold-out choices show crossed out.
                'stock' => $item->hasOptions() ? (object)($item->stock ?? []) : null,
                'shop_orders' => $shop ? [
                    'taking_orders' => $shop->acceptsOrders(), 'fulfilment' => $shop->fulfilmentList(), 'pay_methods' => $shop->payMethodList(),
                    'rating' => $shop->rating, 'rating_count' => (int)$shop->rating_count, 'orders_completed' => (int)$shop->orders_completed,
                    'reply_minutes' => $shop->reply_minutes,
                ] : null,
            ];
        }
        if ($mine) {
            $data += ['visits' => $item->visits, 'chats' => $item->chats, 'status_note' => $item->status_note,
                'blocked_word' => $item->blocked_word];
        }
        return $data;
    }

    public static function conversation(MarketConversation $c, string $viewer): array
    {
        $role = $c->roleOf($viewer);
        $item = $c->item;
        $data = [
            'id' => $c->id,
            'role' => $role,
            'item' => $item ? self::item($item, $viewer) : null,
            'shop' => $c->shop ? ['slug' => $c->shop->slug, 'name' => $c->shop->name, 'verified' => $c->shop->isVerified(),
                'logo' => $c->shop->logoUrl(), 'live' => $c->shop->isLive()] : null,
            // The buyer is shown to the seller by first name only.
            'buyer_name' => $role === 'seller' ? (trim((string)$c->buyer?->first_name) ?: translate('Buyer')) : null,
            'last_message' => $c->last_message,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'unread' => $role === 'buyer' ? $c->buyer_unread : $c->seller_unread,
            'buyer_blocked' => $c->buyer_blocked,
        ];
        // The seller books the delivery: the buyer's details, only once the buyer asked for it.
        if ($role === 'seller' && $c->messages()->where('type', 'delivery_point')->exists()) {
            $data['receiver'] = ['name' => trim($c->buyer?->first_name . ' ' . $c->buyer?->last_name), 'phone' => $c->buyer?->phone];
        }
        return $data;
    }

    public static function order(MarketOrder $order, string $viewer, bool $full = false): array
    {
        $role = $order->roleOf($viewer);
        $shop = $order->shop;
        $lines = $order->relationLoaded('lines') ? $order->lines : $order->lines()->get();
        $held = in_array($order->status, MarketOrder::HELD, true);
        $data = [
            'id' => $order->id,
            'code' => $order->code,
            'role' => $role,
            'status' => $order->status,
            'shop' => $shop ? ['slug' => $shop->slug, 'name' => $shop->name, 'logo' => $shop->logoUrl(), 'verified' => $shop->isVerified(),
                'area' => $shop->area_label] : null,
            'buyer_name' => $role === 'seller' ? (trim((string)$order->buyer?->first_name) ?: translate('Buyer')) : null,
            'fulfilment' => $order->fulfilment,
            'pay_method' => $order->pay_method,
            'pieces' => $lines->sum('qty'),
            'pieces_confirmed' => $lines->sum(fn(MarketOrderLine $l) => $l->finalQty()),
            'requested_total' => $order->requested_total,
            'confirmed_total' => $order->confirmed_total,
            'photo' => $lines->first()?->photo,
            'summary' => $lines->take(3)->map(fn(MarketOrderLine $l) => $l->title . ($l->qty > 1 ? ' ×' . $l->qty : ''))->implode(', '),
            'respond_by' => $order->respond_by?->toIso8601String(),
            'agree_by' => $order->agree_by?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
        if (!$full) {
            return $data;
        }
        $data += [
            'lines' => $lines->map(fn(MarketOrderLine $l) => [
                'id' => $l->id, 'item_code' => $l->item?->code, 'title' => $l->title, 'photo' => $l->photo, 'colour' => $l->colour, 'size' => $l->size,
                'unit_price' => $l->unit_price, 'wholesale' => $l->wholesale, 'qty' => $l->qty, 'qty_confirmed' => $l->qty_confirmed,
                'seller_note' => $l->seller_note,
                // For the seller answering: how many are left now.
                'in_stock' => $role === 'seller' && $l->item ? $l->item->stockFor($l->colour, $l->size) : null,
            ])->values()->all(),
            'note' => $order->note,
            'drop' => $order->drop_lat !== null ? ['lat' => $order->drop_lat, 'lng' => $order->drop_lng, 'address' => $order->drop_address] : null,
            'decline_reason' => $order->decline_reason,
            'decline_note' => $order->decline_note,
            'cancel_reason' => $order->cancel_reason,
            'cancelled_by' => $order->cancelled_by,
            'answered_at' => $order->answered_at?->toIso8601String(),
            'confirmed_at' => $order->confirmed_at?->toIso8601String(),
            'ready_at' => $order->ready_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'closed_at' => $order->closed_at?->toIso8601String(),
            'conversation_id' => $order->conversation_id,
        ];
        // The buyer's code to close the order, only for them and only while the order is held.
        if ($role === 'buyer' && $held) {
            $data['receive_code'] = $order->receive_code;
        }
        // The exact pickup spot only once the seller confirmed.
        if ($role === 'buyer' && $held && $order->fulfilment === 'pickup' && $shop?->pickup_lat !== null) {
            $data['pickup'] = ['lat' => $shop->pickup_lat, 'lng' => $shop->pickup_lng, 'address' => $shop->pickup_address];
        }
        // The seller books the delivery: the buyer's name and phone, only for a held delivery order.
        if ($role === 'seller' && $held && in_array($order->fulfilment, ['eride', 'seller'], true)) {
            $data['receiver'] = ['name' => trim($order->buyer?->first_name . ' ' . $order->buyer?->last_name), 'phone' => $order->buyer?->phone];
        }
        if ($order->trip_id && ($trip = TripRequest::find($order->trip_id, ['id', 'ref_id', 'current_status']))) {
            $data['trip'] = ['id' => $trip->id, 'ref_id' => $trip->ref_id, 'status' => $trip->current_status];
        }
        $review = $order->review;
        $data['review'] = $review ? ['stars' => $review->stars, 'tags' => $review->tags ?? [], 'comment' => $review->comment] : null;
        $data['can_review'] = $role === 'buyer' && $order->status === MarketOrder::COMPLETED && !$review;
        return $data;
    }

    public static function review(MarketReview $review): array
    {
        return [
            'stars' => $review->stars,
            'tags' => $review->tags ?? [],
            'comment' => $review->comment,
            'buyer' => trim((string)$review->buyer?->first_name) ?: translate('Buyer'),
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }

    public static function message(MarketMessage $m, string $viewer): array
    {
        $data = [
            'id' => $m->id,
            'mine' => $m->sender_id === $viewer,
            'type' => $m->type,
            'body' => $m->body,
            'photo' => $m->photoUrl(),
            'meta' => $m->meta,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
        if ($m->type === 'delivery_booked' && ($tripId = $m->meta['trip_id'] ?? null)) {
            $trip = TripRequest::find($tripId, ['id', 'ref_id', 'current_status']);
            $data['delivery'] = $trip ? ['trip_id' => $trip->id, 'ref_id' => $trip->ref_id, 'status' => $trip->current_status] : null;
        }
        return $data;
    }
}
