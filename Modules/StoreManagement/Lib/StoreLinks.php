<?php

namespace Modules\StoreManagement\Lib;

use Illuminate\Support\Facades\DB;
use Modules\MarketManagement\Lib\MarketLinks;
use Modules\StoreManagement\Entities\Store;
use Modules\UserManagement\Entities\User;
use Throwable;

/**
 * A partner's public store page, [domain]/f/<slug> (the brand's slug): the
 * link partners and customers share on WhatsApp. "Open in the app" opens the
 * store in E-Ride; without the app, the Play Store link carries "f:<slug>" so
 * the store opens after installing, and the install is counted for the store.
 */
class StoreLinks
{
    public static function url(Store $store): string
    {
        return MarketLinks::base() . '/f/' . $store->brand()->slug;
    }

    public static function openInApp(Store $store): string
    {
        $slug = $store->brand()->slug;
        return MarketLinks::openInApp('/f/' . $slug, route('store.get-app', ['slug' => $slug]));
    }

    public static function playStore(Store $store): string
    {
        return MarketLinks::playStore('f:' . $store->brand()->slug);
    }

    /** A visit to the page, once per visitor and day. */
    public static function visit(Store $brand, string $visitor): void
    {
        try {
            $inserted = DB::table('store_link_visits')->insertOrIgnore([
                'day' => now()->toDateString(),
                'store_id' => $brand->id,
                'visitor' => sha1($visitor . '|' . config('app.key')),
            ]);
            if ($inserted) {
                Store::whereKey($brand->id)->increment('link_visits');
            }
        } catch (Throwable) {
        }
    }

    /** A new rider who installed from a store link: once per rider, riders who joined in the last 14 days. */
    public static function claim(User $user, string $slug): bool
    {
        if (!$user->created_at || $user->created_at->lt(now()->subDays(14))) {
            return false;
        }
        $brand = Store::where('slug', $slug)->whereNull('parent_id')->first();
        if (!$brand || $brand->user_id === $user->id) {
            return false;
        }
        try {
            $inserted = DB::table('store_link_referrals')->insertOrIgnore(['user_id' => $user->id, 'store_id' => $brand->id, 'created_at' => now()]);
        } catch (Throwable) {
            return false;
        }
        if ($inserted) {
            $brand->increment('link_installs');
        }
        return (bool)$inserted;
    }
}
