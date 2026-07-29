<?php

namespace Modules\BusinessManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BusinessManagement\Entities\ExternalConfiguration;
use Modules\BusinessManagement\Repository\ExternalConfigurationRepositoryInterface;

class ExternalConfigurationRepository extends BaseRepository implements ExternalConfigurationRepositoryInterface
{
    public function __construct(ExternalConfiguration $model)
    {
        parent::__construct($model);
    }
}
