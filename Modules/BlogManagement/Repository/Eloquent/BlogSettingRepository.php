<?php

namespace Modules\BlogManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BlogManagement\Entities\BlogSetting;
use Modules\BlogManagement\Repository\BlogSettingRepositoryInterface;

class BlogSettingRepository extends BaseRepository implements BlogSettingRepositoryInterface
{
    public function __construct(BlogSetting $model)
    {
        parent::__construct($model);
    }
}
