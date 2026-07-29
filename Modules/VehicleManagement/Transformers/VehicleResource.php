<?php

namespace Modules\VehicleManagement\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\UserManagement\Transformers\DriverResource;

class VehicleResource extends JsonResource
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
            'brand' => VehicleBrandResource::make($this->whenLoaded('brand')),
            'model' => VehicleModelResource::make($this->whenLoaded('model')),
            'category' => VehicleCategoryResource::make($this->whenLoaded('category')),
            'licence_plate_number' => $this->licence_plate_number,
            'licence_expire_date' => $this->licence_expire_date ,
            'vin_number' => $this->vin_number ,
            'transmission' => $this->transmission,
            'parcel_weight_capacity' => $this->parcel_weight_capacity,
            'fuel_type' => $this->fuel_type,
            'ownership' => $this->ownership,
            'driver' => (new DriverResource($this->whenLoaded('driver'))),
            'documents' => $this->documents,
            'is_active' => $this->is_active,
            'vehicle_request_status' => $this->vehicle_request_status,
            'deny_note' => $this->deny_note,
            'created_at' => $this->created_at,

        ];
    }
}
