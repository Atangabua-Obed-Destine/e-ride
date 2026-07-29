<?php

namespace Modules\UserManagement\Service\Interfaces;
use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface WalletBonusServiceInterface extends BaseServiceInterface
{
    public function getListForAPI(array $data, int|string $limit = null, int|string $offset = null): Collection|LengthAwarePaginator;
}
