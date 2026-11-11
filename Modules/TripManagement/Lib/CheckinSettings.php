<?php

namespace Modules\TripManagement\Lib;

/**
 * Trip check-ins and night sharing: the numbers, with the plan's starting values
 * (Business setup > Trips > Safety check-ins). Off until an admin turns it on.
 */
class CheckinSettings
{
    public const TYPE = 'trip_checkin_settings';

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        return [
            'enabled' => (bool)(int)$get('checkin_enabled', 0),
            // Stopped: moved less than stop_m in stop_minutes, away from the destination.
            'stop_minutes' => max(2, (int)$get('checkin_stop_minutes', 6)),
            'stop_m' => max(10, (int)$get('checkin_stop_m', 50)),
            // Off route: further than off_route_m from the planned route, off_route_times rounds in a row.
            'off_route_m' => max(200, (int)$get('checkin_off_route_m', 1000)),
            'off_route_times' => max(1, (int)$get('checkin_off_route_times', 2)),
            // Far too long: the ride has lasted long_factor times its estimate.
            'long_factor' => max(1.2, (float)$get('checkin_long_factor', 2)),
            'answer_minutes' => max(1, (int)$get('checkin_answer_minutes', 3)),
            'quiet_minutes' => max(1, (int)$get('checkin_quiet_minutes', 10)),
            // Night sharing hours, 0-23: from night_from to night_to the next morning.
            'night_from' => min(23, max(0, (int)$get('checkin_night_from', 19))),
            'night_to' => min(23, max(0, (int)$get('checkin_night_to', 6))),
        ];
    }

    public static function isNight(\Illuminate\Support\Carbon $at): bool
    {
        $s = self::all();
        $h = $at->hour;
        return $s['night_from'] > $s['night_to'] ? ($h >= $s['night_from'] || $h < $s['night_to']) : ($h >= $s['night_from'] && $h < $s['night_to']);
    }
}
