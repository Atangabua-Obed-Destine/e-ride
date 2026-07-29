<?php

namespace Modules\ZoneManagement\Observers;

use Modules\ZoneManagement\Entities\Zone;

class ZoneObserver
{
    /**
     * Handle the Zone "created" event.
     */
    public function created(Zone $zone): void
    {
        // Get the latest Zone with a readable_id
        $latestZone = Zone::withTrashed()->whereNotNull('readable_id')->orderBy('readable_id', 'desc')->first();
        $newId = $latestZone ? ((int) $latestZone->readable_id + 1) : 1;
        // Set the new readable_id
        $zone->readable_id = $newId;
        $zone->save();
    }

    /**
     * Handle the Zone "updated" event.
     */
    public function updated(Zone $zone): void
    {
        //
    }

    /**
     * Handle the Zone "deleted" event.
     */
    public function deleted(Zone $zone): void
    {
        //
    }

    /**
     * Handle the Zone "restored" event.
     */
    public function restored(Zone $zone): void
    {
        //
    }

    /**
     * Handle the Zone "force deleted" event.
     */
    public function forceDeleted(Zone $zone): void
    {
        //
    }
}
