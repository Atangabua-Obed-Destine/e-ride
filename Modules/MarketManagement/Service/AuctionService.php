<?php

namespace Modules\MarketManagement\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\MarketManagement\Entities\MarketAuction;
use Modules\MarketManagement\Entities\MarketBid;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketOrder;
use Modules\MarketManagement\Entities\MarketStrike;
use Modules\MarketManagement\Lib\MarketPush;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\UserManagement\Entities\User;

/**
 * Auctions. A bid is a promise to buy: the highest bid at or above the hidden reserve wins
 * when the auction ends, and the winner gets a normal confirmed order with a time to arrange
 * payment with the seller (E-Ride never holds the money). A bid in the last minutes extends
 * the end. A winner who does not arrange payment gets a strike, and the seller can offer the
 * item to the next bidder at that bidder's price.
 */
class AuctionService
{
    public function __construct(protected MarketOrderService $orders)
    {
    }

    // ------------------------------------------------------------------ the seller

    /** Called when an item is posted (or updated) as an auction. */
    public function start(MarketItem $item, array $data): MarketAuction
    {
        $s = MarketSettings::all();
        $shop = $item->shop;
        if ($s['auction_who'] === 'verified' && !$shop?->isVerified()) {
            $this->fail('sale_type', translate('Only E-Ride Verified sellers can run auctions for now.'));
        }
        if ($item->isDigital()) {
            $this->fail('sale_type', translate('Digital products cannot be sold by auction.'));
        }
        $live = MarketAuction::where('item_id', $item->id)->where('status', MarketAuction::LIVE)->first();
        if ($live && $live->bid_count > 0) {
            $this->fail('sale_type', translate('This auction already has bids: it cannot be changed.'));
        }
        $start = (float)($data['auction_start'] ?? $item->price ?? 0);
        $step = (float)($data['auction_step'] ?? 0);
        $reserve = isset($data['auction_reserve']) && $data['auction_reserve'] !== '' && (float)$data['auction_reserve'] > 0 ? (float)$data['auction_reserve'] : null;
        $hours = isset($data['auction_hours']) ? (int)$data['auction_hours'] : (int)($data['auction_days'] ?? 3) * 24;
        if ($start <= 0) {
            $this->fail('auction_start', translate('Set a starting price.'));
        }
        if ($step <= 0) {
            $step = max(100, round($start * 0.02, -2));
        }
        if ($hours < 1 || $hours > $s['auction_max_days'] * 24) {
            $this->fail('auction_days', translate(key: 'An auction lasts from 1 hour to {days} days.', replace: ['days' => $s['auction_max_days']]));
        }
        if ($reserve !== null && $reserve <= $start) {
            $reserve = null;
        }
        $fields = [
            'item_id' => $item->id, 'seller_id' => $shop->user_id, 'start_price' => $start, 'step' => $step,
            'reserve_price' => $reserve, 'ends_at' => now()->addHours($hours), 'status' => MarketAuction::LIVE,
        ];
        $auction = $live ? tap($live)->update($fields) : MarketAuction::create($fields);
        $item->forceFill(['price' => $start, 'sale_type' => 'auction', 'negotiable' => false, 'quantity' => 1,
            'options' => null, 'stock' => null, 'wholesale_price' => null, 'wholesale_min' => null])->save();
        return $auction->fresh();
    }

    /** The seller puts an item up again (after it did not sell, or the winner did not pay). */
    public function relist(User $seller, MarketAuction $auction, array $data): MarketAuction
    {
        $this->asSeller($seller, $auction);
        if ($auction->status === MarketAuction::LIVE) {
            $this->fail('auction', translate('This auction is still running.'));
        }
        if ($auction->order_id && ($order = MarketOrder::find($auction->order_id)) && $order->isOpen() && !$order->payment_due_at?->isPast()) {
            $this->fail('auction', translate('The winner still has time to arrange payment.'));
        }
        $item = $auction->item;
        return DB::transaction(function () use ($item, $data, $auction) {
            if ($auction->order_id && ($order = MarketOrder::find($auction->order_id)) && $order->isOpen()) {
                $this->orders->cancelForAuction($order, 'relisted');
            }
            $item->forceFill(['status' => MarketItem::LIVE, 'quantity' => 1])->save();
            return $this->start($item->fresh('shop'), $data + ['auction_start' => $auction->start_price, 'auction_step' => $auction->step,
                    'auction_reserve' => $auction->reserve_price]);
        });
    }

    // ------------------------------------------------------------------ bidding

    public function canBid(User $user): ?string
    {
        $s = MarketSettings::all();
        if ($s['auction_bidders'] === 'phone_verified' && !$this->phoneVerified($user)) {
            return translate('Verify your phone number to bid.');
        }
        if ($this->activeStrikes($user->id) >= $s['auction_strikes']) {
            return translate('You cannot bid for now: you won auctions without arranging payment.');
        }
        return null;
    }

    public function bid(User $user, MarketAuction $auction, float $amount): MarketAuction
    {
        if ($reason = $this->canBid($user)) {
            $this->fail('bid', $reason);
        }
        if ($auction->seller_id === $user->id) {
            $this->fail('bid', translate('You cannot bid on your own item.'));
        }
        $previousLeader = null;
        $auction = DB::transaction(function () use ($user, $auction, $amount, &$previousLeader) {
            /** @var MarketAuction $a */
            $a = MarketAuction::lockForUpdate()->find($auction->id);
            if (!$a->isLive()) {
                $this->fail('bid', translate('This auction has ended.'));
            }
            if ($a->leader_id === $user->id) {
                $this->fail('bid', translate('You already have the highest bid.'));
            }
            if ($amount < $a->minNext()) {
                $this->fail('bid', translate(key: 'Bid at least {amount}.', replace: ['amount' => getCurrencyFormat($a->minNext())]));
            }
            if ($amount > $a->minNext() + 1000 * max(1, $a->step)) {
                $this->fail('bid', translate('That bid is much higher than needed. Check the amount.'));
            }
            $previousLeader = $a->leader_id;
            MarketBid::create(['auction_id' => $a->id, 'user_id' => $user->id, 'amount' => $amount]);
            $extend = MarketSettings::all()['auction_extend_minutes'];
            $endsAt = $a->ends_at;
            if ($extend > 0 && $endsAt->diffInMinutes(now(), true) < $extend) {
                $endsAt = now()->addMinutes($extend);
            }
            $a->update(['current_bid' => $amount, 'bid_count' => $a->bid_count + 1, 'leader_id' => $user->id, 'ends_at' => $endsAt]);
            return $a->fresh();
        });
        $item = $auction->item;
        if ($previousLeader && $previousLeader !== $user->id) {
            MarketPush::send(User::find($previousLeader), 'market_auction_outbid', "You've been outbid", '{title}: someone bid {amount}. Bid again before it ends.',
                ['title' => $item->title, 'amount' => getCurrencyFormat($auction->current_bid)], ['item_code' => $item->code]);
        }
        MarketPush::send(User::find($auction->seller_id), 'market_auction_bid', 'New bid on {title}',
            $auction->bid_count === 1 ? 'First bid: {amount}.' : '{amount}, {count} bids so far.',
            ['title' => $item->title, 'amount' => getCurrencyFormat($auction->current_bid), 'count' => $auction->bid_count], ['item_code' => $item->code]);
        return $auction;
    }

    // ------------------------------------------------------------------ the clock

    /** Every minute: "ending in 1 hour" alerts, and auctions that end. */
    public function tick(): array
    {
        $done = ['ending_soon' => 0, 'sold' => 0, 'unsold' => 0, 'offers_expired' => 0];
        MarketAuction::where('status', MarketAuction::LIVE)->where('ending_notified', false)->where('bid_count', '>', 0)
            ->where('ends_at', '<=', now()->addHour())->where('ends_at', '>', now())->limit(100)->get()
            ->each(function (MarketAuction $a) use (&$done) {
                if (!MarketAuction::whereKey($a->id)->where('ending_notified', false)->update(['ending_notified' => true])) {
                    return;
                }
                $item = $a->item;
                foreach (MarketBid::where('auction_id', $a->id)->distinct()->pluck('user_id') as $userId) {
                    $leading = $userId === $a->leader_id;
                    MarketPush::send(User::find($userId), 'market_auction_ending', 'Ending in 1 hour',
                        $leading ? '{title}: you are leading at {amount}.' : '{title}: the bid is {amount}. Bid again before it ends.',
                        ['title' => $item->title, 'amount' => getCurrencyFormat($a->current_bid)], ['item_code' => $item->code]);
                }
                $done['ending_soon']++;
            });
        MarketAuction::where('status', MarketAuction::LIVE)->where('ends_at', '<=', now())->limit(100)->get()
            ->each(function (MarketAuction $a) use (&$done) {
                $result = $this->finish($a);
                if ($result) {
                    $done[$result]++;
                }
            });
        // An offer to the next bidder that nobody answered.
        MarketAuction::whereNotNull('offered_to')->where('offer_expires_at', '<=', now())->limit(100)->get()
            ->each(function (MarketAuction $a) use (&$done) {
                $a->update(['offered_to' => null, 'offered_price' => null, 'offer_expires_at' => null]);
                MarketPush::send(User::find($a->seller_id), 'market_auction_offer_expired', 'Your offer was not answered',
                    '{title}: offer it to the next bidder or put it up again.', ['title' => $a->item->title], ['item_code' => $a->item->code]);
                $done['offers_expired']++;
            });
        return $done;
    }

    /** Ends one auction: a winner gets an order, or it did not sell. */
    public function finish(MarketAuction $auction): ?string
    {
        $won = $auction->leader_id && ($auction->reserve_price === null || $auction->current_bid >= $auction->reserve_price);
        $claimed = MarketAuction::whereKey($auction->id)->where('status', MarketAuction::LIVE)->update([
            'status' => $won ? MarketAuction::SOLD : MarketAuction::UNSOLD, 'ended_at' => now(),
            'winner_id' => $won ? $auction->leader_id : null, 'winner_bid' => $won ? $auction->current_bid : null,
        ]);
        if (!$claimed) {
            return null;
        }
        $auction->refresh();
        $item = $auction->item;
        $seller = User::find($auction->seller_id);
        if (!$won) {
            $reason = $auction->bid_count === 0 ? 'No one bid on {title}.' : '{title} did not reach your lowest price.';
            MarketPush::send($seller, 'market_auction_unsold', 'Your auction ended', $reason . ' Put it up again or change the price.',
                ['title' => $item->title], ['item_code' => $item->code]);
            return 'unsold';
        }
        $winner = User::find($auction->winner_id);
        $order = $this->orders->createForAuction($auction, $winner, (float)$auction->winner_bid);
        $auction->update(['order_id' => $order->id]);
        $hours = MarketSettings::all()['auction_pay_hours'];
        MarketPush::send($winner, 'market_auction_won', 'You won the auction', '{title} for {amount}. Arrange payment with the seller within {hours} hours.',
            ['title' => $item->title, 'amount' => getCurrencyFormat($auction->winner_bid), 'hours' => $hours], ['order_id' => $order->id]);
        MarketPush::send($seller, 'market_auction_sold', 'Your auction ended', '{title} sold for {amount}. The winner has {hours} hours to arrange payment.',
            ['title' => $item->title, 'amount' => getCurrencyFormat($auction->winner_bid), 'hours' => $hours], ['order_id' => $order->id]);
        foreach (MarketBid::where('auction_id', $auction->id)->where('user_id', '!=', $auction->winner_id)->distinct()->pluck('user_id') as $userId) {
            MarketPush::send(User::find($userId), 'market_auction_lost', 'Auction ended', '{title} went for {amount}.',
                ['title' => $item->title, 'amount' => getCurrencyFormat($auction->winner_bid)], ['item_code' => $item->code]);
        }
        return 'sold';
    }

    // ------------------------------------------------------------------ after the end

    /** The winner did not arrange payment in time: a strike, their order closes, and the seller can offer it to the next bidder. */
    public function noShow(User $seller, MarketOrder $order): MarketAuction
    {
        $auction = MarketAuction::find($order->auction_id);
        if (!$auction) {
            $this->fail('order', translate('This order is not from an auction.'));
        }
        $this->asSeller($seller, $auction);
        if (!$order->isOpen() || $order->payment_arranged_at) {
            $this->fail('order', translate('This order is not waiting for payment.'));
        }
        if ($order->payment_due_at && $order->payment_due_at->isFuture()) {
            $this->fail('order', translate('The winner still has time to arrange payment.'));
        }
        DB::transaction(function () use ($order, $auction) {
            MarketStrike::create(['user_id' => $order->buyer_id, 'auction_id' => $auction->id, 'reason' => 'no_payment']);
            $this->orders->cancelForAuction($order, 'no_payment');
            $auction->update(['order_id' => null]);
        });
        MarketPush::send(User::find($order->buyer_id), 'market_auction_strike', 'You did not arrange payment',
            'Your win of {title} was cancelled and you got a strike. Too many strikes stop you bidding.',
            ['title' => $auction->item->title], ['item_code' => $auction->item->code]);
        return $auction->fresh();
    }

    /** The highest bidder (each person counted once) after those already tried. */
    public function nextBidder(MarketAuction $auction): ?MarketBid
    {
        $tried = MarketStrike::where('auction_id', $auction->id)->pluck('user_id')->push($auction->winner_id)->filter()->unique()->all();
        return MarketBid::where('auction_id', $auction->id)->whereNotIn('user_id', $tried)->orderByDesc('amount')->first();
    }

    public function offerNext(User $seller, MarketAuction $auction): MarketAuction
    {
        $this->asSeller($seller, $auction);
        if ($auction->order_id && ($o = MarketOrder::find($auction->order_id)) && $o->isOpen()) {
            $this->fail('auction', translate('The winner\'s order is still open.'));
        }
        $next = $this->nextBidder($auction);
        if (!$next) {
            $this->fail('auction', translate('There is no other bidder. Put it up again.'));
        }
        $hours = MarketSettings::all()['auction_offer_hours'];
        $auction->update(['offered_to' => $next->user_id, 'offered_price' => $next->amount, 'offer_expires_at' => now()->addHours($hours)]);
        MarketPush::send(User::find($next->user_id), 'market_auction_offer', 'It can be yours',
            'The winner did not buy {title}. The seller offers it to you at your bid, {amount}. Answer within {hours} hours.',
            ['title' => $auction->item->title, 'amount' => getCurrencyFormat($next->amount), 'hours' => $hours], ['item_code' => $auction->item->code]);
        return $auction->fresh();
    }

    /** The next bidder answers the seller's offer. */
    public function answerOffer(User $user, MarketAuction $auction, bool $accept): ?MarketOrder
    {
        if ($auction->offered_to !== $user->id || !$auction->offer_expires_at?->isFuture()) {
            $this->fail('auction', translate('This offer is no longer open.'));
        }
        $item = $auction->item;
        $seller = User::find($auction->seller_id);
        if (!$accept) {
            // Recorded so the next offer skips them; only "no_payment" counts as a strike.
            MarketStrike::create(['user_id' => $user->id, 'auction_id' => $auction->id, 'reason' => 'declined']);
            $auction->update(['offered_to' => null, 'offered_price' => null, 'offer_expires_at' => null]);
            MarketPush::send($seller, 'market_auction_offer_declined', 'Offer declined', '{title}: offer it to the next bidder or put it up again.',
                ['title' => $item->title], ['item_code' => $item->code]);
            return null;
        }
        $price = (float)$auction->offered_price;
        $order = DB::transaction(function () use ($auction, $user, $price) {
            $auction->update(['winner_id' => $user->id, 'winner_bid' => $price, 'offered_to' => null, 'offered_price' => null, 'offer_expires_at' => null,
                'status' => MarketAuction::SOLD]);
            $order = $this->orders->createForAuction($auction->fresh(), $user, $price);
            $auction->update(['order_id' => $order->id]);
            return $order;
        });
        MarketPush::send($seller, 'market_auction_offer_accepted', 'Offer accepted', '{title}: {buyer} takes it for {amount}.',
            ['title' => $item->title, 'buyer' => trim((string)$user->first_name) ?: translate('A buyer'), 'amount' => getCurrencyFormat($price)], ['order_id' => $order->id]);
        return $order;
    }

    public function activeStrikes(string $userId): int
    {
        return MarketStrike::where('user_id', $userId)->where('reason', 'no_payment')
            ->where('created_at', '>', now()->subDays(MarketSettings::all()['auction_strike_days']))->count();
    }

    /** Phone verified, or the platform does not verify customer phones at all. */
    public function phoneVerified(User $user): bool
    {
        if ($user->phone_verified_at) {
            return true;
        }
        $platformVerifies = (bool)(businessConfig('customer_verification')?->value ?? 0) && function_exists('isOtpEnabled') && isOtpEnabled();
        return !$platformVerifies;
    }

    private function asSeller(User $user, MarketAuction $auction): void
    {
        if ($auction->seller_id !== $user->id) {
            $this->fail('auction', translate('This auction is not yours.'));
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
