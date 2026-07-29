<?php

namespace Modules\PromotionManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;

interface BannerSetupServiceInterface extends BaseServiceInterface
{
    public function list($data,$limit,$offset);
}
