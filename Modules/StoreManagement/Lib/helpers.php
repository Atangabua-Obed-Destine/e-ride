<?php

use Modules\StoreManagement\Service\Interfaces\StoreOrderServiceInterface;

/*
| Payment hook for Food & Shops orders, named in PaymentRequest.hook and called
| by the gateways (Fapshi and the rest) after a payment. Loaded by
| StoreManagementServiceProvider, like app/Library/TripRequestUpdate.php is
| loaded through composer.
*/
if (!function_exists('storeOrderPaymentUpdate')) {
    function storeOrderPaymentUpdate($data): void
    {
        app(StoreOrderServiceInterface::class)->onGatewayPaid($data);
    }
}

if (!function_exists('storeOrderDeliveryPaid')) {
    /** Payment hook: the delivery fee of an eat-there or collect order changed to delivery. */
    function storeOrderDeliveryPaid($data): void
    {
        app(StoreOrderServiceInterface::class)->onDeliveryFeePaid($data);
    }
}

if (!function_exists('storeStatementPaid')) {
    /** E-Ride's Fapshi confirmed a business's weekly statement payment. */
    function storeStatementPaid($data): void
    {
        app(\Modules\StoreManagement\Service\StoreStatementService::class)->onGatewayPaid($data);
    }
}
