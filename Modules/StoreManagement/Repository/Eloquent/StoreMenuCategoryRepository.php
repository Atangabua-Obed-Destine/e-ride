<?php

namespace Modules\StoreManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\StoreManagement\Entities\StoreMenuCategory;
use Modules\StoreManagement\Repository\StoreMenuCategoryRepositoryInterface;

class StoreMenuCategoryRepository extends BaseRepository implements StoreMenuCategoryRepositoryInterface
{
    public function __construct(StoreMenuCategory $model)
    {
        parent::__construct($model);
    }

    protected function searchQuery($query, array $searchCriteria = []): void
    {
        $fields = $searchCriteria['fields'] ?? ['name'];
        $value = $searchCriteria['value'];
        $query->where(function ($query) use ($fields, $value) {
            foreach ($fields as $field) {
                $query->orWhere($field, 'like', '%' . $value . '%');
            }
        });
    }
}
