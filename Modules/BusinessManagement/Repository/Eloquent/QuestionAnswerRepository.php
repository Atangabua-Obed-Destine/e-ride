<?php

namespace Modules\BusinessManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BusinessManagement\Entities\QuestionAnswer;
use Modules\BusinessManagement\Repository\QuestionAnswerRepositoryInterface;

class QuestionAnswerRepository extends BaseRepository implements QuestionAnswerRepositoryInterface
{
    public function __construct(QuestionAnswer $model)
    {
        parent::__construct($model);
    }
}
