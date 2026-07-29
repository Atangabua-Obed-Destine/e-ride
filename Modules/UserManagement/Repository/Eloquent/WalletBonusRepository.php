<?php

namespace Modules\UserManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\UserManagement\Entities\WalletBonus;
use Modules\UserManagement\Repository\WalletBonusRepositoryInterface;

class WalletBonusRepository extends BaseRepository implements WalletBonusRepositoryInterface
{
    public function __construct(WalletBonus $model)
    {
        parent::__construct($model);
    }

    public function getList(array $data, int|string $limit = null, int|string $offset = null) : Collection|LengthAwarePaginator
    {
        $model = $this->model
            ->where('is_active', $data['is_active'])
            ->whereDate('end_date', '>=', $data['date'])
            ->whereDate('start_date', '<=', $data['date'])
            ->whereJsonContains('user_type', $data['user_type']);

        if ($limit) {
            return $model->paginate(perPage: $limit, page: $offset);
        }

        return $model->get();
    }
}
