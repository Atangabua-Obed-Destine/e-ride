<?php

namespace Modules\BlogManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BlogManagement\Entities\Blog;
use Modules\BlogManagement\Repository\BlogRepositoryInterface;

class BlogRepository extends BaseRepository implements BlogRepositoryInterface
{
    public function __construct(Blog $model)
    {
        parent::__construct($model);
    }
}
