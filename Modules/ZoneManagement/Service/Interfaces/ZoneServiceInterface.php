<?php

namespace Modules\ZoneManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\ZoneManagement\Entities\Zone;

interface ZoneServiceInterface extends BaseServiceInterface
{
    public function getZones(array $criteria = []): array;

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): Collection|LengthAwarePaginator|\Illuminate\Support\Collection;

    public function getByPoints($point);

    public function storeExtraFare(array $data);

    public function storeExtraFareAll(array $data);

    public function statusChangeExtraFare(string|int $id, array $data): ?Model;

    public function getZoneContainingBothPoints(int|string $zoneId ,Point $pickupPoint, Point $destinationPoint): ?Model;
}
