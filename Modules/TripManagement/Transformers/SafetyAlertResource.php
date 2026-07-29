<?php

namespace Modules\TripManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SafetyAlertResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'alert_location' => $this->alert_location,
            'reason' => $this->reason,
            'comment' => $this->comment,
            'status' => $this->status,
            'trip_request_id' => $this->trip_request_id,
            'sent_by' => $this->sent_by,
            'resolved_location' => $this->resolved_location,
            'number_of_alert' => $this->number_of_alert,
            'resolved_by' => $this->resolved_by,
            'trip_status_when_make_alert' => $this->trip_status_when_make_alert,
        ];
    }
}
