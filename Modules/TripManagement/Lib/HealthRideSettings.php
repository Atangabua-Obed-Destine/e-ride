<?php

namespace Modules\TripManagement\Lib;

/**
 * Health rides: the switch and the numbers (admin Trip management > Health rides).
 * Off until an admin turns it on.
 */
class HealthRideSettings
{
    public const TYPE = 'health_ride_settings';

    /** Cameroon's national numbers (SAMU, fire brigade, police), shown until an admin changes them. */
    public const DEFAULT_NUMBERS = "SAMU (ambulance): 119\nFire brigade: 118\nPolice: 117";

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        return [
            'enabled' => (bool)(int)$get('health_rides_enabled', 0),
            // Health rides a rider can book per day (to stop misuse).
            'daily_limit' => min(10, max(1, (int)$get('health_rides_daily_limit', 2))),
            // Drivers this far from the pickup get the request. 0: twice the normal search radius.
            'radius_km' => max(0, (float)$get('health_rides_radius_km', 0)),
            // One per line, "Label: number".
            'emergency_numbers' => (string)$get('health_rides_emergency_numbers', self::DEFAULT_NUMBERS),
        ];
    }

    public static function enabled(): bool
    {
        return self::all()['enabled'];
    }

    /** @return array<int, array{label: string, number: string}> */
    public static function emergencyNumbers(): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', self::all()['emergency_numbers']) as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$label, $number] = array_map('trim', explode(':', $line, 2));
            $number = preg_replace('/[^0-9+]/', '', $number);
            if ($label !== '' && $number !== '') {
                $out[] = ['label' => mb_substr($label, 0, 60), 'number' => mb_substr($number, 0, 20)];
            }
        }
        return array_slice($out, 0, 6);
    }
}
