<?php

namespace Modules\HotelManagement\Lib;

/**
 * Hotel partners switches and numbers (admin Hotels & Stays > Hotel partners > Settings), with defaults.
 * Off until an admin turns it on: until then the app shows hotels as before (cards, WhatsApp, Google).
 */
class HotelSettings
{
    public const TYPE = 'hotel_settings';

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        return [
            'enabled' => (bool)(int)$get('hotel_enabled', 0),
            // Rooms untouched for this many hours: guests see "Ask the reception" instead of counts.
            'stale_hours' => min(72, max(2, (int)$get('hotel_stale_hours', 12))),
            // An inquiry not answered in this many hours flags the hotel as slow in admin.
            'slow_hours' => min(72, max(1, (int)$get('hotel_slow_hours', 24))),
            // At most this many rooms in one inquiry, and this many nights.
            'max_rooms' => min(10, max(1, (int)$get('hotel_max_rooms', 5))),
            'max_nights' => min(90, max(1, (int)$get('hotel_max_nights', 30))),
            // Guests can ask for dates up to this many days ahead.
            'days_ahead' => min(365, max(7, (int)$get('hotel_days_ahead', 120))),
            // Open questions a guest can have at once, across hotels.
            'max_open' => min(20, max(1, (int)$get('hotel_max_open', 5))),
        ];
    }

    public static function get(string $key)
    {
        return self::all()[$key];
    }

    public static function enabled(): bool
    {
        return self::all()['enabled'];
    }
}
