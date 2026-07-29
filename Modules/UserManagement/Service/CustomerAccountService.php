<?php

namespace Modules\UserManagement\Service;

use App\Service\BaseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\TransactionManagement\Repository\TransactionRepositoryInterface;
use Modules\UserManagement\Repository\UserAccountRepositoryInterface;

class CustomerAccountService extends BaseService implements Interfaces\CustomerAccountServiceInterface
{
    protected $userAccountRepository;
    protected $transactionRepository;

    public function __construct(UserAccountRepositoryInterface $userAccountRepository, TransactionRepositoryInterface $transactionRepository)
    {
        parent::__construct($userAccountRepository);
        $this->userAccountRepository = $userAccountRepository;
        $this->transactionRepository = $transactionRepository;
    }

    public function export(Collection $data): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {
        return $data->map(function ($item) {
            return [
                'Id' => $item['id'],
                'Transaction Id' => $item['id'],
                'Reference' => $item['reference'],
                'Transaction Date' => $item['created_at']->format('d-m-Y h:m:i A'),
                'Transaction To' => $item->user?->first_name . ' ' . $item->user?->last_name,
                'Debit' => getCurrencyFormat($item['debit']),
                'Credit' => getCurrencyFormat($item['credit']),
                'Balance' => getCurrencyFormat($item['balance']),
                'Added Bonus' => getCurrencyFormat($item['added_bonus'])
            ];
        });
    }

    public function updateManyWithIncrement(array $ids, $column, $amount = 0)
    {
        $this->userAccountRepository->updateManyWithIncrement($ids, $column, $amount);
    }

}
