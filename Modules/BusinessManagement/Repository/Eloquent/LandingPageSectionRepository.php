<?php

namespace Modules\BusinessManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BusinessManagement\Entities\LandingPageSection;
use Modules\BusinessManagement\Repository\LandingPageSectionRepositoryInterface;

class LandingPageSectionRepository extends BaseRepository implements LandingPageSectionRepositoryInterface
{
    public function __construct(LandingPageSection $model)
    {
        parent::__construct($model);
    }
}
