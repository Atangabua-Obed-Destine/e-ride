<?php

namespace Modules\BusinessManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BusinessManagement\Entities\ReferralEarningSetting;
use Modules\BusinessManagement\Repository\ReferralEarningSettingRepositoryInterface;

class ReferralEarningSettingRepository extends BaseRepository implements ReferralEarningSettingRepositoryInterface
{
    public function __construct(ReferralEarningSetting $model)
    {
        parent::__construct($model);
    }
}
