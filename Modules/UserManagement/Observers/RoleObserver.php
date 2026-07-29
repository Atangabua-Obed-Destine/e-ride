<?php

namespace Modules\UserManagement\Observers;


use Modules\UserManagement\Entities\Role;

class RoleObserver
{
    /**
     * Handle the Observers/RoleObserver "created" event.
     */
    public function created(Role $role): void
    {
        // Get the latest Role with a readable_id
        $latestRole = Role::whereNotNull('readable_id')->orderBy('readable_id', 'desc')->first();
        if ($latestRole) {
            $latestId = (int)$latestRole->readable_id;
            $newId = $latestId + 1;
        } else {
            $newId = 1000;
        }
        // Set the new readable_id
        $role->readable_id = $newId;
        $role->save();
    }


    public function updated(Role $role): void
    {
        //
    }

    /**
     * Handle the Zone "deleted" event.
     */
    public function deleted(Role $role): void
    {
        //
    }

    /**
     * Handle the Zone "restored" event.
     */
    public function restored(Role $role): void
    {
        //
    }

    /**
     * Handle the Zone "force deleted" event.
     */
    public function forceDeleted(Role $role): void
    {
        //
    }
}
