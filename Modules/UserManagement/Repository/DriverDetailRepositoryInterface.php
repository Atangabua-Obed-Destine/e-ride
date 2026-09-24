<?php

namespace Modules\UserManagement\Repository;

use App\Repository\EloquentRepositoryInterface;

interface DriverDetailRepositoryInterface extends EloquentRepositoryInterface
{
    public function pausePayload(?string $reason = null, ?int $minutes = null): array;

    public function resumePayload(): array;

    public function suspendOfflinePayload(): array;

    public function clearPausePayload(): array;

    public function scheduleOfflinePayload(): array;

    public function scheduleOnlinePayload(): array;

    public function resumeExpiredPauses(): int;

    public function setScheduleOfflineOutsideWindow(int $day, string $time): int;

    public function setScheduleOnlineInsideWindow(int $day, string $time): int;
}
