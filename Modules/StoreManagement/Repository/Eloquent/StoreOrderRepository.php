<?php

namespace Modules\StoreManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Repository\StoreOrderRepositoryInterface;

class StoreOrderRepository extends BaseRepository implements StoreOrderRepositoryInterface
{
    public function __construct(StoreOrder $model)
    {
        parent::__construct($model);
    }

    protected function searchQuery($query, array $searchCriteria = []): void
    {
        $fields = $searchCriteria['fields'] ?? ['ref_id', 'receiver_name', 'receiver_phone'];
        $value = $searchCriteria['value'];
        $query->where(function ($query) use ($fields, $value) {
            foreach ($fields as $field) {
                $query->orWhere($field, 'like', '%' . $value . '%');
            }
        });
    }
}
