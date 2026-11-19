<?php

namespace Modules\UserManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\BusManagement\Entities\BusBooking;
use Modules\HotelManagement\Entities\HotelInquiry;
use Modules\HotelManagement\Entities\HotelStay;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Entities\StoreReview;
use Modules\StoreManagement\Lib\StoreSettings;
use Modules\StoreManagement\Service\StoreOrderService;
use Throwable;

/**
 * "Your activity": everything the customer started and has not finished, or
 * that needs them, across services, for the app's floating button.
 *
 * Each item: kind (the app words it), id (what to open), group (now = needs
 * them, with a deadline; progress = follow along; later = when they like),
 * deadline, at (for sorting) and params for the words. Rides and parcels in
 * progress, the cart, unread driver chats, the tour being taken and the
 * Market, Jobs and Homes counts are added by the app from what it already
 * loads; everything else comes from here.
 */
class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, ['items' => $this->itemsFor($request->user()->id)]));
    }

    /** The customer's activity items (also read by Ask E-Ride, so it can talk about them). */
    public function itemsFor(string $userId): array
    {
        $items = [];
        foreach (['food', 'bus', 'hotel', 'rides', 'idCheck', 'lostItems', 'market', 'homes', 'jobs'] as $service) {
            try {
                $items = array_merge($items, $this->{$service}($userId));
            } catch (Throwable $e) {
                // One service failing never hides the others.
                report($e);
            }
        }
        return $items;
    }

    protected function item(string $kind, string $id, string $group, ?Carbon $deadline, ?Carbon $at, array $params = []): array
    {
        return [
            'kind' => $kind,
            'id' => $id,
            'group' => $group,
            'deadline' => $deadline?->toIso8601String(),
            'at' => ($at ?? now())->toIso8601String(),
            'params' => $params,
        ];
    }

    protected function food(string $userId): array
    {
        if (!class_exists(StoreOrder::class)) {
            return [];
        }
        $respond = StoreSettings::all()['partner_response_minutes'];
        $out = [];
        $orders = StoreOrder::with('store.parent')->where('customer_id', $userId)
            ->where(fn($q) => $q->whereIn('status', [StoreOrder::AWAITING_CONFIRMATION, StoreOrder::CHANGES_PROPOSED, StoreOrder::PLACED,
                StoreOrder::ACCEPTED, StoreOrder::RIDER_REQUESTED, StoreOrder::PICKED_UP])
                ->orWhere(fn($w) => $w->where('status', StoreOrder::AWAITING_PAYMENT)->whereNotNull('confirmed_at')))
            ->latest()->limit(10)->get();
        foreach ($orders as $o) {
            $p = ['store' => $o->store?->displayName() ?? '', 'total' => $o->total, 'ref' => $o->ref_id];
            $out[] = match ($o->status) {
                StoreOrder::AWAITING_CONFIRMATION, StoreOrder::PLACED => $this->item('food_waiting', $o->id, 'progress', $o->placed_at?->copy()->addMinutes($respond), $o->placed_at, $p),
                StoreOrder::CHANGES_PROPOSED => $this->item('food_changes', $o->id, 'now', $o->proposed_at?->copy()->addMinutes(StoreOrderService::ANSWER_MINUTES), $o->proposed_at, $p),
                StoreOrder::AWAITING_PAYMENT => $this->item('food_pay', $o->id, 'now', $o->pay_by, $o->confirmed_at, $p),
                StoreOrder::ACCEPTED => $this->item('food_preparing', $o->id, 'progress', null, $o->accepted_at, $p + ['time' => $o->ready_at?->toIso8601String()]),
                StoreOrder::RIDER_REQUESTED => $this->item('food_rider', $o->id, 'progress', null, $o->rider_requested_at, $p),
                default => $this->item('food_on_the_way', $o->id, 'progress', null, $o->picked_up_at, $p),
            };
        }
        // A delivery that did not happen: the customer should look (refund or another try).
        StoreOrder::with('store.parent')->where('customer_id', $userId)->where('status', StoreOrder::DELIVERY_FAILED)
            ->where('updated_at', '>', now()->subDays(2))->latest('updated_at')->limit(3)->get()
            ->each(function (StoreOrder $o) use (&$out) {
                $out[] = $this->item('food_failed', $o->id, 'now', null, $o->updated_at, ['store' => $o->store?->displayName() ?? '']);
            });
        // Delivered and not rated: a few recent ones.
        StoreOrder::with('store.parent')->where('customer_id', $userId)->where('status', StoreOrder::DELIVERED)
            ->where('delivered_at', '>', now()->subDays(StoreReview::MAX_DAYS_TO_REVIEW))
            ->whereDoesntHave('review')->latest('delivered_at')->limit(3)->get()
            ->each(function (StoreOrder $o) use (&$out) {
                $out[] = $this->item('food_rate', $o->id, 'later', null, $o->delivered_at, ['store' => $o->store?->displayName() ?? '']);
            });
        return $out;
    }

    protected function bus(string $userId): array
    {
        if (!class_exists(BusBooking::class)) {
            return [];
        }
        $out = [];
        BusBooking::with(['agency', 'departure.destination'])->where('customer_id', $userId)
            ->where(fn($q) => $q->whereIn('status', [BusBooking::REQUESTED, BusBooking::ACCEPTED])
                ->orWhere(fn($w) => $w->where('status', BusBooking::PAID)->whereHas('departure', fn($d) => $d->whereBetween('date', [today()->toDateString(), today()->addDay()->toDateString()])
                    ->whereNotIn('status', ['departed', 'cancelled']))))
            ->latest()->limit(10)->get()
            ->each(function (BusBooking $b) use (&$out) {
                $dep = $b->departure;
                $p = ['agency' => $b->agency?->name ?? '', 'city' => $dep?->destination?->city ?? '', 'total' => $b->amount,
                    'time' => $dep ? $dep->leavesAt()->toIso8601String() : null];
                $out[] = match ($b->status) {
                    BusBooking::REQUESTED => $this->item('bus_requested', $b->id, 'progress', $b->respond_by, $b->created_at, $p),
                    BusBooking::ACCEPTED => $this->item('bus_pay', $b->id, 'now', $b->pay_by, $b->accepted_at, $p),
                    default => $this->item('bus_ticket', $b->id, 'progress', null, $dep ? $dep->leavesAt() : null, $p),
                };
            });
        // Paid tickets: further ahead (when they like), at risk of not leaving, or moved by a lockdown.
        BusBooking::with(['agency', 'departure.destination'])->where('customer_id', $userId)->where('status', BusBooking::PAID)
            ->whereHas('departure', fn($d) => $d->where('date', '>=', today()->toDateString())->whereNotIn('status', ['departed', 'cancelled']))
            ->latest()->limit(15)->get()
            ->each(function (BusBooking $b) use (&$out) {
                $dep = $b->departure;
                if (!$dep) {
                    return;
                }
                $p = ['agency' => $b->agency?->name ?? '', 'city' => $dep->destination?->city ?? '', 'time' => $dep->leavesAt()->toIso8601String()];
                $closure = $dep->closure_id && $dep->isOpen() ? \Modules\BusManagement\Entities\BusClosure::find($dep->closure_id) : null;
                if ($closure) {
                    $out[] = $this->item('bus_at_risk', $b->id, 'now', $closure->decide_by, $dep->leavesAt(), $p);
                } elseif ($b->moved_at && !$b->move_kept_at) {
                    $out[] = $this->item('bus_moved', $b->id, 'now', null, $b->moved_at, $p);
                } elseif ($dep->date->gt(today()->addDay())) {
                    $out[] = $this->item('bus_upcoming', $b->id, 'later', null, $dep->leavesAt(), $p);
                }
            });
        // Money the agency owes back.
        BusBooking::with('agency')->where('customer_id', $userId)->where('refund_status', 'due')->latest()->limit(5)->get()
            ->each(function (BusBooking $b) use (&$out) {
                $amount = (float)$b->refund_owed > 0 ? (float)$b->refund_owed : (float)$b->refund_amount;
                $out[] = $this->item('bus_refund', $b->id, 'progress', $b->refund_due_by, $b->updated_at, ['agency' => $b->agency?->name ?? '', 'total' => $amount]);
            });
        return $out;
    }

    protected function hotel(string $userId): array
    {
        if (!class_exists(HotelInquiry::class)) {
            return [];
        }
        $out = [];
        HotelInquiry::with('hotel')->where('customer_id', $userId)->whereIn('status', HotelInquiry::ACTIVE)
            ->latest('last_message_at')->limit(10)->get()
            ->each(function (HotelInquiry $q) use (&$out) {
                $p = ['hotel' => $q->hotel?->name ?? '', 'count' => (int)$q->unread_guest];
                if ($q->unread_guest > 0) {
                    $out[] = $this->item('hotel_reply', $q->id, 'now', null, $q->last_message_at, $p);
                } elseif ($q->status === HotelInquiry::KEPT) {
                    $until = $q->stays()->where('status', HotelStay::KEPT)->min('kept_until');
                    $out[] = $this->item('hotel_kept', $q->id, 'progress', $until ? Carbon::parse($until) : null, $q->last_message_at, $p);
                } elseif ($q->status === HotelInquiry::ANSWERED) {
                    $out[] = $this->item('hotel_answered', $q->id, 'progress', null, $q->last_message_at, $p);
                } elseif ($q->status === HotelInquiry::OPEN) {
                    $out[] = $this->item('hotel_waiting', $q->id, 'progress', null, $q->created_at, $p);
                }
            });
        return $out;
    }

    /** Rides: drivers' offers waiting on a ride request, and finished rides not rated yet. */
    protected function rides(string $userId): array
    {
        $out = [];
        $pending = \Modules\TripManagement\Entities\TripRequest::where('customer_id', $userId)->where('current_status', 'pending')
            ->where('created_at', '>', now()->subHours(3))->latest()->limit(5)->get(['id', 'created_at']);
        foreach ($pending as $trip) {
            $offers = \Modules\TripManagement\Entities\FareBidding::where('trip_request_id', $trip->id)->where('is_ignored', 0)->count();
            if ($offers > 0) {
                $out[] = $this->item('ride_offers', $trip->id, 'now', null, $trip->created_at, ['count' => $offers]);
            }
        }
        // Paid, finished in the last two days, no stars for the driver yet.
        $done = \Modules\TripManagement\Entities\TripRequest::with('driver:id,first_name')->where('customer_id', $userId)->where('current_status', 'completed')
            ->where('payment_status', 'paid')->whereNotNull('driver_id')->where('updated_at', '>', now()->subDays(2))
            ->latest('updated_at')->limit(5)->get(['id', 'driver_id', 'type', 'updated_at']);
        if ($done->isNotEmpty()) {
            $rated = \Modules\ReviewModule\Entities\Review::whereIn('trip_request_id', $done->pluck('id'))->where('given_by', $userId)->pluck('trip_request_id')->flip();
            foreach ($done->take(2) as $trip) {
                if (!isset($rated[$trip->id])) {
                    $out[] = $this->item('ride_rate', $trip->id, 'later', null, $trip->updated_at, ['driver' => $trip->driver?->first_name ?? '', 'parcel' => $trip->type === 'parcel']);
                }
            }
        }
        return $out;
    }

    /** E-Ride ID: waiting for a check, or refused (the reason and a new try). */
    protected function idCheck(string $userId): array
    {
        $r = \Modules\UserManagement\Entities\IdVerification::where('user_id', $userId)->where('status', '!=', 'ended')->latest()->first();
        if (!$r) {
            return [];
        }
        if ($r->status === 'pending') {
            return [$this->item('id_pending', $r->id, 'progress', null, $r->created_at)];
        }
        // A refusal stays useful for a week; after that it is the person's choice to try again.
        if ($r->status === 'refused' && $r->updated_at?->gt(now()->subDays(7))) {
            return [$this->item('id_refused', $r->id, 'now', null, $r->updated_at, ['reason' => $r->refuse_reason])];
        }
        return [];
    }

    /** Lost items: waiting for the driver, or the driver answered. */
    protected function lostItems(string $userId): array
    {
        $out = [];
        \Modules\TripManagement\Entities\LostItem::where('customer_id', $userId)->whereIn('status', ['reported', 'found', 'not_found'])
            ->latest('updated_at')->limit(5)->get()
            ->each(function ($l) use (&$out) {
                $kind = match ($l->status) {
                    'found' => 'lost_found',
                    'not_found' => 'lost_not_found',
                    default => 'lost_waiting',
                };
                $out[] = $this->item($kind, $l->trip_request_id, $kind === 'lost_waiting' ? 'progress' : 'now', null, $l->answered_at ?? $l->created_at,
                    ['item' => mb_substr((string)$l->description, 0, 60)]);
            });
        return $out;
    }

    /**
     * E-Ride Market, every role: buyer, seller, service client, provider,
     * bidder and auction seller, the shop owner, and unread chats. (The cart
     * is on the phone; the app adds it.)
     */
    protected function market(string $userId): array
    {
        if (!class_exists(\Modules\MarketManagement\Entities\MarketOrder::class)) {
            return [];
        }
        $M = '\\Modules\\MarketManagement\\Entities\\';
        $out = [];
        $order = $M . 'MarketOrder';
        $booking = $M . 'MarketBooking';

        // ---- as the buyer
        $order::with('shop:id,name')->where('buyer_id', $userId)->whereIn('status', ['requested', 'changes', 'confirmed', 'ready', 'on_the_way'])
            ->latest('updated_at')->limit(8)->get()
            ->each(function ($o) use (&$out) {
                $p = ['shop' => $o->shop?->name ?? '', 'status' => $o->status, 'total' => (float)($o->confirmed_total ?? $o->requested_total)];
                if ($o->status === 'requested') {
                    $out[] = $this->item('market_order_waiting', $o->id, 'progress', $o->respond_by, $o->created_at, $p);
                } elseif ($o->status === 'changes') {
                    $out[] = $this->item('market_order_changes', $o->id, 'now', $o->agree_by, $o->updated_at, $p);
                } elseif ($o->auction_id && $o->payment_due_at && !$o->payment_arranged_at && $o->status === 'confirmed') {
                    // Won an auction: arrange the payment with the seller in time.
                    $out[] = $this->item('market_auction_pay', $o->id, 'now', $o->payment_due_at, $o->created_at, $p);
                } else {
                    $out[] = $this->item('market_order', $o->id, $o->status === 'ready' ? 'now' : 'progress', null, $o->updated_at, $p);
                }
            });
        $order::with('shop:id,name')->where('buyer_id', $userId)->where('status', 'completed')->where('completed_at', '>', now()->subDays(7))
            ->whereDoesntHave('review')->latest('completed_at')->limit(2)->get()
            ->each(function ($o) use (&$out) {
                $out[] = $this->item('market_order_rate', $o->id, 'later', null, $o->completed_at, ['shop' => $o->shop?->name ?? '']);
            });

        // ---- as the seller
        $order::with('buyer:id,first_name')->where('seller_id', $userId)->whereIn('status', ['requested', 'confirmed', 'ready', 'on_the_way'])
            ->latest('updated_at')->limit(8)->get()
            ->each(function ($o) use (&$out) {
                $p = ['buyer' => $o->buyer?->first_name ?? '', 'total' => (float)($o->confirmed_total ?? $o->requested_total), 'status' => $o->status];
                $out[] = match ($o->status) {
                    'requested' => $this->item('market_sell_new', $o->id, 'now', $o->respond_by, $o->created_at, $p),
                    // An auction sold: wait for the payment; past the time, report a no-show.
                    'confirmed' => $o->auction_id && $o->payment_due_at && !$o->payment_arranged_at
                        ? $this->item('market_sell_wait_pay', $o->id, $o->payment_due_at->isPast() ? 'now' : 'progress', $o->payment_due_at, $o->created_at, $p)
                        : $this->item('market_sell_prepare', $o->id, 'now', null, $o->confirmed_at ?? $o->updated_at, $p),
                    default => $this->item('market_sell_handover', $o->id, 'progress', null, $o->updated_at, $p),
                };
            });

        // ---- services, as the client
        $booking::with('item:id,title')->where('client_id', $userId)->whereIn('status', ['requested', 'quote_requested', 'suggested', 'quoted', 'confirmed'])
            ->latest('updated_at')->limit(8)->get()
            ->each(function ($b) use (&$out) {
                $p = ['title' => $b->item?->title ?? '', 'time' => ($b->suggested_starts_at ?? $b->starts_at)?->toIso8601String(), 'total' => (float)($b->quote_price ?? $b->price)];
                if (in_array($b->status, ['suggested', 'quoted'], true)) {
                    $out[] = $this->item($b->status === 'quoted' ? 'market_quote_answer' : 'market_time_answer', $b->id, 'now',
                        $b->status === 'quoted' ? $b->quote_expires_at : $b->respond_by, $b->updated_at, $p);
                } elseif (in_array($b->status, ['requested', 'quote_requested'], true)) {
                    $out[] = $this->item('market_booking_waiting', $b->id, 'progress', $b->respond_by, $b->created_at, $p);
                } elseif ($b->starts_at && $b->starts_at->between(now()->subHours(3), now()->addDays(7))) {
                    $out[] = $this->item('market_booking', $b->id, 'progress', null, $b->starts_at, $p);
                }
            });
        $booking::with('item:id,title')->where('client_id', $userId)->where('status', 'completed')->where('completed_at', '>', now()->subDays(7))
            ->whereDoesntHave('review')->latest('completed_at')->limit(2)->get()
            ->each(function ($b) use (&$out) {
                $out[] = $this->item('market_booking_rate', $b->id, 'later', null, $b->completed_at, ['title' => $b->item?->title ?? '']);
            });

        // ---- services, as the provider
        $booking::with('item:id,title')->where('provider_id', $userId)->whereIn('status', ['requested', 'quote_requested', 'confirmed'])
            ->latest('updated_at')->limit(8)->get()
            ->each(function ($b) use (&$out) {
                $p = ['title' => $b->item?->title ?? '', 'time' => $b->starts_at?->toIso8601String()];
                if ($b->status !== 'confirmed') {
                    $out[] = $this->item($b->status === 'quote_requested' ? 'market_quote_request' : 'market_provider_request', $b->id, 'now', $b->respond_by, $b->created_at, $p);
                } elseif ($b->starts_at && $b->starts_at->between(now()->subHours(3), now()->addDays(2))) {
                    $out[] = $this->item('market_provider_upcoming', $b->id, 'progress', null, $b->starts_at, $p);
                }
            });

        // ---- auctions
        $auction = $M . 'MarketAuction';
        // A second chance: the seller offers the item to this bidder.
        $auction::with('item:id,code,title')->where('offered_to', $userId)->where('offer_expires_at', '>', now())->limit(3)->get()
            ->each(function ($a) use (&$out) {
                $out[] = $this->item('market_auction_offer', $a->item?->code ?? '', 'now', $a->offer_expires_at, $a->updated_at,
                    ['title' => $a->item?->title ?? '', 'total' => (float)$a->offered_price]);
            });
        // Live auctions this person bid on: leading, or outbid.
        $bidOn = ($M . 'MarketBid')::where('user_id', $userId)->distinct()->pluck('auction_id');
        if ($bidOn->isNotEmpty()) {
            $auction::with('item:id,code,title')->whereIn('id', $bidOn)->where('status', 'live')->where('ends_at', '>', now())
                ->orderBy('ends_at')->limit(5)->get()
                ->each(function ($a) use (&$out, $userId) {
                    $p = ['title' => $a->item?->title ?? '', 'total' => (float)$a->current_bid, 'time' => $a->ends_at?->toIso8601String()];
                    $leading = $a->leader_id === $userId;
                    $out[] = $this->item($leading ? 'market_auction_leading' : 'market_auction_outbid', $a->item?->code ?? '', $leading ? 'progress' : 'now', $a->ends_at, $a->ends_at, $p);
                });
        }
        // The seller's ended auctions with nobody buying: offer to the next bidder or relist.
        $auction::with('item:id,code,title')->where('seller_id', $userId)->where('status', 'unsold')->where('ended_at', '>', now()->subDays(3))
            ->whereNull('offered_to')->limit(3)->get()
            ->each(function ($a) use (&$out) {
                $out[] = $this->item('market_auction_unsold', $a->item?->code ?? '', 'now', null, $a->ended_at, ['title' => $a->item?->title ?? '']);
            });
        // Live auctions on the seller's own items.
        $auction::with('item:id,code,title')->where('seller_id', $userId)->where('status', 'live')->where('ends_at', '>', now())
            ->orderBy('ends_at')->limit(3)->get()
            ->each(function ($a) use (&$out) {
                $out[] = $this->item('market_auction_selling', $a->item?->code ?? '', 'progress', $a->ends_at, $a->ends_at,
                    ['title' => $a->item?->title ?? '', 'total' => (float)$a->current_bid, 'count' => (int)$a->bid_count]);
            });

        // ---- the shop
        $shop = ($M . 'MarketShop')::where('user_id', $userId)->first();
        if ($shop) {
            if ($shop->status === 'closed') {
                $out[] = $this->item('market_shop_closed', $shop->slug, 'now', null, $shop->closed_at, ['shop' => $shop->name, 'reason' => $shop->closed_reason]);
            } elseif ($shop->verification_requested_at && !$shop->verified_at) {
                $out[] = $this->item('market_shop_checking', $shop->slug, 'progress', null, $shop->verification_requested_at, ['shop' => $shop->name]);
            }
            $stuck = ($M . 'MarketItem')::where('shop_id', $shop->id)->whereIn('status', ['hidden', 'blocked'])->count();
            if ($stuck > 0) {
                $out[] = $this->item('market_items_hidden', $shop->slug, 'now', null, null, ['count' => $stuck]);
            }
            $soldOut = ($M . 'MarketItem')::where('shop_id', $shop->id)->where('status', 'sold_out')->count();
            if ($soldOut > 0) {
                $out[] = $this->item('market_items_sold_out', $shop->slug, 'later', null, null, ['count' => $soldOut]);
            }
        }

        // ---- chats (buyer and seller sides)
        $user = \Modules\UserManagement\Entities\User::find($userId);
        $unread = $user ? app(\Modules\MarketManagement\Service\MarketChatService::class)->unread($user) : 0;
        if ($unread > 0) {
            $out[] = $this->item('market_chat_unread', 'market_chats', 'now', null, null, ['count' => $unread]);
        }
        return $out;
    }

    /**
     * Homes & spaces, both sides. As the one looking: a new visit time to
     * answer, how a visit went, a reservation to pay, visits coming up, a home
     * reserved, a problem. As the owner: visit requests, someone taking the
     * place, a reservation to accept, changes E-Ride asked for, a place to confirm.
     */
    protected function homes(string $userId): array
    {
        if (!class_exists(\Modules\PlaceManagement\Entities\ListingVisit::class)) {
            return [];
        }
        $V = \Modules\PlaceManagement\Entities\ListingVisit::class;
        $R = \Modules\PlaceManagement\Entities\ListingReservation::class;
        $L = \Modules\PlaceManagement\Entities\Listing::class;
        $out = [];
        $title = fn($x) => ['title' => $x->listing?->title ?? '', 'time' => ($x->proposed_date ?? $x->visit_date)?->toIso8601String()];

        // ---- looking for a place
        $V::with('listing:id,title')->where('seeker_id', $userId)->whereIn('status', ['proposed', 'accepted', 'done'])
            ->latest('updated_at')->limit(8)->get()
            ->each(function ($v) use (&$out, $title) {
                if ($v->status === 'proposed') {
                    $out[] = $this->item('home_visit_proposed', $v->id, 'now', null, $v->updated_at, $title($v));
                } elseif ($v->outcome === null && $v->visit_date && $v->visit_date->lte(today()) && $v->visit_date->gte(today()->subDays(14))) {
                    $out[] = $this->item('home_visit_outcome', $v->id, 'now', null, $v->visit_date, $title($v));
                } elseif ($v->status === 'accepted' && $v->visit_date && $v->visit_date->gte(today())) {
                    $out[] = $this->item('home_visit', $v->id, 'progress', null, $v->visit_date, $title($v) + ['window' => $v->time_window]);
                }
            });
        $R::with('listing:id,title')->where('seeker_id', $userId)->whereIn('status', ['awaiting', 'held', 'reserved', 'problem'])
            ->latest('updated_at')->limit(5)->get()
            ->each(function ($r) use (&$out) {
                $p = ['title' => $r->listing?->title ?? '', 'time' => $r->move_in_date?->toIso8601String(), 'total' => (float)$r->amount];
                if ($r->status === 'awaiting' && (!$r->pay_due_at || $r->pay_due_at->isFuture())) {
                    $out[] = $this->item('home_reservation_pay', $r->id, 'now', $r->pay_due_at, $r->created_at, $p);
                } elseif ($r->status === 'held') {
                    $out[] = $this->item('home_reservation_waiting', $r->id, 'progress', $r->accept_due_at, $r->paid_at ?? $r->updated_at, $p);
                } elseif ($r->status === 'problem') {
                    $out[] = $this->item('home_problem', $r->id, 'now', null, $r->updated_at, $p);
                } elseif ($r->status === 'reserved') {
                    $out[] = $this->item('home_reserved', $r->id, 'progress', null, $r->move_in_date ?? $r->updated_at, $p);
                }
            });

        // ---- the owner
        $V::with('listing:id,title,status')->where('owner_id', $userId)
            ->where(fn($q) => $q->where('status', 'requested')
                ->orWhere(fn($t) => $t->where('taking', true)->whereIn('status', ['accepted', 'done'])->whereHas('listing', fn($l) => $l->where('status', 'live'))))
            ->latest('updated_at')->limit(8)->get()
            ->each(function ($v) use (&$out, $title) {
                $out[] = $v->status === 'requested'
                    ? $this->item('home_visit_request', $v->id, 'now', null, $v->created_at, $title($v))
                    : $this->item('home_taking', $v->id, 'now', null, $v->updated_at, $title($v));
            });
        $R::with('listing:id,title')->where('owner_id', $userId)->where('status', 'held')->limit(5)->get()
            ->each(function ($r) use (&$out) {
                $out[] = $this->item('home_reservation_accept', $r->id, 'now', $r->accept_due_at, $r->paid_at ?? $r->updated_at,
                    ['title' => $r->listing?->title ?? '', 'total' => (float)$r->amount]);
            });
        $L::where('owner_id', $userId)
            ->where(fn($q) => $q->where('status', 'changes')->orWhere(fn($l) => $l->where('status', 'live')->where('reminder_stage', '>=', 2)))
            ->limit(5)->get(['id', 'title', 'status', 'updated_at'])
            ->each(function ($l) use (&$out) {
                $out[] = $this->item($l->status === 'changes' ? 'home_listing_changes' : 'home_listing_confirm', $l->id, 'now', null, $l->updated_at, ['title' => $l->title]);
            });
        return $out;
    }

    /**
     * Jobs, both sides. Looking for work: the next step from an employer,
     * a hire to confirm, applications waiting. Hiring: new applicants per job,
     * a job post sent back for changes.
     */
    protected function jobs(string $userId): array
    {
        if (!class_exists(\Modules\MarketManagement\Entities\JobPost::class)) {
            return [];
        }
        $A = \Modules\MarketManagement\Entities\JobApplication::class;
        $P = \Modules\MarketManagement\Entities\JobPost::class;
        $out = [];
        $A::with('job:id,code,title')->where('user_id', $userId)
            ->where(fn($q) => $q->where(fn($n) => $n->where('status', 'next_step')->whereNull('next_step_read_at'))
                ->orWhere(fn($h) => $h->where('status', 'hired')->whereNull('hire_confirmed_at')))
            ->latest('updated_at')->limit(5)->get()
            ->each(function ($a) use (&$out) {
                $p = ['title' => $a->job?->title ?? '', 'time' => $a->next_step_at?->toIso8601String()];
                $out[] = $a->status === 'hired'
                    ? $this->item('job_hired', $a->id, 'now', null, $a->updated_at, $p)
                    : $this->item('job_next_step', $a->id, 'now', null, $a->updated_at, $p);
            });
        $open = $A::where('user_id', $userId)->whereIn('status', $A::ACTIVE)->count();
        if ($open > 0) {
            $out[] = $this->item('job_applied', 'applications', 'progress', null, null, ['count' => $open]);
        }
        $employerJobs = $P::whereHas('employer', fn($e) => $e->where('user_id', $userId));
        (clone $employerJobs)->where('status', 'sent_back')->latest('updated_at')->limit(3)->get(['id', 'code', 'title', 'updated_at'])
            ->each(function ($j) use (&$out) {
                $out[] = $this->item('job_sent_back', $j->code, 'now', null, $j->updated_at, ['title' => $j->title]);
            });
        (clone $employerJobs)->withCount(['applications as new_count' => fn($q) => $q->where('status', 'sent')])
            ->having('new_count', '>', 0)->limit(5)->get(['id', 'code', 'title'])
            ->each(function ($j) use (&$out) {
                $out[] = $this->item('job_applicants', $j->code, 'now', null, null, ['title' => $j->title, 'count' => (int)$j->new_count]);
            });
        return $out;
    }
}
