<?php

namespace Modules\MarketManagement\Lib;

use Illuminate\Support\Facades\Log;
use Modules\UserManagement\Entities\User;
use Throwable;

/** Pushes for E-Ride Market (type "market", actions market_*), in the person's language. */
class MarketPush
{
    public static function send(?User $user, string $action, string $title, string $body, array $replace = [], array $data = []): void
    {
        if (!$user) {
            return;
        }
        self::deliver($user, $action, $title, $body, $replace, $data);
        // The shop's business: its on-duty staff who handle it get it too, with the shop to work as.
        foreach (ShopTeam::alsoNotify($user, $data) as $staff) {
            self::deliver($staff->user, $action, $title, $body, $replace, $data + ['as_shop' => (string)$staff->shop_id]);
        }
    }

    private static function deliver(User $user, string $action, string $title, string $body, array $replace, array $data): void
    {
        try {
            $locale = $user->current_language_key;
            sendDeviceNotification(
                fcm_token: $user->fcm_token,
                title: translate(key: $title, replace: $replace, locale: $locale),
                description: translate(key: $body, replace: $replace, locale: $locale),
                status: 1,
                type: 'market',
                notification_type: 'market',
                action: $action,
                user_id: $user->id,
                notificationData: $data,
            );
        } catch (Throwable $e) {
            Log::warning('Market push failed', ['action' => $action, 'user' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
