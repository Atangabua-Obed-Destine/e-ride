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
            // Scheduled rides: when the deposit is asked for. 'accept' (default): when a driver accepts,
            // with a countdown like a ride for now; not paid, no booking. 'booking': when the ride is booked,
            // before any driver sees it. 'before_pickup': once a driver has accepted, this many minutes before the pickup.
            'scheduled_timing' => in_array($get('booking_deposit_scheduled_timing', 'accept'), ['accept', 'booking', 'before_pickup'], true)
                ? $get('booking_deposit_scheduled_timing', 'accept') : 'accept',
            'scheduled_before_minutes' => min(1440, max(10, (int)$get('booking_deposit_scheduled_before_minutes', 60))),
            // Minutes to pay a scheduled ride's deposit once a driver accepts (or when asked before the pickup).
            'scheduled_window_minutes' => max(1, (int)$get('booking_deposit_scheduled_window_minutes', 30)),
            // A scheduled ride cancels for free until this many minutes before the pickup.
            'scheduled_free_cancel_minutes' => min(2880, max(0, (int)$get('booking_deposit_scheduled_free_cancel_minutes', 60))),
            // Reminders to rider and driver this many minutes before a scheduled ride (0: none), and again at its time.
            'scheduled_reminder_minutes' => min(1440, max(0, (int)$get('booking_deposit_scheduled_reminder_minutes', 60))),
            // A scheduled ride no driver accepted is cancelled (deposit refunded) this many minutes after its pickup time.
            'scheduled_no_driver_minutes' => min(240, max(0, (int)$get('booking_deposit_scheduled_no_driver_minutes', 15))),
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
