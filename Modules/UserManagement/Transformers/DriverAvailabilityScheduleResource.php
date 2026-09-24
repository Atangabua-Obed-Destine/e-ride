<?php

namespace Modules\UserManagement\Transformers;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverAvailabilityScheduleResource extends JsonResource
{
    public function toArray($request): array
    {
        $sameTime = (bool)($this->resource['sameTime'] ?? false);
        $grouped = $this->resource['schedules'] ?? collect();

        $schedule = [];
        for ($day = 0; $day <= 6; $day++) {
            $slots = $sameTime ? ($grouped->get(0) ?? collect()) : ($grouped->get($day) ?? collect());
            foreach ($slots as $slot) {
                $schedule[] = [
                    'id' => $slot->id,
                    'day' => $day,
                    'start_time' => Carbon::parse($slot->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($slot->end_time)->format('H:i'),
                ];
            }
        }

        return [
            'same_time_for_every_day' => $sameTime,
            'schedule' => $schedule,
        ];
    }
}
