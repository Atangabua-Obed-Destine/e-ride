<?php

namespace Modules\PlaceManagement\Service;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\PlaceManagement\Entities\DropFare;
use Modules\PlaceManagement\Entities\Landmark;
use Modules\PlaceManagement\Lib\LocalSettings;
use Modules\TripManagement\Entities\TripRequest;

/**
 * Drop fares: a fixed price for a ride between two landmarks, like a taxi
 * "drop". When the pickup and the destination each fall within a route's
 * catchment, the estimate shows that price and the rider pays exactly that at
 * the end (VAT included): no per-km, waiting, delay, surge or automatic
 * discount. A coupon still applies. The server decides, both at the estimate
 * and again at booking, so an app cannot claim a price it was not offered.
 * If the ride ends away from the destination landmark, the normal fare applies.
 */
class DropFareService
{
    /**
     * The drop route for this ride and vehicle category, if any: right now, or at the
     * pickup time of a scheduled ride ($at). Rides with stops never get one, nor
     * scheduled rides when the admin turned drop fares off for them.
     */
    public function match(Point $pickup, Point $destination, string $vehicleCategoryId, ?Carbon $at = null, bool $hasStops = false, bool $scheduled = false): ?DropFare
    {
        if ($hasStops || !LocalSettings::dropFares() || ($scheduled && !LocalSettings::all()['drop_fares_scheduled'])) {
            return null;
        }
        $at ??= now();
        $routes = DropFare::with(['from', 'to'])->where('is_active', 1)->where('vehicle_category_id', $vehicleCategoryId)
            ->orderBy('price')->get();
        foreach ($routes as $route) {
            if (!$route->from?->is_active || !$route->to?->is_active || !$route->openAt($at)) {
                continue;
            }
            $forward = $this->near($pickup, $route->from, $route->radius_m) && $this->near($destination, $route->to, $route->radius_m);
            $back = $route->both_ways && $this->near($pickup, $route->to, $route->radius_m) && $this->near($destination, $route->from, $route->radius_m);
            if ($forward || $back) {
                return $route;
            }
        }
        return null;
    }

    /**
     * The estimate list (one entry per vehicle category) with drop prices put in.
     * Each matched entry says is_drop_fare, the route, and has no surge or extra fare.
     */
    public function applyToEstimate(Collection $estimates, Point $pickup, Point $destination, bool $hasStops, ?Carbon $at = null, bool $scheduled = false): Collection
    {
        if (!LocalSettings::dropFares()) {
            return $estimates;
        }
        if ($hasStops || ($scheduled && !LocalSettings::all()['drop_fares_scheduled'])) {
            return $estimates->map(fn($entry) => ((array)$entry) + ['is_drop_fare' => false]);
        }
        return $estimates->map(function ($entry) use ($pickup, $destination, $at, $scheduled) {
            $entry = (array)$entry;
            if (!empty($entry['is_move']) || empty($entry['vehicle_category_id'])) {
                return $entry + ['is_drop_fare' => false];
            }
            // The same time and rule as at booking, so the price shown is the price booked.
            $route = $this->match($pickup, $destination, $entry['vehicle_category_id'], $at, false, $scheduled);
            if (!$route) {
                return $entry + ['is_drop_fare' => false];
            }
            return array_merge($entry, [
                'estimated_fare' => $route->price,
                'discount_fare' => $route->price,
                'discount_amount' => 0,
                'extra_estimated_fare' => 0,
                'extra_discount_fare' => 0,
                'extra_discount_amount' => 0,
                'extra_fare_amount' => 0,
                'extra_fare_fee' => 0,
                'extra_fare_reason' => '',
                'surge_multiplier' => 0,
                'is_drop_fare' => true,
                'drop_fare_id' => $route->id,
                'drop_fare_label' => $route->from->name . ' - ' . $route->to->name,
            ]);
        });
    }

    /** At the end: the ride finished where it was meant to, so the drop price stands. */
    public function holdsAtEnd(TripRequest $trip): bool
    {
        if (!$trip->drop_fare_id || $trip->drop_fare_price === null) {
            return false;
        }
        $drop = $trip->coordinate?->drop_coordinates;
        $destination = $trip->coordinate?->destination_coordinates;
        if (!$drop || !$destination) {
            return true; // nothing says it ended elsewhere
        }
        $radius = DropFare::find($trip->drop_fare_id)?->radius_m ?? LocalSettings::all()['drop_radius_m'];
        return haversineDistance($drop->latitude, $drop->longitude, $destination->latitude, $destination->longitude) <= $radius;
    }

    protected function near(Point $point, Landmark $landmark, int $radiusM): bool
    {
        return haversineDistance($point->latitude, $point->longitude, $landmark->latitude, $landmark->longitude) <= $radiusM;
    }
}
