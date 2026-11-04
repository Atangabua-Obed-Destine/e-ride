<?php

namespace Modules\TripManagement\Lib;

/**
 * E-Ride Move settings (E-Ride Move > Settings), with defaults. The deposit
 * amount itself is per zone and move vehicle size (E-Ride Move > Deposits).
 */
class MoveSettings
{
    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, TRIP_SETTINGS)?->value ?? $default;
        return [
            'status' => (string)$get('move_status', '0') === '1',
            'deposit_status' => (string)$get('move_deposit_status', '1') === '1',
            // Longer than rides: the customer may call the driver first.
            'deposit_window_minutes' => max(1, (int)$get('move_deposit_window_minutes', 5)),
            'deposit_free_cancel_seconds' => max(0, (int)$get('move_deposit_free_cancel_seconds', 120)),
            // What the driver gets from a forfeited move deposit. E-Ride keeps the rest.
            'deposit_driver_share_percent' => min(100, max(0, (float)$get('move_deposit_driver_share_percent', 0))),
            'directory_status' => (string)$get('move_directory_status', '1') === '1',
        ];
    }

    public static function enabled(): bool
    {
        return self::all()['status'];
    }
}
