<?php

namespace Modules\StoreManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\StoreManagement\Entities\StorePayout;
use Modules\StoreManagement\Repository\StorePayoutRepositoryInterface;

class StorePayoutRepository extends BaseRepository implements StorePayoutRepositoryInterface
{
    public function __construct(StorePayout $model)
    {
        parent::__construct($model);
    }

    protected function searchQuery($query, array $searchCriteria = []): void
    {
        $fields = $searchCriteria['fields'] ?? ['reference', 'note'];
        $value = $searchCriteria['value'];
        $query->where(function ($query) use ($fields, $value) {
            foreach ($fields as $field) {
                $query->orWhere($field, 'like', '%' . $value . '%');
            }
        });
    }
}
