<?php

namespace Modules\FareManagement\Service;

use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\FareManagement\Entities\TripFare;
use Modules\FareManagement\Repository\TripFareRepositoryInterface;
use Modules\FareManagement\Repository\ZoneWiseDefaultTripFareRepositoryInterface;
use Modules\FareManagement\Service\Interfaces\TripFareServiceInterface;

class TripFareService extends BaseService implements TripFareServiceInterface
{
    protected $tripFareRepository;
    protected $zoneWiseDefaultTripFareRepository;

    public function __construct(TripFareRepositoryInterface $tripFareRepository, ZoneWiseDefaultTripFareRepositoryInterface $zoneWiseDefaultTripFareRepository)
    {
        parent::__construct($tripFareRepository);
        $this->tripFareRepository = $tripFareRepository;
        $this->zoneWiseDefaultTripFareRepository = $zoneWiseDefaultTripFareRepository;
    }

    public function create(array $data): ?Model
    {
        DB::beginTransaction();
        $last_query = [];
        $defaultTripFareData = [
            "zone_id" => $data['zone_id'],
            "base_fare" => $data['base_fare'] ?? 0,
            "base_fare_per_km" => $data['base_fare_per_km'] ?? 0,
            "waiting_fee_per_min" => $data['waiting_fee'] ?? 0,
            "cancellation_fee_percent" => $data['cancellation_fee'] ?? 0,
            "min_cancellation_fee" => $data['min_cancellation_fee'] ?? 0,
            "idle_fee_per_min" => $data['idle_fee'] ?? 0,
            "trip_delay_fee_per_min" => $data['trip_delay_fee'] ?? 0,
            "penalty_fee_for_cancel" => 0,
            "fee_add_to_next" => 0,
            "category_wise_different_fare" => array_key_exists('category_wise_different_fare', $data) ? 1 : 0,
        ];
        if ($data['default_fare_id']) {
            $defaultTripFare = $this->zoneWiseDefaultTripFareRepository->update(id: $data['default_fare_id'], data: $defaultTripFareData);
        } else {
            $defaultTripFare = $this->zoneWiseDefaultTripFareRepository->create($defaultTripFareData);
        }

        foreach (($data['vehicleCategories']) as $vehicleCategories) {
            $tripFare = $this->tripFareRepository->findOneBy(criteria: [
                'vehicle_category_id' => $vehicleCategories->id,
                'zone_id' => $data['zone_id']
            ]);
            if (!is_null($tripFare)) {
                $tripFare->delete();
            }

            if (array_key_exists('vehicle_category_' . $vehicleCategories->id, $data)) {
                if (!array_key_exists('category_wise_different_fare', $data)) {
                    $tripFareData = [
                        "vehicle_category_id" => $vehicleCategories->id,
                        "zone_wise_default_trip_fare_id" => $defaultTripFare?->id,
                        "zone_id" => $data['zone_id'],
                        "base_fare" => $data['base_fare'] ?? 0,
                        "base_fare_per_km" => $data['base_fare_per_km'] ?? 0,
                        "waiting_fee_per_min" => $data['waiting_fee'] ?? 0,
                        "cancellation_fee_percent" => $data['cancellation_fee'] ?? 0,
                        "min_cancellation_fee" => $data['min_cancellation_fee'] ?? 0,
                        "idle_fee_per_min" => $data['idle_fee'] ?? 0,
                        "trip_delay_fee_per_min" => $data['trip_delay_fee'] ?? 0,
                        "penalty_fee_for_cancel" => $data['trip_delay_fee'] ?? 0,
                        "fee_add_to_next" => $data['trip_delay_fee'] ?? 0,
                    ];

                } else {
                    $tripFareData = [
                        "vehicle_category_id" => $vehicleCategories->id,
                        "zone_wise_default_trip_fare_id" => $defaultTripFare?->id,
                        "zone_id" => $data['zone_id'],
                        "base_fare" => $data['base_fare_' . $vehicleCategories->id] ?? 0,
                        "base_fare_per_km" => $data['base_fare_per_km_' . $vehicleCategories->id] ?? 0,
                        "waiting_fee_per_min" => $data['waiting_fee_' . $vehicleCategories->id] ?? 0,
                        "cancellation_fee_percent" => $data['cancellation_fee_' . $vehicleCategories->id] ?? 0,
                        "min_cancellation_fee" => $data['min_cancellation_fee_' . $vehicleCategories->id] ?? 0,
                        "idle_fee_per_min" => $data['idle_fee_' . $vehicleCategories->id] ?? 0,
                        "trip_delay_fee_per_min" => $data['trip_delay_fee_' . $vehicleCategories->id] ?? 0,
                        "penalty_fee_for_cancel" => $data['penalty_fee_for_cancel_' . $vehicleCategories->id] ?? 0,
                        "fee_add_to_next" => $data['fee_add_to_next_' . $vehicleCategories->id] ?? 0,
                    ];
                }
                $last_query = $this->tripFareRepository->create($tripFareData);
            }
        }
        DB::commit();
        return $last_query;
    }
}
