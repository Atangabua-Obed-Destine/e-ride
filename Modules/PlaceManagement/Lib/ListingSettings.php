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
        // Reservation money (phase 2).
        'listing_reservations_enabled', 'listing_reservation_min', 'listing_reservation_max', 'listing_reservation_fee_percent',
        'listing_reservation_pay_minutes', 'listing_reservation_accept_hours', 'listing_reservation_release_days',
        'listing_reservation_move_in_days',
    ];

    /** Reservation money settings, with the plan's starting values. Off until an admin turns it on. */
    public static function reservations(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, PLACE_SETTINGS)?->value ?? $default;
        $min = max(100, (float)$get('listing_reservation_min', 5000));
        return [
            'enabled' => (bool)(int)$get('listing_reservations_enabled', 0),
            'min' => $min,
            'max' => max($min, (float)$get('listing_reservation_max', 200000)),
            'fee_percent' => min(50, max(0, (float)$get('listing_reservation_fee_percent', 5))),
            'pay_minutes' => max(5, (int)$get('listing_reservation_pay_minutes', 30)),
            'accept_hours' => max(1, (int)$get('listing_reservation_accept_hours', 24)),
            'release_days' => max(1, (int)$get('listing_reservation_release_days', 3)),
            'move_in_days' => max(1, (int)$get('listing_reservation_move_in_days', 30)),
        ];
    }

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
