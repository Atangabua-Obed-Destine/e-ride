<?php

namespace Modules\StoreManagement\Service;

use App\Jobs\ProcessPushNotifications;
use App\Service\BaseService;
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
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Entities\StoreOrder;
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

    public function quote(Store $store, array $lines, array $drop): array
    {
        $problems = [];
        if (!StoreSettings::enabled()) {
            $problems[] = translate('Ordering is not available right now.');
        }
        if (!$store->isOpenNow()) {
            $problems[] = translate(key: '{store} is closed right now.', replace: ['store' => $store->name]);
        }

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
        $items = StoreItem::where('store_id', $store->id)->whereIn('id', array_keys($wanted))->get()->keyBy('id');
        $resolved = [];
        foreach ($wanted as $id => $qty) {
            $item = $items->get($id);
            if (!$item) {
                $problems[] = translate('An item in your cart is no longer on the menu.');
                continue;
            }
            if (!$item->is_available) {
                $problems[] = translate(key: '{item} is sold out.', replace: ['item' => $item->name]);
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
            $problems[] = translate(key: 'The minimum order at {store} is {amount}.', replace: ['store' => $store->name, 'amount' => getCurrencyFormat($store->min_order_amount)]);
        }

        $delivery = null;
        $deliveryProblem = null;
        try {
            $delivery = $this->deliveryQuote($store, (float)$drop['latitude'], (float)$drop['longitude']);
        } catch (ValidationException $e) {
            $deliveryProblem = collect($e->errors())->flatten()->first();
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
        ];
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
        $drop = ['latitude' => $input['drop_latitude'], 'longitude' => $input['drop_longitude']];
        $quote = $this->quote($store, $input['items'] ?? [], $drop);
        if (!$quote['ok']) {
            throw ValidationException::withMessages(['order' => $quote['problems']]);
        }

        $order = DB::transaction(function () use ($customer, $store, $input, $quote) {
            $order = StoreOrder::create([
                'customer_id' => $customer->id,
                'store_id' => $store->id,
                'zone_id' => $quote['delivery']['zone_id'],
                'status' => StoreOrder::AWAITING_PAYMENT,
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
            return $order;
        });

        if ($input['payment_method'] === 'wallet') {
            $order = $this->payWithWallet($order);
        }
        return $order->fresh(['items', 'store']);
    }

    public function payWithWallet(StoreOrder $order): StoreOrder
    {
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
            if ($order->payment_request_id === $paymentRequest->id
                || Transaction::where('attribute', 'store_order_late_payment')->where('attribute_id', $order->id)->where('trx_ref_id', $paymentRequest->id)->exists()) {
                return;
            }
            if ($order->status === StoreOrder::AWAITING_PAYMENT && $order->payment_status === StoreOrder::UNPAID) {
                $order->payment_request_id = $paymentRequest->id;
                $this->markPaid($order, $paymentRequest->payment_method);
                return;
            }
            // Paid after the order expired or was cancelled, or paid twice:
            // the money goes to the wallet, never lost.
            $this->creditWallet($order->customer_id, (float)$paymentRequest->payment_amount, $order->id, 'store_order_late_payment', 'Late or duplicate food & shop order payment added to customer wallet', $paymentRequest->id);
            $this->notifyCustomer($order, 'refunded_late');
        });
    }

    protected function markPaid(StoreOrder $order, string $method): StoreOrder
    {
        $order->status = StoreOrder::PLACED;
        $order->payment_status = StoreOrder::PAID;
        $order->payment_method = $method;
        $order->paid_at = now();
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
            if ($order->status !== StoreOrder::PLACED) {
                return $order;
            }
            $prepMinutes = max(5, min(180, $prepMinutes));
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

    public function reject(StoreOrder $order, string $reason, string $by = 'partner'): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) use ($reason, $by) {
            if ($order->status !== StoreOrder::PLACED) {
                return $order;
            }
            $this->refundOrder($order, $order->total);
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
            if (!in_array($order->status, [StoreOrder::AWAITING_PAYMENT, StoreOrder::PLACED], true)) {
                throw ValidationException::withMessages(['order' => translate('The store has already started on this order, so it can no longer be cancelled here. Contact support if there is a problem.')]);
            }
            if ($order->payment_status === StoreOrder::PAID) {
                $this->refundOrder($order, $order->total);
            }
            $order->status = StoreOrder::CANCELLED;
            $order->cancelled_at = now();
            $order->cancelled_by = 'customer';
            $order->save();
            return $order;
        });
    }

    public function cancelByAdmin(StoreOrder $order, string $reason, bool $refund = true): StoreOrder
    {
        return $this->locked($order, function (StoreOrder $order) use ($reason, $refund) {
            if (in_array($order->status, StoreOrder::FINAL, true) || $order->status === StoreOrder::PICKED_UP) {
                throw ValidationException::withMessages(['order' => translate('This order can no longer be cancelled.')]);
            }
            $trip = $order->trip_request_id ? TripRequest::find($order->trip_request_id) : null;
            if ($trip && !in_array($trip->current_status, [PENDING, CANCELLED], true)) {
                throw ValidationException::withMessages(['order' => translate('A rider has already accepted this delivery. Ask the rider to cancel it first.')]);
            }
            if ($trip && $trip->current_status === PENDING) {
                $trip->update(['current_status' => CANCELLED]);
                $trip->tripStatus()->update([CANCELLED => now()]);
            }
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
                $order->save();
                $this->requestRiderLocked($order);
            }
            return $order;
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
        if (!in_array($order->status, [StoreOrder::ACCEPTED, StoreOrder::RIDER_REQUESTED], true)) {
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
            'store' => $store->name, 'ref' => $order->ref_id, 'items' => $summary,
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
            'pickup_address' => $store->name . ', ' . $store->address,
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
            'sender_name' => $store->name,
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

        if ($order->rider_attempts === self::ADMIN_ALERT_AFTER_ATTEMPTS) {
            $this->alertAdmin($order, 'store_order_no_rider');
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
                    if ($order->status === StoreOrder::RIDER_REQUESTED) {
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

        StoreOrder::where('status', StoreOrder::AWAITING_PAYMENT)
            ->where('created_at', '<', now()->subMinutes(self::UNPAID_EXPIRY_MINUTES))
            ->pluck('id')->each(function ($id) use (&$counts) {
                $this->locked(StoreOrder::find($id), function (StoreOrder $order) use (&$counts) {
                    if ($order->status === StoreOrder::AWAITING_PAYMENT) {
                        $order->status = StoreOrder::EXPIRED;
                        $order->cancelled_at = now();
                        $order->cancelled_by = 'system';
                        $order->save();
                        $counts['expired']++;
                    }
                    return $order;
                });
            });

        StoreOrder::where('status', StoreOrder::PLACED)
            ->where('placed_at', '<', now()->subMinutes($settings['partner_response_minutes']))
            ->get()->each(function (StoreOrder $order) use (&$counts) {
                $this->reject($order, translate('The store did not respond in time.'), 'timeout');
                $counts['timed_out']++;
            });

        StoreOrder::where('status', StoreOrder::ACCEPTED)
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

    protected function refundOrder(StoreOrder $order, float $amount): void
    {
        $amount = round(min($amount, $order->total - $order->refunded_amount), 2);
        if ($amount <= 0) {
            return;
        }
        $this->creditWallet($order->customer_id, $amount, $order->id, 'store_order_refund', 'Food & shop order refunded to customer wallet');
        $order->refunded_amount = round($order->refunded_amount + $amount, 2);
        $order->refunded_at = now();
        if ($order->refunded_amount + 0.00001 >= $order->total) {
            $order->payment_status = StoreOrder::REFUNDED;
        }
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

    protected function notifyCustomer(StoreOrder $order, string $event): void
    {
        try {
            $customer = User::find($order->customer_id);
            if (!$customer?->fcm_token) {
                return;
            }
            $store = $order->store?->name ?? '';
            $locale = $customer->current_language_key;
            $amount = getCurrencyFormat($order->refunded_amount ?: $order->total);
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
                'rejected', 'timeout' => [
                    translate(key: '{store} could not take your order', replace: ['store' => $store], locale: $locale),
                    translate(key: '{amount} has been refunded to your E-Ride wallet.', replace: ['amount' => $amount], locale: $locale),
                ],
                'cancelled' => [
                    translate(key: 'Your order was cancelled', locale: $locale),
                    translate(key: '{amount} has been refunded to your E-Ride wallet.', replace: ['amount' => $amount], locale: $locale),
                ],
                'rider_assigned' => [
                    translate(key: '{rider} is collecting your order', replace: ['rider' => $rider], locale: $locale),
                    translate(key: 'They will pick it up at {store} and bring it to you.', replace: ['store' => $store], locale: $locale),
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
                    translate(key: 'Our team will contact you. The delivery fee has been returned to your wallet.', locale: $locale),
                ],
                'refunded_late' => [
                    translate(key: 'Payment added to your wallet', locale: $locale),
                    translate(key: 'Your payment arrived after the order closed, so it was added to your E-Ride wallet.', locale: $locale),
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

    /** New-order SMS to the store phone, when an SMS gateway is configured. */
    protected function alertPartner(StoreOrder $order): void
    {
        $store = $order->store;
        if (!$store?->phone) {
            return;
        }
        try {
            $message = translate(key: 'E-Ride: new order #{ref} for {store}, {count} items, {amount}. Accept it in the partner portal: {url}', replace: [
                'ref' => $order->ref_id,
                'store' => $store->name,
                'count' => (int)$order->items()->sum('quantity'),
                'amount' => getCurrencyFormat($order->items_subtotal),
                'url' => route('partner.orders'),
            ]);
            (new class {
                use SmsGatewayForMessage;
            })::send($store->phone, $message);
        } catch (Throwable $e) {
            Log::info('Food & Shops partner SMS not sent', ['order' => $order->id, 'error' => $e->getMessage()]);
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
