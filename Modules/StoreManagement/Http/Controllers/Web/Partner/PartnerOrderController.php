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

    /** Only this partner's orders, never another store's. */
    protected function own(Request $request, StoreOrder $order): StoreOrder
    {
        abort_unless($order->store_id === $this->store($request)->id, 404);
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
        $orders = StoreOrder::with(['items', 'trip.driver', 'trip.vehicle'])
            ->where('store_id', $store->id)
            ->where(function ($q) {
                $q->whereIn('status', StoreOrder::ACTIVE)
                    ->orWhere(fn($w) => $w->whereIn('status', StoreOrder::FINAL)->where('updated_at', '>=', now()->subHours(3)));
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
        $orders = StoreOrder::with(['items', 'review'])
            ->where('store_id', $store->id)
            ->where('status', '!=', StoreOrder::AWAITING_PAYMENT)
            ->latest('placed_at')
            ->paginate(25);
        $rating = $store->ratingSummary();
        return view('storemanagement::partner.history', compact('orders', 'rating'));
    }

    public function accept(Request $request, StoreOrder $order): JsonResponse
    {
        $data = $request->validate(['prep_minutes' => 'required|integer|min:5|max:180']);
        $before = $this->own($request, $order)->status;
        $order = $this->orders->accept($order, (int)$data['prep_minutes']);
        return $this->answer($order, StoreOrder::PLACED, $before);
    }

    public function reject(Request $request, StoreOrder $order): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:200']);
        $before = $this->own($request, $order)->status;
        $order = $this->orders->reject($order, $data['reason']);
        return $this->answer($order, StoreOrder::PLACED, $before);
    }

    public function ready(Request $request, StoreOrder $order): JsonResponse
    {
        try {
            $order = $this->orders->markReady($this->own($request, $order));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'order' => $this->present($order->fresh(['items', 'trip.driver', 'trip.vehicle']), StoreSettings::all()['partner_response_minutes'])]);
    }

    /** Says so when the order had already moved on (another tap, a timeout). */
    protected function answer(StoreOrder $order, string $expectedBefore, ?string $statusBefore = null): JsonResponse
    {
        $order = $order->fresh(['items', 'trip.driver', 'trip.vehicle']);
        $ok = ($statusBefore ?? $expectedBefore) === $expectedBefore && $order->status !== $expectedBefore;
        return response()->json([
            'ok' => $ok,
            'message' => $ok ? null : translate('This order changed in the meantime. The board has been refreshed.'),
            'order' => $this->present($order, StoreSettings::all()['partner_response_minutes']),
        ], $ok ? 200 : 409);
    }

    protected function present(StoreOrder $o, int $responseMinutes): array
    {
        $trip = $o->trip;
        $driver = $trip?->driver;
        // Same test as the apps' config (otp_confirmation_for_trip).
        $showCode = (businessConfig('driver_otp_confirmation_for_trip')?->value ?? 0) == 1;
        return [
            'id' => $o->id,
            'ref' => $o->ref_id,
            'status' => $o->status,
            'items' => $o->items->map(fn($i) => ['name' => $i->name, 'quantity' => $i->quantity, 'line_total' => $i->line_total])->values(),
            'items_count' => (int)$o->items->sum('quantity'),
            'items_subtotal' => $o->items_subtotal,
            'items_subtotal_text' => getCurrencyFormat($o->items_subtotal),
            'note' => $o->note,
            'customer_name' => $o->receiver_name,
            'placed_at' => $o->placed_at?->toIso8601String(),
            'respond_by' => $o->status === StoreOrder::PLACED && $o->placed_at ? $o->placed_at->copy()->addMinutes($responseMinutes)->toIso8601String() : null,
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
        ];
    }
}
