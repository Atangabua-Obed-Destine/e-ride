<?php

namespace Modules\FareManagement\Repository;

use App\Repository\EloquentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface SurgePricingRepositoryInterface extends EloquentRepositoryInterface
{
    public function getSurgePricingListForChecking(int|string $zoneId, array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator;
}
