<?php

namespace Modules\VehicleManagement\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FareManagement\Transformers\TripFareResource;

class VehicleCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->image,
            'type' => $this->type,
            // For E-Ride Move: what the vehicle fits ("A 2-bedroom flat").
            'description' => $this->description,
            'fare' => TripFareResource::collection($this->whenLoaded('tripFares'))
        ];
    }
}
