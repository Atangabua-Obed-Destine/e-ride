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
