<?php

namespace Modules\UserManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Modules\UserManagement\Entities\UserLastLocation;
use Modules\UserManagement\Repository\UserLastLocationRepositoryInterface;

class UserLastLocationRepository extends BaseRepository implements UserLastLocationRepositoryInterface
{
    protected $lastLocation;
    public function __construct(UserLastLocation $model)
    {
        parent::__construct($model);
        $this->lastLocation = $model;
    }

    public function getNearestDrivers($attributes): mixed
    {
        $availabilityAt = !empty($attributes['availability_at'])
            ? \Carbon\Carbon::parse($attributes['availability_at'])
            : now();
        $currentDay = $availabilityAt->dayOfWeek;
        $currentTime = $availabilityAt->format('H:i:s');

        return $this->lastLocation
            ->selectRaw("* ,( 6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude)
            - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$attributes['latitude'], $attributes['longitude'], $attributes['latitude']])
            ->where('type', 'driver')
            ->where('zone_id', $attributes['zone_id'])
            ->having('distance', '<', $attributes['radius'])
            ->with(['user.vehicle.category', 'driverDetails', 'user'])
            ->whereHas('user', fn($query) => $query->where('is_active', true))
            ->when(array_key_exists('driver_gender', $attributes),
                fn($query) => $query->whereHas('user', fn($q) => $q->where('gender', $attributes['driver_gender']))
            )
            ->whereHas('driverDetails', fn($query) => $query->where('is_online', true)
                ->whereNotIn('availability_status', ['unavailable', 'on_trip'])
            )
            ->where(function ($query) use ($currentDay, $currentTime) {
                $query->where(function ($sameTime) use ($currentTime) {
                    $sameTime->whereHas('driverDetails', fn($q) => $q->where('same_time_for_every_day', 1))
                        ->whereHas('user.availabilitySchedules', fn($q) => $q->where('day_of_week', 0)
                            ->where('start_time', '<=', $currentTime)
                            ->where('end_time', '>=', $currentTime));
                })->orWhere(function ($perDay) use ($currentDay, $currentTime) {
                    $perDay->whereHas('driverDetails', fn($q) => $q->where('same_time_for_every_day', 0))
                        ->whereHas('user.availabilitySchedules', fn($q) => $q->where('day_of_week', $currentDay)
                            ->where('start_time', '<=', $currentTime)
                            ->where('end_time', '>=', $currentTime));
                });
            })
            ->whereHas('user.vehicle', fn($query) => $query->where('is_active', true))
            ->when(array_key_exists('vehicle_category_id', $attributes), function ($query) use ($attributes) {
                $query->whereHas('user.vehicle', fn($query) => $query->ofStatus(1)->where('category_id', $attributes['vehicle_category_id']));
            })
            ->when(array_key_exists('service', $attributes),
                fn($query) => $query->whereHas('driverDetails',
                    fn($query) => $query->where(fn($query) => $query->whereNull('service')
                        ->orWhere(fn($query) => $query->whereNotNull('service')
                            ->whereJsonContains('service', $attributes['service'])
                        )
                    )
                )
            )
            ->when($attributes['parcel_weight_capacity'] ?? null,
                fn($query) => $query->whereHas('driverDetails',
                    fn($query) => $query->where(fn($query) => $query->whereNull('service')
                        ->orWhere(fn($query) => $query->whereNotNull('service')
                            ->whereJsonContains('service', 'parcel')
                        )
                    )
                )->whereHas('user.vehicle',
                    fn($query) => $query->whereNull('parcel_weight_capacity')
                        ->orWhere('parcel_weight_capacity', '>=', $attributes['parcel_weight_capacity'])
                )
            )
            ->orderBy('distance')
            ->get();
    }
}
