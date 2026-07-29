<?php

namespace Modules\TripManagement\Observers;

use Modules\TripManagement\Entities\ParcelRefund;

class ParcelRefundObserver
{
    /**
     * Handle the ParcelRefund "created" event.
     */
    public function created(ParcelRefund $parcelRefund): void
    {
        $latestZone = ParcelRefund::withTrashed()
            ->whereNotNull('readable_id')
            ->orderBy('readable_id', 'desc')
            ->first();

// Determine the new readable_id
        if ($latestZone) {
            // Extract the numeric part of the latest readable_id using regex
            preg_match('/\d+$/', $latestZone->readable_id, $matches);
            $latestId = isset($matches[0]) ? (int) $matches[0] : 0;
            $newId = $latestId + 1; // Increment the ID
        } else {
            $newId = 1; // Start with 1 if no previous record exists
        }

// Set the new readable_id
        $parcelRefund->readable_id = $parcelRefund?->tripRequest?->ref_id . '-REF' . $newId;
        $parcelRefund->save();
    }

    /**
     * Handle the ParcelRefund "updated" event.
     */
    public function updated(ParcelRefund $parcelRefund): void
    {
        //
    }

    /**
     * Handle the ParcelRefund "deleted" event.
     */
    public function deleted(ParcelRefund $parcelRefund): void
    {
        //
    }

    /**
     * Handle the ParcelRefund "restored" event.
     */
    public function restored(ParcelRefund $parcelRefund): void
    {
        //
    }

    /**
     * Handle the ParcelRefund "force deleted" event.
     */
    public function forceDeleted(ParcelRefund $parcelRefund): void
    {
        //
    }
}
