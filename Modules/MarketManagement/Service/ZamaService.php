<?php

namespace Modules\MarketManagement\Service;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\MarketManagement\Entities\MarketConversation;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketMessage;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Entities\MarketZamaDeal;
use Modules\MarketManagement\Lib\MarketPush;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\UserManagement\Entities\User;

/**
 * Zama: reselling someone else's goods at your own price, the way it is done on WhatsApp.
 *
 * 1. The owner opens an item to zama with the price they want for it (resellers only see it).
 * 2. A reseller adds it to their shop: a copy with the owner's photos and the reseller's
 *    price, or "Ask for price". Buyers see the reseller's shop, never the owner's.
 * 3. In the chat the reseller sends each buyer a private quote; the buyer accepts or offers.
 * 4. The reseller asks the owner to keep the item, either to collect and deliver it
 *    ("deliver") or for the buyer to come to the shop with a code ("send_buyer").
 * 5. send_buyer: the owner enters the buyer's code and taps Sold; the owner then owes the
 *    reseller the difference, sent by Mobile Money (E-Ride records it, holds no money).
 */
class ZamaService
{
    public function __construct(protected ShopService $shops)
    {
    }

    // ================================================================ copies

    /**
     * The owner's item changed: copies follow it. On sale again: back on sale; sold out: sold out;
     * hidden or blocked: paused; deleted or closed to zama: removed (resellers are told).
     */
    public function syncCopies(MarketItem $source, bool $deleted = false): void
    {
        $copies = MarketItem::where('zama_of', $source->id)->get();
        if ($copies->isEmpty()) {
            return;
        }
        $gone = $deleted || $source->trashed() || !$source->zama_open || $source->status === MarketItem::REMOVED;
        foreach ($copies as $copy) {
            if ($gone) {
                $copy->status = MarketItem::REMOVED;
                $copy->status_note = 'The owner stopped zama for this item';
                $copy->saveQuietly();
                $copy->deleteQuietly();
                MarketPush::send(User::find($copy->shop?->user_id), 'market_zama_closed', 'No more zama for {title}',
                    'The owner stopped selling it through resellers. It left your shop.', ['title' => $source->title], ['item_code' => $copy->code]);
                continue;
            }
            foreach (MarketItem::ZAMA_SYNCED as $field) {
                $copy->{$field} = $source->{$field};
            }
            $paused = $copy->status_note === MarketItem::ZAMA_PAUSED;
            $mine = in_array($copy->status, MarketItem::PUBLIC, true) || $paused;
            if ($mine) {
                if (in_array($source->status, MarketItem::PUBLIC, true)) {
                    $copy->status = $source->status;
                    $copy->status_note = null;
                } else {
                    // Hidden or blocked on the owner's side: paused here until it is back.
                    $copy->status = MarketItem::HIDDEN;
                    $copy->status_note = MarketItem::ZAMA_PAUSED;
                }
            }
            $copy->saveQuietly();
        }
    }

    /**
     * Items open to zama, for a reseller: newest first, not their own, not from owners
     * who removed them. Each with the owner's price and how reliably the owner pays.
     * @return array{total:int, items:Collection}
     */
    public function catalogue(User $reseller, array $filters, int $offset, int $limit): array
    {
        $removedFrom = DB::table('market_zama_removed')->where('reseller_id', $reseller->id)->pluck('owner_shop_id');
        $query = MarketItem::with(['photos', 'shop'])
            ->where('zama_open', true)->whereNull('zama_of')->whereIn('condition', ['new', 'used'])
            ->whereIn('status', MarketItem::PUBLIC)->where('sale_type', '!=', 'auction')
            ->whereHas('shop', fn($s) => $s->where('status', MarketShop::LIVE)->where('user_id', '!=', $reseller->id))
            ->when($removedFrom->isNotEmpty(), fn($q) => $q->whereNotIn('shop_id', $removedFrom));
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (($q = trim((string)($filters['q'] ?? ''))) !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(fn($w) => $w->where('title', 'like', $like)->orWhere('description', 'like', $like)->orWhere('tags_search', 'like', $like)
                ->orWhereHas('shop', fn($s) => $s->where('name', 'like', $like)));
        }
        $total = (clone $query)->count();
        return ['total' => $total, 'items' => $query->latest()->skip($offset)->take($limit)->get()];
    }

    /** The reseller adds an owner's item to their shop (again, if they had removed it). */
    public function take(User $reseller, MarketItem $source, ?float $price): MarketItem
    {
        $this->enabledOrFail();
        $shop = $this->shops->mine($reseller);
        if (!$shop || !$shop->isLive()) {
            $this->fail('shop', translate('Open your shop first: the items you resell show there.'));
        }
        if (!$source->zamaAvailable()) {
            $this->fail('item', translate('This item is not open to zama any more.'));
        }
        if ($source->shop->user_id === $reseller->id) {
            $this->fail('item', translate('This is your own item.'));
        }
        if ($this->removed($source->shop_id, $reseller->id)) {
            $this->fail('item', translate('This shop no longer works with you for zama.'));
        }
        $this->checkPrice($source, $price);
        $existing = MarketItem::where('shop_id', $shop->id)->where('zama_of', $source->id)->first();
        if ($existing) {
            return $this->setPrice($reseller, $existing, $price);
        }
        $count = $shop->items()->whereNotIn('status', [MarketItem::REMOVED])->count();
        if ($count >= MarketSettings::all()['items_per_shop']) {
            $this->fail('items', translate('Your shop is full. Remove some items to add more.'));
        }
        $copy = new MarketItem();
        foreach (MarketItem::ZAMA_SYNCED as $field) {
            $copy->{$field} = $source->{$field};
        }
        $copy->forceFill([
            'shop_id' => $shop->id, 'code' => $this->newItemCode(), 'zama_of' => $source->id, 'price' => $price,
            'negotiable' => true, 'sale_type' => 'fixed', 'pickup' => true, 'seller_delivers' => true, 'eride_delivery' => true,
            'status' => $source->status, 'zama_open' => false, 'zama_only' => false,
        ]);
        $copy->save();
        return $copy->fresh(['photos', 'shop', 'zamaSource.shop']);
    }

    /** The reseller's price: a number, or null for "Ask for price". */
    public function setPrice(User $reseller, MarketItem $copy, ?float $price): MarketItem
    {
        $copy->loadMissing(['shop', 'zamaSource.shop']);
        if (!$copy->isZamaCopy() || $copy->shop?->user_id !== $reseller->id) {
            $this->fail('item', translate('This item is not yours.'));
        }
        if ($copy->zamaSource) {
            $this->checkPrice($copy->zamaSource, $price);
        }
        $copy->update(['price' => $price]);
        return $copy->fresh(['photos', 'shop', 'zamaSource.shop']);
    }

    /** The owner's resellers: who sells their items, how many, and the deals done. */
    public function resellers(User $owner): Collection
    {
        $shop = $this->shops->mine($owner);
        if (!$shop) {
            return collect();
        }
        $copies = MarketItem::with('shop.owner')->whereIn('zama_of', $shop->items()->select('id'))->get()->groupBy(fn($c) => $c->shop?->user_id);
        $deals = MarketZamaDeal::where('owner_shop_id', $shop->id)->where('status', MarketZamaDeal::DONE)
            ->selectRaw('reseller_id, COUNT(*) AS n')->groupBy('reseller_id')->pluck('n', 'reseller_id');
        return $copies->filter(fn($list, $userId) => $userId)->map(function (Collection $list, $userId) use ($deals) {
            $resellerShop = $list->first()->shop;
            return [
                'user_id' => (string)$userId,
                'name' => trim((string)$resellerShop?->owner?->first_name) ?: translate('Reseller'),
                'shop' => $resellerShop ? ['slug' => $resellerShop->slug, 'name' => $resellerShop->name] : null,
                'items' => $list->count(),
                'deals_done' => (int)($deals[$userId] ?? 0),
            ];
        })->values();
    }

    /** The owner stops a reseller: their copies leave, open deals stay until they end. */
    public function removeReseller(User $owner, string $resellerId): int
    {
        $shop = $this->shops->mine($owner) ?? $this->fail('shop', translate('Open your shop first.'));
        DB::table('market_zama_removed')->insertOrIgnore(['owner_shop_id' => $shop->id, 'reseller_id' => $resellerId, 'created_at' => now(), 'updated_at' => now()]);
        $removed = 0;
        MarketItem::whereIn('zama_of', $shop->items()->select('id'))->whereHas('shop', fn($s) => $s->where('user_id', $resellerId))->get()
            ->each(function (MarketItem $copy) use (&$removed) {
                $copy->status = MarketItem::REMOVED;
                $copy->status_note = 'Removed by the owner';
                $copy->saveQuietly();
                $copy->deleteQuietly();
                $removed++;
            });
        if ($removed > 0) {
            MarketPush::send(User::find($resellerId), 'market_zama_removed', '{shop} stopped zama with you',
                'Their items left your shop.', ['shop' => $shop->name]);
        }
        return $removed;
    }

    public function removed(string $ownerShopId, string $resellerId): bool
    {
        return DB::table('market_zama_removed')->where('owner_shop_id', $ownerShopId)->where('reseller_id', $resellerId)->exists();
    }

    /** How the owner pays resellers: zama sold through buyers sent to the shop, last 6 months. */
    public function ownerRecord(string $ownerShopId): array
    {
        $base = MarketZamaDeal::where('owner_shop_id', $ownerShopId)->where('way', MarketZamaDeal::SEND_BUYER)
            ->where('status', MarketZamaDeal::DONE)->where('margin', '>', 0)->where('done_at', '>=', now()->subMonths(6));
        return [
            'due' => (clone $base)->count(),
            'received' => (clone $base)->where('margin_status', 'received')->count(),
            'late' => (clone $base)->whereIn('margin_status', ['owed', 'paid'])->where('done_at', '<', now()->subDays(3))->count(),
            'deals_done' => MarketZamaDeal::where('owner_shop_id', $ownerShopId)->where('status', MarketZamaDeal::DONE)->count(),
        ];
    }

    // ================================================================ quotes in the buyer's chat

    /** The reseller sends one buyer a price: private to that chat, open for some hours. */
    public function sendQuote(User $reseller, MarketConversation $chat, float $price, int $quantity, ?string $colour, ?string $size, ?int $hours = null): MarketMessage
    {
        $copy = $this->copyOfChat($chat);
        if ($chat->seller_id !== $reseller->id) {
            $this->fail('chat', translate('Only the seller sends a price.'));
        }
        $source = $copy->zamaSource;
        if (!$copy->isPublic()) {
            $this->fail('item', translate('This item is not on sale right now.'));
        }
        [$colour, $size] = $this->checkChoice($source, $colour, $size, $quantity);
        $ownerTotal = round((float)$source->zama_price * $quantity, 2);
        if ($price < $ownerTotal) {
            $this->fail('price', translate(key: 'That is below the owner\'s price ({price}).', replace: ['price' => getCurrencyFormat($ownerTotal)]));
        }
        $hours = max(1, min(168, $hours ?: MarketSettings::all()['zama_quote_hours']));
        return DB::transaction(function () use ($chat, $reseller, $price, $quantity, $colour, $size, $hours, $copy) {
            // A new price replaces the ones still open in this chat.
            $this->replaceOpenQuotes($chat);
            $message = MarketMessage::create([
                'conversation_id' => $chat->id, 'sender_id' => $reseller->id, 'type' => 'quote', 'body' => null,
                'meta' => ['price' => round($price, 2), 'quantity' => $quantity, 'colour' => $colour, 'size' => $size,
                    'expires_at' => now()->addHours($hours)->toIso8601String(), 'state' => 'open'],
            ]);
            $this->touchChat($chat, 'seller', translate(key: 'Price: {price}', replace: ['price' => getCurrencyFormat($price)]));
            MarketPush::send($chat->buyer, 'market_zama_quote', '{shop} sent you a price', '{title}: {price}. Accept it in the chat.', [
                'shop' => $chat->shop?->name, 'title' => $copy->title, 'price' => getCurrencyFormat($price),
            ], ['conversation_id' => $chat->id]);
            return $message;
        });
    }

    /** The buyer offers their own price; the reseller answers with a quote. */
    public function sendOffer(User $buyer, MarketConversation $chat, float $price, int $quantity, ?string $note): MarketMessage
    {
        $copy = $this->copyOfChat($chat);
        if ($chat->buyer_id !== $buyer->id) {
            $this->fail('chat', translate('Only the buyer makes an offer.'));
        }
        if ($chat->buyer_blocked) {
            $this->fail('chat', translate('The seller is no longer taking messages from you about this item.'));
        }
        if ($price < 1 || $quantity < 1) {
            $this->fail('price', translate('Give a price.'));
        }
        $message = DB::transaction(function () use ($chat, $buyer, $price, $quantity, $note) {
            $message = MarketMessage::create([
                'conversation_id' => $chat->id, 'sender_id' => $buyer->id, 'type' => 'offer', 'body' => $note ? mb_substr(trim($note), 0, 300) : null,
                'meta' => ['price' => round($price, 2), 'quantity' => $quantity],
            ]);
            $this->touchChat($chat, 'buyer', translate(key: 'Offer: {price}', replace: ['price' => getCurrencyFormat($price)]));
            return $message;
        });
        MarketPush::send($chat->seller, 'market_zama_offer', '{buyer} offers {price}', '{title}. Answer with your price in the chat.', [
            'buyer' => trim((string)$buyer->first_name) ?: translate('A buyer'), 'price' => getCurrencyFormat($price), 'title' => $copy->title,
        ], ['conversation_id' => $chat->id]);
        return $message;
    }

    /** The buyer accepts or declines a quote. */
    public function answerQuote(User $buyer, MarketMessage $quote, bool $accept): MarketMessage
    {
        $chat = $quote->conversation;
        if ($quote->type !== 'quote' || !$chat || $chat->buyer_id !== $buyer->id) {
            $this->fail('quote', translate('This price is not for you.'));
        }
        $copy = $this->copyOfChat($chat);
        return DB::transaction(function () use ($quote, $accept, $chat, $buyer, $copy) {
            $quote = MarketMessage::lockForUpdate()->find($quote->id);
            if (self::quoteState($quote) !== 'open') {
                $this->fail('quote', translate('This price is no longer open. Ask the seller for a new one.'));
            }
            $meta = $quote->meta;
            $meta['state'] = $accept ? 'accepted' : 'declined';
            $meta['answered_at'] = now()->toIso8601String();
            $quote->update(['meta' => $meta]);
            $text = $accept ? translate(key: 'Agreed: {price}', replace: ['price' => getCurrencyFormat($meta['price'])]) : translate('Price declined');
            $this->touchChat($chat, 'buyer', $text);
            if ($accept) {
                MarketPush::send($chat->seller, 'market_zama_accepted', '{buyer} accepted {price}', '{title}. Now ask the owner to keep it.', [
                    'buyer' => trim((string)$buyer->first_name) ?: translate('The buyer'), 'price' => getCurrencyFormat($meta['price']), 'title' => $copy->title,
                ], ['conversation_id' => $chat->id]);
            }
            return $quote->fresh();
        });
    }

    public static function quoteState(MarketMessage $quote): string
    {
        $state = (string)($quote->meta['state'] ?? 'open');
        if ($state === 'open' && ($expires = $quote->meta['expires_at'] ?? null) && now()->gt(\Illuminate\Support\Carbon::parse($expires))) {
            return 'expired';
        }
        return $state;
    }

    // ================================================================ deals with the owner

    /** After the buyer accepted, the reseller asks the owner to keep the item: to collect it, or for the buyer to come. */
    public function startDeal(User $reseller, MarketMessage $quote, string $way): MarketZamaDeal
    {
        $this->enabledOrFail();
        if (!in_array($way, MarketZamaDeal::WAYS, true)) {
            $this->fail('way', translate('Choose how the buyer gets it.'));
        }
        $chat = $quote->conversation;
        if ($quote->type !== 'quote' || !$chat || $chat->seller_id !== $reseller->id) {
            $this->fail('quote', translate('This price is not yours.'));
        }
        $copy = $this->copyOfChat($chat);
        $source = $copy->zamaSource;
        if (!$source->zamaAvailable()) {
            $this->fail('item', translate('The owner no longer sells this item through zama.'));
        }
        $deal = DB::transaction(function () use ($reseller, $quote, $way, $chat, $copy, $source) {
            $quote = MarketMessage::lockForUpdate()->find($quote->id);
            if (($quote->meta['state'] ?? null) !== 'accepted') {
                $this->fail('quote', translate('The buyer has not accepted this price.'));
            }
            if (!empty($quote->meta['deal_id']) && ($old = MarketZamaDeal::find($quote->meta['deal_id'])) && !in_array($old->status, [MarketZamaDeal::DECLINED, MarketZamaDeal::RELEASED, MarketZamaDeal::CANCELLED], true)) {
                return $old;
            }
            $meta = $quote->meta;
            $qty = (int)$meta['quantity'];
            $this->checkChoice($source, $meta['colour'] ?? null, $meta['size'] ?? null, $qty);
            $ownerPrice = round((float)$source->zama_price * $qty, 2);
            $ownerChat = $this->ownerChat($reseller, $source);
            $deal = MarketZamaDeal::create([
                'code' => str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'copy_id' => $copy->id, 'source_id' => $source->id, 'owner_shop_id' => $source->shop_id, 'owner_id' => $source->shop->user_id,
                'reseller_id' => $reseller->id, 'buyer_id' => $chat->buyer_id, 'conversation_id' => $chat->id, 'owner_conversation_id' => $ownerChat->id,
                'quote_message_id' => $quote->id, 'quantity' => $qty, 'colour' => $meta['colour'] ?? null, 'size' => $meta['size'] ?? null,
                'price' => (float)$meta['price'], 'owner_price' => $ownerPrice, 'margin' => max(0, round((float)$meta['price'] - $ownerPrice, 2)),
                'way' => $way, 'status' => MarketZamaDeal::ASKED,
                // While asked, kept_until is when the owner must answer by.
                'kept_until' => now()->addHours(MarketSettings::all()['zama_answer_hours']),
            ]);
            $meta['deal_id'] = $deal->id;
            $quote->update(['meta' => $meta]);
            return $deal;
        });
        if ($deal->wasRecentlyCreated) {
            $this->note($deal, 'asked');
            MarketPush::send($deal->owner, 'market_zama_asked', 'A reseller has a buyer for {title}', '{qty} for you at {price}. Keep it for them?', [
                'title' => $source->title, 'qty' => $deal->quantity, 'price' => getCurrencyFormat($deal->owner_price),
            ], ['zama_deal_id' => $deal->id]);
        }
        return $deal->fresh();
    }

    /** The owner keeps the item for the reseller or their buyer, until a time. Holds the stock. */
    public function keep(User $owner, MarketZamaDeal $deal, ?int $hours = null): MarketZamaDeal
    {
        $hours = max(1, min(72, $hours ?: MarketSettings::all()['zama_keep_hours']));
        $this->asOwner($owner, $deal);
        DB::transaction(function () use ($deal, $hours) {
            $deal = MarketZamaDeal::lockForUpdate()->find($deal->id);
            if ($deal->status !== MarketZamaDeal::ASKED) {
                $this->fail('deal', translate('This request is no longer waiting for you.'));
            }
            $this->hold($deal);
            $deal->update(['status' => MarketZamaDeal::KEPT, 'kept_at' => now(), 'kept_until' => now()->addHours($hours)]);
        });
        $deal->refresh();
        $this->note($deal, 'kept');
        $until = $deal->kept_until->format('H:i');
        MarketPush::send($deal->reseller, 'market_zama_kept', '{shop} is keeping {title}', $deal->way === MarketZamaDeal::DELIVER
            ? 'Collect it before {until} and pay {price}.' : 'Your buyer can come for it before {until} with code {code}.', [
            'shop' => $deal->ownerShop?->name, 'title' => $deal->source?->title, 'until' => $until,
            'price' => getCurrencyFormat($deal->owner_price), 'code' => $deal->code,
        ], ['zama_deal_id' => $deal->id]);
        if ($deal->way === MarketZamaDeal::SEND_BUYER) {
            MarketPush::send($deal->buyer, 'market_zama_pass', 'Your item is kept for you', 'Go before {until} and show code {code}. Pay {price} at the shop.', [
                'until' => $until, 'code' => $deal->code, 'price' => getCurrencyFormat($deal->price),
            ], ['conversation_id' => $deal->conversation_id]);
        }
        return $deal;
    }

    public function decline(User $owner, MarketZamaDeal $deal, string $reason): MarketZamaDeal
    {
        $this->asOwner($owner, $deal);
        $reason = in_array($reason, MarketZamaDeal::DECLINE_REASONS, true) ? $reason : 'other';
        $this->close($deal, MarketZamaDeal::DECLINED, $reason);
        $deal->refresh();
        MarketPush::send($deal->reseller, 'market_zama_declined', '{shop} cannot keep {title}', 'Tell your buyer, or find another item.', [
            'shop' => $deal->ownerShop?->name, 'title' => $deal->source?->title,
        ], ['zama_deal_id' => $deal->id]);
        return $deal;
    }

    /** "I deliver it": the reseller collected the item and paid the owner. */
    public function handedOver(User $owner, MarketZamaDeal $deal): MarketZamaDeal
    {
        $this->asOwner($owner, $deal);
        if ($deal->way !== MarketZamaDeal::DELIVER) {
            $this->fail('deal', translate('The buyer comes for this one: enter their code when they do.'));
        }
        $this->finish($deal);
        MarketPush::send($deal->reseller, 'market_zama_done', '{shop} handed over {title}', 'Now bring it to your buyer.', [
            'shop' => $deal->ownerShop?->name, 'title' => $deal->source?->title,
        ], ['zama_deal_id' => $deal->id]);
        return $deal->fresh();
    }

    /** "The buyer comes to the shop": the owner enters the buyer's code. The owner now owes the reseller the margin. */
    public function sold(User $owner, MarketZamaDeal $deal, string $code): MarketZamaDeal
    {
        $this->asOwner($owner, $deal);
        if ($deal->way !== MarketZamaDeal::SEND_BUYER) {
            $this->fail('deal', translate('The reseller collects this one: tap "Handed over" when they do.'));
        }
        if (!hash_equals((string)$deal->code, trim($code))) {
            $this->fail('code', translate('That code is not right. Ask the buyer for the 4 digits in their chat.'));
        }
        $this->finish($deal);
        $deal->refresh();
        MarketPush::send($deal->reseller, 'market_zama_sold', 'Sold! Your buyer got {title}', '{shop} owes you {margin}.', [
            'title' => $deal->source?->title, 'shop' => $deal->ownerShop?->name, 'margin' => getCurrencyFormat($deal->margin),
        ], ['zama_deal_id' => $deal->id]);
        return $deal;
    }

    /** The reseller calls it off (the buyer changed their mind). */
    public function cancel(User $reseller, MarketZamaDeal $deal): MarketZamaDeal
    {
        if ($deal->reseller_id !== $reseller->id) {
            $this->fail('deal', translate('This deal is not yours.'));
        }
        $this->close($deal, MarketZamaDeal::CANCELLED, 'reseller');
        $deal->refresh();
        MarketPush::send($deal->owner, 'market_zama_cancelled', 'Zama called off: {title}', 'You can sell it to anyone again.', [
            'title' => $deal->source?->title,
        ], ['zama_deal_id' => $deal->id]);
        return $deal;
    }

    /** The buyer says they bought it at the shop: the reseller sees it, even if the owner forgot the code. */
    public function buyerBought(User $buyer, MarketZamaDeal $deal): MarketZamaDeal
    {
        if ($deal->buyer_id !== $buyer->id || $deal->way !== MarketZamaDeal::SEND_BUYER || !in_array($deal->status, [MarketZamaDeal::KEPT, MarketZamaDeal::DONE], true)) {
            $this->fail('deal', translate('Nothing to confirm here.'));
        }
        if ($deal->buyer_bought_at) {
            return $deal;
        }
        $deal->update(['buyer_bought_at' => now()]);
        if ($deal->status === MarketZamaDeal::KEPT) {
            MarketPush::send($deal->reseller, 'market_zama_bought', 'Your buyer says they bought {title}', 'The shop has not entered the code yet.', [
                'title' => $deal->source?->title,
            ], ['zama_deal_id' => $deal->id]);
            MarketPush::send($deal->owner, 'market_zama_code', 'Enter the buyer\'s code for {title}', 'The buyer says they paid. Enter their code so the reseller gets their zama.', [
                'title' => $deal->source?->title,
            ], ['zama_deal_id' => $deal->id]);
        }
        return $deal->fresh();
    }

    /** The owner says they sent the reseller's margin. */
    public function marginPaid(User $owner, MarketZamaDeal $deal): MarketZamaDeal
    {
        $this->asOwner($owner, $deal);
        if ($deal->margin_status !== 'owed') {
            $this->fail('deal', translate('Nothing is owed on this deal.'));
        }
        $deal->update(['margin_status' => 'paid', 'margin_paid_at' => now()]);
        MarketPush::send($deal->reseller, 'market_zama_paid', '{shop} sent your zama', '{margin} for {title}. Confirm when you have it.', [
            'shop' => $deal->ownerShop?->name, 'margin' => getCurrencyFormat($deal->margin), 'title' => $deal->source?->title,
        ], ['zama_deal_id' => $deal->id]);
        return $deal->fresh();
    }

    /** The reseller confirms they have the margin (also when the owner paid in cash and did not tap). */
    public function marginReceived(User $reseller, MarketZamaDeal $deal): MarketZamaDeal
    {
        if ($deal->reseller_id !== $reseller->id) {
            $this->fail('deal', translate('This deal is not yours.'));
        }
        if (!in_array($deal->margin_status, ['owed', 'paid'], true)) {
            $this->fail('deal', translate('Nothing is owed on this deal.'));
        }
        $deal->update(['margin_status' => 'received', 'margin_received_at' => now(), 'margin_paid_at' => $deal->margin_paid_at ?? now()]);
        return $deal->fresh();
    }

    /** Deals of one person: as reseller, as owner, or as buyer (send_buyer passes). */
    public function deals(User $user, string $as, int $limit = 100): Collection
    {
        $column = match ($as) { 'owner' => 'owner_id', 'buyer' => 'buyer_id', default => 'reseller_id' };
        return MarketZamaDeal::with(['source.photos', 'ownerShop', 'reseller', 'owner'])->where($column, $user->id)
            ->orderByRaw("CASE WHEN status IN ('asked','kept') OR margin_status IN ('owed','paid') THEN 0 ELSE 1 END")
            ->latest()->limit($limit)->get();
    }

    /** Badge numbers. */
    public function counts(User $user): array
    {
        return [
            'owner_asked' => MarketZamaDeal::where('owner_id', $user->id)->where('status', MarketZamaDeal::ASKED)->count(),
            'owner_owes' => MarketZamaDeal::where('owner_id', $user->id)->where('margin_status', 'owed')->count(),
            'reseller_open' => MarketZamaDeal::where('reseller_id', $user->id)->whereIn('status', MarketZamaDeal::OPEN)->count(),
            'reseller_owed' => (float)MarketZamaDeal::where('reseller_id', $user->id)->whereIn('margin_status', ['owed', 'paid'])->sum('margin'),
        ];
    }

    /** Every minute: unanswered requests and kept items past their time go back on sale; owed zama is reminded daily. */
    public function tick(): array
    {
        $counts = ['zama_unanswered' => 0, 'zama_released' => 0, 'zama_reminded' => 0];
        MarketZamaDeal::whereIn('status', MarketZamaDeal::OPEN)->where('kept_until', '<', now())->limit(200)->get()
            ->each(function (MarketZamaDeal $deal) use (&$counts) {
                $asked = $deal->status === MarketZamaDeal::ASKED;
                try {
                    $this->close($deal, MarketZamaDeal::RELEASED, $asked ? 'no_answer' : 'time_up');
                } catch (ValidationException) {
                    return;
                }
                $counts[$asked ? 'zama_unanswered' : 'zama_released']++;
                $deal->refresh();
                MarketPush::send($deal->reseller, 'market_zama_released', $asked ? '{shop} did not answer for {title}' : 'Time is up for {title}',
                    $asked ? 'Ask again, or find another item.' : 'The shop no longer keeps it for you.', [
                        'shop' => $deal->ownerShop?->name, 'title' => $deal->source?->title,
                    ], ['zama_deal_id' => $deal->id]);
            });
        MarketZamaDeal::where('margin_status', 'owed')->where('done_at', '<', now()->subDay())->where('done_at', '>', now()->subDays(14))
            ->where(fn($q) => $q->whereNull('margin_reminded_at')->orWhere('margin_reminded_at', '<', now()->subDay()))
            ->limit(200)->get()->each(function (MarketZamaDeal $deal) use (&$counts) {
                if (MarketZamaDeal::whereKey($deal->id)->where('margin_status', 'owed')->update(['margin_reminded_at' => now()]) !== 1) {
                    return;
                }
                MarketPush::send($deal->owner, 'market_zama_owe', 'You owe {name} {margin}', 'The zama for {title}. Send it, then tap "I sent it".', [
                    'name' => trim((string)$deal->reseller?->first_name) ?: translate('your reseller'), 'margin' => getCurrencyFormat($deal->margin),
                    'title' => $deal->source?->title,
                ], ['zama_deal_id' => $deal->id]);
                $counts['zama_reminded']++;
            });
        return $counts;
    }

    // ================================================================ inside

    private function finish(MarketZamaDeal $deal): void
    {
        DB::transaction(function () use ($deal) {
            $deal = MarketZamaDeal::lockForUpdate()->find($deal->id);
            if (!$deal->isOpen()) {
                $this->fail('deal', translate('This deal is already closed.'));
            }
            // Sold without keeping first: take the stock now.
            if (!$deal->stock_held) {
                $this->hold($deal);
            }
            $deal->update(['status' => MarketZamaDeal::DONE, 'done_at' => now(), 'stock_held' => false,
                'margin_status' => $deal->way === MarketZamaDeal::SEND_BUYER && $deal->margin > 0 ? 'owed' : 'none']);
            MarketItem::whereKey($deal->source_id)->increment('orders');
            MarketItem::whereKey($deal->copy_id)->increment('orders');
        });
        $this->note($deal->fresh(), 'done');
    }

    private function close(MarketZamaDeal $deal, string $status, string $reason): void
    {
        DB::transaction(function () use ($deal, $status, $reason) {
            $deal = MarketZamaDeal::lockForUpdate()->find($deal->id);
            if (!$deal->isOpen()) {
                $this->fail('deal', translate('This deal is already closed.'));
            }
            if ($deal->stock_held) {
                $this->moveStock($deal, (int)$deal->quantity);
            }
            $deal->update(['status' => $status, 'close_reason' => $reason, 'stock_held' => false]);
        });
        $this->note($deal->fresh(), $status);
    }

    /** Takes the pieces from the owner's stock (fails when there are not enough left). */
    private function hold(MarketZamaDeal $deal): void
    {
        $item = MarketItem::lockForUpdate()->find($deal->source_id);
        if (!$item || $item->stockFor($deal->colour, $deal->size) < $deal->quantity) {
            $this->fail('stock', translate('There are not enough left of this item.'));
        }
        $this->moveStock($deal, -(int)$deal->quantity, $item);
        $deal->update(['stock_held' => true]);
    }

    /** Same as an order: one colour and size, quantity and sold out kept in step. Copies follow through the saved hook. */
    private function moveStock(MarketZamaDeal $deal, int $delta, ?MarketItem $item = null): void
    {
        $item ??= MarketItem::lockForUpdate()->find($deal->source_id);
        if (!$item) {
            return;
        }
        if ($item->hasOptions()) {
            $stock = $item->stock ?? [];
            $key = $deal->stockKey();
            $stock[$key] = max(0, (int)($stock[$key] ?? 0) + $delta);
            $item->stock = $stock;
            $item->quantity = array_sum(array_map('intval', $stock));
        } else {
            $item->quantity = max(0, (int)$item->quantity + $delta);
        }
        if ($item->status === MarketItem::LIVE && $item->quantity < 1) {
            $item->status = MarketItem::SOLD_OUT;
        } elseif ($item->status === MarketItem::SOLD_OUT && $item->quantity > 0 && $delta > 0) {
            $item->status = MarketItem::LIVE;
        }
        $item->save();
    }

    /** The reseller's private chat with the owner, about the owner's item. */
    public function ownerChat(User $reseller, MarketItem $source): MarketConversation
    {
        $chat = MarketConversation::where('item_id', $source->id)->where('buyer_id', $reseller->id)->lockForUpdate()->first();
        if ($chat) {
            if (!$chat->zama) {
                $chat->update(['zama' => true]);
            }
            return $chat;
        }
        return MarketConversation::create(['item_id' => $source->id, 'shop_id' => $source->shop_id, 'buyer_id' => $reseller->id,
            'seller_id' => $source->shop->user_id, 'zama' => true]);
    }

    /** The deal's step, written in both chats (the buyer's only where it concerns them). */
    private function note(MarketZamaDeal $deal, string $event): void
    {
        $title = (string)$deal->source?->title;
        $ownerText = match ($event) {
            'asked' => translate(key: 'Zama request: {qty} x {title}', replace: ['qty' => $deal->quantity, 'title' => $title]),
            'kept' => translate(key: 'Kept until {until}', replace: ['until' => $deal->kept_until?->format('H:i')]),
            'done' => $deal->way === MarketZamaDeal::DELIVER ? translate('Handed over') : translate('Sold to the buyer'),
            MarketZamaDeal::DECLINED => translate('Not available'),
            MarketZamaDeal::RELEASED => translate('No longer kept'),
            MarketZamaDeal::CANCELLED => translate('Called off by the reseller'),
            default => $event,
        };
        $this->post($deal->owner_conversation_id, $event === 'asked' || $event === MarketZamaDeal::CANCELLED ? $deal->reseller_id : $deal->owner_id,
            $event === 'asked' || $event === MarketZamaDeal::CANCELLED ? 'buyer' : 'seller', $ownerText, $deal, $event);
        // The buyer: what they need to know, never who the owner is unless they go to the shop.
        $buyerText = match (true) {
            $event === 'asked' => translate('Agreed. The seller is getting it ready.'),
            $event === 'kept' && $deal->way === MarketZamaDeal::SEND_BUYER => translate(key: 'Kept for you until {until}. Your code: {code}', replace: [
                'until' => $deal->kept_until?->format('H:i'), 'code' => $deal->code]),
            $event === 'kept' => translate('The seller has it and will bring it to you.'),
            $event === 'done' && $deal->way === MarketZamaDeal::SEND_BUYER => translate('Bought. Enjoy it!'),
            in_array($event, [MarketZamaDeal::DECLINED, MarketZamaDeal::RELEASED], true) => translate('This one is no longer available. The seller will tell you more.'),
            $event === MarketZamaDeal::CANCELLED => translate('This deal was called off.'),
            default => null,
        };
        if ($buyerText !== null) {
            $this->post($deal->conversation_id, $deal->reseller_id, 'seller', $buyerText, $deal, $event);
        }
    }

    private function post(?string $chatId, string $senderId, string $role, string $text, MarketZamaDeal $deal, string $event): void
    {
        if (!$chatId) {
            return;
        }
        MarketMessage::create(['conversation_id' => $chatId, 'sender_id' => $senderId, 'type' => 'zama', 'body' => $text,
            'meta' => ['deal_id' => $deal->id, 'event' => $event]]);
        MarketConversation::whereKey($chatId)->update(['last_message_at' => now(), 'last_message' => mb_substr($text, 0, 120)]);
        MarketConversation::whereKey($chatId)->increment($role === 'buyer' ? 'seller_unread' : 'buyer_unread');
    }

    private function touchChat(MarketConversation $chat, string $role, string $preview): void
    {
        MarketConversation::whereKey($chat->id)->update(['last_message_at' => now(), 'last_message' => mb_substr($preview, 0, 120)]);
        MarketConversation::whereKey($chat->id)->increment($role === 'buyer' ? 'seller_unread' : 'buyer_unread');
    }

    private function replaceOpenQuotes(MarketConversation $chat): void
    {
        MarketMessage::where('conversation_id', $chat->id)->where('type', 'quote')->lockForUpdate()->get()
            ->filter(fn(MarketMessage $m) => ($m->meta['state'] ?? 'open') === 'open')
            ->each(fn(MarketMessage $m) => $m->update(['meta' => array_merge($m->meta, ['state' => 'replaced'])]));
    }

    /** The copy a buyer's chat is about, with its owner's item. */
    private function copyOfChat(MarketConversation $chat): MarketItem
    {
        $copy = $chat->item;
        if (!$copy || !$copy->isZamaCopy() || !$copy->zamaSource) {
            $this->fail('chat', translate('Prices are sent like this only for zama items.'));
        }
        return $copy;
    }

    /** A valid colour and size for the item, with enough pieces. */
    private function checkChoice(MarketItem $source, ?string $colour, ?string $size, int $quantity): array
    {
        $colour = $colour !== null && trim($colour) !== '' ? trim($colour) : null;
        $size = $size !== null && trim($size) !== '' ? trim($size) : null;
        $colours = array_map(fn($c) => $c['name'], $source->colours());
        $sizes = $source->sizes();
        if ($colours && !in_array($colour, $colours, true)) {
            $this->fail('colour', translate('Choose a colour.'));
        }
        if ($sizes && !in_array($size, $sizes, true)) {
            $this->fail('size', translate('Choose a size.'));
        }
        if (!$colours) {
            $colour = null;
        }
        if (!$sizes) {
            $size = null;
        }
        if ($quantity < 1) {
            $this->fail('quantity', translate('Choose how many.'));
        }
        if ($source->stockFor($colour, $size) < $quantity) {
            $this->fail('quantity', translate(key: 'Only {count} left.', replace: ['count' => $source->stockFor($colour, $size)]));
        }
        return [$colour, $size];
    }

    private function checkPrice(MarketItem $source, ?float $price): void
    {
        if ($price !== null && $price < (float)$source->zama_price) {
            $this->fail('price', translate(key: 'Your price cannot be below the owner\'s price ({price}).', replace: ['price' => getCurrencyFormat($source->zama_price)]));
        }
    }

    private function asOwner(User $owner, MarketZamaDeal $deal): void
    {
        if ($deal->owner_id !== $owner->id) {
            $this->fail('deal', translate('This deal is not for your shop.'));
        }
    }

    private function enabledOrFail(): void
    {
        if (!MarketSettings::all()['zama_enabled']) {
            $this->fail('zama', translate('Zama is not available right now.'));
        }
    }

    private function newItemCode(): string
    {
        do {
            $code = '';
            $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (MarketItem::withTrashed()->where('code', $code)->exists());
        return $code;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
