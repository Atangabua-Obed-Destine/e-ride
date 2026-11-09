<?php

namespace Modules\PlaceManagement\Lib;

/** Homes & spaces settings (Explore Bamenda > Homes & spaces > Settings), with defaults. */
class ListingSettings
{
    public const KEYS = [
        'listing_confirm_days_short', 'listing_confirm_days_long', 'listing_late_after_days', 'listing_verified_months',
        'listing_stale_days', 'listing_taken_reports', 'listing_taken_hours', 'listing_strike_limit', 'listing_strike_months',
        'listing_ban_days', 'listing_visit_radius_m', 'listing_neighbourhoods', 'listing_landmark_name',
        'listing_landmark_latitude', 'listing_landmark_longitude',
    ];

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, PLACE_SETTINGS)?->value ?? $default;
        $lat = $get('listing_landmark_latitude', '');
        $lng = $get('listing_landmark_longitude', '');
        return [
            'confirm_days_short' => max(1, (int)$get('listing_confirm_days_short', 14)),
            'confirm_days_long' => max(1, (int)$get('listing_confirm_days_long', 30)),
            'late_after_days' => max(0, (int)$get('listing_late_after_days', 3)),
            'verified_months' => max(1, (int)$get('listing_verified_months', 6)),
            'stale_days' => max(1, (int)$get('listing_stale_days', 45)),
            'taken_reports' => max(1, (int)$get('listing_taken_reports', 2)),
            'taken_hours' => max(1, (int)$get('listing_taken_hours', 48)),
            'strike_limit' => max(1, (int)$get('listing_strike_limit', 3)),
            'strike_months' => max(1, (int)$get('listing_strike_months', 6)),
            'ban_days' => max(1, (int)$get('listing_ban_days', 30)),
            'visit_radius_m' => max(20, (int)$get('listing_visit_radius_m', 100)),
            'neighbourhoods' => array_values(array_filter(array_map('trim', explode(',', (string)$get('listing_neighbourhoods', ''))))),
            // A place seekers measure from (for Bambili: the UBa gate). Empty: no distance shown.
            'landmark_name' => trim((string)$get('listing_landmark_name', '')),
            'landmark_latitude' => is_numeric($lat) ? (float)$lat : null,
            'landmark_longitude' => is_numeric($lng) ? (float)$lng : null,
        ];
    }
}
