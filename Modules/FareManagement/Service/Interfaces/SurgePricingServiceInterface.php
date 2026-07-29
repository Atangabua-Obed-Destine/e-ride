<?php

namespace Modules\FareManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface SurgePricingServiceInterface extends BaseServiceInterface
{
    public function checkSurgePricing(int|string $zoneId, int|string $tripType, int|string $vehicleCategoryId = null, $scheduledAt = null): array;

    public function updatesSurgePricing(int|string $id, array $data = []): ?Model;

    public function updateZone(string|int $id, array $data): ?Model;

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection;

    public function getRidesByDateAndTimeRange($data, $id): array;

    public function fetchSurgesForZone(int|string $zoneId, $scheduledAt = null): Collection;

    public function evaluateSurgeForCategory(Collection $surges, int|string $tripType, int|string $vehicleCategoryId = null, $scheduledAt = null): array;

}
