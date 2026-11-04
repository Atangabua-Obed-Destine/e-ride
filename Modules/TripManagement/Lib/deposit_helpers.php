<?php

use Modules\TripManagement\Service\TripDepositService;

/*
| Payment hook for booking deposits, named in PaymentRequest.hook and called
| by the gateways (Fapshi and the rest) after a payment. Loaded by
| TripManagementServiceProvider, like app/Library/TripRequestUpdate.php is
| loaded through composer.
*/
if (!function_exists('tripDepositPaymentUpdate')) {
    function tripDepositPaymentUpdate($data): void
    {
        app(TripDepositService::class)->onGatewayPaid($data);
    }
}
