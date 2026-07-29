<?php

namespace Modules\UserManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface DriverIdentityVerificationServiceInterface extends BaseServiceInterface
{
    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator;
    public function skip(?Model $user): void;
    public function verify(array $data): array;

    public function MarkIdentityAsVerified(?Model $unverifiedDriverInfo, array $data): void;
    public function MarkIdentityAsSuspended(?Model $unverifiedDriverInfo): void;
}
