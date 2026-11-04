<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\TripManagement\Entities\TripRequest;

/** To the customer: the deposit was not paid in time, the trip is cancelled. */
class TripDepositExpiredEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tripRequest;

    public function __construct(TripRequest $tripRequest)
    {
        $this->tripRequest = $tripRequest;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("trip-deposit-expired.{$this->tripRequest->id}"),
        ];
    }

    public function broadcastAs()
    {
        return "trip-deposit-expired.{$this->tripRequest->id}";
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->tripRequest->id,
            'type' => $this->tripRequest->type,
        ];
    }
}
