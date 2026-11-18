<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\TripManagement\Entities\TripRequest;

class DriverTripCancelledEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tripRequest;
    /** The trip E-Ride opened again for the customer (smart rebooking), if any. */
    public ?string $newTripId;
    /** Why: 'no_driver' when nobody accepted in time (there never was a driver). */
    public ?string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(TripRequest $tripRequest, ?string $newTripId = null, ?string $reason = null)
    {
        $this->tripRequest = $tripRequest;
        $this->newTripId = $newTripId;
        $this->reason = $reason;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("driver-trip-cancelled.{$this->tripRequest->id}"),
        ];
    }

    public function broadcastAs()
    {
        return "driver-trip-cancelled.{$this->tripRequest->id}";
    }

    public function broadcastWith()
    {
        return [
            'id'=>$this->tripRequest->id,
            'type'=>$this->tripRequest->type,
            'user_id'=>$this->tripRequest->customer->id,
            'new_trip_id' => $this->newTripId,
            'reason' => $this->reason,
        ];
    }
}
