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
 * deadline, at (for sorting) and params for the words. Rides, parcels, the
 * cart, Market, Jobs and Homes are added by the app from what it already loads.
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
        foreach (['food', 'bus', 'hotel'] as $service) {
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
                } elseif ($q->status === HotelInquiry::OPEN) {
                    $out[] = $this->item('hotel_waiting', $q->id, 'progress', null, $q->created_at, $p);
                }
            });
        return $out;
    }
}
