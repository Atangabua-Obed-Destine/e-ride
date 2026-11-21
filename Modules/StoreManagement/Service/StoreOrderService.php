<?php

namespace Modules\StoreManagement\Service;

use App\Jobs\ProcessPushNotifications;
use App\Service\BaseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\AdminModule\Entities\AdminNotification;
use Modules\FareManagement\Entities\ParcelFare;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Traits\Payment;
use Modules\Gateways\Traits\SmsGatewayForMessage;
use Modules\ParcelManagement\Entities\ParcelWeight;
use Modules\ReviewModule\Entities\Review;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Entities\StoreReview;
use Modules\StoreManagement\Lib\StoreSettings;
use Modules\StoreManagement\Repository\StoreOrderRepositoryInterface;
use Modules\StoreManagement\Service\Interfaces\StoreOrderServiceInterface;
use Modules\TransactionManagement\Entities\Transaction;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Lib\CommonTrait;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAccount;
use Modules\ZoneManagement\Entities\Zone;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;
use Throwable;

/**
 * Every order transition goes through here, under a row lock, so a webhook,
 * the scheduler and a tap in the portal can race safely.
 *
 * Money:
 *  - The customer prepays food + delivery (wallet now, or a gateway).
 *  - Refunds before pickup go to the customer's E-Ride wallet.
 *  - The delivery is an ordinary parcel trip created already paid. The
 *    driver's share is booked at pickup with the standard
 *    digitalPaymentTransaction, exactly like a sender-paid parcel, so the
 *    existing return/reversal code stays consistent.
 *  - The partner earns the food subtotal once delivered (store ledger).
 */
class StoreOrderService extends BaseService implements StoreOrderServiceInterface
{
    use Payment;

    public const UNPAID_EXPIRY_MINUTES = 30;
    /** After the restaurant confirms, the customer pays within this. */
    public const PAY_MINUTES = 10;
    /** A customer still approving the payment on their phone when the time ends gets this long. */
    public const PAYING_GRACE_MINUTES = 3;
    /** The customer answers suggested changes within this. */
    public const ANSWER_MINUTES = 10;
    public const QUOTE_MINUTES = 15;
    public const MAX_QTY = 50;
    public const ADMIN_ALERT_AFTER_ATTEMPTS = 3;

    public function __construct(
        protected StoreOrderRepositoryInterface $orderRepository,
        protected ZoneServiceInterface $zoneService,
    )
    {
        parent::__construct($orderRepository);
    }

    /* ------------------------------------------------------------ quote */

    public function quote(Store $store, array $lines, array $drop, string $way = StoreOrder::DELIVERY, ?Carbon $arriveAt = null): array
    {
        $problems = [];
        if (!in_array($way, $store->ways(), true)) {
            $problems[] = translate(key: '{store} does not offer this. Choose another way to get your order.', replace: ['store' => $store->displayName()]);
            $way = StoreOrder::DELIVERY;
        }
        if (!StoreSettings::enabled()) {
            $problems[] = translate('Ordering is not available right now.');
        }
        if (!$store->isOpenNow()) {
            $problems[] = translate(key: '{store} is closed right now.', replace: ['store' => $store->displayName()]);
        }
        // The brand's menu, this kitchen's switches.
        $kitchenStates = $store->stateMap();

        // Merge duplicate lines, clamp quantities, keep only this store's items.
        $wanted = [];
        foreach ($lines as $line) {
            $id = (string)($line['item_id'] ?? $line['id'] ?? '');
            $qty = (int)($line['quantity'] ?? 0);
            if ($id === '' || $qty < 1) {
                continue;
            }
            $wanted[$id] = min(self::MAX_QTY, ($wanted[$id] ?? 0) + $qty);
        }
        $items = StoreItem::with('category')->where('store_id', $store->brand()->id)->whereIn('id', array_keys($wanted))->get()->keyBy('id');
        $resolved = [];
        foreach ($wanted as $id => $qty) {
            $item = $items->get($id);
            $state = $item?->menuState(null, $kitchenStates->get($item->id)) ?? StoreItem::HIDDEN;
            if ($state === StoreItem::HIDDEN) {
                $problems[] = translate('An item in your cart is no longer on the menu.');
                continue;
            }
            if ($state === StoreItem::NOT_HERE) {
                $problems[] = translate(key: '{item} is not made at {store}.', replace: ['item' => $item->name, 'store' => $store->displayName()]);
                continue;
            }
            if ($state === StoreItem::SOLD_OUT) {
                $problems[] = translate(key: '{item} is sold out today.', replace: ['item' => $item->name]);
                continue;
            }
            if ($state === StoreItem::LATER) {
                $problems[] = translate(key: '{item} is served from {time}.', replace: ['item' => $item->name, 'time' => $item->effectiveWindow()[0]]);
                continue;
            }
            if ($state === StoreItem::NOT_TODAY) {
                $problems[] = translate(key: '{item} is not served right now.', replace: ['item' => $item->name]);
                continue;
            }
            $resolved[] = [
                'store_item_id' => $item->id,
                'name' => $item->name,
                'unit_price' => round($item->price, 2),
                'quantity' => $qty,
                'line_total' => round($item->price * $qty, 2),
            ];
        }
        if (empty($resolved) && empty($problems)) {
            $problems[] = translate('Your cart is empty.');
        }
        $subtotal = round(array_sum(array_column($resolved, 'line_total')), 2);
        if ($store->min_order_amount > 0 && $subtotal < $store->min_order_amount) {
            $problems[] = translate(key: 'The minimum order at {store} is {amount}.', replace: ['store' => $store->displayName(), 'amount' => getCurrencyFormat($store->min_order_amount)]);
        }

        $delivery = null;
        $deliveryProblem = null;
        if ($way === StoreOrder::DELIVERY) {
            try {
                $delivery = $this->deliveryQuote($store, (float)($drop['latitude'] ?? 0), (float)($drop['longitude'] ?? 0));
            } catch (ValidationException $e) {
                $deliveryProblem = collect($e->errors())->flatten()->first();
            }
        } else {
            // Eat there or collect: no rider, no fee. The time must be reachable and inside opening hours.
            $earliest = now()->addMinutes(max(5, (int)$store->default_prep_minutes) - 1);
            if (!$arriveAt) {
                $deliveryProblem = translate('Say what time you will come.');
            } elseif ($arriveAt->lt($earliest)) {
                $deliveryProblem = translate(key: 'The earliest time is {time}: they need about {minutes} minutes to prepare.', replace: [
                    'time' => $earliest->copy()->addMinute()->format('H:i'), 'minutes' => (int)$store->default_prep_minutes]);
            } elseif ($arriveAt->gt(now()->addHours(24))) {
                $deliveryProblem = translate('Choose a time within the next 24 hours.');
            } elseif (!$store->isOpenNow($arriveAt)) {
                $deliveryProblem = translate(key: '{store} is closed at that time.', replace: ['store' => $store->displayName()]);
            }
        }
        if ($deliveryProblem) {
            $problems[] = $deliveryProblem;
        }
        $deliveryFee = $delivery['fee'] ?? 0;

        return [
            'ok' => empty($problems),
            'problems' => array_values(array_unique($problems)),
            'items' => $resolved,
            'items_subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => round($subtotal + $deliveryFee, 2),
            'distance_km' => $delivery['distance_km'] ?? null,
            'duration_min' => $delivery['duration_min'] ?? null,
            'prep_minutes' => $store->default_prep_minutes,
            'delivery' => $delivery,
            'way' => $way,
            'ways' => $store->ways(),
        ];
    }

    /** Delivery fee from this kitchen to a point, or null when it can't deliver there. */
    public function deliveryFee(Store $store, float $lat, float $lng): ?float
    {
        try {
            return $this->deliveryQuote($store, $lat, $lng)['fee'];
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * The parcel fare from the store to the drop point, in the store's zone,
     * for the "Food & shop order" category. Same formula as the app's parcel
     * estimate (CommonTrait::estimatedFare), without customer discounts.
     *
     * @throws ValidationException with a customer-readable reason
     */
    protected function deliveryQuote(Store $store, float $lat, float $lng): array
    {
        $fail = fn(string $msg) => throw ValidationException::withMessages(['delivery' => $msg]);
        $settings = StoreSettings::all();

        $zone = $store->zone_id ? Zone::where('id', $store->zone_id)->where('is_active', 1)->first() : null;
        if (!$zone) {
            $fail(translate('This store is outside our delivery area right now.'));
        }
        $pickup = new Point($store->latitude, $store->longitude);
        $dropPoint = new Point($lat, $lng);
        if (!$this->zoneService->getZoneContainingBothPoints($zone->id, $pickup, $dropPoint)) {
            $fail(translate('This address is outside the delivery area of this store.'));
        }

        $weight = ParcelWeight::where('is_active', 1)
            ->where('min_weight', '<=', $settings['parcel_weight'])
            ->where('max_weight', '>=', $settings['parcel_weight'])
            ->first();
        $categoryId = $settings['parcel_category_id'];
        if (!$weight || !$categoryId) {
            $fail(translate('Delivery is not set up yet. Please try again later.'));
        }
        $where = [['parcel_weight_id', '=', $weight->id], ['zone_id', '=', $zone->id], ['parcel_category_id', '=', $categoryId]];
        $tripFare = ParcelFare::where('zone_id', $zone->id)
            ->whereHas('fares', fn($q) => $q->where($where))
            ->with(['fares' => fn($q) => $q->where($where), 'zone'])
            ->first();
        if (!$tripFare) {
            Log::warning('Food & Shops: no parcel fare for the food category', ['zone' => $zone->id, 'category' => $categoryId, 'weight' => $weight->id]);
            $fail(translate('Delivery is not set up yet. Please try again later.'));
        }

        $routes = getRoutes(
            originCoordinates: [$store->latitude, $store->longitude],
            destinationCoordinates: [$lat, $lng],
            intermediateCoordinates: [],
            drivingMode: ['TWO_WHEELER'],
        );
        if (array_key_exists('error', $routes)) {
            $fail(translate('We could not find a route to this address.'));
        }

        $estimate = (new class {
            use CommonTrait;
        })->estimatedFare(tripRequest: ['type' => PARCEL], routes: $routes, zone_id: $zone->id, zone: $zone, tripFare: $tripFare, beforeCreate: true);

        $surcharged = ($estimate['extra_fare_fee'] ?? 0) > 0 || ($estimate['surge_multiplier'] ?? 0) > 0;
        $fee = round($surcharged ? $estimate['extra_estimated_fare'] : $estimate['estimated_fare']);

        return [
            'fee' => (float)$fee,
            'zone_id' => $zone->id,
            'distance_km' => round((float)$estimate['estimated_distance'], 1),
            'duration_min' => max(1, (int)ceil(($routes[0]['duration_sec'] ?? 0) / 60)),
            'encoded_polyline' => $estimate['encoded_polyline'],
            'return_fee' => $surcharged ? $estimate['extra_return_fee'] : $estimate['return_fee'],
            'cancellation_fee' => $surcharged ? $estimate['extra_cancellation_fee'] : $estimate['cancellation_fee'],
            'extra_fare_fee' => $estimate['extra_fare_fee'] ?? 0,
            'extra_fare_amount' => $estimate['extra_fare_amount'] ?? 0,
            'surge_multiplier' => $estimate['surge_multiplier'] ?? 0,
            'parcel_category_id' => $categoryId,
            'weight' => $settings['parcel_weight'],
        ];
    }

    /* ------------------------------------------------------------ place and pay */

    public function place(User $customer, Store $store, array $input): StoreOrder
    {
        $way = in_array($input['fulfilment'] ?? null, StoreOrder::WAYS, true) ? $input['fulfilment'] : StoreOrder::DELIVERY;
        $arriveAt = $way !== StoreOrder::DELIVERY && filled($input['arrive_at'] ?? null) ? Carbon::parse($input['arrive_at']) : null;
        $drop = ['latitude' => $input['drop_latitude'] ?? null, 'longitude' => $input['drop_longitude'] ?? null];
        $quote = $this->quote($store, $input['items'] ?? [], $drop, $way, $arriveAt);
        if (!$quote['ok']) {
            throw ValidationException::withMessages(['order' => $quote['problems']]);
        }
        if ($way !== StoreOrder::DELIVERY) {
            // No address to deliver to: the order is had at the store.
            $input['drop_address'] = $store->address;
            $input['drop_latitude'] = $store->latitude;
            $input['drop_longitude'] = $store->longitude;
        }

        $order = DB::transaction(function () use ($customer, $store, $input, $quote, $way, $arriveAt) {
            $order = StoreOrder::create([
                'customer_id' => $customer->id,
                'store_id' => $store->id,
                'zone_id' => $quote['delivery']['zone_id'] ?? $store->zone_id,
                'fulfilment' => $way,
                'arrive_at' => $arriveAt,
                'guests' => $way === StoreOrder::DINE_IN ? max(1, min(30, (int)($input['guests'] ?? 2))) : null,
                // Sent to the restaurant: the customer pays once it is confirmed.
                'status' => StoreOrder::AWAITING_CONFIRMATION,
                'placed_at' => now(),
                'items_subtotal' => $quote['items_subtotal'],
                'delivery_fee' => $quote['delivery_fee'],
                'total' => $quote['total'],
                'payment_method' => $input['payment_method'],
                'payment_status' => StoreOrder::UNPAID,
                'drop_address' => trim($input['drop_address']),
                'drop_latitude' => (float)$input['drop_latitude'],
                'drop_longitude' => (float)$input['drop_longitude'],
                'receiver_name' => trim($input['receiver_name'] ?? '') ?: trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
                'receiver_phone' => trim($input['receiver_phone'] ?? '') ?: (string)$customer->phone,
                'note' => $input['note'] ?? null,
                'delivery_quote' => $quote['delivery'],
                'quote_expires_at' => now()->addMinutes(self::QUOTE_MINUTES),
                'prep_minutes' => $store->default_prep_minutes,
            ]);
            $order->items()->createMany($quote['items']);
            DB::afterCommit(function () use ($order) {
                $this->notifyCustomer($order, 'sent');
                $this->alertPartner($order);
            });
            return $order;
        });

        return $order->fresh(['items', 'store']);
    }

    /**
     * Pay a confirmed order: from the wallet now, or a gateway link (null for
     * the wallet). The kitchen starts when the payment lands (markPaid).
     *
     * @throws ValidationException
     */
    public function payConfirmed(StoreOrder $order, string $method): ?string
    {
        if (!$order->isConfirmedUnpaid() || $order->payment_status !== StoreOrder::UNPAID) {
            throw ValidationException::withMessages(['order' => translate('This order is not waiting for payment.')]);
        }
        if ($order->pay_by && $order->pay_by->isPast()) {
            throw ValidationException::withMessages(['order' => translate('The time to pay for this order is over. Please order again.')]);
        }
        $order->payment_method = $method;
        $order->save();
        if ($method === 'wallet') {
            $this->payWithWallet($order);
            return null;
        }
        // Opened in the app's payment webview, which redirects to the gateway.
        return route('store-order.pay', ['order' => $order->id, 'payment_method' => $method]);
    }

    /* ------------------------------------------------------------ before payment */

    /**
     * Dishes the restaurant can offer instead: on its menu and available at
     * this kitchen right now.
     */
    public function swapOptions(StoreOrder $order): array
    {
        $kitchen = $order->store;
        $brand = $kitchen->brand()->load(['categories', 'items']);
        $brand->linkMenu();
        $states = $kitchen->stateMap();
        return $brand->items
            ->filter(fn(StoreItem $i) => $i->menuState(null, $states->get($i->id)) === StoreItem::AVAILABLE)
            ->map(fn(StoreItem $i) => ['id' => $i->id, 'name' => $i->name, 'price' => $i->price, 'price_text' => getCurrencyFormat($i->price)])
            ->values()->all();
    }

    /**
     * The restaurant suggests changes. $changes, one per order line in order:
     * ['quantity' => n] (0 = removed) and optionally ['swap_item_id' => id]
     * to offer another dish instead. The customer then accepts or cancels.
     *
     * @throws ValidationException
     */
    public function proposeChanges(StoreOrder $order, array $changes, ?string $note): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) use ($changes, $note) {
            if ($order->status !== StoreOrder::AWAITING_CONFIRMATION) {
                throw ValidationException::withMessages(['order' => translate('This order changed in the meantime. The board has been refreshed.')]);
            }
            $swaps = collect($this->swapOptions($order))->keyBy('id');
            $lines = [];
            $changed = false;
            foreach ($order->items()->orderBy('id')->get()->values() as $i => $line) {
                $c = $changes[$i] ?? [];
                $qty = max(0, min(self::MAX_QTY, (int)($c['quantity'] ?? $line->quantity)));
                $swapId = $c['swap_item_id'] ?? null;
                $entry = ['was_name' => $line->name, 'was_quantity' => $line->quantity];
                if ($swapId && $swapId !== $line->store_item_id) {
                    $swap = $swaps->get($swapId);
                    if (!$swap) {
                        throw ValidationException::withMessages(['order' => translate('That dish is not available to offer right now.')]);
                    }
                    $qty = max(1, $qty);
                    $entry += ['change' => 'swapped', 'store_item_id' => $swap['id'], 'name' => $swap['name'], 'unit_price' => round($swap['price'], 2), 'quantity' => $qty];
                } elseif ($qty === 0) {
                    $entry += ['change' => 'removed', 'store_item_id' => $line->store_item_id, 'name' => $line->name, 'unit_price' => $line->unit_price, 'quantity' => 0];
                } else {
                    $entry += ['change' => $qty < $line->quantity ? 'reduced' : ($qty > $line->quantity ? 'increased' : 'same'),
                        'store_item_id' => $line->store_item_id, 'name' => $line->name, 'unit_price' => $line->unit_price, 'quantity' => $qty];
                }
                $entry['line_total'] = round($entry['unit_price'] * $entry['quantity'], 2);
                $changed = $changed || $entry['change'] !== 'same';
                $lines[] = $entry;
            }
            if (!$changed) {
                throw ValidationException::withMessages(['order' => translate('Nothing was changed. Confirm the order as it is, or decline it.')]);
            }
            if (!collect($lines)->contains(fn($l) => $l['quantity'] > 0)) {
                throw ValidationException::withMessages(['order' => translate('Every dish was removed. Decline the order instead, with a reason.')]);
            }
            $subtotal = round(collect($lines)->sum('line_total'), 2);
            $order->proposal = [
                'lines' => $lines,
                'note' => $note ? mb_substr(trim($note), 0, 300) : null,
                'items_subtotal' => $subtotal,
                'total' => round($subtotal + $order->delivery_fee, 2),
            ];
            $order->proposed_at = now();
            $order->status = StoreOrder::CHANGES_PROPOSED;
            $order->save();
            DB::afterCommit(fn() => $this->notifyCustomer($order, 'changes'));
            return $order;
        });
    }

    /**
     * The customer's answer to suggested changes: accept (the order becomes the
     * proposal and waits for payment) or refuse (cancelled, nothing was paid).
     *
     * @throws ValidationException
     */
    public function answerChanges(StoreOrder $order, bool $accept): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) use ($accept) {
            if ($order->status !== StoreOrder::CHANGES_PROPOSED || !$order->proposal) {
                throw ValidationException::withMessages(['order' => translate('These changes are no longer open.')]);
            }
            if (!$accept) {
                $order->status = StoreOrder::CANCELLED;
                $order->cancelled_at = now();
                $order->cancelled_by = 'customer';
                $order->cancel_reason = translate('The customer did not accept the changes.');
                $order->save();
                return $order;
            }
            $lines = collect($order->proposal['lines'])->filter(fn($l) => $l['quantity'] > 0);
            $order->items()->delete();
            $order->items()->createMany($lines->map(fn($l) => [
                'store_item_id' => $l['store_item_id'], 'name' => $l['name'], 'unit_price' => $l['unit_price'],
                'quantity' => $l['quantity'], 'line_total' => $l['line_total'],
            ])->values()->all());
            $order->items_subtotal = $order->proposal['items_subtotal'];
            $order->total = $order->proposal['total'];
            $this->markConfirmed($order);
            $order->save();
            return $order;
        });
    }

    protected function markConfirmed(StoreOrder $order): void
    {
        $order->status = StoreOrder::AWAITING_PAYMENT;
        $order->confirmed_at = now();
        $order->pay_by = now()->addMinutes(self::PAY_MINUTES);
    }

    public function payWithWallet(StoreOrder $order): StoreOrder
    {
        // Ways to pay: the admin may turn the wallet off.
        if (!\Modules\TripManagement\Lib\PaymentWays::wallet()) {
            throw ValidationException::withMessages(['payment' => \Modules\TripManagement\Lib\PaymentWays::refusal('wallet')]);
        }
        return DB::transaction(function () use ($order) {
            $order = StoreOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== StoreOrder::AWAITING_PAYMENT || $order->payment_status !== StoreOrder::UNPAID) {
                return $order;
            }
            $account = UserAccount::where('user_id', $order->customer_id)->lockForUpdate()->first();
            if (!$account || $account->wallet_balance + 0.00001 < $order->total) {
                throw ValidationException::withMessages(['payment_method' => translate('Your wallet balance is too low for this order.')]);
            }
            $account->wallet_balance -= $order->total;
            $account->save();
            Transaction::create([
                'attribute' => 'store_order_payment',
                'attribute_id' => $order->id,
                'debit' => $order->total,
                'balance' => $account->wallet_balance,
                'user_id' => $order->customer_id,
                'account' => 'wallet_balance',
                'trx_type' => 'Food & shop order paid from customer wallet',
            ]);
            return $this->markPaid($order, 'wallet');
        });
    }

    public function paymentLink(StoreOrder $order, string $gateway): string
    {
        $customer = $order->customer;
        $paymentInfo = new PaymentInfo(
            hook: 'storeOrderPaymentUpdate',
            currencyCode: businessConfig('currency_code')?->value ?? 'XAF',
            paymentMethod: $gateway,
            paymentPlatform: 'mono',
            payerId: $customer->id,
            receiverId: '100',
            additionalData: [
                'business_name' => businessConfig('business_name')?->value,
                'business_logo' => dynamicStorage('storage/app/public/business') . '/' . businessConfig('header_logo')?->value,
            ],
            paymentAmount: $order->total,
            externalRedirectLink: null,
            attribute: 'store_order',
            attributeId: $order->id,
        );
        $payer = new Payer(name: $customer->first_name, email: $customer->email, phone: $customer->phone, address: '');
        $link = $this->generate_link($payer, $paymentInfo, new Receiver('receiver_name', 'example.png'));
        if (!$link) {
            throw ValidationException::withMessages(['payment_method' => translate('This payment method is not available.')]);
        }
        return $link;
    }

    public function onGatewayPaid(PaymentRequest $paymentRequest): void
    {
        // Some gateways call the hook on failure too. Only real money counts.
        if (!(int)$paymentRequest->is_paid) {
            return;
        }
        DB::transaction(function () use ($paymentRequest) {
            $order = StoreOrder::whereKey($paymentRequest->attribute_id)->lockForUpdate()->first();
            if (!$order) {
                return;
            }
            // The same payment reported twice (some gateways do): already applied.
            if ($order->payment_request_id === $paymentRequest->id) {
                return;
            }
            if ($order->status === StoreOrder::AWAITING_PAYMENT && $order->payment_status === StoreOrder::UNPAID) {
                $order->payment_request_id = $paymentRequest->id;
                $this->markPaid($order, $paymentRequest->payment_method);
                return;
            }
            // Paid after the order closed, or paid twice. Mobile Money links are
            // expired when the time to pay ends (closePayments), so this is rare.
            $this->onLatePayment($order->id, (float)$paymentRequest->payment_amount, (string)$paymentRequest->id);
        });
    }

    /**
     * Why this order can no longer be paid, or null while it can. The payment
     * gateway asks before opening a payment page and before accepting money.
     * $starting: a new payment page (refused once the time to pay is over);
     * otherwise money already sent (accepted while the order still waits for
     * it, which covers a customer still approving on their phone at the end).
     */
    public function paymentClosedReason(string $orderId, bool $starting): ?string
    {
        $order = StoreOrder::find($orderId);
        if (!$order) {
            return translate('This order no longer exists.');
        }
        if ($order->status !== StoreOrder::AWAITING_PAYMENT || $order->payment_status !== StoreOrder::UNPAID) {
            return $order->payment_status === StoreOrder::PAID
                ? translate('This order is already paid.')
                : translate('The time to pay for this order is over. Please order again.');
        }
        if ($starting && $order->pay_by && $order->pay_by->isPast()) {
            return translate('The time to pay for this order is over. Please order again.');
        }
        return null;
    }

    /**
     * Money arrived for an order that is no longer waiting for it. It is not
     * put in the wallet: the admin is alerted to send it back by Mobile Money,
     * and the customer is told so. Once per payment.
     */
    public function onLatePayment(string $orderId, float $amount, string $reference): void
    {
        if (!Cache::add('store-order-late-payment:' . $reference, 1, now()->addDays(30))) {
            return;
        }
        $order = StoreOrder::find($orderId);
        if (!$order) {
            return;
        }
        Log::warning('Food & Shops payment arrived after the order closed', ['order' => $order->id, 'amount' => $amount, 'reference' => $reference]);
        $this->alertAdmin($order, 'store_order_late_payment_send_it_back');
        DB::afterCommit(fn() => $this->notifyCustomer($order, 'paid_late', $amount));
    }

    /**
     * Before an unpaid order expires: settle what is in flight and close its
     * Mobile Money links so nobody can pay after the time is over.
     * False while it must wait: paid just now (the order moved on), or the
     * customer is approving the payment on their phone (a few minutes' grace).
     */
    protected function closePayments(StoreOrder $order): bool
    {
        $requests = PaymentRequest::where('attribute', 'store_order')->where('attribute_id', $order->id)->where('is_paid', 0)->get();
        if ($requests->isEmpty()) {
            return true;
        }
        $fapshi = app(\Modules\Gateways\Services\FapshiPayments::class);
        $graceOver = !$order->pay_by || $order->pay_by->lt(now()->subMinutes(self::PAYING_GRACE_MINUTES));
        foreach ($requests as $request) {
            foreach ($fapshi->openAttempts($request) as $attempt) {
                $status = $fapshi->sync($attempt);
                if ($status === \Modules\Gateways\Entities\FapshiTransaction::SUCCESSFUL) {
                    return false;
                }
                if ($status === \Modules\Gateways\Entities\FapshiTransaction::PENDING && !$graceOver) {
                    return false;
                }
                if (!$fapshi->expire($attempt) && !$graceOver) {
                    return false;
                }
            }
        }
        return true;
    }

    protected function markPaid(StoreOrder $order, string $method): StoreOrder
    {
        $order->payment_status = StoreOrder::PAID;
        $order->payment_method = $method;
        $order->paid_at = now();
        if ($order->confirmed_at) {
            // Confirmed before payment: the kitchen starts now.
            $order->status = StoreOrder::ACCEPTED;
            $order->accepted_at = now();
            $order->ready_at = now()->addMinutes(max(5, (int)$order->prep_minutes));
            if (!$order->isDelivery()) {
                // Ready for when they come, and a code they show at the restaurant.
                if ($order->arrive_at && $order->arrive_at->gt($order->ready_at)) {
                    $order->ready_at = $order->arrive_at;
                }
                $order->collect_code = (string)random_int(1000, 9999);
                $order->save();
                DB::afterCommit(fn() => $this->notifyCustomer($order, 'accepted_here'));
                return $order;
            }
            $order->save();
            DB::afterCommit(fn() => $this->notifyCustomer($order, 'accepted'));
            if ((int)$order->prep_minutes <= StoreSettings::all()['rider_lead_minutes']) {
                $this->requestRiderLocked($order);
            }
            return $order;
        }
        // Older flow: paid first, the store accepts after.
        $order->status = StoreOrder::PLACED;
        $order->placed_at = now();
        $order->save();
        DB::afterCommit(function () use ($order) {
            $this->notifyCustomer($order, 'placed');
            $this->alertPartner($order);
        });
        return $order;
    }

    /* ------------------------------------------------------------ partner and customer actions */

    public function accept(StoreOrder $order, int $prepMinutes): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) use ($prepMinutes) {
            $prepMinutes = max(5, min(180, $prepMinutes));
            // Confirmed before payment: the customer pays, then the kitchen starts.
            if ($order->status === StoreOrder::AWAITING_CONFIRMATION) {
                $order->prep_minutes = $prepMinutes;
                $this->markConfirmed($order);
                $order->save();
                DB::afterCommit(fn() => $this->notifyCustomer($order, 'confirmed'));
                return $order;
            }
            if ($order->status !== StoreOrder::PLACED) {
                return $order;
            }
            $order->status = StoreOrder::ACCEPTED;
            $order->prep_minutes = $prepMinutes;
            $order->accepted_at = now();
            $order->ready_at = now()->addMinutes($prepMinutes);
            $order->save();
            DB::afterCommit(fn() => $this->notifyCustomer($order, 'accepted'));
            // Short prep times call a rider straight away.
            if ($prepMinutes <= StoreSettings::all()['rider_lead_minutes']) {
                $this->requestRiderLocked($order);
            }
            return $order;
        });
    }

    /**
     * The restaurant declines or cancels, only before payment: nothing to refund.
     * A paid order goes ahead (the restaurant confirmed it before the customer paid).
     * Older pay-first orders (placed) are still refunded when declined.
     *
     * @throws ValidationException when the order is already paid
     */
    public function reject(StoreOrder $order, string $reason, string $by = 'partner'): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) use ($reason, $by) {
            $open = in_array($order->status, [StoreOrder::AWAITING_CONFIRMATION, StoreOrder::CHANGES_PROPOSED, StoreOrder::PLACED], true)
                || $order->isConfirmedUnpaid();
            if (!$open) {
                // Paid, cooked, and no rider has taken it: E-Ride keeps calling riders
                // until the store decides to give up on it.
                if ($by === 'partner' && $order->status === StoreOrder::RIDER_REQUESTED && $order->payment_status === StoreOrder::PAID) {
                    return $this->cancelWithoutRider($order, $reason);
                }
                if ($order->payment_status === StoreOrder::PAID && $by === 'partner') {
                    throw ValidationException::withMessages(['order' => translate('This order is paid, so it can no longer be cancelled. Call the customer, or E-Ride support if there is a real problem.')]);
                }
                return $order;
            }
            if ($order->status === StoreOrder::PLACED && $order->payment_status === StoreOrder::PAID
                && !$this->refundOrder($order, $order->total)) {
                $this->alertAdmin($order, 'store_order_paid_cancelled_settle_with_customer');
            }
            $order->status = StoreOrder::REJECTED;
            $order->cancelled_at = now();
            $order->cancelled_by = $by;
            $order->cancel_reason = mb_substr($reason, 0, 255);
            $order->save();
            DB::afterCommit(fn() => $this->notifyCustomer($order, $by === 'timeout' ? 'timeout' : 'rejected'));
            return $order;
        });
    }

    public function cancelByCustomer(StoreOrder $order): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) {
            if (!in_array($order->status, [StoreOrder::AWAITING_CONFIRMATION, StoreOrder::CHANGES_PROPOSED, StoreOrder::AWAITING_PAYMENT, StoreOrder::PLACED], true)) {
                throw ValidationException::withMessages(['order' => translate('The store has already started on this order, so it can no longer be cancelled here. Contact support if there is a problem.')]);
            }
            if ($order->payment_status === StoreOrder::PAID) {
                if (!StoreSettings::refunds()) {
                    throw ValidationException::withMessages(['order' => translate('This order is paid, so it can no longer be cancelled. Call the store, or E-Ride support if there is a problem.')]);
                }
                $this->refundOrder($order, $order->total);
            }
            $order->status = StoreOrder::CANCELLED;
            $order->cancelled_at = now();
            $order->cancelled_by = 'customer';
            $order->save();
            return $order;
        });
    }

    /**
     * The store gives up on a paid order no rider has taken. Caller holds the
     * order lock. Refunded when refunds are on; otherwise E-Ride's team is
     * alerted and settles with the customer.
     *
     * @throws ValidationException when a rider has just accepted it
     */
    protected function cancelWithoutRider(StoreOrder $order, string $reason): StoreOrder
    {
        $this->releaseUnclaimedTrip($order, translate('A rider has just accepted this delivery, so the order goes ahead.'));
        if (!$this->refundOrder($order, $order->total)) {
            $this->alertAdmin($order, 'store_order_paid_cancelled_settle_with_customer');
        }
        $order->status = StoreOrder::CANCELLED;
        $order->cancelled_at = now();
        $order->cancelled_by = 'partner';
        $order->cancel_reason = mb_substr($reason, 0, 255);
        $order->save();
        DB::afterCommit(fn() => $this->notifyCustomer($order, 'cancelled'));
        return $order;
    }

    /**
     * Cancels the order's delivery trip if no rider has taken it, and stops it
     * ringing on the riders it was offered to. Caller holds the order lock.
     *
     * @throws ValidationException when a rider has accepted it
     */
    protected function releaseUnclaimedTrip(StoreOrder $order, string $taken): void
    {
        // Locked: a rider accepting at this moment either wins (and the cancel is
        // refused) or finds the trip cancelled.
        $trip = $order->trip_request_id ? TripRequest::whereKey($order->trip_request_id)->lockForUpdate()->first() : null;
        if ($trip && ($trip->driver_id || !in_array($trip->current_status, [PENDING, CANCELLED], true))) {
            throw ValidationException::withMessages(['order' => $taken]);
        }
        if (!$trip || $trip->current_status !== PENDING) {
            return;
        }
        $trip->update(['current_status' => CANCELLED]);
        $trip->tripStatus()->update([CANCELLED => now()]);
        $tripId = $trip->id;
        DB::afterCommit(function () use ($tripId) {
            $trip = TripRequest::find($tripId);
            $offers = \Modules\TripManagement\Entities\TempTripNotification::with('user')->where('trip_request_id', $tripId)->get();
            if (checkReverbConnection()) {
                foreach ($offers as $offer) {
                    try {
                        $offer->user && \App\Events\CustomerTripCancelledEvent::broadcast($offer->user, $trip);
                    } catch (\Throwable) {
                    }
                }
            }
            $push = getNotification('customer_canceled_trip');
            foreach ($offers as $offer) {
                if ($offer->user?->fcm_token) {
                    sendDeviceNotification(fcm_token: $offer->user->fcm_token,
                        title: translate(key: $push['title'], locale: $offer->user->current_language_key),
                        description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, sentTime: pushSentTime($trip->updated_at), locale: $offer->user->current_language_key),
                        status: $push['status'], ride_request_id: $trip->id, type: $trip->type,
                        notification_type: 'parcel', action: $push['action'], user_id: $offer->user->id);
                }
            }
            \Modules\TripManagement\Entities\TempTripNotification::where('trip_request_id', $tripId)->delete();
        });
    }

    public function cancelByAdmin(StoreOrder $order, string $reason, bool $refund = true): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) use ($reason, $refund) {
            if (in_array($order->status, StoreOrder::FINAL, true) || $order->status === StoreOrder::PICKED_UP) {
                throw ValidationException::withMessages(['order' => translate('This order can no longer be cancelled.')]);
            }
            $this->releaseUnclaimedTrip($order, translate('A rider has already accepted this delivery. Ask the rider to cancel it first.'));
            if ($refund && $order->payment_status === StoreOrder::PAID) {
                $this->refundOrder($order, $order->total);
            }
            $order->status = StoreOrder::CANCELLED;
            $order->cancelled_at = now();
            $order->cancelled_by = 'admin';
            $order->cancel_reason = mb_substr($reason, 0, 255);
            $order->save();
            DB::afterCommit(fn() => $this->notifyCustomer($order, 'cancelled'));
            return $order;
        });
    }

    public function resolveFailedDelivery(StoreOrder $order, bool $refundFood, ?string $note = null): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) use ($refundFood, $note) {
            if ($order->status !== StoreOrder::DELIVERY_FAILED) {
                return $order;
            }
            // The delivery fee was already returned to the wallet by the
            // parcel return flow; this settles the food.
            if ($refundFood && $order->payment_status === StoreOrder::PAID) {
                $this->refundOrder($order, $order->items_subtotal);
            }
            $order->cancel_reason = mb_substr(trim(($order->cancel_reason ? $order->cancel_reason . ' | ' : '') . ($note ?? '') . ($refundFood ? ' [food refunded]' : ' [no food refund]')), 0, 255);
            $order->save();
            return $order;
        });
    }

    public function markReady(StoreOrder $order): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) {
            if ($order->status === StoreOrder::ACCEPTED) {
                $order->ready_at = now();
                if (!$order->isDelivery()) {
                    $first = $order->ready_told_at === null;
                    $order->ready_told_at = now();
                    $order->save();
                    if ($first) {
                        DB::afterCommit(fn() => $this->notifyCustomer($order, 'ready_here'));
                    }
                    return $order;
                }
                $order->save();
                $this->requestRiderLocked($order);
            }
            return $order;
        });
    }

    /**
     * Eat there or collect: the customer is here and shows their code; the
     * staff type it and the order is done.
     *
     * @throws ValidationException when the code is wrong or the order is not waiting to be handed over
     */
    public function handOver(StoreOrder $order, string $code): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) use ($code) {
            if ($order->isDelivery() || $order->status !== StoreOrder::ACCEPTED) {
                throw ValidationException::withMessages(['order' => translate('This order is not waiting to be handed over.')]);
            }
            if (!hash_equals((string)$order->collect_code, trim($code))) {
                throw ValidationException::withMessages(['code' => translate('That is not the code of this order. Ask the customer to open the order in the app.')]);
            }
            $order->status = StoreOrder::DELIVERED;
            $order->delivered_at = now();
            $order->partner_earning = $order->items_subtotal;
            $order->switch = null;
            $order->save();
            DB::afterCommit(fn() => $this->notifyCustomer($order, 'handed_over'));
            return $order;
        });
    }

    /**
     * "Deliver it instead": an eat-there or collect order becomes a delivery.
     * Before payment the order simply changes (the total now has the delivery
     * fee). After payment the change waits for the delivery fee: the answer is
     * the page to pay it, and the order changes when the money lands.
     *
     * @return array{order: StoreOrder, pay_url: ?string}
     * @throws ValidationException
     */
    public function switchToDelivery(StoreOrder $order, array $drop, ?string $method = null): array
    {
        $payUrl = null;
        $order = $this->locked($order, function (StoreOrder $order) use ($drop, $method, &$payUrl) {
            $open = in_array($order->status, [StoreOrder::AWAITING_CONFIRMATION, StoreOrder::AWAITING_PAYMENT, StoreOrder::ACCEPTED], true);
            if ($order->isDelivery() || !$open) {
                throw ValidationException::withMessages(['order' => translate('This order can no longer be changed to a delivery.')]);
            }
            // Throws when E-Ride cannot deliver there.
            $quote = $this->deliveryQuote($order->store, (float)$drop['drop_latitude'], (float)$drop['drop_longitude']);
            $change = [
                'fee' => (float)$quote['fee'],
                'quote' => $quote,
                'drop_address' => trim($drop['drop_address']),
                'drop_latitude' => (float)$drop['drop_latitude'],
                'drop_longitude' => (float)$drop['drop_longitude'],
            ];
            if ($order->payment_status !== StoreOrder::PAID) {
                $this->applySwitch($order, $change, false);
                $order->save();
                return $order;
            }
            // Paid: the fee first.
            if (!$method) {
                throw ValidationException::withMessages(['payment_method' => translate('Choose how to pay the delivery fee.')]);
            }
            $order->switch = $change + ['pay_by' => now()->addMinutes(self::PAY_MINUTES)->toIso8601String(), 'method' => $method];
            $order->save();
            $payUrl = route('store-order.pay-delivery', ['order' => $order->id, 'payment_method' => $method]);
            return $order;
        });
        return ['order' => $order, 'pay_url' => $payUrl];
    }

    public function keepWay(StoreOrder $order): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) {
            if ($order->switch && !$order->isDelivery()) {
                $order->switch = null;
                $order->save();
            }
            return $order;
        });
    }

    /** The order becomes a delivery to the address of the change. Caller holds the lock and saves. */
    protected function applySwitch(StoreOrder $order, array $change, bool $feePaid): void
    {
        $order->fulfilment = StoreOrder::DELIVERY;
        $order->drop_address = $change['drop_address'];
        $order->drop_latitude = $change['drop_latitude'];
        $order->drop_longitude = $change['drop_longitude'];
        $order->delivery_quote = $change['quote'];
        $order->zone_id = $change['quote']['zone_id'] ?? $order->zone_id;
        $order->delivery_fee = $change['fee'];
        $order->total = round((float)$order->items_subtotal + (float)$change['fee'], 2);
        $order->arrive_at = null;
        $order->guests = null;
        $order->collect_code = null;
        $order->switch = null;
        if ($feePaid && $order->status === StoreOrder::ACCEPTED) {
            // The kitchen may be ahead already: a rider as soon as the food is close to ready.
            $soonest = now()->addMinutes(5);
            if (!$order->ready_at || $order->ready_at->gt(now()->addMinutes(max(5, (int)$order->prep_minutes)))) {
                $order->ready_at = now()->addMinutes(max(5, (int)$order->prep_minutes));
            } elseif ($order->ready_at->lt($soonest)) {
                $order->ready_at = $soonest;
            }
        }
    }

    /** The page to pay the delivery fee of a pending change to delivery. */
    public function deliveryFeeLink(StoreOrder $order, string $gateway): string
    {
        $change = $order->switch;
        if (!$change || $order->isDelivery() || $order->status !== StoreOrder::ACCEPTED || Carbon::parse($change['pay_by'])->isPast()) {
            throw ValidationException::withMessages(['order' => translate('There is no delivery fee to pay for this order.')]);
        }
        $customer = $order->customer;
        $paymentInfo = new PaymentInfo(
            hook: 'storeOrderDeliveryPaid',
            currencyCode: businessConfig('currency_code')?->value ?? 'XAF',
            paymentMethod: $gateway,
            paymentPlatform: 'mono',
            payerId: $customer->id,
            receiverId: '100',
            additionalData: [
                'business_name' => businessConfig('business_name')?->value,
                'business_logo' => dynamicStorage('storage/app/public/business') . '/' . businessConfig('header_logo')?->value,
            ],
            paymentAmount: (float)$change['fee'],
            externalRedirectLink: null,
            attribute: 'store_order_delivery',
            attributeId: $order->id,
        );
        $payer = new Payer(name: $customer->first_name, email: $customer->email, phone: $customer->phone, address: '');
        $link = $this->generate_link($payer, $paymentInfo, new Receiver('receiver_name', 'example.png'));
        if (!$link) {
            throw ValidationException::withMessages(['payment_method' => translate('This payment method is not available.')]);
        }
        return $link;
    }

    /** The delivery fee of a change to delivery was paid: the order becomes a delivery. */
    public function onDeliveryFeePaid(PaymentRequest $paymentRequest): void
    {
        if (!(int)$paymentRequest->is_paid) {
            return;
        }
        DB::transaction(function () use ($paymentRequest) {
            $order = StoreOrder::whereKey($paymentRequest->attribute_id)->lockForUpdate()->first();
            if (!$order || $order->delivery_payment_request_id === $paymentRequest->id) {
                return;
            }
            if ($order->switch && !$order->isDelivery() && $order->status === StoreOrder::ACCEPTED) {
                $order->delivery_payment_request_id = $paymentRequest->id;
                $this->applySwitch($order, $order->switch, true);
                $order->save();
                DB::afterCommit(fn() => $this->notifyCustomer($order, 'now_delivery'));
                if ($order->ready_at && $order->ready_at->lte(now()->addMinutes(StoreSettings::all()['rider_lead_minutes']))) {
                    $this->requestRiderLocked($order);
                }
                return;
            }
            // Paid when the change no longer applies (handed over meanwhile): flagged like any late payment.
            $this->onLatePayment($order->id, (float)$paymentRequest->payment_amount, (string)$paymentRequest->id);
        });
    }

    public function requestRider(StoreOrder $order): StoreOrder
    {
        return $this->locked($order, fn(StoreOrder $order) => $this->requestRiderLocked($order));
    }

    /* ------------------------------------------------------------ delivery */

    /** Creates the paid parcel trip and calls nearby riders. Caller holds the lock. */
    protected function requestRiderLocked(StoreOrder $order): StoreOrder
    {
        if (!in_array($order->status, [StoreOrder::ACCEPTED, StoreOrder::RIDER_REQUESTED], true) || !$order->isDelivery()) {
            return $order;
        }
        if ($order->trip_request_id) {
            $current = TripRequest::find($order->trip_request_id);
            if ($current && !in_array($current->current_status, [CANCELLED], true)) {
                return $order;
            }
        }

        $store = $order->store;
        $quote = $order->delivery_quote ?? [];
        // Capped so the PREPAID warning and store phone survive the 255-char note limit.
        $summary = mb_strimwidth($order->load('items')->itemsSummary(), 0, 140, '...');
        $pickupNote = translate(key: '{store} order #{ref}: {items}. PREPAID, do not collect money.', replace: [
            'store' => $store->displayName(), 'ref' => $order->ref_id, 'items' => $summary,
        ]);
        // The driver's call button reaches the customer; at pickup they need the store.
        if ($store->phone) {
            $pickupNote .= ' ' . translate(key: 'Store phone {phone}', replace: ['phone' => $store->phone]);
        }
        $note = trim($pickupNote . ($order->note ? ' ' . translate('Customer note') . ': ' . $order->note : ''));

        $trip = app(TripRequestServiceInterface::class)->createRideRequest([
            'customer_id' => $order->customer_id,
            'zone_id' => $quote['zone_id'] ?? $order->zone_id,
            'pickup_coordinates' => new Point($store->latitude, $store->longitude),
            'destination_coordinates' => new Point($order->drop_latitude, $order->drop_longitude),
            'customer_request_coordinates' => new Point($store->latitude, $store->longitude),
            'pickup_address' => $store->displayName() . ', ' . $store->address,
            'destination_address' => $order->drop_address,
            'intermediate_coordinates' => '[]',
            'intermediate_addresses' => null,
            'estimated_fare' => $order->delivery_fee,
            'actual_fare' => $order->delivery_fee,
            'return_fee' => $quote['return_fee'] ?? 0,
            'cancellation_fee' => $quote['cancellation_fee'] ?? 0,
            'extra_fare_fee' => $quote['extra_fare_fee'] ?? 0,
            'extra_fare_amount' => $quote['extra_fare_amount'] ?? 0,
            'surge_multiplier' => $quote['surge_multiplier'] ?? 0,
            'rise_request_count' => 0,
            'estimated_distance' => (string)($quote['distance_km'] ?? 0),
            'estimated_time' => (string)($quote['duration_min'] ?? 0),
            'encoded_polyline' => $quote['encoded_polyline'] ?? null,
            'payment_method' => 'digital',
            'pickup_note' => mb_substr($pickupNote, 0, 255),
            'note' => mb_substr($note, 0, 255),
            'type' => PARCEL,
            'ride_request_type' => null,
            'scheduled_at' => null,
            'payer' => 'sender',
            'weight' => $quote['weight'] ?? StoreSettings::all()['parcel_weight'],
            'parcel_category_id' => $quote['parcel_category_id'] ?? StoreSettings::all()['parcel_category_id'],
            'sender_name' => $store->displayName(),
            'sender_phone' => $store->phone,
            'sender_address' => $store->address,
            'receiver_name' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'receiver_address' => $order->drop_address,
        ]);
        // Prepaid: the customer never sees a payment screen for this trip.
        $trip->update(['payment_status' => PAID, 'paid_fare' => $order->delivery_fee]);

        $order->trip_request_id = $trip->id;
        $order->status = StoreOrder::RIDER_REQUESTED;
        $order->rider_attempts = $order->rider_attempts + 1;
        $order->rider_requested_at = now();
        $order->save();

        // From the third try on, every try: the order is paid and the food is waiting.
        if ($order->rider_attempts >= self::ADMIN_ALERT_AFTER_ATTEMPTS) {
            $this->alertAdmin($order, 'store_order_no_rider');
        }
        if ($order->rider_attempts === self::ADMIN_ALERT_AFTER_ATTEMPTS) {
            $orderForPush = $order;
            DB::afterCommit(fn() => $this->notifyCustomer($orderForPush, 'still_looking'));
        }

        $tripId = $trip->id;
        DB::afterCommit(function () use ($tripId) {
            $final = TripRequest::with(['driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund'])->find($tripId);
            $radius = (double)(get_cache('search_radius') ?? 5);
            $job = ProcessPushNotifications::dispatch(radius: $radius, trip: $final, parcelWeight: $final?->parcel?->weight);
            if (!app()->runningInConsole()) {
                $job->afterResponse();
            }
        });

        return $order;
    }

    public function review(StoreOrder $order, array $data): StoreReview
    {
        $review = null;
        $this->locked($order, function (StoreOrder $order) use ($data, &$review) {
            if (!$order->canBeReviewed()) {
                throw ValidationException::withMessages(['order' => translate('This order cannot be rated.')]);
            }
            $review = StoreReview::create([
                'store_order_id' => $order->id,
                'store_id' => $order->store_id,
                'customer_id' => $order->customer_id,
                'rating' => (int)$data['store_rating'],
                'comment' => filled($data['store_comment'] ?? null) ? trim($data['store_comment']) : null,
            ]);

            // The rider: a normal trip review, so it counts toward the driver's
            // rating and level like any ride. Skipped when customer reviews are
            // switched off or this trip was already rated.
            $trip = $order->trip_request_id ? TripRequest::find($order->trip_request_id) : null;
            if ($trip?->driver_id && !empty($data['rider_rating']) && (businessConfig('customer_review')?->value ?? 0)
                && !Review::where('trip_request_id', $trip->id)->where('given_by', $order->customer_id)->exists()) {
                Review::create([
                    'trip_request_id' => $trip->id,
                    'given_by' => $order->customer_id,
                    'received_by' => $trip->driver_id,
                    'trip_type' => $trip->type,
                    'rating' => (int)$data['rider_rating'],
                    'feedback' => filled($data['rider_comment'] ?? null) ? trim($data['rider_comment']) : null,
                ]);
                $tripId = $trip->id;
                DB::afterCommit(fn() => $this->notifyRiderOfReview($tripId));
            }
            return $order;
        });
        return $review;
    }

    public function syncFromTrip(StoreOrder $order): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) {
            if (!$order->trip_request_id || in_array($order->status, StoreOrder::FINAL, true)) {
                return $order;
            }
            $trip = TripRequest::with(['driver', 'fee', 'parcel', 'customer'])->find($order->trip_request_id);
            if (!$trip) {
                return $order;
            }

            switch ($trip->current_status) {
                case ACCEPTED:
                case OUT_FOR_PICKUP:
                    // Once per rider: this runs every minute while the rider is on the way.
                    if ($order->status === StoreOrder::RIDER_REQUESTED && $trip->driver_id
                        && Cache::add('store-order-rider-told:' . $order->id . ':' . $trip->driver_id, 1, now()->addDay())) {
                        DB::afterCommit(fn() => $this->notifyCustomer($order, 'rider_assigned'));
                    }
                    break;
                case ONGOING:
                    if ($order->status === StoreOrder::RIDER_REQUESTED) {
                        $this->bookDriverShare($trip);
                        $order->status = StoreOrder::PICKED_UP;
                        $order->picked_up_at = now();
                        $order->save();
                        DB::afterCommit(fn() => $this->notifyCustomer($order, 'picked_up'));
                    }
                    break;
                case COMPLETED:
                    if ($order->status === StoreOrder::RIDER_REQUESTED) {
                        // Completed without passing through "ongoing" here
                        // (missed event): book the share now.
                        $this->bookDriverShare($trip);
                    }
                    if (in_array($order->status, [StoreOrder::RIDER_REQUESTED, StoreOrder::PICKED_UP], true)) {
                        $order->status = StoreOrder::DELIVERED;
                        $order->picked_up_at = $order->picked_up_at ?? now();
                        $order->delivered_at = now();
                        $order->partner_earning = $order->items_subtotal;
                        $order->save();
                        DB::afterCommit(fn() => $this->notifyCustomer($order, 'delivered'));
                    }
                    break;
                case RETURNING:
                case RETURNED:
                    if ($order->status === StoreOrder::PICKED_UP) {
                        $order->status = StoreOrder::DELIVERY_FAILED;
                        $order->cancelled_at = now();
                        $order->cancelled_by = 'driver';
                        $order->cancel_reason = mb_substr((string)$trip->trip_cancellation_reason, 0, 255) ?: null;
                        $order->save();
                        $this->alertAdmin($order, 'store_order_delivery_failed');
                        DB::afterCommit(fn() => $this->notifyCustomer($order, 'delivery_failed'));
                        // The food comes back to the store, not to the customer: the store
                        // gives the rider the return code (also on its order board).
                        if ($trip->current_status === RETURNING && $trip->otp) {
                            $code = (string)$trip->otp;
                            DB::afterCommit(fn() => $this->tellStoreReturnCode($order, $code));
                        }
                    }
                    break;
                case CANCELLED:
                    if ($order->status === StoreOrder::RIDER_REQUESTED) {
                        // No rider took it, or the rider dropped it before
                        // pickup: ask again.
                        $this->requestRiderLocked($order);
                    }
                    break;
            }
            return $order;
        });
    }

    /** Sets the trip's commission and VAT, then books the driver's share. Once per trip. */
    protected function bookDriverShare(TripRequest $trip): void
    {
        if (!$trip->driver) {
            return;
        }
        $already = Transaction::where('attribute', 'driver_earning')->where('attribute_id', $trip->id)->exists();
        if ($already) {
            return;
        }
        $final = (new class {
            use CommonTrait;
        })->calculateFinalFare($trip, null);
        $trip->update([
            'paid_fare' => round($final['final_fare'], 2),
            'extra_fare_amount' => round($final['extra_fare_amount'], 2),
        ]);
        $trip->refresh()->load(['driver', 'fee']);
        (new class {
            use TransactionTrait;
        })->digitalPaymentTransaction($trip);
    }

    /* ------------------------------------------------------------ scheduler */

    public function tick(): array
    {
        $counts = ['expired' => 0, 'timed_out' => 0, 'riders' => 0, 'synced' => 0];
        $settings = StoreSettings::all();

        // Not paid in time: confirmed orders by pay_by, older ones after 30 minutes.
        StoreOrder::where('status', StoreOrder::AWAITING_PAYMENT)
            ->where(fn($q) => $q->where('pay_by', '<', now())
                ->orWhere(fn($w) => $w->whereNull('pay_by')->where('created_at', '<', now()->subMinutes(self::UNPAID_EXPIRY_MINUTES))))
            ->pluck('id')->each(function ($id) use (&$counts) {
                $order = StoreOrder::find($id);
                // Outside the order lock: this asks Mobile Money, and a payment that
                // lands meanwhile takes the lock itself to move the order on.
                try {
                    if ($order && !$this->closePayments($order)) {
                        return;
                    }
                } catch (Throwable $e) {
                    Log::warning('Food & Shops: could not close payments', ['order' => $id, 'error' => $e->getMessage()]);
                    if ($order?->pay_by && $order->pay_by->gt(now()->subMinutes(self::PAYING_GRACE_MINUTES))) {
                        return;
                    }
                }
                $this->locked($order, function (StoreOrder $order) use (&$counts) {
                    if ($order->status === StoreOrder::AWAITING_PAYMENT && $order->payment_status === StoreOrder::UNPAID) {
                        $order->status = StoreOrder::EXPIRED;
                        $order->cancelled_at = now();
                        $order->cancelled_by = 'system';
                        $order->cancel_reason = $order->confirmed_at ? translate('Not paid in time.') : null;
                        $order->save();
                        $counts['expired']++;
                        if ($order->confirmed_at) {
                            DB::afterCommit(fn() => $this->notifyCustomer($order, 'unpaid'));
                        }
                    }
                    return $order;
                });
            });

        // Suggested changes the customer did not answer.
        StoreOrder::where('status', StoreOrder::CHANGES_PROPOSED)
            ->where('proposed_at', '<', now()->subMinutes(self::ANSWER_MINUTES))
            ->pluck('id')->each(function ($id) use (&$counts) {
                $this->locked(StoreOrder::find($id), function (StoreOrder $order) use (&$counts) {
                    if ($order->status === StoreOrder::CHANGES_PROPOSED) {
                        $order->status = StoreOrder::EXPIRED;
                        $order->cancelled_at = now();
                        $order->cancelled_by = 'system';
                        $order->cancel_reason = translate('The customer did not answer the changes in time.');
                        $order->save();
                        $counts['expired']++;
                        DB::afterCommit(fn() => $this->notifyCustomer($order, 'changes_expired'));
                    }
                    return $order;
                });
            });

        StoreOrder::whereIn('status', [StoreOrder::AWAITING_CONFIRMATION, StoreOrder::PLACED])
            ->where('placed_at', '<', now()->subMinutes($settings['partner_response_minutes']))
            ->get()->each(function (StoreOrder $order) use (&$counts) {
                $this->reject($order, translate('The store did not respond in time.'), 'timeout');
                $counts['timed_out']++;
            });

        // Eat there or collect, never handed over: closed 6 hours after the time (paid, the restaurant prepared it).
        StoreOrder::where('status', StoreOrder::ACCEPTED)->where('fulfilment', '!=', StoreOrder::DELIVERY)
            ->where('arrive_at', '<', now()->subHours(6))
            ->pluck('id')->each(function ($id) {
                $this->locked(StoreOrder::find($id), function (StoreOrder $order) {
                    if ($order->status === StoreOrder::ACCEPTED && !$order->isDelivery()) {
                        $order->status = StoreOrder::DELIVERED;
                        $order->delivered_at = now();
                        $order->partner_earning = $order->items_subtotal;
                        $order->cancel_reason = translate('Closed on its own: the customer did not show the code.');
                        $order->switch = null;
                        $order->save();
                    }
                    return $order;
                });
            });

        StoreOrder::where('status', StoreOrder::ACCEPTED)
            ->where('fulfilment', StoreOrder::DELIVERY)
            ->whereNull('trip_request_id')
            ->where('ready_at', '<=', now()->addMinutes($settings['rider_lead_minutes']))
            ->get()->each(function (StoreOrder $order) use (&$counts) {
                $this->requestRider($order);
                $counts['riders']++;
            });

        StoreOrder::whereIn('status', [StoreOrder::RIDER_REQUESTED, StoreOrder::PICKED_UP])
            ->get()->each(function (StoreOrder $order) use (&$counts) {
                $this->syncFromTrip($order);
                $counts['synced']++;
            });

        return $counts;
    }

    /* ------------------------------------------------------------ helpers */

    protected function locked(?StoreOrder $order, callable $fn): ?StoreOrder
    {
        if (!$order) {
            return null;
        }
        return DB::transaction(function () use ($order, $fn) {
            $fresh = StoreOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            return $fn($fresh);
        });
    }

    /** Refunds to the wallet when Food & Shops refunds are on. False when nothing was credited. */
    protected function refundOrder(StoreOrder $order, float $amount): bool
    {
        $amount = round(min($amount, $order->total - $order->refunded_amount), 2);
        if ($amount <= 0 || !StoreSettings::refunds()) {
            return false;
        }
        $this->creditWallet($order->customer_id, $amount, $order->id, 'store_order_refund', 'Food & shop order refunded to customer wallet');
        $order->refunded_amount = round($order->refunded_amount + $amount, 2);
        $order->refunded_at = now();
        if ($order->refunded_amount + 0.00001 >= $order->total) {
            $order->payment_status = StoreOrder::REFUNDED;
        }
        return true;
    }

    protected function creditWallet(string $userId, float $amount, string $orderId, string $attribute, string $label, ?string $reference = null): void
    {
        $account = UserAccount::where('user_id', $userId)->lockForUpdate()->first();
        if (!$account) {
            $account = UserAccount::create(['user_id' => $userId]);
            $account = UserAccount::whereKey($account->id)->lockForUpdate()->first();
        }
        $account->wallet_balance += $amount;
        $account->save();
        Transaction::create([
            'attribute' => $attribute,
            'attribute_id' => $orderId,
            'credit' => $amount,
            'balance' => $account->wallet_balance,
            'user_id' => $userId,
            'account' => 'wallet_balance',
            'trx_ref_id' => $reference,
            'trx_type' => $label,
        ]);
    }

    protected function notifyCustomer(StoreOrder $order, string $event, ?float $paidAmount = null): void
    {
        try {
            $customer = User::find($order->customer_id);
            if (!$customer?->fcm_token) {
                return;
            }
            $store = $order->store?->displayName() ?? '';
            $locale = $customer->current_language_key;
            $amount = getCurrencyFormat($paidAmount ?? ($order->refunded_amount ?: $order->total));
            // Cancelled after payment with refunds off: no refund is promised.
            $paidNoRefund = $order->payment_status === StoreOrder::PAID && (float)$order->refunded_amount <= 0;
            $rider = trim((string)$order->trip?->driver?->first_name) ?: translate(key: 'Your rider', locale: $locale);
            [$title, $body] = match ($event) {
                'placed' => [
                    translate(key: 'Order sent to {store}', replace: ['store' => $store], locale: $locale),
                    translate(key: 'You will get a notification as soon as they accept it.', locale: $locale),
                ],
                'accepted' => [
                    translate(key: '{store} is preparing your order', replace: ['store' => $store], locale: $locale),
                    translate(key: 'Ready in about {minutes} min. A rider will bring it to you.', replace: ['minutes' => $order->prep_minutes], locale: $locale),
                ],
                'accepted_here' => [
                    translate(key: '{store} is preparing your order', replace: ['store' => $store], locale: $locale),
                    $order->arrive_at
                        ? translate(key: 'It will be ready for {time}. Show your code {code} when you arrive.', replace: ['time' => $order->arrive_at->format('H:i'), 'code' => $order->collect_code], locale: $locale)
                        : translate(key: 'Show your code {code} when you arrive.', replace: ['code' => $order->collect_code], locale: $locale),
                ],
                'ready_here' => [
                    translate(key: 'Your order is ready at {store}', replace: ['store' => $store], locale: $locale),
                    translate(key: 'Show your code {code} when you arrive.', replace: ['code' => $order->collect_code], locale: $locale),
                ],
                'handed_over' => [
                    translate(key: 'Enjoy! Your order at {store} is complete', replace: ['store' => $store], locale: $locale),
                    translate(key: 'Tell others how it was: rate {store} in the app.', replace: ['store' => $store], locale: $locale),
                ],
                'now_delivery' => [
                    translate(key: 'Your order from {store} will be delivered', replace: ['store' => $store], locale: $locale),
                    translate(key: 'A rider will bring it to {address}.', replace: ['address' => mb_substr((string)$order->drop_address, 0, 80)], locale: $locale),
                ],
                'sent' => [
                    translate(key: 'Order sent to {store}', replace: ['store' => $store], locale: $locale),
                    translate(key: 'They will confirm it in a few minutes. You pay after they confirm.', locale: $locale),
                ],
                'confirmed' => [
                    translate(key: '{store} confirmed your order', replace: ['store' => $store], locale: $locale),
                    translate(key: 'Pay within {minutes} minutes and they start cooking.', replace: ['minutes' => self::PAY_MINUTES], locale: $locale),
                ],
                'changes' => [
                    translate(key: '{store} suggests changes to your order', replace: ['store' => $store], locale: $locale),
                    translate(key: 'Something is not available. Open the order to accept the changes or cancel, within {minutes} minutes.', replace: ['minutes' => self::ANSWER_MINUTES], locale: $locale),
                ],
                'unpaid' => [
                    translate(key: 'Your order was not paid in time', locale: $locale),
                    translate(key: 'Nothing was charged. You can order again whenever you like.', locale: $locale),
                ],
                'changes_expired' => [
                    translate(key: 'Your order was closed', locale: $locale),
                    translate(key: 'You did not answer the changes from {store} in time. Nothing was charged.', replace: ['store' => $store], locale: $locale),
                ],
                'rejected', 'timeout' => [
                    translate(key: '{store} could not take your order', replace: ['store' => $store], locale: $locale),
                    match (true) {
                        $order->refunded_amount > 0 => translate(key: '{amount} has been refunded to your E-Ride wallet.', replace: ['amount' => $amount], locale: $locale),
                        $paidNoRefund => translate(key: 'E-Ride will contact you about your payment.', locale: $locale),
                        default => ($order->cancel_reason && $event === 'rejected'
                            ? translate(key: 'Reason: {reason}. Nothing was charged.', replace: ['reason' => $order->cancel_reason], locale: $locale)
                            : translate(key: 'Nothing was charged. Try another restaurant or branch.', locale: $locale)),
                    },
                ],
                'cancelled' => [
                    translate(key: 'Your order was cancelled', locale: $locale),
                    $order->refunded_amount > 0
                        ? translate(key: '{amount} has been refunded to your E-Ride wallet.', replace: ['amount' => $amount], locale: $locale)
                        : ($paidNoRefund
                            ? trim(($order->cancel_reason ? translate(key: 'Reason: {reason}.', replace: ['reason' => $order->cancel_reason], locale: $locale) . ' ' : '')
                                . translate(key: 'E-Ride will contact you about your payment.', locale: $locale))
                            : translate(key: 'Nothing was charged.', locale: $locale)),
                ],
                'rider_assigned' => [
                    translate(key: '{rider} is collecting your order', replace: ['rider' => $rider], locale: $locale),
                    translate(key: 'They will pick it up at {store} and bring it to you.', replace: ['store' => $store], locale: $locale),
                ],
                'still_looking' => [
                    translate(key: 'Still finding a rider for your order', locale: $locale),
                    translate(key: 'Riders are busy right now. We keep looking and our team is following your order.', locale: $locale),
                ],
                'picked_up' => [
                    translate(key: 'Your {store} order is on the way', replace: ['store' => $store], locale: $locale),
                    translate(key: '{rider} has picked it up and is heading to you.', replace: ['rider' => $rider], locale: $locale),
                ],
                'delivered' => [
                    translate(key: 'Your {store} order was delivered', replace: ['store' => $store], locale: $locale),
                    translate(key: 'Enjoy! Thank you for ordering with E-Ride.', locale: $locale),
                ],
                'delivery_failed' => [
                    translate(key: 'Your order could not be delivered', locale: $locale),
                    StoreSettings::refunds()
                        ? translate(key: 'Our team will contact you. The delivery fee has been returned to your wallet.', locale: $locale)
                        : translate(key: 'Our team will contact you.', locale: $locale),
                ],
                'paid_late' => [
                    translate(key: 'Payment not used for your order', locale: $locale),
                    translate(key: 'Your order was no longer waiting for this payment. E-Ride will send {amount} back to your Mobile Money number.', replace: ['amount' => $amount], locale: $locale),
                ],
                default => [null, null],
            };
            if (!$title) {
                return;
            }
            sendDeviceNotification(
                fcm_token: $customer->fcm_token,
                title: $title,
                description: $body,
                status: 1,
                ride_request_id: $order->trip_request_id,
                type: 'store_order',
                notification_type: 'store_order',
                action: 'store_order_' . $event,
                user_id: $customer->id,
                notificationData: ['extra' => ['order_id' => $order->id, 'order_status' => $order->status]],
            );
        } catch (Throwable $e) {
            Log::warning('Food & Shops customer push failed', ['order' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /** "{customer} rated you", as after a ride. */
    protected function notifyRiderOfReview(string $tripId): void
    {
        try {
            $trip = TripRequest::with(['driver', 'customer'])->find($tripId);
            $push = getNotification('review_from_customer');
            if (!$trip?->driver?->fcm_token || !$push) {
                return;
            }
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate(key: $push['title'], locale: $trip->driver->current_language_key),
                description: textVariableDataFormat(value: $push['description'], customerName: trim($trip->customer?->first_name . ' ' . $trip->customer?->last_name), locale: $trip->driver->current_language_key),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                notification_type: 'review',
                action: $push['action'],
                user_id: $trip->driver->id,
            );
        } catch (Throwable $e) {
            Log::warning('Food & Shops rider review push failed', ['trip' => $tripId, 'error' => $e->getMessage()]);
        }
    }

    /** New-order SMS to the store phone, when an SMS gateway is configured. */
    protected function alertPartner(StoreOrder $order): void
    {
        $store = $order->store;
        if (!$store?->phone) {
            return;
        }
        try {
            $how = match ($order->fulfilment) {
                StoreOrder::DINE_IN => ' ' . translate(key: 'To eat there at {time}, {guests} people.', replace: ['time' => $order->arrive_at?->format('H:i'), 'guests' => (int)$order->guests]),
                StoreOrder::PICKUP => ' ' . translate(key: 'To collect at {time}.', replace: ['time' => $order->arrive_at?->format('H:i')]),
                default => '',
            };
            $message = translate(key: 'E-Ride: new order #{ref} for {store}, {count} items, {amount}. Accept it in the partner portal: {url}', replace: [
                'ref' => $order->ref_id,
                'store' => $store->displayName(),
                'count' => (int)$order->items()->sum('quantity'),
                'amount' => getCurrencyFormat($order->items_subtotal),
                'url' => route('partner.orders'),
            ]) . $how;
            (new class {
                use SmsGatewayForMessage;
            })::send($store->phone, $message);
        } catch (Throwable $e) {
            Log::info('Food & Shops partner SMS not sent', ['order' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /** SMS to the store: the rider is bringing the order back; the code to give them. */
    public function tellStoreReturnCode(StoreOrder $order, string $code): void
    {
        $store = $order->store;
        if (!$store?->phone) {
            return;
        }
        try {
            $message = translate(key: 'E-Ride: the rider is bringing order #{ref} back to {store}. When you have it, give the rider this code: {code}', replace: [
                'ref' => $order->ref_id, 'store' => $store->displayName(), 'code' => $code,
            ]);
            (new class {
                use SmsGatewayForMessage;
            })::send($store->phone, $message);
        } catch (Throwable $e) {
            Log::info('Food & Shops return code SMS not sent', ['order' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    protected function alertAdmin(StoreOrder $order, string $message): void
    {
        try {
            AdminNotification::create(['model' => 'store_order', 'model_id' => $order->id, 'message' => $message]);
        } catch (Throwable $e) {
            Log::info('Food & Shops admin notification not saved', ['order' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
