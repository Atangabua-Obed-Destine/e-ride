<?php

namespace Modules\UserManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface DriverServiceInterface extends BaseServiceInterface
{
    public function show(int|string $id, array $data);

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection;

    public function getStatisticsData(array $data);

    public function getStatusCounts(): array;

    public function getDriverWithoutVehicle(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator;

    public function changeLanguage(int|string $id, array $data = []): ?Model;

    public function getChattingDriverList(array $data): Collection;

    public function changeSuspensionStatus(?Model $driver, string $action): void;

    public function hasUnsettledTrip(int|string $driverId): bool;

    public function pauseStatus(?Model $driver, array $data): void;

    public function resumeStatus(?Model $driver): void;

    public function createAfterOtpMatch(array $data): ?Model;
}
