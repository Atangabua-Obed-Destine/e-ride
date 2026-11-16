<?php

namespace Modules\BusManagement\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusDropPoint;
use Modules\BusManagement\Entities\BusSeatClaim;
use Modules\BusManagement\Entities\BusVehicle;
use Modules\BusManagement\Lib\SeatPlan;

/**
 * An agency's buses (with their seat plans) and its destinations (with drop-off points and fares).
 */
class FleetService
{
    // ------------------------------------------------------------- buses

    /** Adds or changes a bus. Departures with no sold seat follow its new plan; the others keep theirs. */
    public function saveVehicle(BusAgency $agency, array $input, ?BusVehicle $vehicle = null): BusVehicle
    {
        // "nw 123  ab" and "NW 123 AB" are the same plate: tidy it before checking it is unique.
        if (isset($input['registration']) && is_string($input['registration'])) {
            $input['registration'] = strtoupper(trim(preg_replace('/\s+/', ' ', $input['registration'])));
        }
        $data = Validator::make($input, [
            'registration' => ['required', 'string', 'max:20',
                Rule::unique('bus_vehicles', 'registration')->where('agency_id', $agency->id)->ignore($vehicle?->id)],
            'name' => 'nullable|string|max:60',
            'class' => ['required', Rule::in(BusVehicle::CLASSES)],
            'status' => ['required', Rule::in(BusVehicle::STATUSES)],
            'total_seats' => 'required|integer|min:4|max:100',
            'driver_position' => ['required', Rule::in(SeatPlan::DRIVER_POSITIONS)],
            'is_double_decker' => 'boolean',
            'upper_deck_seats' => 'nullable|integer|min:0|max:99',
            'has_rear_seats' => 'boolean',
            'rear_seats_count' => 'nullable|integer|min:3|max:6',
            'seats_per_row' => 'required|integer|min:2|max:6',
            'aisle_position' => 'required|integer|min:1|max:5',
            'first_seat_position' => ['required', Rule::in(SeatPlan::FIRST_SEAT_POSITIONS)],
            'door_positions' => 'nullable|array|max:4',
            'door_positions.*.row' => 'required|integer|min:1|max:30',
            'door_positions.*.side' => ['required', Rule::in(['left', 'right'])],
            'cargo_kg' => 'nullable|integer|min:0|max:20000',
            'amenities' => 'nullable|array',
            'amenities.*' => [Rule::in(BusVehicle::AMENITIES)],
        ], [], [
            'registration' => translate('registration number'),
            'total_seats' => translate('seat capacity'),
            'aisle_position' => translate('aisle after seat'),
        ])->validate();

        $data['is_double_decker'] = (bool)($data['is_double_decker'] ?? false);
        $data['has_rear_seats'] = (bool)($data['has_rear_seats'] ?? false);
        $data['rear_seats_count'] = (int)($data['rear_seats_count'] ?? 5);
        $data['upper_deck_seats'] = $data['is_double_decker'] ? (int)($data['upper_deck_seats'] ?? 0) : 0;
        $data['door_positions'] = array_values($data['door_positions'] ?? []);
        $data['cargo_kg'] = (int)($data['cargo_kg'] ?? 500);
        $data['amenities'] = array_values(array_unique($data['amenities'] ?? []));

        if ($data['aisle_position'] >= $data['seats_per_row']) {
            $this->fail('aisle_position', translate('The aisle comes after the last seat of the row. Lower the number in Aisle after seat.'));
        }
        if ($data['is_double_decker'] && ($data['upper_deck_seats'] < 1 || $data['upper_deck_seats'] >= $data['total_seats'])) {
            $this->fail('upper_deck_seats', translate('Put some seats on each deck.'));
        }
        if ($data['has_rear_seats'] && $data['rear_seats_count'] >= $data['total_seats']) {
            $this->fail('rear_seats_count', translate('The back row has more seats than the bus.'));
        }

        return DB::transaction(function () use ($agency, $data, $vehicle) {
            $vehicle ??= new BusVehicle(['agency_id' => $agency->id]);
            $vehicle->fill($data);
            $vehicle->agency_id = $agency->id;
            $vehicle->save();
            $this->refreshPlans($vehicle);
            return $vehicle->fresh();
        });
    }

    /** Open departures of this bus with no sold seat take its current plan. Returns how many kept their old one. */
    public function refreshPlans(BusVehicle $vehicle): int
    {
        $kept = 0;
        $plan = $vehicle->seatPlan();
        BusDeparture::where('vehicle_id', $vehicle->id)->whereIn('status', BusDeparture::OPEN)->where('date', '>=', today())
            ->get()->each(function (BusDeparture $d) use ($plan, &$kept) {
                if (BusSeatClaim::where('departure_id', $d->id)->exists()) {
                    $kept++;
                    return;
                }
                $d->update(['seat_plan' => $plan, 'capacity' => $plan['total']]);
            });
        return $kept;
    }

    // ------------------------------------------------------------- destinations

    /**
     * Adds or changes a destination with its drop-off points, in order (the last one is the end of the line).
     * Each point has a fare for at least one class.
     */
    public function saveDestination(BusAgency $agency, array $input, ?BusDestination $destination = null): BusDestination
    {
        $data = Validator::make($input, [
            'city' => ['required', 'string', 'max:80',
                Rule::unique('bus_destinations', 'city')->where('agency_id', $agency->id)->ignore($destination?->id)],
            'duration_minutes' => 'nullable|integer|min:10|max:2880',
            'active' => 'boolean',
            'points' => 'required|array|min:1|max:12',
            'points.*.id' => 'nullable|uuid',
            'points.*.name' => 'required|string|max:120',
            'points.*.lat' => 'nullable|numeric|between:-90,90',
            'points.*.lng' => 'nullable|numeric|between:-180,180',
            'points.*.fare_classic' => 'nullable|numeric|min:0|max:1000000',
            'points.*.fare_vip' => 'nullable|numeric|min:0|max:1000000',
            'points.*.fare_executive' => 'nullable|numeric|min:0|max:1000000',
            'points.*.minutes_from_start' => 'nullable|integer|min:0|max:2880',
        ], [], ['city' => translate('destination')])->validate();

        $city = trim($data['city']);
        if (mb_strtolower($city) === mb_strtolower(trim($agency->origin_city))) {
            $this->fail('city', translate(key: 'Your departures leave from {city}: choose another city.', replace: ['city' => $agency->origin_city]));
        }
        foreach ($data['points'] as $i => $p) {
            if (!collect(['fare_classic', 'fare_vip', 'fare_executive'])->contains(fn($k) => ($p[$k] ?? 0) > 0)) {
                $this->fail("points.$i.fare_classic", translate(key: 'Give {name} a fare for at least one class.', replace: ['name' => $p['name']]));
            }
        }

        return DB::transaction(function () use ($agency, $data, $city, $destination) {
            $destination ??= new BusDestination(['agency_id' => $agency->id]);
            $destination->fill([
                'city' => $city,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'active' => $data['active'] ?? true,
            ]);
            $destination->agency_id = $agency->id;
            $destination->save();

            $keep = [];
            foreach (array_values($data['points']) as $i => $p) {
                $point = isset($p['id']) ? BusDropPoint::where('destination_id', $destination->id)->find($p['id']) : null;
                $point ??= new BusDropPoint(['destination_id' => $destination->id]);
                $point->fill([
                    'name' => trim($p['name']),
                    'lat' => $p['lat'] ?? null,
                    'lng' => $p['lng'] ?? null,
                    'fare_classic' => ($p['fare_classic'] ?? 0) > 0 ? $p['fare_classic'] : null,
                    'fare_vip' => ($p['fare_vip'] ?? 0) > 0 ? $p['fare_vip'] : null,
                    'fare_executive' => ($p['fare_executive'] ?? 0) > 0 ? $p['fare_executive'] : null,
                    'minutes_from_start' => $p['minutes_from_start'] ?? null,
                    'sort' => $i,
                    'active' => true,
                ]);
                $point->destination_id = $destination->id;
                $point->save();
                $keep[] = $point->id;
            }
            // Points no longer listed stop being offered (tickets that use them keep pointing to them).
            BusDropPoint::where('destination_id', $destination->id)->whereNotIn('id', $keep)->update(['active' => false]);
            return $destination->fresh('dropPoints');
        });
    }

    protected function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
