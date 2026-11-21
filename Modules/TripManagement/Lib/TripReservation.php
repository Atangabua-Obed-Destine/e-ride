<?php

namespace Modules\TripManagement\Lib;

use App\Jobs\ProcessPushNotifications;
use Modules\TripManagement\Entities\TripRequest;

/**
 * A request held for some drivers: a store's delivery that rings the riders
 * the store chose before anyone else, or only them.
 *
 * trip_requests.reserved_for lists the driver ids; reserved_until is when it
 * opens to everyone (null = stays held until someone opens it). While held,
 * other drivers are not rung, do not see it in their pending list and cannot
 * accept it.
 */
class TripReservation
{
    /** Still held: reserved, not opened, and its time is not over. */
    public static function active(TripRequest $trip): bool
    {
        return !empty($trip->reserved_for) && ($trip->reserved_until === null || $trip->reserved_until->isFuture());
    }

    /** Whether this driver may see and accept the request. */
    public static function allows(TripRequest $trip, ?string $driverId): bool
    {
        return !self::active($trip) || in_array((string)$driverId, (array)$trip->reserved_for, true);
    }

    /** Narrows a trip_requests query to what this driver may see. */
    public static function visibleTo($query, ?string $driverId)
    {
        return $query->where(fn($q) => $q->whereNull('reserved_for')
            ->orWhere(fn($w) => $w->whereNotNull('reserved_until')->where('reserved_until', '<=', now()))
            ->orWhereJsonContains('reserved_for', (string)$driverId));
    }

    /**
     * Opens a held request to every driver and rings the ones who were not
     * rung yet. False when there was nothing to open.
     */
    public static function open(TripRequest $trip): bool
    {
        if (empty($trip->reserved_for) || $trip->current_status !== PENDING) {
            return false;
        }
        // Also moves updated_at, so the request gets its full waiting time with everyone.
        TripRequest::whereKey($trip->id)->update(['reserved_for' => null, 'reserved_until' => null]);
        $final = TripRequest::with(['driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund'])->find($trip->id);
        if (!$final) {
            return false;
        }
        $job = ProcessPushNotifications::dispatch(
            radius: (double)(get_cache('search_radius') ?? 5),
            trip: $final,
            parcelWeight: $final->parcel?->weight,
            skipNotified: true,
        );
        if (!app()->runningInConsole()) {
            $job->afterResponse();
        }
        return true;
    }

    /** Opens every held request whose time is over. Run every minute. */
    public static function openDue(): int
    {
        $opened = 0;
        TripRequest::where('current_status', PENDING)
            ->whereNotNull('reserved_for')
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<=', now())
            ->get()
            ->each(function (TripRequest $trip) use (&$opened) {
                self::open($trip) && $opened++;
            });
        return $opened;
    }
}
