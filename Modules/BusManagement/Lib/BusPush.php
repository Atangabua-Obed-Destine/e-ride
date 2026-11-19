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
        $locale = $user->current_language_key;
        $title = translate(key: $title, replace: $replace, locale: $locale);
        $body = translate(key: $body, replace: $replace, locale: $locale);
        try {
            sendDeviceNotification(
                fcm_token: $user->fcm_token,
                title: $title,
                description: $body,
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
        // Booked on the website: the same news by WhatsApp (or SMS), since they may not have the app.
        BusMessenger::fromPush($action, $data['bus_booking_id'] ?? null, (string)$title, (string)$body);
    }
}
