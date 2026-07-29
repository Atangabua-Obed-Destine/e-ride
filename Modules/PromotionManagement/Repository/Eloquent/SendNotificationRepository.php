<?php

namespace Modules\PromotionManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\PromotionManagement\Entities\SendNotification;
use Modules\PromotionManagement\Repository\SendNotificationRepositoryInterface;

class SendNotificationRepository extends BaseRepository implements SendNotificationRepositoryInterface
{
    public function __construct(SendNotification $model)
    {
        parent::__construct($model);
    }

}
