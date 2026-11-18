<?php

namespace App\Broadcasting;

use Modules\TripManagement\Entities\TempTripNotification;
use Modules\UserManagement\Entities\User;

class CustomerTripCanceledChannel
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
    public function join(User $user, $id, $userId): array|bool
    {
        // The driver offered the trip, or the one it was given (the offer row is
        // deleted on accept): both must hear that the customer cancelled.
        return $user->id == $userId && (
            TempTripNotification::where(['trip_request_id' => $id, 'user_id' => $userId])->exists()
            || \Modules\TripManagement\Entities\TripRequest::whereKey($id)->where('driver_id', $userId)->exists()
            || \Modules\TripManagement\Entities\TripRequest::whereKey($id)->whereIn('current_status', [PENDING, CANCELLED])->exists()
        );
    }
}
