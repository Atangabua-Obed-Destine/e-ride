<?php

namespace Modules\StoreManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Lib\StoreSettings;
use Modules\StoreManagement\Service\Interfaces\StoreOrderServiceInterface;
use Modules\StoreManagement\Transformers\StorePresenter;

/** Customer app: browse stores, quote a cart, place, pay, follow and cancel orders. */
class StoreOrderController extends Controller
{
    public function __construct(protected StoreOrderServiceInterface $orders)
    {
    }

    public function stores(Request $request): JsonResponse
    {
        $lat = $request->filled('latitude') ? (float)$request->query('latitude') : null;
        $lng = $request->filled('longitude') ? (float)$request->query('longitude') : null;
        if (!StoreSettings::enabled()) {
            return response()->json(responseFormatter(DEFAULT_200, ['stores' => [], 'enabled' => false]));
        }
        $zoneId = $request->header('zoneId');
        $stores = Store::where('is_active', 1)
            ->withAvg('reviews', 'rating')->withCount('reviews')
            ->whereNotNull('zone_id')
            ->when($zoneId, fn($q) => $q->where('zone_id', $zoneId))
            ->orderBy('sort')->orderBy('name')
            ->get()
            ->map(fn(Store $s) => StorePresenter::store($s, $lat, $lng))
            // Open first, then nearest.
            ->sortBy(fn($s) => [$s['open_now'] ? 0 : 1, $s['distance_km'] ?? 0])
            ->values();
        return response()->json(responseFormatter(DEFAULT_200, ['stores' => $stores, 'enabled' => true, 'label' => StoreSettings::all()['service_label']]));
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->is_active && StoreSettings::enabled(), 404);
        $store->load(['categories', 'items']);
        $lat = $request->filled('latitude') ? (float)$request->query('latitude') : null;
        $lng = $request->filled('longitude') ? (float)$request->query('longitude') : null;
        return response()->json(responseFormatter(DEFAULT_200, [
            'store' => StorePresenter::store($store, $lat, $lng),
            'menu' => StorePresenter::menu($store),
        ]));
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $this->validateCart($request);
        $store = Store::where('is_active', 1)->find($data['store_id']);
        if (!$store) {
            return $this->problem(translate('This store is not available.'));
        }
        $quote = $this->orders->quote($store, $data['items'], ['latitude' => $data['drop_latitude'], 'longitude' => $data['drop_longitude']]);
        unset($quote['delivery']);
        return response()->json(responseFormatter(DEFAULT_200, $quote));
    }

    public function place(Request $request): JsonResponse
    {
        $gateways = collect(app(\Modules\BusinessManagement\Http\Controllers\Api\Customer\ConfigController::class)->getPaymentMethods())->pluck('gateway')->all();
        $data = $this->validateCart($request, [
            'drop_address' => 'required|string|max:255',
            'receiver_name' => 'nullable|string|max:120',
            'receiver_phone' => 'nullable|string|max:25',
            'note' => 'nullable|string|max:300',
            'payment_method' => ['required', 'string', Rule::in(array_merge(['wallet'], $gateways))],
        ]);
        $store = Store::where('is_active', 1)->find($data['store_id']);
        if (!$store) {
            return $this->problem(translate('This store is not available.'));
        }
        try {
            $order = $this->orders->place(auth('api')->user(), $store, $data);
        } catch (ValidationException $e) {
            return $this->problem(collect($e->errors())->flatten()->first(), collect($e->errors())->flatten()->values()->all());
        }
        return response()->json(responseFormatter(DEFAULT_200, [
            'order' => StorePresenter::order($order),
            'pay_url' => $order->status === StoreOrder::AWAITING_PAYMENT
                ? route('store-order.pay', ['order' => $order->id, 'payment_method' => $data['payment_method']])
                : null,
        ]));
    }

    /** Opened in the app's payment webview (no auth header there, like ride/digital-payment). */
    public function pay(Request $request, StoreOrder $order): RedirectResponse|JsonResponse
    {
        $method = (string)$request->query('payment_method', '');
        if ($order->status !== StoreOrder::AWAITING_PAYMENT || $order->payment_status !== StoreOrder::UNPAID) {
            return redirect()->route($order->payment_status === StoreOrder::PAID ? 'payment-success' : 'payment-fail');
        }
        try {
            return redirect($this->orders->paymentLink($order, $method));
        } catch (\Throwable $e) {
            return redirect()->route('payment-fail');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int)$request->query('limit', 20)));
        $offset = max(1, (int)$request->query('offset', 1));
        $orders = StoreOrder::with(['store', 'items', 'review'])
            ->where('customer_id', auth('api')->id())
            ->where('status', '!=', StoreOrder::AWAITING_PAYMENT)
            ->latest()
            ->paginate(perPage: $limit, page: $offset);
        $orders->setCollection($orders->getCollection()->map(fn($o) => StorePresenter::order($o, false)));
        return response()->json(array_merge(responseFormatter(DEFAULT_200, $orders->items()), [
            'total_size' => $orders->total(), 'limit' => $limit, 'offset' => $offset,
        ]));
    }

    public function show(StoreOrder $order): JsonResponse
    {
        abort_unless($order->customer_id === auth('api')->id(), 404);
        $order->load(['store', 'items', 'trip.driver', 'review']);
        return response()->json(responseFormatter(DEFAULT_200, StorePresenter::order($order)));
    }

    public function cancel(StoreOrder $order): JsonResponse
    {
        abort_unless($order->customer_id === auth('api')->id(), 404);
        try {
            $order = $this->orders->cancelByCustomer($order);
        } catch (ValidationException $e) {
            return $this->problem(collect($e->errors())->flatten()->first());
        }
        return response()->json(responseFormatter(DEFAULT_200, StorePresenter::order($order->load(['store', 'items']))));
    }

    public function review(Request $request, StoreOrder $order): JsonResponse
    {
        abort_unless($order->customer_id === auth('api')->id(), 404);
        $data = $request->validate([
            'store_rating' => 'required|integer|between:1,5',
            'store_comment' => 'nullable|string|max:500',
            'rider_rating' => 'nullable|integer|between:1,5',
            'rider_comment' => 'nullable|string|max:500',
        ]);
        try {
            $this->orders->review($order, $data);
        } catch (ValidationException $e) {
            return $this->problem(collect($e->errors())->flatten()->first());
        }
        return response()->json(responseFormatter(DEFAULT_STORE_200, StorePresenter::order($order->fresh()->load(['store', 'items', 'trip.driver', 'review']))));
    }

    protected function validateCart(Request $request, array $extra = []): array
    {
        return Validator::make($request->all(), array_merge([
            'store_id' => 'required|uuid',
            'items' => 'required|array|min:1|max:50',
            'items.*.item_id' => 'required|uuid',
            'items.*.quantity' => 'required|integer|min:1|max:' . \Modules\StoreManagement\Service\StoreOrderService::MAX_QTY,
            'drop_latitude' => 'required|numeric|between:-90,90',
            'drop_longitude' => 'required|numeric|between:-180,180',
        ], $extra))->validate();
    }

    protected function problem(string $message, array $all = []): JsonResponse
    {
        return response()->json(responseFormatter(constant: DEFAULT_400, errors: array_map(
            fn($m) => ['error_code' => 'order', 'message' => $m],
            $all ?: [$message]
        )), 400);
    }
}
