<?php

namespace Modules\TripManagement\Service;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\TripManagement\Entities\TripRequest;

/**
 * Share trip: the public link (/t/{token}) friends, family or a parcel's
 * receiver open in any browser to follow the trip live. It is live from the
 * driver's acceptance until the trip ends, then shows how it ended for
 * ENDED_VISIBLE_HOURS. It never shows phone numbers, last names or the fare.
 */
class TripShareService
{
    public const ENDED_VISIBLE_HOURS = 2;
    public const LIVE_STATUSES = [ACCEPTED, OUT_FOR_PICKUP, ONGOING, RETURNING];

    /** Road distance is longer than a straight line; used when there is no route. */
    private const ROAD_FACTOR = 1.3;
    /** km per minute when the trip has no estimate (about 24 km/h in town). */
    private const DEFAULT_SPEED = 0.4;

    public static function enabled(): bool
    {
        return (bool)(businessConfig('enable_real_time_location_sharing', TRIP_SETTINGS)?->value ?? 0);
    }

    public function url(TripRequest $trip): ?string
    {
        if (!self::enabled() || !$trip->share_token) {
            return null;
        }
        return url('t/' . $trip->share_token);
    }

    /** The link, while the trip can be followed. */
    public function liveUrl(TripRequest $trip): ?string
    {
        return in_array($trip->current_status, self::LIVE_STATUSES, true) ? $this->url($trip) : null;
    }

    public function findByToken(string $token): ?TripRequest
    {
        if (!self::enabled() || !preg_match('/^[A-Za-z0-9]{20,40}$/', $token)) {
            return null;
        }
        return TripRequest::with([
            'coordinate', 'tripStatus', 'time', 'tripNavigation', 'vehicleCategory', 'vehicle.model',
            'customer', 'driver.lastLocations', 'parcelUserInfo',
        ])->where('share_token', $token)->first();
    }

    /** Stop sharing: the old link stops working at once. */
    public function regenerate(TripRequest $trip): void
    {
        $trip->share_token = Str::random(32);
        $trip->save();
    }

    public function markShared(TripRequest $trip): void
    {
        if (!$trip->shared_at) {
            $trip->shared_at = now();
            $trip->save();
        }
    }

    /**
     * waiting (no driver yet), coming (driver on the way to the pickup), on_trip,
     * returning (parcel going back to the sender), arrived, cancelled, returned,
     * or expired (ended more than ENDED_VISIBLE_HOURS ago, or no such trip).
     */
    public function state(?TripRequest $trip): string
    {
        if (!$trip) {
            return 'expired';
        }
        $state = match ($trip->current_status) {
            PENDING => 'waiting',
            ACCEPTED, OUT_FOR_PICKUP => 'coming',
            ONGOING => 'on_trip',
            RETURNING => 'returning',
            COMPLETED => 'arrived',
            CANCELLED => 'cancelled',
            RETURNED => 'returned',
            default => 'expired',
        };
        $endedAt = $this->endedAt($trip);
        if ($endedAt && $endedAt->lt(now()->subHours(self::ENDED_VISIBLE_HOURS))) {
            return 'expired';
        }
        return $state;
    }

    public function payload(?TripRequest $trip): array
    {
        $state = $this->state($trip);
        $payload = [
            'state' => $state,
            'type' => $trip ? $this->type($trip) : 'ride',
            'business_name' => businessConfig('business_name')?->value ?? 'E-Ride',
        ];
        if (!$trip || $state === 'expired') {
            return $payload;
        }

        $coordinate = $trip->coordinate;
        $receiver = $trip->type === PARCEL ? $trip->parcelUserInfo->firstWhere('user_type', 'receiver') : null;
        $payload += [
            'customer_name' => $this->firstName($trip->customer?->first_name),
            'receiver_name' => $receiver ? $this->firstName($receiver->name) : null,
            'pickup' => $this->point($coordinate?->pickup_coordinates, $coordinate?->pickup_address),
            'destination' => $this->point($coordinate?->destination_coordinates, $coordinate?->destination_address),
            'started_at' => $this->iso($trip->tripStatus?->ongoing),
            'ended_at' => $this->iso($this->endedAt($trip)),
        ];

        $driver = $trip->driver;
        if ($driver && $state !== 'waiting') {
            $payload['driver'] = [
                'name' => $this->firstName($driver->first_name),
                'photo' => onErrorImage(
                    $driver->profile_image,
                    dynamicStorage('storage/app/public/driver/profile') . '/' . $driver->profile_image,
                    dynamicAsset('public/assets/admin-module/img/avatar/avatar.png'),
                    'driver/profile/',
                ),
            ];
            $payload['vehicle'] = [
                'model' => $trip->vehicle?->model?->name,
                'plate' => $trip->vehicle?->licence_plate_number,
                'category' => $trip->vehicleCategory?->name,
                'type' => $trip->vehicleCategory?->type,
                'image' => onErrorImage(
                    $trip->vehicleCategory?->image,
                    dynamicStorage('storage/app/public/vehicle/category') . '/' . $trip->vehicleCategory?->image,
                    dynamicAsset('public/assets/admin-module/img/media/car.png'),
                    'vehicle/category/',
                ),
            ];
        }

        if (in_array($state, ['coming', 'on_trip', 'returning'], true)) {
            $location = $driver?->lastLocations;
            $position = $location && $location->latitude && $location->longitude
                ? ['lat' => (float)$location->latitude, 'lng' => (float)$location->longitude]
                : null;
            $payload['position'] = $position;
            $payload['route'] = $state === 'on_trip'
                ? ($trip->tripNavigation?->encoded_polyline ?: $trip->encoded_polyline)
                : null;
            $payload['eta_minutes'] = $this->etaMinutes($trip, $state, $position, $payload);
        }

        return $payload;
    }

    private function type(TripRequest $trip): string
    {
        if ($trip->type === PARCEL) {
            return 'parcel';
        }
        return $trip->is_move ? 'move' : 'ride';
    }

    /** Minutes to the pickup (coming), the destination (on_trip) or back to the sender (returning). */
    private function etaMinutes(TripRequest $trip, string $state, ?array $position, array $payload): ?int
    {
        if (!$position) {
            return null;
        }
        $speed = self::DEFAULT_SPEED;
        $estimatedMinutes = (float)($trip->time?->estimated_time ?? 0);
        if ($estimatedMinutes > 0 && (float)$trip->estimated_distance > 0) {
            $speed = min(1.2, max(0.15, (float)$trip->estimated_distance / $estimatedMinutes));
        }

        $target = $state === 'on_trip' ? $payload['destination'] : $payload['pickup'];
        if (!$target) {
            return null;
        }
        $straightKm = haversineDistance($position['lat'], $position['lng'], $target['lat'], $target['lng']) / 1000;
        $km = $straightKm * self::ROAD_FACTOR;
        // The live route is cut at the driver's position: its length is what's left,
        // unless it is stale (far longer than the way there).
        if ($state === 'on_trip' && $trip->tripNavigation?->encoded_polyline) {
            $routeKm = $this->polylineKm($trip->tripNavigation->encoded_polyline);
            if ($routeKm !== null && $routeKm <= $straightKm * 3 + 2) {
                $km = $routeKm;
            }
        }

        return max(1, (int)round($km / $speed));
    }

    private function polylineKm(string $encoded): ?float
    {
        $points = decodePolyline($encoded);
        if (count($points) < 2) {
            return null;
        }
        $meters = 0;
        for ($i = 1; $i < count($points); $i++) {
            $meters += haversineDistance($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1]);
        }
        return $meters / 1000;
    }

    private function endedAt(TripRequest $trip): ?Carbon
    {
        $value = match ($trip->current_status) {
            COMPLETED => $trip->tripStatus?->completed,
            CANCELLED => $trip->tripStatus?->cancelled,
            RETURNED => $trip->tripStatus?->returned,
            default => null,
        };
        if ($value) {
            return Carbon::parse($value);
        }
        return in_array($trip->current_status, [COMPLETED, CANCELLED, RETURNED], true) ? Carbon::parse($trip->updated_at) : null;
    }

    private function point($point, ?string $address): ?array
    {
        if (!$point) {
            return null;
        }
        return ['lat' => (float)$point->latitude, 'lng' => (float)$point->longitude, 'address' => $address];
    }

    private function firstName(?string $name): ?string
    {
        $name = trim((string)$name);
        return $name === '' ? null : Str::before($name, ' ');
    }

    private function iso($time): ?string
    {
        return $time ? Carbon::parse($time)->toIso8601String() : null;
    }
}
