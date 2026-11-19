<?php

namespace Modules\BusManagement\Lib;

use Modules\BusManagement\Entities\BusBooking;

/**
 * Links of the bus website, and the ones that open the E-Ride app.
 * The app opens with its own scheme (eride://market/...), which works without App Links verification;
 * without the app, Android goes to the fallback (the Play Store page).
 */
class BusWebLinks
{
    /** The booking's page on the website (what WhatsApp and SMS messages link to). */
    public static function booking(BusBooking|string $booking): string
    {
        return route('bus.web.booking', ['code' => is_string($booking) ? $booking : $booking->code]);
    }

    /**
     * What "Continue in the app" opens: bt:CODE (a booking), bv (ID verification), bh (bus home).
     * Android Chrome opens the app on it, or goes to the Play Store.
     */
    public static function openInApp(string $target): string
    {
        $path = match (true) {
            str_starts_with($target, 'bt:') => '/bt/' . substr($target, 3),
            $target === 'bv' => '/bv/id',
            default => '/bh/home',
        };
        return 'intent://market' . $path . '#Intent;scheme=eride;package=' . config('marketmanagement.android_package')
            . ';S.browser_fallback_url=' . rawurlencode(self::playStore($target)) . ';end';
    }

    /** The Play Store page, carrying what to open once installed. */
    public static function playStore(string $target): string
    {
        $url = businessConfig('customer_app_version_control_for_android')?->value['app_url'] ?? null;
        $url = $url ?: 'https://play.google.com/store/apps/details?id=' . config('marketmanagement.android_package');
        $ref = str_starts_with($target, 'bt:') ? 'bt:' . substr($target, 3) : ($target === 'bv' ? 'bv:id' : 'bh:home');
        return $url . (str_contains($url, '?') ? '&' : '?') . 'referrer=' . rawurlencode('market=' . $ref);
    }

    public static function valid(?string $target): bool
    {
        return $target !== null && (bool)preg_match('/^(bt:[A-Z0-9]{6,12}|bv|bh)$/', $target);
    }
}
