<?php

namespace Modules\TripManagement\Observers;

use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;

class TripRequestObserver
{
    public function updated(TripRequest $tripRequest): void
    {
        if ($tripRequest->driver_id && (
            ($tripRequest->wasChanged('current_status') && in_array($tripRequest->current_status, [COMPLETED, CANCELLED, RETURNED])) ||
            ($tripRequest->wasChanged('payment_status') && $tripRequest->payment_status == PAID)
        )) {
            app(TripRequestServiceInterface::class)->revokeSuspendedDriverAccess($tripRequest->driver_id);
        }
    }
}
