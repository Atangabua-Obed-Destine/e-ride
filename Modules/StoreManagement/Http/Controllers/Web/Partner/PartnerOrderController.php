<?php

namespace Modules\StoreManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Lib\StoreSettings;
use Modules\StoreManagement\Service\Interfaces\StoreOrderServiceInterface;

/**
 * The live orders board. The page polls feed() every few seconds; actions
 * answer JSON so the board updates without a reload.
 */
class PartnerOrderController extends Controller
{
    public function __construct(protected StoreOrderServiceInterface $orders)
    {
    }

    protected function store(Request $request): Store
    {
        return $request->attributes->get('partnerStore');
    }

    /** Kitchens on this board: a branch its own, the owner (main store) every branch. */
    protected function kitchenIds(Request $request): array
    {
        $store = $this->store($request);
        return $store->isBranch() ? [$store->id] : $store->kitchens()->pluck('id')->all();
    }

    /** Only this partner's orders, never another store's. */
    protected function own(Request $request, StoreOrder $order): StoreOrder
    {
        abort_unless(in_array($order->store_id, $this->kitchenIds($request), true), 404);
        return $order;
    }

    public function board(Request $request): View
    {
        $staff = $request->attributes->get('partnerStaff');
        return view('storemanagement::partner.orders', [
            'store' => $this->store($request),
            'responseMinutes' => StoreSettings::all()['partner_response_minutes'],
            'todayMenu' => $this->todayMenu($request),
            'canToggle' => !$staff || \Modules\StoreManagement\Lib\StoreTeam::allows($staff, 'partner.menu.item.availability'),
            'canEditMenu' => !$staff || \Modules\StoreManagement\Lib\StoreTeam::allows($staff, 'partner.menu'),
        ]);
    }

    /**
     * What customers can order today, per kitchen on this board: on sale now, later today
     * (with the time it starts), sold out today, and how many dishes are not served today.
     */
    protected function todayMenu(Request $request): array
    {
        $store = $this->store($request);
        $brand = $store->brand();
        $items = $brand->items()->with('category')->orderBy('sort')->orderBy('name')->get();
        $kitchens = $store->isBranch() ? collect([$store]) : $store->kitchens();
        $now = now();
        $out = [];
        foreach ($kitchens as $kitchen) {
            $states = $kitchen->stateMap();
            $groups = ['now' => [], 'later' => [], 'sold_out' => []];
            $notToday = 0;
            foreach ($items as $i) {
                $state = $i->menuState($now, $states->get($i->id));
                $row = [
                    'id' => $i->id,
                    'name' => $i->name,
                    'price' => getCurrencyFormat((float)$i->price),
                    'image' => $i->imageUrl(),
                    'from' => $i->effectiveWindow()[0] ?? null,
                    'until' => $i->effectiveWindow()[1] ?? null,
                ];
                match ($state) {
                    \Modules\StoreManagement\Entities\StoreItem::AVAILABLE => $groups['now'][] = $row,
                    \Modules\StoreManagement\Entities\StoreItem::LATER => $groups['later'][] = $row,
                    \Modules\StoreManagement\Entities\StoreItem::SOLD_OUT => $groups['sold_out'][] = $row,
                    \Modules\StoreManagement\Entities\StoreItem::NOT_TODAY => $notToday++,
                    default => null,
                };
            }
            $out[] = [
                'id' => $kitchen->id,
                'name' => $kitchen->branch_name ?: $kitchen->name,
                'groups' => $groups,
                'not_today' => $notToday,
            ];
        }
        return $out;
    }

    public function feed(Request $request): JsonResponse
    {
        $store = $this->store($request);
        $orders = StoreOrder::with(['items', 'store', 'trip.driver', 'trip.vehicle'])
            ->whereIn('store_id', $this->kitchenIds($request))
            ->where(function ($q) {
                $q->whereIn('status', StoreOrder::ACTIVE)
                    // Confirmed, waiting for the customer to pay.
                    ->orWhere(fn($w) => $w->where('status', StoreOrder::AWAITING_PAYMENT)->whereNotNull('confirmed_at'))
                    ->orWhere(fn($w) => $w->whereIn('status', StoreOrder::FINAL)->where('updated_at', '>=', now()->subHours(3)))
                    // Not delivered: on the board while the rider brings it back (with the return code).
                    ->orWhere(fn($w) => $w->where('status', StoreOrder::DELIVERY_FAILED)->where('updated_at', '>=', now()->subDay())
                        ->whereHas('trip', fn($t) => $t->where('current_status', RETURNING)));
            })
            ->orderBy('placed_at')
            ->limit(100)
            ->get();

        $responseMinutes = StoreSettings::all()['partner_response_minutes'];
        return response()->json([
            'server_time' => now()->toIso8601String(),
            'store' => ['is_open' => $store->is_open, 'open_now' => $store->isOpenNow()],
            'orders' => $orders->map(fn(StoreOrder $o) => $this->present($o, $responseMinutes))->values(),
        ]);
    }

    public function history(Request $request): View
    {
        $store = $this->store($request);
        $orders = StoreOrder::with(['items', 'review', 'store'])
            ->whereIn('store_id', $this->kitchenIds($request))
            ->where('status', '!=', StoreOrder::AWAITING_PAYMENT)
            ->latest('placed_at')
            ->paginate(25);
        $rating = $store->brandRatingSummary();
        return view('storemanagement::partner.history', compact('orders', 'rating'));
    }

    /** Confirm a new order (the customer then pays), or accept an older paid one. */
    public function accept(Request $request, StoreOrder $order): JsonResponse
    {
        // by_store: "Our staff will bring it" (a customer close by), instead of an E-Ride rider.
        $data = $request->validate(['prep_minutes' => 'required|integer|min:5|max:180', 'by_store' => 'nullable|boolean']);
        $before = $this->own($request, $order)->status;
        try {
            $order = $this->orders->accept($order, (int)$data['prep_minutes'], $request->boolean('by_store'));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return $this->answer($order, in_array($before, [StoreOrder::AWAITING_CONFIRMATION, StoreOrder::PLACED], true) ? $before : StoreOrder::AWAITING_CONFIRMATION, $before);
    }

    /** Decline a new order, or cancel one before it is paid. */
    public function reject(Request $request, StoreOrder $order): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:200']);
        $before = $this->own($request, $order)->status;
        try {
            $order = $this->orders->reject($order, $data['reason']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        // Declined before payment, or a paid order cancelled because no rider came.
        $ended = in_array($order->status, [StoreOrder::REJECTED, StoreOrder::CANCELLED], true);
        return response()->json([
            'ok' => $ended,
            'message' => $ended ? null : translate('This order changed in the meantime. The board has been refreshed.'),
            'order' => $this->present($order->fresh(['items', 'store', 'trip.driver', 'trip.vehicle']), StoreSettings::all()['partner_response_minutes']),
        ], $ended ? 200 : 409);
    }

    /** Dishes that can be offered instead, for the "Suggest changes" sheet. */
    public function swaps(Request $request, StoreOrder $order): JsonResponse
    {
        $this->own($request, $order);
        return response()->json(['ok' => true, 'items' => $this->orders->swapOptions($order)]);
    }

    /** Suggest changes: per order line a quantity (0 = remove) and maybe a dish to offer instead. */
    public function propose(Request $request, StoreOrder $order): JsonResponse
    {
        $data = $request->validate([
            'changes' => 'required|array|max:50',
            'changes.*.quantity' => 'required|integer|min:0|max:' . \Modules\StoreManagement\Service\StoreOrderService::MAX_QTY,
            'changes.*.swap_item_id' => 'nullable|uuid',
            'note' => 'nullable|string|max:300',
            'by_store' => 'nullable|boolean',
        ]);
        $this->own($request, $order);
        try {
            $order = $this->orders->proposeChanges($order, $data['changes'], $data['note'] ?? null, $request->boolean('by_store'));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'order' => $this->present($order->fresh(['items', 'store', 'trip.driver', 'trip.vehicle']), StoreSettings::all()['partner_response_minutes'])]);
    }

    public function ready(Request $request, StoreOrder $order): JsonResponse
    {
        try {
            $order = $this->orders->markReady($this->own($request, $order));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'order' => $this->present($order->fresh(['items', 'store', 'trip.driver', 'trip.vehicle']), StoreSettings::all()['partner_response_minutes'])]);
    }

    /** Eat there or collect: the customer is here, the staff type their code. */
    public function handOver(Request $request, StoreOrder $order): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:6']);
        try {
            $order = $this->orders->handOver($this->own($request, $order), $data['code']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'order' => $this->present($order->fresh(['items', 'store', 'trip.driver', 'trip.vehicle']), StoreSettings::all()['partner_response_minutes'])]);
    }

    /** "The shop brings it": the staff member leaves with the order. */
    public function out(Request $request, StoreOrder $order): JsonResponse
    {
        return $this->act(fn() => $this->orders->sendOut($this->own($request, $order)));
    }

    /** "The shop brings it", paid, and nobody can go: an E-Ride rider instead. */
    public function useRider(Request $request, StoreOrder $order): JsonResponse
    {
        return $this->act(fn() => $this->orders->useRider($this->own($request, $order)));
    }

    /** "My riders": ring every rider for this order, not only the store's own. */
    public function openRiders(Request $request, StoreOrder $order): JsonResponse
    {
        return $this->act(fn() => $this->orders->openToAllRiders($this->own($request, $order)));
    }

    /** Runs a board action and answers with the order as it now is, or the reason it was refused. */
    protected function act(callable $do): JsonResponse
    {
        try {
            $order = $do();
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'order' => $this->present($order->fresh(['items', 'store', 'trip.driver', 'trip.vehicle']), StoreSettings::all()['partner_response_minutes'])]);
    }

    /** Says so when the order had already moved on (another tap, a timeout). */
    protected function answer(StoreOrder $order, string $expectedBefore, ?string $statusBefore = null): JsonResponse
    {
        $order = $order->fresh(['items', 'store', 'trip.driver', 'trip.vehicle']);
        $ok = ($statusBefore ?? $expectedBefore) === $expectedBefore && $order->status !== $expectedBefore;
        return response()->json([
            'ok' => $ok,
            'message' => $ok ? null : translate('This order changed in the meantime. The board has been refreshed.'),
            'order' => $this->present($order, StoreSettings::all()['partner_response_minutes']),
        ], $ok ? 200 : 409);
    }

    /** "6 77 12 34 56" or "+237677123456" to "237677123456" for wa.me. */
    protected function whatsappNumber(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        if ($digits === '') {
            return null;
        }
        return strlen($digits) === 9 ? '237' . $digits : $digits;
    }

    protected ?array $posKitchens = null;

    /** Kitchens with a POS Innova linked (and heard from), once per request. */
    protected function posKitchens(): array
    {
        return $this->posKitchens ??= \Modules\StoreManagement\Entities\StorePosLink::whereNull('revoked_at')->whereNotNull('linked_at')
            ->pluck('store_id')->unique()->values()->all();
    }

    protected function present(StoreOrder $o, int $responseMinutes): array
    {
        $trip = $o->trip;
        $driver = $trip?->driver;
        // Same test as the apps' config (otp_confirmation_for_trip).
        $showCode = (businessConfig('driver_otp_confirmation_for_trip')?->value ?? 0) == 1;
        $noRider = $o->status === StoreOrder::RIDER_REQUESTED && $o->payment_status === StoreOrder::PAID
            && !($trip?->driver_id && in_array($trip->current_status, [ACCEPTED, OUT_FOR_PICKUP, ONGOING, COMPLETED], true));
        $byStore = $o->byStore();
        // A new delivery close enough for the store's own staff: it chooses who brings it.
        $own = $o->status === StoreOrder::AWAITING_CONFIRMATION && $o->isDelivery() && $o->store
            ? $this->orders->ownDeliveryOffer($o->store, $o->delivery_quote) : null;
        // "My riders": still held for the store's own riders.
        $held = $noRider && $trip && $trip->current_status === PENDING && \Modules\TripManagement\Lib\TripReservation::active($trip);
        return [
            // rider | store ("The shop brings it").
            'delivered_by' => $o->isDelivery() ? ($o->delivered_by ?? StoreOrder::BY_RIDER) : null,
            'own_offer' => $own ? [
                'fee_text' => getCurrencyFormat($own['fee']),
                'rider_fee_text' => getCurrencyFormat($own['rider_fee']),
                'distance_text' => $own['distance_km'] < 1 ? round($own['distance_km'] * 1000) . ' m' : $own['distance_km'] . ' km',
            ] : null,
            'delivery_fee_text' => $o->isDelivery() ? getCurrencyFormat($o->delivery_fee) : null,
            // Where the staff go, and the code the customer gives them at the door (never shown here: the staff must ask for it).
            'drop_address' => $byStore || $own ? $o->drop_address : null,
            'drop_map' => $byStore && $o->isActive() ? 'https://www.google.com/maps/search/?api=1&query=' . $o->drop_latitude . ',' . $o->drop_longitude : null,
            // Paid and the staff cannot go after all: a rider, and what that costs the store.
            'can_use_rider' => $byStore && $o->status === StoreOrder::ACCEPTED && $o->payment_status === StoreOrder::PAID && $o->rider_fee !== null,
            'rider_extra' => $byStore && $o->rider_fee !== null ? max(0, (float)$o->rider_fee - (float)$o->delivery_fee) : null,
            'rider_extra_text' => $byStore && $o->rider_fee !== null ? getCurrencyFormat(max(0, (float)$o->rider_fee - (float)$o->delivery_fee)) : null,
            'store_covers_text' => $o->store_covers > 0 ? getCurrencyFormat($o->store_covers) : null,
            'riders_held' => $held ? ($trip->reserved_until === null ? 'only' : 'first') : null,
            'id' => $o->id,
            'ref' => $o->ref_id,
            'status' => $o->status,
            // Which kitchen cooks it, on an owner's board with several branches.
            'branch' => $o->store && ($o->store->isBranch() || $o->store->branch_name) ? \Modules\StoreManagement\Lib\StoreBranches::label($o->store) : null,
            'items' => $o->items->map(fn($i) => ['item_id' => $i->store_item_id, 'name' => $i->name, 'quantity' => $i->quantity, 'unit_price' => $i->unit_price, 'line_total' => $i->line_total])->values(),
            'items_count' => (int)$o->items->sum('quantity'),
            'items_subtotal' => $o->items_subtotal,
            'items_subtotal_text' => getCurrencyFormat($o->items_subtotal),
            'note' => $o->note,
            // How it is had: delivery, dine_in (eat there) or pickup (collect), and when they come.
            'fulfilment' => $o->fulfilment ?? StoreOrder::DELIVERY,
            'arrive_at' => $o->arrive_at?->toIso8601String(),
            'guests' => $o->guests,
            'ready_told' => $o->ready_told_at !== null,
            'customer_name' => $o->receiver_name,
            'placed_at' => $o->placed_at?->toIso8601String(),
            'respond_by' => in_array($o->status, [StoreOrder::AWAITING_CONFIRMATION, StoreOrder::PLACED], true) && $o->placed_at ? $o->placed_at->copy()->addMinutes($responseMinutes)->toIso8601String() : null,
            'answer_by' => $o->status === StoreOrder::CHANGES_PROPOSED && $o->proposed_at ? $o->proposed_at->copy()->addMinutes(\Modules\StoreManagement\Service\StoreOrderService::ANSWER_MINUTES)->toIso8601String() : null,
            'pay_by' => $o->isConfirmedUnpaid() ? $o->pay_by?->toIso8601String() : null,
            'paid' => $o->payment_status === StoreOrder::PAID,
            'proposal' => $o->status === StoreOrder::CHANGES_PROPOSED ? $o->proposal : null,
            // Call or WhatsApp the customer while the order is open; hidden once it is over.
            'customer_phone' => $o->isActive() ? $o->receiver_phone : null,
            'whatsapp' => $o->isActive() ? $this->whatsappNumber($o->receiver_phone) : null,
            // Before payment; after payment only when no rider has taken it (E-Ride
            // keeps calling riders until the store gives up on it).
            'can_cancel' => in_array($o->status, [StoreOrder::CHANGES_PROPOSED], true) || $o->isConfirmedUnpaid() || $noRider,
            'no_rider' => $noRider,
            // POS Innova at this kitchen: did the salesperson ring it up there yet?
            'pos' => $o->payment_status === StoreOrder::PAID && in_array($o->store_id, $this->posKitchens(), true) ? [
                'rung' => $o->pos_rung_up_at !== null,
                'invoice' => $o->pos_invoice_no,
                'matches' => $o->pos_matches,
            ] : null,
            'rider_attempts' => (int)$o->rider_attempts,
            'refunds' => StoreSettings::refunds(),
            'prep_minutes' => $o->prep_minutes,
            'ready_at' => $o->ready_at?->toIso8601String(),
            'picked_up_at' => $o->picked_up_at?->toIso8601String(),
            'delivered_at' => $o->delivered_at?->toIso8601String(),
            'cancel_reason' => $o->cancel_reason,
            'rider' => $driver && in_array($trip->current_status, [ACCEPTED, OUT_FOR_PICKUP, ONGOING, COMPLETED], true) ? [
                'name' => trim($driver->first_name . ' ' . $driver->last_name),
                'phone' => $driver->phone,
                'vehicle' => $trip->vehicle?->licence_plate_number,
                'pickup_code' => $showCode && in_array($trip->current_status, [ACCEPTED, OUT_FOR_PICKUP], true) ? $trip->otp : null,
            ] : null,
            'trip_status' => $trip?->current_status,
            // Coming back to the store: the store gives the rider this code when it has the food.
            'return_code' => $trip?->current_status === RETURNING ? $trip->otp : null,
        ];
    }
}
