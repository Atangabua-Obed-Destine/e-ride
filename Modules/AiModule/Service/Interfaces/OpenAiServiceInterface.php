<?php

namespace Modules\AiModule\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\ZoneManagement\Entities\Zone;

interface OpenAiServiceInterface extends AiServiceInterface
{

}
