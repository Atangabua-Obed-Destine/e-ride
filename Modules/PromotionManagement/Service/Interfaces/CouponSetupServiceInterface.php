<?php

namespace Modules\PromotionManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;

interface CouponSetupServiceInterface extends BaseServiceInterface
{
    public function getUserCouponList(array $data, $limit = null, $offset = null);

    public function getAppliedCoupon($tripType, $vehicleCategoryId, array $data);

}
