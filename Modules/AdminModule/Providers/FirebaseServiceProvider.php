<?php

namespace Modules\AdminModule\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('firebase.firestore', function ($app) {
            $serviceAccountKey = json_decode(businessConfig(key: SERVER_KEY, settingsType: NOTIFICATION_SETTINGS)?->value,true) ?? [];
            if (count($serviceAccountKey) > 0) {
                $serviceAccount = $serviceAccountKey;
                return (new Factory)
                    ->withServiceAccount($serviceAccount)
                    ->createMessaging();
            }
            return false;
        });

        $this->app->singleton('firebase.messaging', function ($app) {
            $serviceAccountKey = json_decode(businessConfig(key: SERVER_KEY, settingsType: NOTIFICATION_SETTINGS)?->value,true) ?? [];
            if (count($serviceAccountKey) > 0) {
                $serviceAccount = $serviceAccountKey;
                return (new Factory)
                    ->withServiceAccount($serviceAccount)
                    ->createMessaging();
            }
            return false;
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
