<?php

namespace Modules\PlaceManagement\Lib;

/** Places to stay settings (sidebar Hotels & Stays > Settings), with defaults. */
class PlaceSettings
{
    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, PLACE_SETTINGS)?->value ?? $default;
        return [
            'status' => (string)$get('stay_status', '0') === '1',
            'label' => (string)$get('stay_label', 'Hotels'),
            'google_status' => (string)$get('stay_google_status', '1') === '1',
            'radius_km' => (float)$get('stay_radius_km', 10),
            'min_reviews' => (int)$get('stay_min_reviews', 5),
            'search_cap' => (int)$get('stay_google_search_cap', 1000),
            'photo_cap' => (int)$get('stay_google_photo_cap', 1000),
            'explore_label' => (string)$get('explore_label', 'Explore Bamenda'),
            'emergency_note' => (string)$get('explore_emergency_note', ''),
        ];
    }

    public static function enabled(): bool
    {
        return self::all()['status'];
    }
}
