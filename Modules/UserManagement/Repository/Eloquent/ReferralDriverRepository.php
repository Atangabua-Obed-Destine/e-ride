<?php

namespace Modules\UserManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\UserManagement\Entities\ReferralDriver;
use Modules\UserManagement\Repository\ReferralDriverRepositoryInterface;

class ReferralDriverRepository extends BaseRepository implements ReferralDriverRepositoryInterface
{
    public function __construct(ReferralDriver $model)
    {
        parent::__construct($model);
    }
}
