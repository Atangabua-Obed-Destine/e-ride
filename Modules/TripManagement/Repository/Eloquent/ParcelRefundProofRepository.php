<?php

namespace Modules\TripManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Modules\TripManagement\Entities\ParcelRefundProof;
use Modules\TripManagement\Repository\ParcelRefundProofRepositoryInterface;

class ParcelRefundProofRepository extends BaseRepository implements ParcelRefundProofRepositoryInterface
{
    public function __construct(ParcelRefundProof $model)
    {
        parent::__construct($model);
    }
}
