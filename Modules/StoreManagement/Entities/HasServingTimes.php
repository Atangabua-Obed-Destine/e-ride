<?php

namespace Modules\StoreManagement\Entities;

use Illuminate\Support\Carbon;

/**
 * When a menu item or section is served: days of the week (served_days,
 * bitmask Monday = 1 ... Sunday = 64, NULL = every day) and an optional time
 * window (served_from, served_until as "HH:MM", both or neither).
 */
trait HasServingTimes
{
    public const ALL_DAYS = 127;

    /** Days as ['mon', 'thu'], or null for every day. */
    public function servedDayList(): ?array
    {
        return self::maskToDays($this->served_days);
    }

    /** [from, until] or null for all opening hours. */
    public function servedWindow(): ?array
    {
        return ($this->served_from && $this->served_until) ? [$this->served_from, $this->served_until] : null;
    }

    public static function maskToDays(?int $mask): ?array
    {
        if ($mask === null || ($mask & self::ALL_DAYS) === self::ALL_DAYS) {
            return null;
        }
        $days = [];
        foreach (Store::DAYS as $i => $day) {
            if ($mask & (1 << $i)) {
                $days[] = $day;
            }
        }
        return $days;
    }

    /** ['mon', 'thu'] to a mask; empty or all seven days is null (every day). */
    public static function daysToMask(?array $days): ?int
    {
        $mask = 0;
        foreach ((array)$days as $day) {
            $i = array_search($day, Store::DAYS, true);
            if ($i !== false) {
                $mask |= 1 << $i;
            }
        }
        return ($mask === 0 || $mask === self::ALL_DAYS) ? null : $mask;
    }

    public static function dayBit(Carbon $at): int
    {
        return 1 << ($at->dayOfWeekIso - 1);
    }
}
