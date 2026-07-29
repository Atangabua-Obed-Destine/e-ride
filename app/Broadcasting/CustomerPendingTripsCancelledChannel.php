<?php

namespace App\Broadcasting;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

class CustomerPendingTripsCancelledChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Authenticate the user's access to the channel.
     */
    public function join(User $user, $tripId, $userId): array|bool
    {
        return $user->id == $userId && $user->id == TripRequest::where(['trip_request_id' => $tripId, 'customer_id' => $userId])->with('customer')->first()->customer->id;
    }
}
