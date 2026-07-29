<?php

namespace Modules\FareManagement\Service;

use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\FareManagement\Repository\ParcelFareRepositoryInterface;

class ParcelFareService extends BaseService implements Interfaces\ParcelFareServiceInterface
{
    protected $parcelFareRepository;

    public function __construct(ParcelFareRepositoryInterface $parcelFareRepository)
    {
        parent::__construct($parcelFareRepository);
        $this->parcelFareRepository = $parcelFareRepository;
    }

    public function create(array $data): ?Model
    {
        DB::beginTransaction();
        $fare = $this->parcelFareRepository->findOneBy(criteria: ['zone_id' => $data['zone_id']]);
        $parcelFareData = [
            "zone_id" => $data['zone_id'],
            "base_fare" => $data['base_fare'],
            "return_fee" => $data['return_fee'],
            "cancellation_fee" => $data['cancellation_fee'] ?? 0,
            "base_fare_per_km" => 0,
            "cancellation_fee_percent" => 0,
            "min_cancellation_fee" => 0,
        ];
        if (is_null($fare)) {
            $parcelFare = $this->parcelFareRepository->create(data: $parcelFareData);
        } else {
            $parcelFare = $this->parcelFareRepository->update(id: $fare->id, data: $parcelFareData);
            $fare->fares()->delete();
        }


        foreach ($data['parcel_category'] as $category) {
            if (array_key_exists('weight_' . $category, $data)) {
                foreach ($data['parcel_weight'] as $weight) {
                    if (array_key_exists($weight['id'], $data['weight_' . $category])) {
                        $parcelFare?->fares()->create([
                            'parcel_weight_id' => $weight->id,
                            'parcel_category_id' => $category,
                            'base_fare' => $data['base_fare_' . $category] ?? 0,
                            'return_fee' => $data['return_fee'] ?? 0,
                            'cancellation_fee' => $data['cancellation_fee'] ?? 0,
                            'fare_per_km' => $data['weight_' . $category][$weight->id] ?? 0,
                            'zone_id' => $data['zone_id']
                        ]);
                    }
                }
            }
        }
        DB::commit();
        return $parcelFare;
    }

    public function syncNewWeightToZones(string $parcelWeightId): void
    {
        DB::beginTransaction();
        try {
            $parcelFares = $this->parcelFareRepository->getAll(relations: ['fares']);
            foreach ($parcelFares as $parcelFare) {
                $categoryIds = $parcelFare->fares->pluck('parcel_category_id')->unique()->filter();
                foreach ($categoryIds as $categoryId) {
                    $exists = $parcelFare->fares
                        ->where('parcel_category_id', $categoryId)
                        ->where('parcel_weight_id', $parcelWeightId)
                        ->isNotEmpty();
                    if ($exists) {
                        continue;
                    }
                    $categoryBaseFare = $parcelFare->fares
                        ->where('parcel_category_id', $categoryId)
                        ->first()?->base_fare ?? $parcelFare->base_fare ?? 0;
                    $parcelFare->fares()->create([
                        'parcel_weight_id' => $parcelWeightId,
                        'parcel_category_id' => $categoryId,
                        'base_fare' => $categoryBaseFare,
                        'return_fee' => $parcelFare->return_fee ?? 0,
                        'cancellation_fee' => $parcelFare->cancellation_fee ?? 0,
                        'fare_per_km' => $categoryBaseFare,
                        'zone_id' => $parcelFare->zone_id,
                    ]);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
