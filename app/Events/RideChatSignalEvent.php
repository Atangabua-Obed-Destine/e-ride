<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A small, non-persisted signal about a ride chat, sent to the other party's
 * existing private chat channel ("customer-ride-chat.{tripId}" or
 * "driver-ride-chat.{tripId}"), so the apps need no new subscription:
 *
 *  - "ride-chat-read.{tripId}"   the other party has read your messages
 *  - "ride-chat-typing.{tripId}" the other party is typing
 *
 * Broadcast immediately (not queued): a late typing or read signal is useless.
 */
class RideChatSignalEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public const READ = 'ride-chat-read';
    public const TYPING = 'ride-chat-typing';

    public function __construct(
        protected string $audience,
        protected string $signal,
        protected string $tripId,
        protected array  $payload = [],
    )
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("{$this->audience}-ride-chat.{$this->tripId}")];
    }

    public function broadcastAs(): string
    {
        return "{$this->signal}.{$this->tripId}";
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
