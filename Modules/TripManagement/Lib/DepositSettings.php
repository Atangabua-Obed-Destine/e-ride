<?php

namespace Modules\TripManagement\Lib;

use Modules\TripManagement\Entities\TripRequest;

/**
 * Booking deposit settings (Trip management > Booking deposit), with defaults.
 * The amount itself is per zone and vehicle category, in the fare setup.
 * Moves have their own values (MoveSettings); forTrip() picks the right ones.
 */
class DepositSettings
{
    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, TRIP_SETTINGS)?->value ?? $default;
        $commission = (float)(get_cache('trip_commission') ?? 0);
        return [
            'status' => (string)$get('booking_deposit_status', '0') === '1',
            // Minutes the customer has to pay after a driver accepts.
            'window_minutes' => max(1, (int)$get('booking_deposit_window_minutes', 3)),
            // Scheduled rides are often accepted long before pickup.
            'scheduled_window_minutes' => max(1, (int)$get('booking_deposit_scheduled_window_minutes', 30)),
            // After paying, the customer can still cancel for free this long.
            'free_cancel_seconds' => max(0, (int)$get('booking_deposit_free_cancel_seconds', 60)),
            // A driver this many minutes past the promised arrival lets the customer cancel for free.
            'late_minutes' => max(1, (int)$get('booking_deposit_late_minutes', 10)),
            // E-Ride's share of a forfeited deposit; the driver gets the rest.
            'admin_share_percent' => min(100, max(0, (float)$get('booking_deposit_admin_share_percent', $commission))),
        ];
    }

    /** The same keys, with the E-Ride Move values. */
    public static function move(): array
    {
        $move = MoveSettings::all();
        return array_merge(self::all(), [
            'status' => $move['status'] && $move['deposit_status'],
            'window_minutes' => $move['deposit_window_minutes'],
            'scheduled_window_minutes' => $move['deposit_window_minutes'],
            'free_cancel_seconds' => $move['deposit_free_cancel_seconds'],
            'admin_share_percent' => 100 - $move['deposit_driver_share_percent'],
        ]);
    }

    /** The settings that apply to this trip (a model or an id). */
    public static function forTrip(TripRequest|string|null $trip): array
    {
        $isMove = $trip instanceof TripRequest
            ? (bool)$trip->is_move
            : ($trip && (bool)TripRequest::whereKey($trip)->value('is_move'));
        return $isMove ? self::move() : self::all();
    }

    public static function enabled(): bool
    {
        return self::all()['status'];
    }
}
