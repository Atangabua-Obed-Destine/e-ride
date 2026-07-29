<?php

namespace Modules\TripManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\TripManagement\Entities\TempTripNotification;
use Modules\TripManagement\Repository\TempTripNotificationRepositoryInterface;

class TempTripNotificationRepository extends BaseRepository implements TempTripNotificationRepositoryInterface
{
    public function __construct(TempTripNotification $model)
    {
        parent::__construct($model);
    }

    public function getData(array $criteria = [], array $relations = [], array $orderBy = [], array $whereNotInCriteria = []): mixed
    {
        $query = $this->model->where($criteria)
            ->with($relations);

        // Handle ordering properly
        if (!empty($orderBy)) {
            foreach ($orderBy as $column => $direction) {
                $query->orderBy($column, $direction); // Separate column and direction
            }
        }

        // Handle whereNotIn criteria properly
        if (!empty($whereNotInCriteria) && count($whereNotInCriteria) === 2) {
            $query->whereNotIn($whereNotInCriteria[0], $whereNotInCriteria[1]);
        }

        return $query->get();
    }
}
