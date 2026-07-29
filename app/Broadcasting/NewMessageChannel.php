<?php

namespace App\Broadcasting;

use Modules\UserManagement\Entities\User;

class NewMessageChannel
{

    /**
     * Create a new channel instance.
     */
    public function __construct()
    {


    }

    /**
     * Authenticate the user's access to the channel.
     */
    public function join(User $user): array|bool
    {
        return true;
    }
}
