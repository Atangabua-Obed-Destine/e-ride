<?php

namespace App\Providers;

use App\Events\StoreDriverLastLocationEvent;
use App\Listeners\BroadcastMessage;
use App\Listeners\StoreDriverLastLocationToDatabase;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Laravel\Reverb\Events\MessageReceived;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen(
            MessageReceived::class,
            BroadcastMessage::class
        );
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
