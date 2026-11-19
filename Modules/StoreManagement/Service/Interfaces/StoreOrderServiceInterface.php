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
    public function quote(Store $store, array $lines, array $drop): array;

    /** Pay a confirmed order: wallet now (returns null) or a payment page URL for a gateway. */
    public function payConfirmed(StoreOrder $order, string $method): ?string;

    /** Dishes the restaurant can offer instead, available at the order's kitchen now. */
    public function swapOptions(StoreOrder $order): array;

    /** The restaurant suggests changes; one entry per order line: quantity (0 = removed), swap_item_id. */
    public function proposeChanges(StoreOrder $order, array $changes, ?string $note): StoreOrder;

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

    /** Why the order can no longer be paid ($starting: a new payment page), or null while it can. */
    public function paymentClosedReason(string $orderId, bool $starting): ?string;

    /** Money arrived after the order closed: alert the admin to send it back, tell the customer. */
    public function onLatePayment(string $orderId, float $amount, string $reference): void;

    public function accept(StoreOrder $order, int $prepMinutes): StoreOrder;

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
