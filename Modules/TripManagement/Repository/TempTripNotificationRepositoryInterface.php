<?php

namespace Modules\TripManagement\Repository;

use App\Repository\EloquentRepositoryInterface;

interface TempTripNotificationRepositoryInterface extends EloquentRepositoryInterface
{
    public function getData(array $criteria = [], array $relations = [], array $orderBy = [], array $whereNotInCriteria = []): mixed;
}
