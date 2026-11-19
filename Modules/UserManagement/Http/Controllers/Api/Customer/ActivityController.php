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

    /** Market: orders on their way to the buyer, and booked services coming up (what the Market count leaves out). */
    protected function market(string $userId): array
    {
        if (!class_exists(\Modules\MarketManagement\Entities\MarketOrder::class)) {
            return [];
        }
        $out = [];
        \Modules\MarketManagement\Entities\MarketOrder::with('shop:id,name')->where('buyer_id', $userId)
            ->whereIn('status', ['confirmed', 'ready', 'on_the_way'])->latest('updated_at')->limit(5)->get()
            ->each(function ($o) use (&$out) {
                $out[] = $this->item('market_order', $o->id, 'progress', null, $o->updated_at, ['shop' => $o->shop?->name ?? '', 'status' => $o->status]);
            });
        \Modules\MarketManagement\Entities\MarketBooking::with('item:id,title')->where('client_id', $userId)->where('status', 'confirmed')
            ->whereNotNull('starts_at')->whereBetween('starts_at', [now()->subHours(3), now()->addDays(7)])->orderBy('starts_at')->limit(5)->get()
            ->each(function ($b) use (&$out) {
                $out[] = $this->item('market_booking', $b->id, 'progress', null, $b->starts_at, ['title' => $b->item?->title ?? '', 'time' => $b->starts_at?->toIso8601String()]);
            });
        return $out;
    }

    /** Homes, as the one looking: visits coming up, a place reserved, a reservation with a problem. */
    protected function homes(string $userId): array
    {
        if (!class_exists(\Modules\PlaceManagement\Entities\ListingVisit::class)) {
            return [];
        }
        $out = [];
        \Modules\PlaceManagement\Entities\ListingVisit::with('listing:id,title')->where('seeker_id', $userId)->where('status', 'accepted')
            ->whereDate('visit_date', '>=', today())->orderBy('visit_date')->limit(5)->get()
            ->each(function ($v) use (&$out) {
                $out[] = $this->item('home_visit', $v->id, 'progress', null, $v->visit_date, ['title' => $v->listing?->title ?? '', 'time' => $v->visit_date?->toIso8601String(), 'window' => $v->time_window]);
            });
        \Modules\PlaceManagement\Entities\ListingReservation::with('listing:id,title')->where('seeker_id', $userId)->whereIn('status', ['reserved', 'problem'])
            ->latest('updated_at')->limit(5)->get()
            ->each(function ($r) use (&$out) {
                $p = ['title' => $r->listing?->title ?? '', 'time' => $r->move_in_date?->toIso8601String()];
                $out[] = $r->status === 'problem'
                    ? $this->item('home_problem', $r->id, 'now', null, $r->updated_at, $p)
                    : $this->item('home_reserved', $r->id, 'progress', null, $r->move_in_date ?? $r->updated_at, $p);
            });
        return $out;
    }

    /** Jobs, as an employer: a job post the team sent back for changes. */
    protected function jobs(string $userId): array
    {
        if (!class_exists(\Modules\MarketManagement\Entities\JobPost::class)) {
            return [];
        }
        $out = [];
        \Modules\MarketManagement\Entities\JobPost::whereHas('employer', fn($e) => $e->where('user_id', $userId))->where('status', 'sent_back')
            ->latest('updated_at')->limit(3)->get(['id', 'code', 'title', 'updated_at'])
            ->each(function ($j) use (&$out) {
                $out[] = $this->item('job_sent_back', $j->code, 'now', null, $j->updated_at, ['title' => $j->title]);
            });
        return $out;
    }
}
