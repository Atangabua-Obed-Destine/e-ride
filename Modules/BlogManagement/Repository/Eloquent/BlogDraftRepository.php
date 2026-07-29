<?php

namespace Modules\BlogManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BlogManagement\Entities\BlogDraft;
use Modules\BlogManagement\Repository\BlogDraftRepositoryInterface;
use Modules\BlogManagement\Repository\BlogRepositoryInterface;

class BlogDraftRepository extends BaseRepository implements BlogDraftRepositoryInterface
{
    public function __construct(BlogDraft $model)
    {
        parent::__construct($model);
    }
}
