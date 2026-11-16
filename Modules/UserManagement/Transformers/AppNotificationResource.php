<?php

namespace Modules\UserManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\TripManagement\Entities\TripRequest;

class AppNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'ride_request_id' => $this->ride_request_id,
            'title' => $this->clean($this->title),
            'description' => $this->clean($this->description),
            'type' => $this->type,
            'notification_type' => $this->notification_type,
            'action' => $this->action,
            // What it is about (chat, order, job, place...), for "Open" in the list.
            'data' => (object)$this->routeData(),
            'is_read' => $this->is_read,
            'group' => \Modules\UserManagement\Lib\NotificationGroups::of($this->type, $this->notification_type, $this->action),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Older notifications were saved with a "lang." prefix and unfilled
     * placeholders ("You got a new message from {userName}."). Show them as
     * they should have read: the chat partner's name, or the sentence without it.
     */
    private function clean(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }
        $text = preg_replace('/^lang\./', '', $text);
        if (str_contains($text, '{userName}')) {
            $name = $this->data['user_name'] ?? $this->otherPartyName();
            $text = $name ? str_replace('{userName}', $name, $text) : preg_replace('/\s+(from|de)\s+\{userName\}/i', '', $text);
        }
        return trim(preg_replace('/\s*\{[A-Za-z]+\}/', '', $text));
    }

    /** The stored data; an older trip chat also gets the other person's name (the chat screen shows it). */
    private function routeData(): array
    {
        $data = $this->data ?? [];
        if ($this->action === 'new_message' && empty($data['user_name']) && ($name = $this->otherPartyName())) {
            $data['user_name'] = $name;
        }
        return $data;
    }

    /** For a trip chat: the other person on the trip. */
    private function otherPartyName(): ?string
    {
        if (!$this->ride_request_id) {
            return null;
        }
        $trip = TripRequest::with(['customer:id,first_name', 'driver:id,first_name'])->select('id', 'customer_id', 'driver_id')->find($this->ride_request_id);
        if (!$trip) {
            return null;
        }
        $other = $trip->customer_id === $this->user_id ? $trip->driver : $trip->customer;
        return $other?->first_name ?: null;
    }
}
