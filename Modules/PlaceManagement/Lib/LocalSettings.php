<?php

namespace Modules\PlaceManagement\Lib;

/**
 * Landmarks and drop fares: the switches and numbers (admin Landmarks page).
 * Both are off until an admin turns them on.
 */
class LocalSettings
{
    public const TYPE = 'local_places_settings';

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        return [
            'landmarks_enabled' => (bool)(int)$get('landmarks_enabled', 0),
            'drop_fares_enabled' => (bool)(int)$get('drop_fares_enabled', 0),
            // Scheduled rides get drop prices too, judged at their pickup time (a route's hours).
            'drop_fares_scheduled' => (bool)(int)$get('drop_fares_scheduled', 1),
            // Landmarks shown before Google's results while typing.
            'search_results' => min(8, max(1, (int)$get('landmarks_search_results', 4))),
            // Landmarks shown when nothing is typed yet.
            'popular_results' => min(12, max(0, (int)$get('landmarks_popular_results', 6))),
            // Catchment for new drop routes: a pickup a little up the road still counts.
            'drop_radius_m' => min(2000, max(50, (int)$get('drop_fares_radius_m', 400))),
        ];
    }

    public static function landmarks(): bool
    {
        return self::all()['landmarks_enabled'];
    }

    public static function dropFares(): bool
    {
        return self::all()['drop_fares_enabled'];
    }
}
