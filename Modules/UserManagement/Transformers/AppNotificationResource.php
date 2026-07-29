<?php

namespace Modules\UserManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'notification_type' => $this->notification_type,
            'action' => $this->action,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at,
        ];
    }
}
