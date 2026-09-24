<?php

namespace Modules\UserManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Model;

interface DriverDetailServiceInterface extends BaseServiceInterface
{
    public function updateAvailability(array $data = []);

    public function resumeExpiredPauses(): int;

    public function suspendOffline(int|string $userId): void;

    public function pauseIfCashInHandLimitExceeded(Model $trip): void;

    public function resumeIfCashInHandLimitCleared(Model $driver): void;
}
