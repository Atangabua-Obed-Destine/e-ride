<?php

namespace Modules\StoreManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\UserManagement\Entities\User;

interface StoreOrderServiceInterface extends BaseServiceInterface
{
    /** Prices, delivery fee and problems for a cart. Never trusts client amounts. */
    public function quote(Store $store, array $lines, array $drop, string $way = StoreOrder::DELIVERY, ?\Illuminate\Support\Carbon $arriveAt = null): array;

    /** Eat there or collect: the staff type the customer's code and the order is done. */
    public function handOver(StoreOrder $order, string $code): StoreOrder;

    /** An eat-there or collect order becomes a delivery; pay_url is the delivery fee's page when the order was already paid. */
    public function switchToDelivery(StoreOrder $order, array $drop, ?string $method = null): array;

    public function deliveryFeeLink(StoreOrder $order, string $gateway): string;

    /** The customer keeps eating there or collecting: a change to delivery waiting for its fee is dropped. */
    public function keepWay(StoreOrder $order): StoreOrder;

    /** Payment hook (storeOrderDeliveryPaid). */
    public function onDeliveryFeePaid(PaymentRequest $paymentRequest): void;

    /** Pay a confirmed order: wallet now (returns null) or a payment page URL for a gateway. */
    public function payConfirmed(StoreOrder $order, string $method): ?string;

    /** Dishes the restaurant can offer instead, available at the order's kitchen now. */
    public function swapOptions(StoreOrder $order): array;

    /** The restaurant suggests changes; one entry per order line: quantity (0 = removed), swap_item_id. */
    public function proposeChanges(StoreOrder $order, array $changes, ?string $note, bool $byStore = false): StoreOrder;

    /** The customer accepts the suggested changes (then pays) or refuses them (cancelled). */
    public function answerChanges(StoreOrder $order, bool $accept): StoreOrder;

    /** Delivery fee from this kitchen to a point, or null when it can't deliver there. */
    public function deliveryFee(Store $store, float $lat, float $lng): ?float;

    /** Creates an order awaiting payment; pays at once when the method is wallet. */
    public function place(User $customer, Store $store, array $input): StoreOrder;

    public function payWithWallet(StoreOrder $order): StoreOrder;

    /** A gateway checkout link for an unpaid order. */
    public function paymentLink(StoreOrder $order, string $gateway): string;

    /** Payment hook (storeOrderPaymentUpdate). */
    public function onGatewayPaid(PaymentRequest $paymentRequest): void;

    /** A payment into the business's own Fapshi succeeded (StorePaymentService, inside its transaction). */
    public function onShopPaid(\Modules\StoreManagement\Entities\StorePayment $payment): void;

    /** Why the order can no longer be paid ($starting: a new payment page), or null while it can. */
    public function paymentClosedReason(string $orderId, bool $starting): ?string;

    /** Money arrived after the order closed: alert the admin to send it back, tell the customer. */
    public function onLatePayment(string $orderId, float $amount, string $reference): void;

    /** SMS to the store: the rider brings the order back; the return code to give them. */
    public function tellStoreReturnCode(StoreOrder $order, string $code): void;

    public function accept(StoreOrder $order, int $prepMinutes, bool $byStore = false): StoreOrder;

    /** "The shop brings it": what the store's own staff would charge for a quoted delivery, or null. */
    public function ownDeliveryOffer(Store $store, ?array $quote): ?array;

    /** "The shop brings it": the staff leave with the order. */
    public function sendOut(StoreOrder $order): StoreOrder;

    /** "The shop brings it": the customer says they have their order. */
    public function confirmReceived(StoreOrder $order): StoreOrder;

    /** "The shop brings it", paid, and the staff cannot go: an E-Ride rider instead (the store pays the difference). */
    public function useRider(StoreOrder $order): StoreOrder;

    /** "My riders": stop waiting for the store's own riders on this order and ring everyone. */
    public function openToAllRiders(StoreOrder $order): StoreOrder;

    public function reject(StoreOrder $order, string $reason, string $by = 'partner'): StoreOrder;

    public function cancelByCustomer(StoreOrder $order): StoreOrder;

    /**
     * The customer rates a delivered order: the store (required) and the rider
     * (optional, stored as a normal trip review so the driver's rating moves).
     * Throws ValidationException when it is not ratable.
     */
    public function review(StoreOrder $order, array $data): \Modules\StoreManagement\Entities\StoreReview;

    public function cancelByAdmin(StoreOrder $order, string $reason, bool $refund = true): StoreOrder;

    /** Admin decision on a delivery that failed after pickup. */
    public function resolveFailedDelivery(StoreOrder $order, bool $refundFood, ?string $note = null): StoreOrder;

    public function markReady(StoreOrder $order): StoreOrder;

    public function requestRider(StoreOrder $order): StoreOrder;

    public function syncFromTrip(StoreOrder $order): StoreOrder;

    /** Scheduler: timeouts, unpaid expiry, automatic rider requests, trip sync. */
    public function tick(): array;
}
