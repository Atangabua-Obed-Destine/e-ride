<?php

namespace Modules\HotelManagement\Lib;

use Illuminate\Support\Facades\Log;
use Modules\UserManagement\Entities\User;
use Throwable;

/** Pushes to guests about their hotel questions (type "hotel", actions hotel_*, group Travel), in their language. */
class HotelPush
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
                type: 'hotel',
                notification_type: 'hotel',
                action: $action,
                user_id: $user->id,
                notificationData: $data,
            );
        } catch (Throwable $e) {
            Log::warning('Hotel push failed', ['action' => $action, 'user' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
