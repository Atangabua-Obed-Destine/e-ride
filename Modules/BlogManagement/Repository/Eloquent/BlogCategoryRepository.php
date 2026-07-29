<?php

namespace Modules\BlogManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BlogManagement\Entities\BlogCategory;
use Modules\BlogManagement\Repository\BlogCategoryRepositoryInterface;

class BlogCategoryRepository extends BaseRepository implements BlogCategoryRepositoryInterface
{
    public function __construct(BlogCategory $model)
    {
        parent::__construct($model);
    }
}
