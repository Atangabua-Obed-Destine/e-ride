<?php

namespace Modules\BusManagement\Lib;

use Illuminate\Support\Facades\Log;
use Modules\UserManagement\Entities\User;
use Throwable;

/** Pushes for E-Ride Bus Travel (type "bus", actions bus_*, group Travel), in the person's language. */
class BusPush
{
    public static function send(?User $user, string $action, string $title, string $body, array $replace = [], array $data = []): void
    {
        if (!$user) {
            return;
        }
        try {
            $locale = $user->current_language_key;
            sendDeviceNotification(
                fcm_token: $user->fcm_token,
                title: translate(key: $title, replace: $replace, locale: $locale),
                description: translate(key: $body, replace: $replace, locale: $locale),
                status: 1,
                type: 'bus',
                notification_type: 'bus',
                action: $action,
                user_id: $user->id,
                notificationData: $data,
            );
        } catch (Throwable $e) {
            Log::warning('Bus push failed', ['action' => $action, 'user' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
