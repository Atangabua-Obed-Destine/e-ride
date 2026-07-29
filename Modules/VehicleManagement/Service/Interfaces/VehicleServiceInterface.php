<?php

namespace Modules\VehicleManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

interface VehicleServiceInterface extends BaseServiceInterface
{
    public function updatedByAdmin(int|string $id, array $data = []): ?Model;



    public function export(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection;

    public function updatedByDriver(int|string $id, array $data): ?Model;

    public function deniedVehicleUpdateByAdmin(int|string $id, array $data = []): ?Model;

    public function exportUpdateVehicle(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection;

}
