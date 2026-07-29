<?php

namespace App\Broadcasting;

use Modules\TripManagement\Entities\TempTripNotification;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

class DriverPendingTripsCancelledChannel
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
        return $user->id == $userId && $user->id == TempTripNotification::where(['trip_request_id' => $tripId, 'user_id' => $userId])->first()->user_id;
    }
}
