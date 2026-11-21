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
use Modules\StoreManagement\Entities\StoreReview;
use Modules\StoreManagement\Lib\StoreBranches;
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
        // One card per brand, showing the branch that would serve this customer.
        $stores = Store::where('is_active', 1)->whereNull('parent_id')
            ->with('branches')
            ->orderBy('sort')->orderBy('name')
            ->get()
            ->map(function (Store $brand) use ($lat, $lng, $zoneId) {
                $kitchens = $brand->kitchens(true)->filter(fn(Store $k) => $k->zone_id && (!$zoneId || $k->zone_id === $zoneId));
                if ($kitchens->isEmpty()) {
                    return null;
                }
                $pick = StoreBranches::pick($brand, $lat, $lng, null, $zoneId);
                return StorePresenter::store($pick['kitchen'], $lat, $lng) + [
                    // The app opens the brand; the menu call picks the branch again.
                    'id' => $brand->id,
                    'branch_count' => $pick['options']->count(),
                ];
            })
            ->filter()
            // Open first, then nearest.
            ->sortBy(fn($s) => [$s['open_now'] ? 0 : 1, $s['distance_km'] ?? 0])
            ->values();
        return response()->json(responseFormatter(DEFAULT_200, ['stores' => $stores, 'enabled' => true, 'label' => StoreSettings::all()['service_label']]));
    }

    /**
     * Search every store at once: "rice" finds each store that has it, with the
     * matching products. One group per brand (in the customer's zone), stores
     * whose name matches first, then by how many products match.
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|min:2|max:60']);
        if (!StoreSettings::enabled()) {
            return response()->json(responseFormatter(DEFAULT_200, ['results' => []]));
        }
        $lat = $request->filled('latitude') ? (float)$request->query('latitude') : null;
        $lng = $request->filled('longitude') ? (float)$request->query('longitude') : null;
        $zoneId = $request->header('zoneId');
        $words = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($data['q']))), fn($w) => mb_strlen($w) >= 2));
        $results = Store::where('is_active', 1)->whereNull('parent_id')->with(['branches', 'categories', 'items'])->get()
            ->map(function (Store $brand) use ($words, $lat, $lng, $zoneId) {
                $kitchens = $brand->kitchens(true)->filter(fn(Store $k) => $k->zone_id && (!$zoneId || $k->zone_id === $zoneId));
                if ($kitchens->isEmpty()) {
                    return null;
                }
                $hits = $brand->items->filter(function ($it) use ($words) {
                    $text = mb_strtolower($it->name . ' ' . $it->description);
                    foreach ($words as $w) {
                        if (!str_contains($text, $w)) {
                            return false;
                        }
                    }
                    return true;
                });
                $nameHit = collect($words)->every(fn($w) => str_contains(mb_strtolower($brand->name . ' ' . $brand->description), $w));
                if ($hits->isEmpty() && !$nameHit) {
                    return null;
                }
                $pick = StoreBranches::pick($brand, $lat, $lng, null, $zoneId);
                $sections = $brand->categories->keyBy('id');
                return [
                    'store' => StorePresenter::store($pick['kitchen'], $lat, $lng) + ['id' => $brand->id],
                    'name_match' => $nameHit,
                    'items' => $hits->sortByDesc(fn($it) => (int)$it->is_available)->take(8)->map(fn($it) => [
                        'id' => $it->id,
                        'name' => $it->name,
                        'price' => (float)$it->price,
                        'image' => $it->imageUrl(),
                        'section' => $sections->get($it->store_menu_category_id)?->name,
                        'is_available' => (bool)$it->is_available,
                    ])->values()->all(),
                    'count' => $hits->count(),
                ];
            })
            ->filter()
            ->sortBy(fn($r) => [$r['name_match'] ? 0 : 1, $r['store']['open_now'] ? 0 : 1, -$r['count']])
            ->take(15)->values();
        return response()->json(responseFormatter(DEFAULT_200, ['results' => $results]));
    }

    /**
     * A brand's menu as served by one of its kitchens. {store} is the brand or
     * one of its branches (taken as the preferred branch); ?branch= is the
     * branch the customer chose. The answer says which branch and why.
     */
    public function store(Request $request, Store $store): JsonResponse
    {
        $brand = $store->brand();
        abort_unless($brand->is_active && StoreSettings::enabled(), 404);
        $lat = $request->filled('latitude') ? (float)$request->query('latitude') : null;
        $lng = $request->filled('longitude') ? (float)$request->query('longitude') : null;
        $preferred = $request->query('branch') ?: ($store->isBranch() ? $store->id : null);
        $pick = StoreBranches::pick($brand, $lat, $lng, $preferred);
        $kitchen = $pick['kitchen'];
        $many = $pick['options']->count() > 1;
        $brand->load(['categories', 'items']);
        $at = now();
        return response()->json(responseFormatter(DEFAULT_200, [
            'store' => StorePresenter::store($kitchen, $lat, $lng),
            'branch' => $many ? [
                'id' => $kitchen->id,
                'name' => StoreBranches::label($kitchen),
                'address' => $kitchen->address,
                'distance_km' => $pick['options']->firstWhere('kitchen.id', $kitchen->id)['distance_km'] ?? null,
                'delivery_fee' => ($lat !== null && $lng !== null && $kitchen->isOpenNow($at)) ? $this->orders->deliveryFee($kitchen, $lat, $lng) : null,
                'reason' => $pick['reason'],
                'skipped' => $pick['skipped'],
                'count' => $pick['options']->count(),
            ] : null,
            'branches' => $many ? $this->branchOptions($pick['options'], $kitchen) : [],
            'menu' => StorePresenter::menu($brand, $at, $kitchen, $many ? StoreBranches::elsewhereFinder($pick['options'], $kitchen, $at) : null),
        ]));
    }

    /** Every branch of a brand for the "Choose a branch" sheet, with delivery fees to this address. */
    public function branches(Request $request, Store $store): JsonResponse
    {
        $brand = $store->brand();
        abort_unless($brand->is_active && StoreSettings::enabled(), 404);
        $lat = $request->filled('latitude') ? (float)$request->query('latitude') : null;
        $lng = $request->filled('longitude') ? (float)$request->query('longitude') : null;
        $pick = StoreBranches::pick($brand, $lat, $lng, $request->query('branch'));
        return response()->json(responseFormatter(DEFAULT_200, [
            'branches' => $this->branchOptions($pick['options'], $pick['kitchen'], $lat, $lng),
        ]));
    }

    protected function branchOptions($options, Store $chosen, ?float $lat = null, ?float $lng = null): array
    {
        $now = now();
        return $options->map(function ($o) use ($chosen, $lat, $lng, $now) {
            /** @var Store $k */
            $k = $o['kitchen'];
            $hours = $k->hoursFor($now);
            return [
                'id' => $k->id,
                'name' => StoreBranches::label($k),
                'address' => $k->address,
                'distance_km' => $o['distance_km'],
                'open_now' => $o['open'],
                'delivers' => $o['delivers'],
                'today_hours' => $hours ? ['open' => $hours[0], 'close' => $hours[1]] : null,
                'delivery_fee' => ($lat !== null && $lng !== null && $o['open'] && $o['delivers']) ? $this->orders->deliveryFee($k, $lat, $lng) : null,
                'selected' => $k->id === $chosen->id,
            ];
        })->values()->all();
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $this->validateCart($request);
        $store = Store::where('is_active', 1)->find($data['store_id']);
        if (!$store || !$store->brand()->is_active) {
            return $this->problem(translate('This store is not available.'));
        }
        $quote = $this->orders->quote($store, $data['items'], ['latitude' => $data['drop_latitude'] ?? null, 'longitude' => $data['drop_longitude'] ?? null],
            $data['fulfilment'] ?? StoreOrder::DELIVERY, filled($data['arrive_at'] ?? null) ? \Illuminate\Support\Carbon::parse($data['arrive_at']) : null);
        unset($quote['delivery']);
        return response()->json(responseFormatter(DEFAULT_200, $quote));
    }

    public function place(Request $request): JsonResponse
    {
        $gateways = collect(app(\Modules\BusinessManagement\Http\Controllers\Api\Customer\ConfigController::class)->getPaymentMethods())->pluck('gateway')->all();
        $data = $this->validateCart($request, [
            'drop_address' => [Rule::requiredIf(fn() => !in_array($request->input('fulfilment'), [StoreOrder::DINE_IN, StoreOrder::PICKUP], true)), 'nullable', 'string', 'max:255'],
            'receiver_name' => 'nullable|string|max:120',
            'receiver_phone' => 'nullable|string|max:25',
            'note' => 'nullable|string|max:300',
            'payment_method' => ['required', 'string', Rule::in(array_merge(['wallet'], $gateways))],
        ]);
        $store = Store::where('is_active', 1)->find($data['store_id']);
        if (!$store || !$store->brand()->is_active) {
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

    /**
     * Pay a confirmed order. Wallet: paid now, the kitchen starts. Gateway:
     * pay_url to open in the payment webview.
     */
    public function payment(Request $request, StoreOrder $order): JsonResponse
    {
        abort_unless($order->customer_id === auth('api')->id(), 404);
        $gateways = collect(app(\Modules\BusinessManagement\Http\Controllers\Api\Customer\ConfigController::class)->getPaymentMethods())->pluck('gateway')->all();
        $data = $request->validate(['payment_method' => ['required', 'string', Rule::in(array_merge(['wallet'], $gateways))]]);
        try {
            $payUrl = $this->orders->payConfirmed($order, $data['payment_method']);
        } catch (ValidationException $e) {
            return $this->problem(collect($e->errors())->flatten()->first());
        }
        return response()->json(responseFormatter(DEFAULT_200, [
            'order' => StorePresenter::order($order->fresh()->load(['store', 'items'])),
            'pay_url' => $payUrl,
        ]));
    }

    /**
     * "Deliver it instead": an eat-there or collect order becomes a delivery to
     * this address. pay_url is the delivery fee's page when the order was paid.
     */
    public function deliver(Request $request, StoreOrder $order): JsonResponse
    {
        abort_unless($order->customer_id === auth('api')->id(), 404);
        $gateways = collect(app(\Modules\BusinessManagement\Http\Controllers\Api\Customer\ConfigController::class)->getPaymentMethods())->pluck('gateway')->all();
        $data = $request->validate([
            'drop_address' => 'required|string|max:255',
            'drop_latitude' => 'required|numeric|between:-90,90',
            'drop_longitude' => 'required|numeric|between:-180,180',
            'payment_method' => ['nullable', 'string', Rule::in($gateways)],
            // true: only say what the delivery would cost.
            'preview' => 'nullable|boolean',
        ]);
        if ($request->boolean('preview')) {
            $fee = $this->orders->deliveryFee($order->store, (float)$data['drop_latitude'], (float)$data['drop_longitude']);
            if ($fee === null) {
                return $this->problem(translate('E-Ride cannot deliver this order to that address.'));
            }
            return response()->json(responseFormatter(DEFAULT_200, ['fee' => $fee, 'paid' => $order->payment_status === StoreOrder::PAID]));
        }
        try {
            $result = $this->orders->switchToDelivery($order, $data, $data['payment_method'] ?? null);
        } catch (ValidationException $e) {
            return $this->problem(collect($e->errors())->flatten()->first());
        }
        return response()->json(responseFormatter(DEFAULT_200, [
            'order' => StorePresenter::order($result['order']->fresh()->load(['store', 'items'])),
            'pay_url' => $result['pay_url'],
        ]));
    }

    /** Opened in the app's payment webview: the delivery fee of a change to delivery. */
    public function payDelivery(Request $request, StoreOrder $order): RedirectResponse|JsonResponse
    {
        try {
            return redirect($this->orders->deliveryFeeLink($order, (string)$request->query('payment_method', '')));
        } catch (\Throwable $e) {
            return redirect()->route($order->isDelivery() ? 'payment-success' : 'payment-fail');
        }
    }

    /** The customer's answer to changes the restaurant suggested. */
    public function changes(Request $request, StoreOrder $order): JsonResponse
    {
        abort_unless($order->customer_id === auth('api')->id(), 404);
        $data = $request->validate(['accept' => 'required|boolean']);
        try {
            $order = $this->orders->answerChanges($order, (bool)$data['accept']);
        } catch (ValidationException $e) {
            return $this->problem(collect($e->errors())->flatten()->first());
        }
        return response()->json(responseFormatter(DEFAULT_200, StorePresenter::order($order->fresh()->load(['store', 'items']))));
    }

    /** Opened in the app's payment webview (no auth header there, like ride/digital-payment). */
    public function pay(Request $request, StoreOrder $order): RedirectResponse|JsonResponse
    {
        $method = (string)$request->query('payment_method', '');
        if ($order->status !== StoreOrder::AWAITING_PAYMENT || $order->payment_status !== StoreOrder::UNPAID
            || ($order->pay_by && $order->pay_by->isPast())) {
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
            // Older pay-first orders never paid are left out; confirmed ones waiting for payment are shown.
            ->where(fn($q) => $q->where('status', '!=', StoreOrder::AWAITING_PAYMENT)->orWhereNotNull('confirmed_at'))
            ->latest()
            ->paginate(perPage: $limit, page: $offset);
        $orders->setCollection($orders->getCollection()->map(fn($o) => StorePresenter::order($o, false)));
        return response()->json(array_merge(responseFormatter(DEFAULT_200, $orders->items()), [
            'total_size' => $orders->total(), 'limit' => $limit, 'offset' => $offset,
        ]));
    }

    /** What needs the customer in Food & Shops, for the red count on Home: delivered orders not rated yet. */
    public function attention(): JsonResponse
    {
        $toRate = StoreOrder::where('customer_id', auth('api')->id())
            ->where('status', StoreOrder::DELIVERED)
            ->where('delivered_at', '>', now()->subDays(StoreReview::MAX_DAYS_TO_REVIEW))
            ->whereDoesntHave('review')
            ->count();
        return response()->json(responseFormatter(DEFAULT_200, ['to_rate' => $toRate]));
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
            // delivery (default), dine_in (eat there) or pickup (collect): no address for the last two.
            'fulfilment' => ['nullable', Rule::in(StoreOrder::WAYS)],
            'arrive_at' => 'nullable|date',
            'guests' => 'nullable|integer|min:1|max:30',
            'drop_latitude' => [Rule::requiredIf(fn() => !in_array($request->input('fulfilment'), [StoreOrder::DINE_IN, StoreOrder::PICKUP], true)), 'nullable', 'numeric', 'between:-90,90'],
            'drop_longitude' => [Rule::requiredIf(fn() => !in_array($request->input('fulfilment'), [StoreOrder::DINE_IN, StoreOrder::PICKUP], true)), 'nullable', 'numeric', 'between:-180,180'],
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
