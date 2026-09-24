<?php

namespace Modules\UserManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\UserManagement\Entities\DriverAvailabilitySchedule;
use Modules\UserManagement\Repository\DriverAvailabilityScheduleRepositoryInterface;

class DriverAvailabilityScheduleRepository extends BaseRepository implements DriverAvailabilityScheduleRepositoryInterface
{
    public function __construct(DriverAvailabilitySchedule $model)
    {
        parent::__construct($model);
    }

    public function deleteExceptDay(string $userId, int $day): bool
    {
        return (bool)$this->model->newQuery()
            ->where('user_id', $userId)
            ->where('day_of_week', '!=', $day)
            ->delete();
    }
}
