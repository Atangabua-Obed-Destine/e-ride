<?php

namespace Modules\TripManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\TripManagement\Entities\TripNavigation;
use Modules\TripManagement\Repository\TripNavigationRepositoryInterface;

class TripNavigationRepository extends BaseRepository implements TripNavigationRepositoryInterface
{
    public function __construct(TripNavigation $model)
    {
        parent::__construct($model);
    }

    public function getLockedTrip(array $data = []): mixed
    {
        return $this->model->where($data)->lockForUpdate()->first();
    }
}
