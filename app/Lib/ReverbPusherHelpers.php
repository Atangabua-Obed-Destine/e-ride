<?php

if (!function_exists('isReverbRunning')) {
    function isReverbRunning(): bool
    {
        $host = env('REVERB_HOST', '127.0.0.1');
        $port = env('REVERB_PORT', 6001);

        $connection = @fsockopen($host, $port, $errno, $errstr, 0.2);

        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }

        return false;
    }
}

use Pusher\Pusher;

if (!function_exists('isPusherRunning')) {
    function isPusherRunning(): bool
    {
        try {
            $pusher = new Pusher(
                env('PUSHER_APP_KEY'),
                env('PUSHER_APP_SECRET'),
                env('PUSHER_APP_ID'),
                [
                    'cluster' => env('PUSHER_APP_CLUSTER'),
                    'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
                    'host' => env('PUSHER_HOST'),
                    'port' => env('PUSHER_PORT'),
                    'scheme' => env('PUSHER_SCHEME', 'https'),
                ]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}



if (!function_exists('isBroadcastDriverRunning')) {
    function isBroadcastDriverRunning(): bool
    {
        $driver = config('broadcasting.default');

        return match ($driver) {
            'reverb' => isReverbRunning(),
            'pusher' => isPusherRunning(),
            default => false, // strict: any unknown driver is "not running"
        };
    }
}

if (!function_exists('areAllBroadcastServicesRunning')) {
    function areAllBroadcastServicesRunning(): bool
    {
        return isReverbRunning() && isPusherRunning();
    }
}




