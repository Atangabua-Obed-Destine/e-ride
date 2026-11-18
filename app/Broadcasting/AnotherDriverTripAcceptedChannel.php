<?php

namespace App\Broadcasting;

use Modules\TripManagement\Entities\TempTripNotification;
use Modules\UserManagement\Entities\User;

class AnotherDriverTripAcceptedChannel
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
        // Any driver can hear that a trip offered to drivers is taken; the offer
        // row may be gone already (accepted, or not inserted yet).
        return $user->id == $userId && $user->user_type == 'driver';
    }
}
