<?php

namespace Modules\UserManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerAccountServiceInterface extends BaseServiceInterface
{
    public function export(Collection $data): Collection|LengthAwarePaginator|\Illuminate\Support\Collection;
    public function updateManyWithIncrement(array $ids, $column, $amount = 0);
}
