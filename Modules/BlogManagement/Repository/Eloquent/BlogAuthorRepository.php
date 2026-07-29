<?php

namespace Modules\BlogManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BlogManagement\Entities\BlogAuthor;
use Modules\BlogManagement\Repository\BlogAuthorRepositoryInterface;

class BlogAuthorRepository extends BaseRepository implements BlogAuthorRepositoryInterface
{
    public function __construct(BlogAuthor $model)
    {
        parent::__construct($model);
    }
}
