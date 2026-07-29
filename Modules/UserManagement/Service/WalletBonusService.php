<?php

namespace Modules\UserManagement\Service;

use App\Service\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\UserManagement\Repository\WalletBonusRepositoryInterface;
use Modules\UserManagement\Service\Interfaces\WalletBonusServiceInterface;

class WalletBonusService extends BaseService implements WalletBonusServiceInterface
{
    protected $walletBonusRepository;
    public function __construct(WalletBonusRepositoryInterface $walletBonusRepository)
    {
        parent::__construct($walletBonusRepository);
        $this->walletBonusRepository = $walletBonusRepository;
    }

    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $data = [];
        $searchData = [];
        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['name'];
            $searchData['value'] = $criteria['search'];
        }
        return $this->walletBonusRepository->getBy(criteria: $data, searchCriteria: $searchData, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, withCountQuery: $withCountQuery);
    }

    public function create(array $data): ?Model
    {
        return $this->walletBonusRepository->create(data: $data);
    }

    public function statusChange(string|int $id, array $data): ?Model
    {
        $data = [
            'is_active' => $data['status'] == 0 ? $data['status'] : 1
        ];

        return $this->walletBonusRepository->update(id: $id, data: $data);
    }

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {
        $data = $this->index(criteria: $criteria, relations: $relations, orderBy: $orderBy)->map(function ($item) {
            $bonusInfo = '';
            $bonusInfo .= 'Minimum Add Amount - ' .set_currency_symbol($item->min_add_amount);
            $bonusInfo .= $item->max_bonus_amount > 0 ? ', Maximum Bonus Amount - ' . set_currency_symbol($item->max_bonus_amount) : '';
            return [
                'Bonus Title' => $item['name'],
                'Bonus Info' => $bonusInfo,
                'Bonus Amount' => setSymbol($item->amount_type, $item->bonus_amount),
                'Started On' => $item->start_date->format('d F Y'),
                'Expires On' => $item->end_date->format('d F Y'),
                'Status' => $item['is_active'] ? 'Active' : 'Inactive',
            ];
        });
        $headerRow = $data->first() ? array_keys($data->first()) : [];
        $requestedParameters = [
            ['Filter' => translate('search'), 'Value' => $criteria['search']]
        ];

        return collect($requestedParameters)->concat([['Filter' => '', 'Value' => '']])->concat([array_combine($headerRow, $headerRow)])->concat($data);
    }

    public function update(int|string $id, array $data = []): ?Model
    {
        return $this->walletBonusRepository->update(id: $id, data: $data);
    }

    public function getListForAPI(array $data, int|string $limit = null, int|string $offset = null): Collection|LengthAwarePaginator
    {
        return $this->walletBonusRepository->getList(data: $data, limit: $limit, offset: $offset);
    }

    public function matchData($newData)
    {
        $list = $this->walletBonusRepository->getBy();
        $newData = array_map(function($v) {
            return is_numeric($v) ? (float)$v : $v;
        }, $newData);

        return $list->filter(function ($item) use($newData){
            $existingData = [$item->bonus_amount, $item->amount_type, $item->min_add_amount, $item->max_bonus_amount];
            return $existingData == $newData;
        });
    }
}
