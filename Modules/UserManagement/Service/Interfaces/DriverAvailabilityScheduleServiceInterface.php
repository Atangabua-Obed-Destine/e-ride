<?php

namespace Modules\UserManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Model;

interface DriverAvailabilityScheduleServiceInterface extends BaseServiceInterface
{
    public function isDriverAvailableForTrip(string $userId, Model $trip): bool;

    public function listGroupedByDay(string $userId);

    public function getDisplaySchedule(string $userId): array;

    public function createDefault(string $userId): void;

    public function isAvailableNow(string $userId): bool;

    public function canGoOnline(string $userId): bool;

    public function syncAvailabilityWithSchedule(): array;

    public function isAvailableAt(string $userId, \Carbon\CarbonInterface $moment): bool;

    public function storeSlot(string $userId, array $data): ?Model;

    public function deleteSlot(string $userId, string $scheduleId): void;

    public function setSameTime(string $userId, bool $sameTime): void;
}
