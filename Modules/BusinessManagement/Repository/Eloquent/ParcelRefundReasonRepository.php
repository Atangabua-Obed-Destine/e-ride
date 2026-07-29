<?php

namespace Modules\BusinessManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BusinessManagement\Entities\ParcelRefundReason;
use Modules\BusinessManagement\Repository\ParcelRefundReasonRepositoryInterface;

class ParcelRefundReasonRepository extends BaseRepository implements ParcelRefundReasonRepositoryInterface
{
    public function __construct(ParcelRefundReason $model)
    {
        parent::__construct($model);
    }
}
