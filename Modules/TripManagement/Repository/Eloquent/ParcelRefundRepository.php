<?php

namespace Modules\TripManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Modules\TripManagement\Entities\ParcelRefund;
use Modules\TripManagement\Repository\ParcelRefundRepositoryInterface;

class ParcelRefundRepository extends BaseRepository implements ParcelRefundRepositoryInterface
{
    public function __construct(ParcelRefund $model)
    {
        parent::__construct($model);
    }
}
