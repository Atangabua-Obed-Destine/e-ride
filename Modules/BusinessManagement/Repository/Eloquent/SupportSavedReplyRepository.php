<?php

namespace Modules\BusinessManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BusinessManagement\Entities\SupportSavedReply;
use Modules\BusinessManagement\Repository\SupportSavedReplyRepositoryInterface;

class SupportSavedReplyRepository extends BaseRepository implements SupportSavedReplyRepositoryInterface
{
    public function __construct(SupportSavedReply $model)
    {
        parent::__construct($model);
    }
}
