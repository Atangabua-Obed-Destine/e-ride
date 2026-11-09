<?php

use Modules\PlaceManagement\Service\ListingReservationService;

/*
| Payment hook for Homes & spaces reservations, named in PaymentRequest.hook and
| called by the gateways (Fapshi and the rest) after a payment. Loaded by
| PlaceManagementServiceProvider, as deposit_helpers.php is for ride deposits.
*/
if (!function_exists('listingReservationPaid')) {
    function listingReservationPaid($data): void
    {
        app(ListingReservationService::class)->onGatewayPaid($data);
    }
}
