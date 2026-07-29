<?php

namespace Modules\UserManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerServiceInterface extends BaseServiceInterface
{
    public function createExternalCustomer(array $data): ?Model;

    public function updateExternalCustomer(int|string $id, array $data = []): ?Model;

    public function show(int|string $id, array $data);

    public function loyalCustomerCount($loyalLevelId);

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection;

    public function changeLanguage(int|string $id, array $data = []): ?Model;

    public function walletTransfer($customer, array $data = []);

    public function createAfterOtpMatch(array $data): ?Model;
}
