<?php

namespace Modules\UserManagement\Repository;

use App\Repository\EloquentRepositoryInterface;

interface UserLastLocationRepositoryInterface extends EloquentRepositoryInterface
{
    public function getNearestDrivers($attributes):mixed;
}
