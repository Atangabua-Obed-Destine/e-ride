<?php

namespace Modules\ZoneManagement\Lib;

use Illuminate\Support\Carbon;

/**
 * The E-Ride support desk: who answers safety alerts and lost items that need support,
 * the number riders can call, and when it is staffed (Zone management > Safety map).
 * Riders see the number and the hours on a lost item that needs support; the admin
 * pages show who is on duty now.
 */
class SupportDesk
{
    public const TYPE = 'support_desk_settings';

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        $businessPhone = (string)(businessConfig('business_contact_phone', BUSINESS_INFORMATION)?->value ?? '');
        return [
            // What riders see, e.g. "E-Ride safety desk".
            'name' => (string)$get('support_desk_name', 'E-Ride support'),
            // The number riders call; the business contact number until an admin sets one.
            'phone' => (string)$get('support_desk_phone', $businessPhone),
            // Staffed from..to (0-23, to the next morning when to < from). Same hour twice: around the clock.
            'from' => min(23, max(0, (int)$get('support_desk_from', 8))),
            'to' => min(23, max(0, (int)$get('support_desk_to', 20))),
            // For the team only: who is on duty when, e.g. "Marie 08-14, Paul 14-20".
            'staff' => (string)$get('support_desk_staff', ''),
        ];
    }

    public static function openNow(?Carbon $at = null): bool
    {
        $s = self::all();
        $h = ($at ?? now())->hour;
        if ($s['from'] === $s['to']) {
            return true;
        }
        return $s['from'] < $s['to'] ? ($h >= $s['from'] && $h < $s['to']) : ($h >= $s['from'] || $h < $s['to']);
    }

    /** "from 08:00 to 20:00", or "around the clock" (read inside a sentence). */
    public static function hoursLabel(?string $locale = null): string
    {
        $s = self::all();
        if ($s['from'] === $s['to']) {
            return translate(key: 'around the clock', locale: $locale);
        }
        return translate(key: 'from {from} to {to}', replace: [
            'from' => str_pad((string)$s['from'], 2, '0', STR_PAD_LEFT) . ':00',
            'to' => str_pad((string)$s['to'], 2, '0', STR_PAD_LEFT) . ':00',
        ], locale: $locale);
    }

    /** What the apps show. */
    public static function present(): array
    {
        $s = self::all();
        return [
            'name' => $s['name'],
            'phone' => $s['phone'] !== '' ? $s['phone'] : null,
            'hours' => self::hoursLabel(),
            'open_now' => self::openNow(),
        ];
    }
}
