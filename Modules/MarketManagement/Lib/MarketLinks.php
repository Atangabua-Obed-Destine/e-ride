<?php

namespace Modules\MarketManagement\Lib;

use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketShop;

/**
 * The links people share: [domain]/s/<slug> for a shop, [domain]/i/<code> for an item,
 * on the short domain once it is set (config marketmanagement.base_url).
 * "Open in the app" uses the app's own scheme (eride://market/...), so it works before
 * the domain is verified for App Links; without the app, Android goes to the fallback.
 */
class MarketLinks
{
    public static function base(): string
    {
        $base = trim((string)config('marketmanagement.base_url'));
        return rtrim($base !== '' ? $base : url('/'), '/');
    }

    public static function shop(MarketShop $shop): string
    {
        return self::base() . '/s/' . $shop->slug;
    }

    public static function item(MarketItem $item): string
    {
        return self::base() . '/i/' . $item->code;
    }

    /** What opens the app on this item or shop (Android Chrome), else $fallback. $path: "/i/<code>" or "/s/<slug>". */
    public static function openInApp(string $path, string $fallback): string
    {
        return 'intent://market' . $path . '#Intent;scheme=eride;package=' . config('marketmanagement.android_package')
            . ';S.browser_fallback_url=' . rawurlencode($fallback) . ';end';
    }

    /** The Play Store page, carrying what to open once installed ("i:<code>" or "s:<slug>"). */
    public static function playStore(string $target): string
    {
        $url = businessConfig('customer_app_version_control_for_android')?->value['app_url'] ?? null;
        $url = $url ?: 'https://play.google.com/store/apps/details?id=' . config('marketmanagement.android_package');
        return $url . (str_contains($url, '?') ? '&' : '?') . 'referrer=' . rawurlencode('market=' . $target);
    }
}
