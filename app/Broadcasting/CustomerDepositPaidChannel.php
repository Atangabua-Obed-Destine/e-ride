<?php

namespace App\Broadcasting;

use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

/** The driver of the trip hears that the customer paid the booking deposit. */
class CustomerDepositPaidChannel
{
    public function join(User $user, $id): array|bool
    {
        return $user->id == TripRequest::find($id)?->driver_id;
    }
}
