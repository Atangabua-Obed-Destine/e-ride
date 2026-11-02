<?php

namespace Modules\StoreManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Repository\StoreItemRepositoryInterface;

class StoreItemRepository extends BaseRepository implements StoreItemRepositoryInterface
{
    public function __construct(StoreItem $model)
    {
        parent::__construct($model);
    }

    protected function searchQuery($query, array $searchCriteria = []): void
    {
        $fields = $searchCriteria['fields'] ?? ['name', 'description'];
        $value = $searchCriteria['value'];
        $query->where(function ($query) use ($fields, $value) {
            foreach ($fields as $field) {
                $query->orWhere($field, 'like', '%' . $value . '%');
            }
        });
    }
}
