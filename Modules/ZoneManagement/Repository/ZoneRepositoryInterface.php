<?php

namespace Modules\ZoneManagement\Repository;

use App\Repository\EloquentRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use MatanYadaev\EloquentSpatial\Objects\Point;

interface ZoneRepositoryInterface extends EloquentRepositoryInterface
{
    public function getByPoints($point);

    public function getZoneContainingBothPoints(int|string $zoneId,Point $pickupPoint, Point $destinationPoint): ?Model;
}
