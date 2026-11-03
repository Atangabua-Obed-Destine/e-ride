<?php

namespace Modules\PlaceManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\PlaceManagement\Entities\Place;
use Modules\PlaceManagement\Repository\PlaceRepositoryInterface;

class PlaceRepository extends BaseRepository implements PlaceRepositoryInterface
{
    public function __construct(Place $model)
    {
        parent::__construct($model);
    }

    protected function searchQuery($query, array $searchCriteria = []): void
    {
        $fields = $searchCriteria['fields'] ?? ['name', 'phone', 'address'];
        $value = $searchCriteria['value'];
        $query->where(function ($query) use ($fields, $value) {
            foreach ($fields as $field) {
                $query->orWhere($field, 'like', '%' . $value . '%');
            }
        });
    }
}
