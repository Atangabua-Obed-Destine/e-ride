<?php

namespace App\Listeners;

use App\Events\SendMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Reverb\Events\MessageReceived;

class BroadcastMessage
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(MessageReceived $event): void
    {
        $message = json_decode($event->message);

        $data = $message->data;
        info($message->event);


        if ($message->event === 'client-message') {

            info(json_encode($data,true));
            info("data received");
//            broadcast(new SendMessage($data))->toOthers();

        }

    }
}
