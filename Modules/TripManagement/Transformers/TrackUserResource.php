<?php

namespace Modules\TripManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackUserResource extends JsonResource
{


    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $trip = $this->trip;
        $driver = $this->trip->driver;
        $customer = $this->trip->customer;
        $driverLasLocations = $this->trip->driver->lastLocations;

        return [
            'id' => $this->id,
            'trip_request_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'customer_id' => $trip->customer_id,
            'encoded_polyline' => $this->encoded_polyline,
            'driver_profile_image' => onErrorImage(
                $driver->profile_image,
                dynamicStorage('storage/app/public/driver/profile') . '/' . $driver->profile_image,
                dynamicAsset('public/assets/admin-module/img/avatar/avatar.png'),
                'driver/profile/',
            ),
            'driver_name' => $driver->full_name ?? $driver->first_name . ' ' . $driver->last_name,
            'customer_name' => $customer->full_name ?? $customer->first_name . ' ' . $customer->last_name,
            'customer_profile_image' => onErrorImage(
                $customer->profile_image,
                dynamicStorage('storage/app/public/customer/profile') . '/' . $customer->profile_image,
                dynamicAsset('public/assets/admin-module/img/avatar/avatar.png'),
                'customer/profile/',
            ),
            'vehicle_image' => onErrorImage(
                $trip->vehicleCategory->image,
                dynamicStorage('storage/app/public/vehicle/category') . '/' . $trip->vehicleCategory->image,
                dynamicAsset('public/assets/admin-module/img/media/car.png'),
                'vehicle/category/',
            ),
            'vehicle_type' => $trip->vehicleCategory->type,
            'vehicle_model_name' => $trip->vehicle->model->name,
            'licence_plate_number' => $trip->vehicle->licence_plate_number,
            'latitude' => $driverLasLocations->latitude,
            'longitude' => $driverLasLocations->longitude,
            'trip_status' => $trip->current_status
        ];
    }
}
