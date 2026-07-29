<?php

namespace Modules\BusinessManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;

interface ReferralEarningServiceInterface extends BaseServiceInterface
{
    public function storeInfo(array $data);

}
