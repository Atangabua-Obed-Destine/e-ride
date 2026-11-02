<?php

namespace Modules\StoreManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Repository\StoreRepositoryInterface;

class StoreRepository extends BaseRepository implements StoreRepositoryInterface
{
    public function __construct(Store $model)
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
