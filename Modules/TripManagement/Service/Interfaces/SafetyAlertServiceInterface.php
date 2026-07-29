<?php

namespace Modules\TripManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SafetyAlertServiceInterface extends BaseServiceInterface
{
    public function create(array $data): ?Model;

    public function updatedBy(array $criteria = [], array $whereInCriteria = [], array $data = [], bool $withTrashed = false): ?Model;

    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): EloquentCollection|LengthAwarePaginator;

    public function export(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []): Collection;

    public function safetyAlertLatestUserRoute(): string;
}
