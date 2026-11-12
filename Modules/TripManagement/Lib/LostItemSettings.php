<?php

namespace Modules\TripManagement\Lib;

/**
 * Lost and found: the switch and the numbers (admin Trip management > Lost and found).
 * Off until an admin turns it on.
 */
class LostItemSettings
{
    public const TYPE = 'lost_item_settings';

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        return [
            'enabled' => (bool)(int)$get('lost_items_enabled', 0),
            // A rider can report a lost item this many days after the ride.
            'report_days' => min(30, max(1, (int)$get('lost_items_report_days', 7))),
            // The driver has this long to answer; then E-Ride support takes over.
            'answer_hours' => min(72, max(1, (int)$get('lost_items_answer_hours', 24))),
            // Paid by the rider to the driver, in cash, when the driver brings the item.
            'return_fee' => max(0, (float)$get('lost_items_return_fee', 500)),
            // Found but not back after this many days: E-Ride support takes over.
            'stale_days' => min(14, max(1, (int)$get('lost_items_stale_days', 3))),
            // The driver says it is back; with no word from the rider in this time, the case closes as returned.
            'confirm_hours' => min(168, max(1, (int)$get('lost_items_confirm_hours', 48))),
        ];
    }

    public static function enabled(): bool
    {
        return self::all()['enabled'];
    }
}
