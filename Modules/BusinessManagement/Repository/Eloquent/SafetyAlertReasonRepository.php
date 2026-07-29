<?php

namespace Modules\BusinessManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BusinessManagement\Entities\SafetyAlertReason;
use Modules\BusinessManagement\Repository\SafetyAlertReasonRepositoryInterface;

class SafetyAlertReasonRepository extends BaseRepository implements SafetyAlertReasonRepositoryInterface
{
    public function __construct(SafetyAlertReason $model)
    {
        parent::__construct($model);
    }
}
