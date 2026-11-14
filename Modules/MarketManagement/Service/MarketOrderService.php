<?php

namespace Modules\MarketManagement\Service;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\MarketManagement\Entities\MarketConversation;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketMessage;
use Modules\MarketManagement\Entities\MarketOrder;
use Modules\MarketManagement\Entities\MarketOrderLine;
use Modules\MarketManagement\Entities\MarketReview;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Lib\MarketPush;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

/**
 * Market orders, like the partner portal but without money: the buyer sends a cart to a
 * shop, the seller answers each line within the answer time (or declines), the buyer agrees
 * when something changed, the stock is held, and the order completes when the seller types
 * the buyer's receive code (given after the buyer has the items and has paid the seller).
 */
class MarketOrderService
{
    public function __construct(protected ShopService $shops, protected MarketChatService $chats)
    {
    }

    // ------------------------------------------------------------------ buyer sends

    /**
     * @param array<int, array{item: string, colour?: ?string, size?: ?string, qty: int}> $lines
     * @param array{fulfilment: string, pay_method: string, drop_lat?: ?float, drop_lng?: ?float, drop_address?: ?string, note?: ?string} $input
     */
    public function place(User $buyer, MarketShop $shop, array $lines, array $input): MarketOrder
    {
        $settings = MarketSettings::all();
        if (!$shop->acceptsOrders()) {
            $this->fail('shop', translate('This shop is not taking orders right now. You can still chat with the seller.'));
        }
        if ($shop->user_id === $buyer->id) {
            $this->fail('shop', translate('This is your own shop.'));
        }
        if ($this->shops->blocked($buyer->id, $shop->id)) {
            $this->fail('shop', translate('You blocked this shop.'));
        }
        $open = MarketOrder::where('buyer_id', $buyer->id)->whereIn('status', MarketOrder::OPEN)->count();
        if ($open >= $settings['open_orders_per_buyer']) {
            $this->fail('orders', translate(key: 'You already have {count} orders in progress. Finish or cancel one first.', replace: ['count' => $open]));
        }
        $fulfilment = (string)($input['fulfilment'] ?? '');
        if (!in_array($fulfilment, $shop->fulfilmentList(), true)) {
            $this->fail('fulfilment', translate('Choose how you get the items.'));
        }
        $pay = (string)($input['pay_method'] ?? '');
        if (!in_array($pay, $shop->payMethodList(), true)) {
            $this->fail('pay_method', translate('Choose how you will pay the seller.'));
        }
        $needsPoint = in_array($fulfilment, ['eride', 'seller'], true);
        if ($needsPoint && (!isset($input['drop_lat'], $input['drop_lng']) || !is_numeric($input['drop_lat']) || !is_numeric($input['drop_lng']))) {
            $this->fail('drop', translate('Choose where to deliver.'));
        }

        $wanted = $this->mergeLines($lines);
        if ($wanted === []) {
            $this->fail('lines', translate('Your cart is empty.'));
        }
        $items = MarketItem::with('photos')->where('shop_id', $shop->id)->whereIn('code', array_unique(array_column($wanted, 'item')))->get()->keyBy('code');

        // Pieces per item decide the wholesale price.
        $perItem = [];
        foreach ($wanted as $line) {
            $perItem[$line['item']] = ($perItem[$line['item']] ?? 0) + $line['qty'];
        }
        $rows = [];
        $total = 0.0;
        foreach ($wanted as $line) {
            /** @var MarketItem|null $item */
            $item = $items->get($line['item']);
            if (!$item || !$item->orderable()) {
                $this->fail('lines', translate(key: '"{title}" can no longer be ordered. Remove it from your cart.',
                    replace: ['title' => $item?->title ?? translate('An item')]));
            }
            [$colour, $size] = $this->checkOptions($item, $line['colour'], $line['size']);
            $left = $item->stockFor($colour, $size);
            if ($line['qty'] > $left) {
                $this->fail('lines', $left < 1
                    ? translate(key: '"{title}" is sold out in that choice.', replace: ['title' => $item->title])
                    : translate(key: 'Only {count} left of "{title}" in that choice.', replace: ['count' => $left, 'title' => $item->title]));
            }
            [$unit, $wholesale] = $item->unitPriceFor($perItem[$item->code]);
            $total += $unit * $line['qty'];
            $rows[] = [
                'item_id' => $item->id, 'title' => $item->title, 'photo' => $this->photoFor($item, $colour),
                'colour' => $colour, 'size' => $size, 'unit_price' => $unit, 'wholesale' => $wholesale, 'qty' => $line['qty'],
            ];
        }
        if ($shop->min_order && $total < $shop->min_order) {
            $this->fail('lines', translate(key: 'This shop takes orders from {amount}.', replace: ['amount' => getCurrencyFormat($shop->min_order)]));
        }

        $firstItem = $items->get($wanted[0]['item']);
        $conversation = $this->chats->start($buyer, $firstItem);

        $order = DB::transaction(function () use ($buyer, $shop, $rows, $total, $fulfilment, $pay, $input, $needsPoint, $settings, $conversation) {
            $order = MarketOrder::create([
                'code' => $this->newCode(),
                'shop_id' => $shop->id, 'buyer_id' => $buyer->id, 'seller_id' => $shop->user_id, 'conversation_id' => $conversation->id,
                'status' => MarketOrder::REQUESTED, 'fulfilment' => $fulfilment, 'pay_method' => $pay,
                'drop_lat' => $needsPoint ? (float)$input['drop_lat'] : null,
                'drop_lng' => $needsPoint ? (float)$input['drop_lng'] : null,
                'drop_address' => $needsPoint ? mb_substr((string)($input['drop_address'] ?? ''), 0, 191) : null,
                'note' => isset($input['note']) && trim((string)$input['note']) !== '' ? mb_substr(trim((string)$input['note']), 0, 500) : null,
                'requested_total' => round($total, 2),
                // Outside opening hours the answer time starts when the shop opens.
                'respond_by' => $shop->nextOpening(now())->copy()->addMinutes($settings['answer_minutes']),
            ]);
            foreach ($rows as $row) {
                $order->lines()->create($row);
            }
            return $order;
        });

        $this->chatNote($order, $buyer, 'placed');
        $count = array_sum(array_column($rows, 'qty'));
        MarketPush::send($shop->owner, 'market_order_new', 'New order {code}', '{buyer} sent an order of {total}. Answer within {time}.', [
            'code' => $order->code, 'buyer' => $this->firstName($buyer), 'count' => $count, 'total' => getCurrencyFormat($total),
            'time' => $this->duration($order->respond_by),
        ], ['order_id' => $order->id]);
        return $order->fresh(['lines', 'shop']);
    }

    // ------------------------------------------------------------------ seller answers

    /**
     * The seller's answer, line by line: how many of each they can give (0 = not available).
     * @param array<int|string, array{qty: int, note?: ?string}> $answers keyed by line id
     */
    public function respond(User $seller, MarketOrder $order, array $answers): MarketOrder
    {
        $this->asSeller($seller, $order);
        if ($order->status !== MarketOrder::REQUESTED) {
            $this->fail('order', translate('This order was already answered.'));
        }
        $all = true;
        $none = true;
        DB::transaction(function () use ($order, $answers, &$all, &$none) {
            foreach ($order->lines as $line) {
                $answer = $answers[$line->id] ?? $answers[(string)$line->id] ?? null;
                if ($answer === null) {
                    $this->fail('lines', translate('Answer every item of the order.'));
                }
                $qty = max(0, min($line->qty, (int)($answer['qty'] ?? 0)));
                $note = isset($answer['note']) && trim((string)$answer['note']) !== '' ? mb_substr(trim((string)$answer['note']), 0, 200) : null;
                $line->update(['qty_confirmed' => $qty, 'seller_note' => $note]);
                $all = $all && $qty === $line->qty;
                $none = $none && $qty === 0;
            }
        });
        $order->refresh();
        $this->noteReplyTime($order);
        if ($none) {
            return $this->decline($seller, $order, 'sold_out', null, false);
        }
        if ($all) {
            $order = $this->confirm($order, 'seller');
            return $order;
        }
        $settings = MarketSettings::all();
        $order->update([
            'status' => MarketOrder::CHANGES,
            'answered_at' => now(),
            'confirmed_total' => $this->linesTotal($order),
            'agree_by' => now()->addMinutes($settings['agree_minutes']),
        ]);
        $this->chatNote($order, $seller, 'changes');
        MarketPush::send($order->buyer, 'market_order_changes', '{shop} answered your order', 'Some items changed. New total {total}. Agree within {time}.', [
            'shop' => $order->shop->name, 'total' => getCurrencyFormat($order->confirmed_total), 'time' => $this->duration($order->agree_by),
        ], ['order_id' => $order->id]);
        return $order->fresh(['lines', 'shop']);
    }

    public function decline(User $seller, MarketOrder $order, string $reason, ?string $note, bool $markSoldOut): MarketOrder
    {
        $this->asSeller($seller, $order);
        if (!in_array($order->status, [MarketOrder::REQUESTED, MarketOrder::CHANGES], true)) {
            $this->fail('order', translate('This order can no longer be declined.'));
        }
        $reason = in_array($reason, MarketOrder::DECLINE_REASONS, true) ? $reason : 'other';
        DB::transaction(function () use ($order, $reason, $note, $markSoldOut) {
            if ($markSoldOut) {
                foreach ($order->lines as $line) {
                    $this->setStock($line, 0);
                }
            }
            $order->update([
                'status' => MarketOrder::DECLINED, 'decline_reason' => $reason,
                'decline_note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 300) : null,
                'answered_at' => $order->answered_at ?? now(), 'closed_at' => now(),
            ]);
        });
        $this->noteReplyTime($order);
        $this->chatNote($order, $seller, 'declined');
        MarketPush::send($order->buyer, 'market_order_declined', '{shop} declined your order', 'Reason: {reason}. Have a look at similar items nearby.', [
            'shop' => $order->shop->name, 'reason' => translate('market_decline_' . $reason),
        ], ['order_id' => $order->id]);
        return $order->fresh(['lines', 'shop']);
    }

    // ------------------------------------------------------------------ buyer agrees or cancels

    public function agree(User $buyer, MarketOrder $order): MarketOrder
    {
        $this->asBuyer($buyer, $order);
        if ($order->status !== MarketOrder::CHANGES) {
            $this->fail('order', translate('There is nothing to agree to on this order.'));
        }
        if ($order->agree_by && $order->agree_by->isPast()) {
            $this->fail('order', translate('The time to agree has passed. Send the order again.'));
        }
        return $this->confirm($order, 'buyer');
    }

    /** The buyer (any time before completion) or the seller (once confirmed, e.g. the buyer never came) cancels. */
    public function cancel(User $user, MarketOrder $order, ?string $reason): MarketOrder
    {
        $role = $order->roleOf($user->id) ?? $this->fail('order', translate('This order is not yours.'));
        $allowed = $role === 'buyer' ? array_diff(MarketOrder::OPEN, []) : MarketOrder::HELD;
        if (!in_array($order->status, $allowed, true)) {
            $this->fail('order', translate('This order can no longer be cancelled.'));
        }
        $wasHeld = in_array($order->status, MarketOrder::HELD, true);
        if (in_array($order->status, MarketOrder::HELD, true) && trim((string)$reason) === '') {
            $this->fail('reason', translate('Say why you cancel: the other side sees it.'));
        }
        DB::transaction(function () use ($order, $role, $reason) {
            $this->release($order);
            $order->update([
                'status' => MarketOrder::CANCELLED, 'cancelled_by' => $role,
                'cancel_reason' => $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 300) : null,
                'closed_at' => now(),
            ]);
        });
        // An auction winner backing out is a no-show.
        if ($role === 'buyer' && $order->auction_id && $wasHeld) {
            \Modules\MarketManagement\Entities\MarketStrike::create(['user_id' => $user->id, 'auction_id' => $order->auction_id, 'reason' => 'no_payment']);
            \Modules\MarketManagement\Entities\MarketAuction::whereKey($order->auction_id)->update(['order_id' => null]);
        }
        $this->chatNote($order, $user, 'cancelled');
        $other = $role === 'buyer' ? $order->shop->owner : $order->buyer;
        MarketPush::send($other, 'market_order_cancelled', 'Order {code} cancelled', '{who} cancelled the order.', [
            'code' => $order->code, 'who' => $role === 'buyer' ? $this->firstName($order->buyer) : $order->shop->name,
        ], ['order_id' => $order->id]);
        return $order->fresh(['lines', 'shop']);
    }

    // ------------------------------------------------------------------ auctions

    /** The auction winner's order: confirmed at once (the item is held), with a time to arrange payment. */
    public function createForAuction(\Modules\MarketManagement\Entities\MarketAuction $auction, User $winner, float $price): MarketOrder
    {
        $item = MarketItem::with(['shop.owner', 'photos'])->withTrashed()->find($auction->item_id);
        $shop = $item->shop;
        $conversation = null;
        try {
            $conversation = $this->chats->start($winner, $item);
        } catch (\Throwable) {
        }
        $settings = MarketSettings::all();
        $order = DB::transaction(function () use ($auction, $winner, $price, $item, $shop, $conversation, $settings) {
            $order = MarketOrder::create([
                'code' => $this->newCode(),
                'shop_id' => $shop->id, 'buyer_id' => $winner->id, 'seller_id' => $shop->user_id, 'conversation_id' => $conversation?->id,
                'auction_id' => $auction->id, 'status' => MarketOrder::CONFIRMED,
                'fulfilment' => $shop->fulfilmentList()[0], 'pay_method' => $shop->payMethodList()[0],
                'requested_total' => $price, 'confirmed_total' => $price, 'confirmed_at' => now(), 'answered_at' => now(),
                'payment_due_at' => now()->addHours($settings['auction_pay_hours']),
                'receive_code' => str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT), 'stock_held' => true,
            ]);
            $order->lines()->create(['item_id' => $item->id, 'title' => $item->title, 'photo' => $item->cover()?->url(),
                'unit_price' => $price, 'wholesale' => false, 'qty' => 1, 'qty_confirmed' => 1]);
            $item->forceFill(['quantity' => 0, 'status' => MarketItem::SOLD_OUT])->save();
            MarketItem::whereKey($item->id)->increment('orders');
            return $order;
        });
        $this->chatNote($order, $winner, 'auction_won');
        return $order->fresh(['lines', 'shop']);
    }

    /** An auction order that ends without a sale (no-show, or put up again): the item is free again. */
    public function cancelForAuction(MarketOrder $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            if ($order->stock_held) {
                $item = MarketItem::lockForUpdate()->find($order->lines()->value('item_id'));
                if ($item) {
                    $item->forceFill(['quantity' => 1, 'status' => MarketItem::LIVE])->save();
                }
            }
            $order->update(['status' => MarketOrder::CANCELLED, 'cancelled_by' => 'seller', 'cancel_reason' => $reason, 'closed_at' => now(), 'stock_held' => false]);
        });
    }

    /** The seller says the winner arranged payment: the deadline no longer applies. */
    public function paymentArranged(User $seller, MarketOrder $order): MarketOrder
    {
        $this->asSeller($seller, $order);
        if (!$order->auction_id || !$order->isOpen()) {
            $this->fail('order', translate('This order is not waiting for payment.'));
        }
        $order->update(['payment_arranged_at' => now()]);
        return $order->fresh(['lines', 'shop']);
    }

    // ------------------------------------------------------------------ seller fulfils

    /** Pickup or the seller's own delivery: the items are ready (the buyer now sees the pickup spot). */
    public function ready(User $seller, MarketOrder $order): MarketOrder
    {
        $this->asSeller($seller, $order);
        if ($order->status !== MarketOrder::CONFIRMED) {
            $this->fail('order', translate('Only a confirmed order can be marked ready.'));
        }
        $order->update(['status' => MarketOrder::READY, 'ready_at' => now()]);
        $this->chatNote($order, $seller, 'ready');
        MarketPush::send($order->buyer, 'market_order_ready', 'Your order {code} is ready',
            $order->fulfilment === 'pickup' ? 'Pick it up at {shop}. Check the items, pay, then give your code.' : '{shop} is bringing it. Check the items, pay, then give your code.',
            ['code' => $order->code, 'shop' => $order->shop->name], ['order_id' => $order->id]);
        return $order->fresh(['lines', 'shop']);
    }

    /** The seller booked the E-Ride parcel for this order. */
    public function attachTrip(User $seller, MarketOrder $order, string $tripId): MarketOrder
    {
        $this->asSeller($seller, $order);
        if (!in_array($order->status, [MarketOrder::CONFIRMED, MarketOrder::READY], true)) {
            $this->fail('order', translate('This order is not waiting for delivery.'));
        }
        $trip = TripRequest::find($tripId);
        if (!$trip || $trip->customer_id !== $seller->id || $trip->type !== PARCEL || $trip->created_at->lt(now()->subDay())) {
            $this->fail('trip', translate('Book the delivery from this order first.'));
        }
        $order->update(['status' => MarketOrder::ON_THE_WAY, 'trip_id' => $trip->id, 'ready_at' => $order->ready_at ?? now()]);
        if ($order->conversation_id && ($conversation = MarketConversation::find($order->conversation_id))) {
            try {
                $this->chats->send($seller, $conversation, 'delivery_booked', null, null, ['trip_id' => $trip->id]);
            } catch (\Throwable) {
            }
        }
        MarketPush::send($order->buyer, 'market_order_on_the_way', 'Your order {code} is on the way', 'An E-Ride driver is bringing it. Check the items, pay, then give your code.',
            ['code' => $order->code], ['order_id' => $order->id]);
        return $order->fresh(['lines', 'shop']);
    }

    /** The seller types the buyer's receive code: the order is complete. */
    public function complete(User $seller, MarketOrder $order, string $code): MarketOrder
    {
        $this->asSeller($seller, $order);
        if (!in_array($order->status, MarketOrder::HELD, true)) {
            $this->fail('order', translate('This order is not waiting to be completed.'));
        }
        if (!hash_equals((string)$order->receive_code, trim($code))) {
            $this->fail('code', translate('That code is not right. Ask the buyer for the 4 digits on their order.'));
        }
        DB::transaction(function () use ($order) {
            $order->update(['status' => MarketOrder::COMPLETED, 'completed_at' => now(), 'closed_at' => now(), 'stock_held' => false]);
            MarketShop::whereKey($order->shop_id)->increment('orders_completed');
        });
        $this->chatNote($order, $seller, 'completed');
        MarketPush::send($order->buyer, 'market_order_completed', 'Order {code} completed', 'How was {shop}? Your rating helps good sellers.',
            ['code' => $order->code, 'shop' => $order->shop->name], ['order_id' => $order->id]);
        return $order->fresh(['lines', 'shop']);
    }

    // ------------------------------------------------------------------ ratings

    public function review(User $buyer, MarketOrder $order, int $stars, array $tags, ?string $comment): MarketReview
    {
        $this->asBuyer($buyer, $order);
        if ($order->status !== MarketOrder::COMPLETED) {
            $this->fail('order', translate('You can rate a shop once the order is completed.'));
        }
        if ($order->review()->exists()) {
            $this->fail('order', translate('You already rated this order.'));
        }
        $review = MarketReview::create([
            'order_id' => $order->id, 'shop_id' => $order->shop_id, 'buyer_id' => $buyer->id,
            'stars' => max(1, min(5, $stars)),
            'tags' => array_values(array_intersect(MarketReview::TAGS, $tags)),
            'comment' => $comment !== null && trim($comment) !== '' ? mb_substr(trim($comment), 0, 500) : null,
        ]);
        $this->refreshRating($order->shop_id);
        return $review;
    }

    public function refreshRating(string $shopId): void
    {
        $row = MarketReview::where('shop_id', $shopId)->where('hidden', false)->selectRaw('count(*) as n, avg(stars) as a')->first();
        MarketShop::whereKey($shopId)->update(['rating_count' => (int)$row->n, 'rating' => $row->n ? round((float)$row->a, 2) : null]);
    }

    // ------------------------------------------------------------------ the clock

    /** Every minute: unanswered requests expire, unanswered changes are cancelled. */
    public function tick(): array
    {
        $done = ['expired' => 0, 'changes_cancelled' => 0];
        $settings = MarketSettings::all();
        MarketOrder::with(['shop.owner', 'buyer'])->where('status', MarketOrder::REQUESTED)->where('respond_by', '<', now())->limit(200)->get()
            ->each(function (MarketOrder $order) use (&$done, $settings) {
                $updated = MarketOrder::whereKey($order->id)->where('status', MarketOrder::REQUESTED)
                    ->update(['status' => MarketOrder::EXPIRED, 'closed_at' => now()]);
                if (!$updated) {
                    return;
                }
                $done['expired']++;
                MarketPush::send($order->buyer, 'market_order_expired', '{shop} did not answer', 'Your order {code} closed. Have a look at similar items nearby.',
                    ['shop' => $order->shop->name, 'code' => $order->code], ['order_id' => $order->id]);
                MarketPush::send($order->shop->owner, 'market_order_expired', 'You missed order {code}', 'It closed because nobody answered in time. Turn off "Taking orders" when you are busy.',
                    ['code' => $order->code], ['order_id' => $order->id]);
                $recent = MarketOrder::where('shop_id', $order->shop_id)->where('status', MarketOrder::EXPIRED)->where('closed_at', '>', now()->subDays(7))->count();
                if ($recent >= $settings['demote_after_expired']) {
                    MarketShop::whereKey($order->shop_id)->update(['demoted_until' => now()->addDays(7)]);
                }
            });
        MarketOrder::with(['shop.owner', 'buyer'])->where('status', MarketOrder::CHANGES)->where('agree_by', '<', now())->limit(200)->get()
            ->each(function (MarketOrder $order) use (&$done) {
                $updated = MarketOrder::whereKey($order->id)->where('status', MarketOrder::CHANGES)
                    ->update(['status' => MarketOrder::CANCELLED, 'cancelled_by' => 'timeout', 'closed_at' => now()]);
                if (!$updated) {
                    return;
                }
                $done['changes_cancelled']++;
                MarketPush::send($order->shop->owner, 'market_order_cancelled', 'Order {code} closed', 'The buyer did not agree to the changes in time.',
                    ['code' => $order->code], ['order_id' => $order->id]);
            });
        return $done;
    }

    // ------------------------------------------------------------------ lists

    public function listFor(User $user, string $role, string $tab, int $offset, int $limit): array
    {
        $query = MarketOrder::with(['lines', 'shop', 'buyer'])->where($role === 'selling' ? 'seller_id' : 'buyer_id', $user->id);
        $tabs = [
            'new' => [MarketOrder::REQUESTED],
            'agree' => [MarketOrder::CHANGES],
            'active' => MarketOrder::OPEN,
            'confirmed' => MarketOrder::HELD,
            'done' => MarketOrder::FINAL,
        ];
        $statuses = $tabs[$tab] ?? MarketOrder::OPEN;
        $query->whereIn('status', $statuses);
        $total = (clone $query)->count();
        $orders = $query->orderByRaw($tab === 'done' ? 'updated_at desc' : 'created_at desc')->skip($offset)->take($limit)->get();
        return [$total, $orders];
    }

    /** Badge numbers: requests waiting for the seller, and the buyer's orders that need them. */
    public function counts(User $user): array
    {
        return [
            'selling_new' => MarketOrder::where('seller_id', $user->id)->where('status', MarketOrder::REQUESTED)->count(),
            'buying_action' => MarketOrder::where('buyer_id', $user->id)->where('status', MarketOrder::CHANGES)->count(),
            'buying_open' => MarketOrder::where('buyer_id', $user->id)->whereIn('status', MarketOrder::OPEN)->count(),
        ];
    }

    // ------------------------------------------------------------------ inside

    private function confirm(MarketOrder $order, string $by): MarketOrder
    {
        DB::transaction(function () use ($order) {
            $order->load('lines');
            foreach ($order->lines as $line) {
                // Stock may have moved while the buyer decided: never hold more than is left.
                $item = MarketItem::lockForUpdate()->find($line->item_id);
                $left = $item ? $item->stockFor($line->colour, $line->size) : 0;
                $qty = min($line->finalQty(), $left);
                if ($qty !== $line->finalQty()) {
                    $line->update(['qty_confirmed' => $qty]);
                }
                if ($item && $qty > 0) {
                    $this->moveStock($item, $line, -$qty);
                    MarketItem::whereKey($item->id)->increment('orders');
                }
            }
            $order->refresh();
            $total = $this->linesTotal($order);
            if ($total <= 0) {
                $this->fail('order', translate('Everything in this order is now sold out.'));
            }
            $order->update([
                'status' => MarketOrder::CONFIRMED, 'confirmed_total' => $total, 'confirmed_at' => now(),
                'answered_at' => $order->answered_at ?? now(), 'receive_code' => str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'stock_held' => true,
            ]);
        });
        $order->refresh();
        if ($by === 'seller') {
            $this->chatNote($order, $order->seller, 'confirmed');
            MarketPush::send($order->buyer, 'market_order_confirmed', '{shop} confirmed your order', 'Order {code}: {total}. Pay the seller when you have the items, then give your code.', [
                'shop' => $order->shop->name, 'code' => $order->code, 'total' => getCurrencyFormat($order->confirmed_total),
            ], ['order_id' => $order->id]);
        } else {
            $this->chatNote($order, $order->buyer, 'agreed');
            MarketPush::send($order->shop->owner, 'market_order_agreed', '{buyer} agreed to your changes', 'Order {code} is confirmed: {total}. Prepare it.', [
                'buyer' => $this->firstName($order->buyer), 'code' => $order->code, 'total' => getCurrencyFormat($order->confirmed_total),
            ], ['order_id' => $order->id]);
        }
        return $order->fresh(['lines', 'shop']);
    }

    /** Gives back what a confirmed order held. */
    private function release(MarketOrder $order): void
    {
        if (!$order->stock_held) {
            return;
        }
        foreach ($order->lines as $line) {
            $item = MarketItem::lockForUpdate()->find($line->item_id);
            if ($item && $line->finalQty() > 0) {
                $this->moveStock($item, $line, $line->finalQty());
            }
        }
        $order->update(['stock_held' => false]);
    }

    /** Adds $delta (negative to take) to one colour and size of an item; keeps quantity and sold out in step. */
    private function moveStock(MarketItem $item, MarketOrderLine $line, int $delta): void
    {
        if ($item->hasOptions()) {
            $stock = $item->stock ?? [];
            $key = $line->stockKey();
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

    /** The seller says a choice is gone (declining with "mark sold out"). */
    private function setStock(MarketOrderLine $line, int $qty): void
    {
        $item = MarketItem::lockForUpdate()->find($line->item_id);
        if (!$item) {
            return;
        }
        $current = $item->stockFor($line->colour, $line->size);
        $this->moveStock($item, $line, $qty - $current);
    }

    private function linesTotal(MarketOrder $order): float
    {
        return round((float)$order->lines()->get()->sum(fn(MarketOrderLine $l) => $l->unit_price * $l->finalQty()), 2);
    }

    /** @return array<int, array{item: string, colour: ?string, size: ?string, qty: int}> same choice twice becomes one line */
    private function mergeLines(array $lines): array
    {
        $out = [];
        foreach (array_slice($lines, 0, 50) as $line) {
            $code = trim((string)($line['item'] ?? ''));
            $qty = (int)($line['qty'] ?? 0);
            if ($code === '' || $qty < 1) {
                continue;
            }
            $colour = isset($line['colour']) && trim((string)$line['colour']) !== '' ? trim((string)$line['colour']) : null;
            $size = isset($line['size']) && trim((string)$line['size']) !== '' ? trim((string)$line['size']) : null;
            $key = $code . '|' . $colour . '|' . $size;
            $out[$key] = ['item' => $code, 'colour' => $colour, 'size' => $size, 'qty' => min(9999, ($out[$key]['qty'] ?? 0) + $qty)];
        }
        return array_values($out);
    }

    /** The colour and size must be ones the item has; an item without that option takes none. */
    private function checkOptions(MarketItem $item, ?string $colour, ?string $size): array
    {
        $colours = array_column($item->colours(), 'name');
        $sizes = $item->sizes();
        if ($colours !== [] && !in_array($colour, $colours, true)) {
            $this->fail('lines', translate(key: 'Choose a colour for "{title}".', replace: ['title' => $item->title]));
        }
        if ($sizes !== [] && !in_array($size, $sizes, true)) {
            $this->fail('lines', translate(key: 'Choose a size for "{title}".', replace: ['title' => $item->title]));
        }
        return [$colours !== [] ? $colour : null, $sizes !== [] ? $size : null];
    }

    private function photoFor(MarketItem $item, ?string $colour): ?string
    {
        if ($colour !== null) {
            foreach ($item->colours() as $c) {
                if ($c['name'] === $colour && !empty($c['photo_id']) && ($photo = $item->photos->firstWhere('id', (int)$c['photo_id']))) {
                    return $photo->url();
                }
            }
        }
        return $item->cover()?->url();
    }

    /** A short line in the order's chat, so both sides see the order's story next to their messages. */
    private function chatNote(MarketOrder $order, ?User $by, string $event): void
    {
        if (!$order->conversation_id || !$by) {
            return;
        }
        try {
            $text = match ($event) {
                'placed' => translate(key: 'Order {code} sent', replace: ['code' => $order->code]),
                'changes' => translate(key: 'Order {code}: some items changed', replace: ['code' => $order->code]),
                'confirmed' => translate(key: 'Order {code} confirmed', replace: ['code' => $order->code]),
                'agreed' => translate(key: 'Order {code}: changes agreed', replace: ['code' => $order->code]),
                'declined' => translate(key: 'Order {code} declined', replace: ['code' => $order->code]),
                'cancelled' => translate(key: 'Order {code} cancelled', replace: ['code' => $order->code]),
                'ready' => translate(key: 'Order {code} is ready', replace: ['code' => $order->code]),
                'completed' => translate(key: 'Order {code} completed', replace: ['code' => $order->code]),
                'auction_won' => translate(key: 'Auction won: order {code}', replace: ['code' => $order->code]),
                'auction_won' => translate(key: 'Auction won: order {code}', replace: ['code' => $order->code]),
                default => $order->code,
            };
            DB::transaction(function () use ($order, $by, $event, $text) {
                MarketMessage::create([
                    'conversation_id' => $order->conversation_id, 'sender_id' => $by->id, 'type' => 'order', 'body' => $text,
                    'meta' => ['order_id' => $order->id, 'event' => $event],
                ]);
                MarketConversation::whereKey($order->conversation_id)->update(['last_message_at' => now(), 'last_message' => $text]);
                $role = $order->roleOf($by->id);
                MarketConversation::whereKey($order->conversation_id)->increment($role === 'buyer' ? 'seller_unread' : 'buyer_unread');
            });
        } catch (\Throwable) {
        }
    }

    /** A running average of how fast the shop answers, in minutes. */
    private function noteReplyTime(MarketOrder $order): void
    {
        $minutes = max(1, (int)round($order->created_at->diffInMinutes(now())));
        $shop = MarketShop::find($order->shop_id);
        if ($shop) {
            $shop->update(['reply_minutes' => $shop->reply_minutes ? (int)round(($shop->reply_minutes * 4 + $minutes) / 5) : $minutes]);
        }
    }

    private function newCode(): string
    {
        do {
            $code = 'MK-' . random_int(10000, 99999);
        } while (MarketOrder::where('code', $code)->exists());
        return $code;
    }

    private function duration(?\Carbon\CarbonInterface $until): string
    {
        $minutes = $until ? max(1, (int)ceil(now()->diffInMinutes($until, false))) : 0;
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        return intdiv($minutes, 60) . ' h' . ($minutes % 60 ? ' ' . str_pad((string)($minutes % 60), 2, '0', STR_PAD_LEFT) : '');
    }

    private function firstName(?User $user): string
    {
        return trim((string)$user?->first_name) ?: translate('A buyer');
    }

    private function asSeller(User $user, MarketOrder $order): void
    {
        if ($order->seller_id !== $user->id) {
            $this->fail('order', translate('This order is not yours.'));
        }
    }

    private function asBuyer(User $user, MarketOrder $order): void
    {
        if ($order->buyer_id !== $user->id) {
            $this->fail('order', translate('This order is not yours.'));
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
