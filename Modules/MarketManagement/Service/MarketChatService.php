<?php

namespace Modules\MarketManagement\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\MarketManagement\Entities\MarketConversation;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketMessage;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Lib\MarketPush;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

/**
 * Buyer-seller chat about one item. Only its two people read or write it. Buyer and seller
 * agree on price and payment here (E-Ride holds no money). The buyer can share a delivery
 * point; the seller then books the parcel with E-Ride and the chat shows that delivery.
 */
class MarketChatService
{
    public function __construct(protected ShopService $shops)
    {
    }

    /** The buyer opens (or reopens) the chat about an item. */
    public function start(User $buyer, MarketItem $item): MarketConversation
    {
        $shop = $item->shop;
        if (!$item->isPublic()) {
            $this->fail('item', translate('This item is no longer available.'));
        }
        if ($shop->user_id === $buyer->id) {
            $this->fail('item', translate('This is your own item.'));
        }
        if ($this->shops->blocked($buyer->id, $shop->id)) {
            $this->fail('shop', translate('You blocked this shop.'));
        }
        return DB::transaction(function () use ($buyer, $item, $shop) {
            $conversation = MarketConversation::where('item_id', $item->id)->where('buyer_id', $buyer->id)->lockForUpdate()->first();
            if ($conversation) {
                return $conversation;
            }
            $conversation = MarketConversation::create([
                'item_id' => $item->id, 'shop_id' => $shop->id, 'buyer_id' => $buyer->id, 'seller_id' => $shop->user_id,
            ]);
            $item->increment('chats');
            MarketShop::whereKey($shop->id)->increment('chats');
            return $conversation->fresh();
        });
    }

    public function send(User $sender, MarketConversation $conversation, string $type, ?string $body, ?UploadedFile $photo = null, array $meta = []): MarketMessage
    {
        $role = $conversation->roleOf($sender->id) ?? $this->fail('chat', translate('This chat is not yours.'));
        if ($role === 'buyer' && $conversation->buyer_blocked) {
            $this->fail('chat', translate('The seller is no longer taking messages from you about this item.'));
        }
        if ($role === 'seller' && !$conversation->shop?->isLive()) {
            $this->fail('chat', translate('This shop is closed.'));
        }
        $body = $body !== null ? trim($body) : null;
        $data = ['conversation_id' => $conversation->id, 'sender_id' => $sender->id, 'type' => $type];
        switch ($type) {
            case 'text':
                if ($body === null || $body === '') {
                    $this->fail('body', translate('Write a message.'));
                }
                $data['body'] = mb_substr($body, 0, 2000);
                break;
            case 'photo':
                $file = $photo ? fileUploader(MarketMessage::PHOTO_DIR, APPLICATION_IMAGE_FORMAT, $photo) : null;
                if (!$file) {
                    $this->fail('photo', translate('Send a photo.'));
                }
                $data['photo'] = $file;
                $data['body'] = $body ? mb_substr($body, 0, 500) : null;
                break;
            case 'delivery_point':
                // The buyer asks for delivery: where to bring it.
                if ($role !== 'buyer' || !isset($meta['latitude'], $meta['longitude'])) {
                    $this->fail('delivery', translate('Only the buyer shares where to deliver.'));
                }
                $data['meta'] = [
                    'latitude' => (float)$meta['latitude'],
                    'longitude' => (float)$meta['longitude'],
                    'address' => mb_substr((string)($meta['address'] ?? ''), 0, 191),
                ];
                $data['body'] = $body ? mb_substr($body, 0, 300) : null;
                break;
            case 'delivery_booked':
                // The seller booked the parcel with E-Ride: it must be theirs, a parcel, and not old.
                $trip = isset($meta['trip_id']) ? TripRequest::find($meta['trip_id']) : null;
                if ($role !== 'seller' || !$trip || $trip->customer_id !== $sender->id || $trip->type !== PARCEL || $trip->created_at->lt(now()->subDay())) {
                    $this->fail('delivery', translate('Book the delivery from this chat first.'));
                }
                $data['meta'] = ['trip_id' => $trip->id];
                break;
            default:
                $this->fail('type', translate('Unknown message type.'));
        }
        $message = DB::transaction(function () use ($data, $conversation, $role) {
            $message = MarketMessage::create($data);
            $preview = match ($data['type']) {
                'photo' => translate('Photo'),
                'delivery_point' => translate('Asked for delivery'),
                'delivery_booked' => translate('Delivery booked with E-Ride'),
                default => mb_substr((string)$data['body'], 0, 120),
            };
            // The other side's unread count, in the database (two messages at once both count).
            MarketConversation::whereKey($conversation->id)->update(['last_message_at' => now(), 'last_message' => $preview]);
            MarketConversation::whereKey($conversation->id)->increment($role === 'buyer' ? 'seller_unread' : 'buyer_unread');
            return $message;
        });

        $conversation->refresh();
        $other = $role === 'buyer' ? $conversation->seller : $conversation->buyer;
        $from = $role === 'buyer' ? (trim((string)$sender->first_name) ?: translate('A buyer')) : ($conversation->shop?->name ?? translate('The seller'));
        MarketPush::send($other, 'market_message', '{from}', '{text}', [
            'from' => $from,
            'text' => $message->type === 'text' ? mb_substr((string)$message->body, 0, 140) : $conversation->last_message,
        ], ['conversation_id' => $conversation->id]);
        return $message;
    }

    /** Messages after $afterId (all when null), oldest first; reading them clears the reader's unread count. */
    public function messages(User $reader, MarketConversation $conversation, ?string $afterId = null, int $limit = 100): Collection
    {
        $role = $conversation->roleOf($reader->id) ?? $this->fail('chat', translate('This chat is not yours.'));
        $query = $conversation->messages()->orderBy('created_at')->orderBy('id');
        if ($afterId && ($after = MarketMessage::where('conversation_id', $conversation->id)->find($afterId))) {
            $query->where('created_at', '>=', $after->created_at)->where('id', '!=', $after->id);
        } else {
            $query = $conversation->messages()->latest()->limit($limit);
        }
        $messages = $query->get()->sortBy(fn(MarketMessage $m) => $m->created_at->format('U.u') . $m->id)->values();
        $conversation->update([$role === 'buyer' ? 'buyer_unread' : 'seller_unread' => 0]);
        return $messages;
    }

    /** Both sides' chats, newest first. */
    public function inbox(User $user, int $limit = 50): Collection
    {
        return MarketConversation::with(['item.photos', 'shop', 'buyer'])
            ->where(fn($q) => $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')->limit($limit)->get();
    }

    public function unread(User $user): int
    {
        return (int)MarketConversation::where('buyer_id', $user->id)->sum('buyer_unread')
            + (int)MarketConversation::where('seller_id', $user->id)->sum('seller_unread');
    }

    /** The seller stops (or allows again) a buyer in one chat. */
    public function blockBuyer(User $seller, MarketConversation $conversation, bool $on): MarketConversation
    {
        if ($conversation->seller_id !== $seller->id) {
            $this->fail('chat', translate('This chat is not yours.'));
        }
        $conversation->update(['buyer_blocked' => $on]);
        return $conversation->fresh();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
