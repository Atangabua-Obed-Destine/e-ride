<?php

namespace Modules\ZoneManagement\Lib;

/**
 * Area pause and danger reports: the numbers, with the plan's starting values
 * (Zone management > Safety map > Settings). Off until an admin turns it on.
 */
class SafetySettings
{
    public const TYPE = 'safety_settings';

    public const KEYS = [
        'safety_enabled', 'safety_auto_reports', 'safety_auto_radius_m', 'safety_auto_window_min', 'safety_auto_minutes',
        'safety_caution_radius_km', 'safety_reports_per_hour', 'safety_report_fade_min', 'safety_banner_radius_km',
        'safety_report_reward', 'safety_scheduled_alert_minutes', 'safety_pause_blocks',
    ];

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        return [
            'enabled' => (bool)(int)$get('safety_enabled', 0),
            // A pause or no-go area refuses new trips to or from it. Off (the default): trips go ahead, both sides are warned.
            'pause_blocks' => (bool)(int)$get('safety_pause_blocks', 0),
            // Driver reports within this circle and time that make an automatic no-go area.
            'auto_reports' => max(2, (int)$get('safety_auto_reports', 2)),
            'auto_radius_m' => max(100, (int)$get('safety_auto_radius_m', 500)),
            'auto_window_min' => max(5, (int)$get('safety_auto_window_min', 15)),
            'auto_minutes' => max(15, (int)$get('safety_auto_minutes', 60)),
            // Drivers this close to a report get a caution.
            'caution_radius_km' => max(0.5, (float)$get('safety_caution_radius_km', 3)),
            'reports_per_hour' => max(1, (int)$get('safety_reports_per_hour', 3)),
            // A report stops warning after this long.
            'report_fade_min' => max(15, (int)$get('safety_report_fade_min', 60)),
            // The apps show a banner this close to a blocked area or a fresh report.
            'banner_radius_km' => max(0.5, (float)$get('safety_banner_radius_km', 3)),
            // Paid to a driver when E-Ride confirms their danger report (0: no reward).
            'report_reward' => max(0, (float)$get('safety_report_reward', 0)),
            // Scheduled rides are never blocked by a pause: rider and driver get a heads-up this long before the pickup.
            'scheduled_alert_minutes' => min(720, max(15, (int)$get('safety_scheduled_alert_minutes', 60))),
        ];
    }
}
