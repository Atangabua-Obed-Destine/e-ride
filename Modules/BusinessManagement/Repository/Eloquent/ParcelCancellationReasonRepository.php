<?php

namespace Modules\BusinessManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BusinessManagement\Entities\ParcelCancellationReason;
use Modules\BusinessManagement\Repository\ParcelCancellationReasonRepositoryInterface;

class ParcelCancellationReasonRepository extends BaseRepository implements ParcelCancellationReasonRepositoryInterface
{
    public function __construct(ParcelCancellationReason $model)
    {
        parent::__construct($model);
    }
}
