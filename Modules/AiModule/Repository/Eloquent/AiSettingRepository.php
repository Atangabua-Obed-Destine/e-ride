<?php

namespace Modules\AiModule\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\AiModule\Entities\AiSetting;
use Modules\AiModule\Repository\AiSettingRepositoryInterface;

class AiSettingRepository extends BaseRepository implements AiSettingRepositoryInterface
{
    public function __construct(AiSetting $model)
    {
        parent::__construct($model);
    }
}
