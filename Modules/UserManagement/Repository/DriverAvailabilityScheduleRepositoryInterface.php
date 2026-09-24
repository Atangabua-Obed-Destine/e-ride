<?php

namespace Modules\UserManagement\Repository;

use App\Repository\EloquentRepositoryInterface;

interface DriverAvailabilityScheduleRepositoryInterface extends EloquentRepositoryInterface
{
    public function deleteExceptDay(string $userId, int $day): bool;
}
