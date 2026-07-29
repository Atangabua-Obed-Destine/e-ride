<?php

namespace Modules\UserManagement\Repository;

use App\Repository\EloquentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface WalletBonusRepositoryInterface extends EloquentRepositoryInterface
{
    public function getList(array $data, int|string $limit = null, int|string $offset = null) : Collection|LengthAwarePaginator;
}
