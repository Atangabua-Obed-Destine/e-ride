<?php

namespace Modules\TripManagement\Repository;

use App\Repository\EloquentRepositoryInterface;

interface TripNavigationRepositoryInterface extends EloquentRepositoryInterface
{
    public function getLockedTrip(array $data = []): mixed;
}
