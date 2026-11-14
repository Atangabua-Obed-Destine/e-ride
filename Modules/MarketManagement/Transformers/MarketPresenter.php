<?php

namespace Modules\MarketManagement\Transformers;

use Modules\MarketManagement\Entities\MarketConversation;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketItemPhoto;
use Modules\MarketManagement\Entities\MarketMessage;
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
