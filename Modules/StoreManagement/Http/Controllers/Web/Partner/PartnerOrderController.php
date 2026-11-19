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
        return view('storemanagement::partner.orders', [
            'store' => $this->store($request),
            'responseMinutes' => StoreSettings::all()['partner_response_minutes'],
        ]);
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
        $data = $request->validate(['prep_minutes' => 'required|integer|min:5|max:180']);
        $before = $this->own($request, $order)->status;
        $order = $this->orders->accept($order, (int)$data['prep_minutes']);
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
        ]);
        $this->own($request, $order);
        try {
            $order = $this->orders->proposeChanges($order, $data['changes'], $data['note'] ?? null);
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

    protected function present(StoreOrder $o, int $responseMinutes): array
    {
        $trip = $o->trip;
        $driver = $trip?->driver;
        // Same test as the apps' config (otp_confirmation_for_trip).
        $showCode = (businessConfig('driver_otp_confirmation_for_trip')?->value ?? 0) == 1;
        $noRider = $o->status === StoreOrder::RIDER_REQUESTED && $o->payment_status === StoreOrder::PAID
            && !($trip?->driver_id && in_array($trip->current_status, [ACCEPTED, OUT_FOR_PICKUP, ONGOING, COMPLETED], true));
        return [
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
