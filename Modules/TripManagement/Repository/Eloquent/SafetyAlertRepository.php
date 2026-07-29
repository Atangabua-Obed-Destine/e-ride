<?php

namespace Modules\TripManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\TripManagement\Entities\SafetyAlert;
use Modules\TripManagement\Repository\SafetyAlertRepositoryInterface;

class SafetyAlertRepository extends BaseRepository implements SafetyAlertRepositoryInterface
{
    public function __construct(SafetyAlert $model)
    {
        parent::__construct($model);
    }
}
