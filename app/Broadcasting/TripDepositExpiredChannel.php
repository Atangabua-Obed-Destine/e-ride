<?php

namespace App\Broadcasting;

use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

/** The customer hears that the booking deposit was not paid in time. */
class TripDepositExpiredChannel
{
    public function join(User $user, $id): array|bool
    {
        return $user->id == TripRequest::find($id)?->customer_id;
    }
}
