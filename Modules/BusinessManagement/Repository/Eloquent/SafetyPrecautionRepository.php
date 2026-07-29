<?php

namespace Modules\BusinessManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BusinessManagement\Entities\SafetyPrecaution;
use Modules\BusinessManagement\Repository\SafetyPrecautionRepositoryInterface;

class SafetyPrecautionRepository extends BaseRepository implements SafetyPrecautionRepositoryInterface
{
    public function __construct(SafetyPrecaution $model)
    {
        parent::__construct($model);
    }
}
