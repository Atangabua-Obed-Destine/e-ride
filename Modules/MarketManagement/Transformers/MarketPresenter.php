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
            // shop | private (a person selling things they own)
            'kind' => $shop->kind ?? 'shop',
            // A shop's place (pin, address, how to find it); private sellers never.
            'map' => $shop->onMap() ? ['lat' => $shop->pickup_lat, 'lng' => $shop->pickup_lng, 'address' => $shop->pickup_address,
                'note' => $shop->address_note] : null,
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
            // Services
            'taking_bookings' => (bool)($shop->taking_bookings ?? true),
            'bookings_completed' => (int)$shop->bookings_completed,
            'service_hours' => $shop->serviceHours(),
        ];
        if ($mine) {
            $data += [
                'service_capacity' => (int)$shop->service_capacity,
                'service_notice_minutes' => (int)$shop->service_notice_minutes,
                'service_ahead_days' => (int)$shop->service_ahead_days,
                'service_auto_confirm' => (bool)$shop->service_auto_confirm,
                'service_areas' => $shop->service_areas,
                'travel_fee' => $shop->travel_fee,
                'has_services' => $shop->items()->where('condition', 'service')->whereNotIn('status', [MarketItem::REMOVED])->exists(),
            ];
            $data += [
                'status' => $shop->status,
                'closed_reason' => $shop->closed_reason,
                'verification_requested' => (bool)$shop->verification_requested_at,
                'pickup_lat' => $shop->pickup_lat,
                'pickup_lng' => $shop->pickup_lng,
                'pickup_address' => $shop->pickup_address,
                'map_visible' => (bool)$shop->map_visible,
                'address_note' => $shop->address_note,
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
                'logo' => $shop->logoUrl(), 'kind' => $shop->kind ?? 'shop'] : null,
            'sale_type' => $item->sale_type ?? 'fixed',
            'used_grade' => $item->used_grade,
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
        if ($item->isAuction() && ($auction = $item->auction)) {
            $data['auction'] = self::auction($auction, $viewer, $mine, $full);
        }
        if ($item->isService()) {
            $next = app(\Modules\MarketManagement\Service\BookingService::class)->nextFree($item);
            $data['service'] = [
                'price_type' => $item->price_type ?? 'fixed',
                'duration_minutes' => (int)($item->duration_minutes ?: 60),
                'where' => $item->whereList(),
                'extras' => $item->extrasList(),
                'kind' => $item->service_kind,
                'next_free' => $next?->toIso8601String(),
                'taking_bookings' => (bool)($shop?->taking_bookings ?? true),
                'bookings_done' => (int)($shop?->bookings_completed ?? 0),
            ];
            if ($full && $shop) {
                $data['service'] += [
                    'hours' => $shop->serviceHours(),
                    'areas' => $shop->service_areas,
                    'travel_fee' => $shop->travel_fee,
                ];
            }
        }
        if ($mine) {
            $data += ['visits' => $item->visits, 'chats' => $item->chats, 'status_note' => $item->status_note,
                'blocked_word' => $item->blocked_word];
        }
        return $data;
    }

    /** An auction as one person sees it: everyone sees the bid and the time; the bidder sees where they stand; the seller sees the result. */
    public static function auction(\Modules\MarketManagement\Entities\MarketAuction $a, ?string $viewer, bool $mine, bool $full): array
    {
        $data = [
            'live' => $a->isLive(),
            'status' => $a->isLive() ? 'live' : ($a->status === 'live' ? 'ending' : $a->status),
            'start_price' => $a->start_price,
            'step' => $a->step,
            'current_bid' => $a->current_bid,
            'bid_count' => $a->bid_count,
            'ends_at' => $a->ends_at?->toIso8601String(),
            'has_reserve' => $a->reserve_price !== null,
            'reserve_met' => $a->reserveMet(),
            'min_next' => $a->minNext(),
        ];
        if (!$full || !$viewer) {
            return $data;
        }
        $data['leading'] = $a->leader_id === $viewer && $a->isLive();
        $myBid = \Modules\MarketManagement\Entities\MarketBid::where('auction_id', $a->id)->where('user_id', $viewer)->max('amount');
        $data['my_bid'] = $myBid === null ? null : (float)$myBid;
        $data['won'] = $a->winner_id === $viewer;
        if ($a->winner_id === $viewer || $mine) {
            $data['order_id'] = $a->order_id;
        }
        if ($a->offered_to === $viewer && $a->offer_expires_at?->isFuture()) {
            $data['offer'] = ['price' => $a->offered_price, 'expires_at' => $a->offer_expires_at->toIso8601String()];
        }
        if ($mine) {
            $winner = $a->winner_id ? \Modules\UserManagement\Entities\User::find($a->winner_id) : null;
            $order = $a->order_id ? MarketOrder::find($a->order_id) : null;
            $data += [
                'reserve_price' => $a->reserve_price,
                'winner_name' => $winner ? (trim((string)$winner->first_name) ?: translate('Buyer')) : null,
                'winner_bid' => $a->winner_bid,
                'order_status' => $order?->status,
                'payment_due_at' => $order && $order->isOpen() && !$order->payment_arranged_at ? $order->payment_due_at?->toIso8601String() : null,
                'offered' => $a->offered_to !== null && $a->offer_expires_at?->isFuture(),
                'offer_expires_at' => $a->offer_expires_at?->toIso8601String(),
                'next_bidder' => (bool)app(\Modules\MarketManagement\Service\AuctionService::class)->nextBidder($a),
            ];
        }
        return $data;
    }

    /** The bids, highest first; other people as initials ("B***a"). */
    public static function bids(\Modules\MarketManagement\Entities\MarketAuction $a, ?string $viewer): array
    {
        return $a->bids()->with('user:id,first_name')->limit(50)->get()->map(function ($b) use ($viewer) {
            $name = trim((string)$b->user?->first_name);
            $masked = $name === '' ? '***' : mb_substr($name, 0, 1) . '***' . (mb_strlen($name) > 1 ? mb_substr($name, -1) : '');
            return ['amount' => $b->amount, 'name' => $b->user_id === $viewer ? null : $masked, 'mine' => $b->user_id === $viewer,
                'created_at' => $b->created_at?->toIso8601String()];
        })->all();
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
            'auction' => $order->auction_id !== null,
            'payment_due_at' => $order->auction_id && !$order->payment_arranged_at && $order->isOpen() ? $order->payment_due_at?->toIso8601String() : null,
            'payment_arranged' => $order->payment_arranged_at !== null,
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

    /** A booking as one side sees it. The client's exact place shows to the provider only once confirmed. */
    public static function booking(\Modules\MarketManagement\Entities\MarketBooking $b, string $viewer, bool $full = false): array
    {
        $role = $b->roleOf($viewer) ?? 'client';
        $item = $b->item;
        $shop = $b->shop;
        $service = app(\Modules\MarketManagement\Service\BookingService::class);
        $confirmedOrLater = in_array($b->status, [\Modules\MarketManagement\Entities\MarketBooking::CONFIRMED, \Modules\MarketManagement\Entities\MarketBooking::COMPLETED,
            \Modules\MarketManagement\Entities\MarketBooking::NO_SHOW], true);
        $photos = $item && $item->relationLoaded('photos') ? $item->photos : ($item ? $item->photos()->get() : collect());
        $cover = $photos->firstWhere('preview', false) ?? $photos->first();
        $data = [
            'id' => $b->id,
            'code' => $b->code,
            'kind' => $b->kind,
            'status' => $b->status,
            'role' => $role,
            'item' => $item ? ['code' => $item->code, 'title' => $item->title, 'cover' => $cover?->url(),
                'duration_minutes' => (int)($item->duration_minutes ?: 60), 'price_type' => $item->price_type] : null,
            'shop' => $shop ? ['slug' => $shop->slug, 'name' => $shop->name, 'logo' => $shop->logoUrl(), 'kind' => $shop->kind ?? 'shop'] : null,
            'client_name' => $role === 'provider' ? (trim((string)$b->client?->first_name) ?: translate('A client')) : null,
            'starts_at' => $b->starts_at?->toIso8601String(),
            'ends_at' => $b->ends_at?->toIso8601String(),
            'suggested_starts_at' => $b->suggested_starts_at?->toIso8601String(),
            'location' => $b->location,
            'price_type' => $b->price_type,
            'price' => $b->shownPrice(),
            'quote_price' => $b->quote_price,
            'quote_note' => $b->quote_note,
            'quote_expires_at' => $b->quote_expires_at?->toIso8601String(),
            'final_price' => $b->final_price,
            'respond_by' => $b->respond_by?->toIso8601String(),
            'extras' => $b->extras ?? [],
            'note' => $b->note,
            'when_text' => $b->when_text,
            'decline_reason' => $b->decline_reason,
            'cancelled_by' => $b->cancelled_by,
            'cancel_reason' => $b->cancel_reason,
            'late_cancel' => (bool)$b->late_cancel,
            'conversation_id' => $b->conversation_id,
            'created_at' => $b->created_at?->toIso8601String(),
        ];
        // Where: the client's place (to the provider only once confirmed), or the provider's place (to the client once confirmed,
        // or at once when the shop is on the map).
        if ($b->location === 'client') {
            $show = $role === 'client' || $confirmedOrLater;
            $data['place'] = $show ? ['lat' => $b->lat, 'lng' => $b->lng, 'address' => $b->address] : ['lat' => null, 'lng' => null, 'address' => null];
            $data['area_only'] = !$show;
        } elseif ($b->location === 'place' && $shop) {
            $show = $shop->onMap() || ($role === 'client' && $confirmedOrLater) || $role === 'provider';
            $data['place'] = $show && $shop->pickup_lat !== null
                ? ['lat' => $shop->pickup_lat, 'lng' => $shop->pickup_lng, 'address' => $shop->pickup_address, 'note' => $shop->address_note]
                : ['lat' => null, 'lng' => null, 'address' => $shop->area_label, 'note' => null];
            $data['area_only'] = !$show;
        }
        if ($role === 'client') {
            $data['receive_code'] = $b->status === \Modules\MarketManagement\Entities\MarketBooking::CONFIRMED ? $b->receive_code : null;
            $data['can_review'] = $b->status === \Modules\MarketManagement\Entities\MarketBooking::COMPLETED && !$b->review()->exists();
            $data['cancel_free_until'] = $b->status === \Modules\MarketManagement\Entities\MarketBooking::CONFIRMED ? $service->cancelFreeUntil($b)?->toIso8601String() : null;
            $data['free_to_change'] = $service->freeToChange($b);
        } else {
            $data['client_record'] = $service->record($b->client_id);
            $data['can_no_show'] = $b->status === \Modules\MarketManagement\Entities\MarketBooking::CONFIRMED && $b->starts_at && $b->starts_at->copy()->addMinutes(30)->isPast();
        }
        if ($full) {
            $data['photos'] = $b->photoUrls();
            if ($r = $b->review) {
                $data['review'] = ['stars' => $r->stars, 'tags' => $r->tags ?? [], 'comment' => $r->comment];
            }
        }
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
