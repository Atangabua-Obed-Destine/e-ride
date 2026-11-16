<?php

namespace Modules\UserManagement\Lib;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Groups a person's notifications by the part of E-Ride they come from, so the app can filter
 * them (Trips, Market, Jobs, Homes...). Decided from the stored type, notification_type and action;
 * the order of the checks matters (Jobs are sent as "market", trip check-ins as "trip").
 * "other" is everything no group claims (announcements, account messages).
 *
 * Columns are read through COALESCE(..., ''): a NULL would make "NOT (...)" unknown in SQL and drop the row.
 */
class NotificationGroups
{
    public const GROUPS = ['trips', 'travel', 'market', 'jobs', 'homes', 'food', 'safety', 'wallet', 'other'];

    private const SAFETY_TYPES = ['safety', 'safety_alert', 'safety_reward', 'danger_report'];
    private const TRIP_TYPES = ['trip', 'parcel', 'ride_request', 'auto_arrival', 'chatting', 'review', 'lost_item', 'driver'];
    private const TRIP_TYPE_FIELD = ['ride_request', 'lost_item'];
    private const WALLET_TYPES = ['fund', 'wallet', 'coupon', 'referral_code', 'level', 'withdraw_request', 'payment'];

    /** Limit $query to one group (the same rules as of()). */
    public static function apply(Builder $query, string $group): Builder
    {
        return match ($group) {
            'jobs' => $query->where(fn($q) => self::jobs($q)),
            'safety' => $query->where(fn($q) => self::safety($q)),
            'market' => $query->where(fn($q) => self::market($q))->whereNot(fn($q) => self::jobs($q))->whereNot(fn($q) => self::safety($q)),
            'travel' => $query->where(fn($q) => self::travel($q))->whereNot(fn($q) => self::claimedBefore($q, 'travel')),
            'homes' => $query->where(fn($q) => self::homes($q))->whereNot(fn($q) => self::claimedBefore($q, 'homes')),
            'food' => $query->where(fn($q) => self::food($q))->whereNot(fn($q) => self::claimedBefore($q, 'food')),
            'trips' => $query->where(fn($q) => self::trips($q))->whereNot(fn($q) => self::claimedBefore($q, 'trips')),
            'wallet' => $query->where(fn($q) => self::wallet($q))->whereNot(fn($q) => self::claimedBefore($q, 'wallet')),
            default => $query->whereNot(fn($q) => self::claimedBefore($q, 'other')),
        };
    }

    /** The group of one notification. */
    public static function of(?string $type, ?string $notificationType, ?string $action): string
    {
        $type = (string)$type;
        $nt = (string)$notificationType;
        $action = (string)$action;
        if (str_starts_with($action, 'job_')) {
            return 'jobs';
        }
        if (in_array($nt, self::SAFETY_TYPES, true) || in_array($type, self::SAFETY_TYPES, true)
            || str_starts_with($action, 'trip_checkin') || $action === 'night_ride_shared') {
            return 'safety';
        }
        if ($nt === 'market' || $type === 'market') {
            return 'market';
        }
        if ($nt === 'bus' || $type === 'bus') {
            return 'travel';
        }
        if ($nt === 'listing' || $type === 'listing' || str_starts_with($action, 'listing_')) {
            return 'homes';
        }
        if ($nt === 'store_order' || $type === 'store_order') {
            return 'food';
        }
        if (in_array($nt, self::TRIP_TYPES, true) || in_array($type, self::TRIP_TYPE_FIELD, true)) {
            return 'trips';
        }
        if (in_array($nt, self::WALLET_TYPES, true) || in_array($type, self::WALLET_TYPES, true)) {
            return 'wallet';
        }
        return 'other';
    }

    // The checks, in the order of() runs them.
    private const ORDER = ['jobs', 'safety', 'market', 'travel', 'homes', 'food', 'trips', 'wallet'];

    /** Rows that a group earlier in the order already claims. */
    private static function claimedBefore(Builder $q, string $group): Builder
    {
        $earlier = array_slice(self::ORDER, 0, array_search($group, self::ORDER, true) === false ? count(self::ORDER) : array_search($group, self::ORDER, true));
        $q->whereRaw('1 = 0');
        foreach ($earlier as $g) {
            $q->orWhere(fn($w) => self::{$g}($w));
        }
        return $q;
    }

    private static function col(string $name): \Illuminate\Contracts\Database\Query\Expression
    {
        return DB::raw("COALESCE($name, '')");
    }

    private static function jobs(Builder $q): Builder
    {
        return $q->where(self::col('action'), 'like', 'job\_%');
    }

    private static function safety(Builder $q): Builder
    {
        return $q->whereIn(self::col('notification_type'), self::SAFETY_TYPES)->orWhereIn(self::col('type'), self::SAFETY_TYPES)
            ->orWhere(self::col('action'), 'like', 'trip\_checkin%')->orWhere(self::col('action'), 'night_ride_shared');
    }

    private static function market(Builder $q): Builder
    {
        return $q->where(self::col('notification_type'), 'market')->orWhere(self::col('type'), 'market');
    }

    /** E-Ride Bus Travel. */
    private static function travel(Builder $q): Builder
    {
        return $q->where(self::col('notification_type'), 'bus')->orWhere(self::col('type'), 'bus');
    }

    private static function homes(Builder $q): Builder
    {
        return $q->where(self::col('notification_type'), 'listing')->orWhere(self::col('type'), 'listing')
            ->orWhere(self::col('action'), 'like', 'listing\_%');
    }

    private static function food(Builder $q): Builder
    {
        return $q->where(self::col('notification_type'), 'store_order')->orWhere(self::col('type'), 'store_order');
    }

    private static function trips(Builder $q): Builder
    {
        return $q->whereIn(self::col('notification_type'), self::TRIP_TYPES)->orWhereIn(self::col('type'), self::TRIP_TYPE_FIELD);
    }

    private static function wallet(Builder $q): Builder
    {
        return $q->whereIn(self::col('notification_type'), self::WALLET_TYPES)->orWhereIn(self::col('type'), self::WALLET_TYPES);
    }
}
