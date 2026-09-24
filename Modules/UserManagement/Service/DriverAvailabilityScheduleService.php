<?php

namespace Modules\UserManagement\Service;

use App\Service\BaseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\UserManagement\Repository\DriverAvailabilityScheduleRepositoryInterface;
use Modules\UserManagement\Repository\DriverDetailRepositoryInterface;
use Modules\UserManagement\Service\Interfaces\DriverAvailabilityScheduleServiceInterface;

class DriverAvailabilityScheduleService extends BaseService implements DriverAvailabilityScheduleServiceInterface
{
    protected $driverAvailabilityScheduleRepository;
    protected $driverDetailRepository;

    public function __construct(
        DriverAvailabilityScheduleRepositoryInterface $driverAvailabilityScheduleRepository,
        DriverDetailRepositoryInterface               $driverDetailRepository
    )
    {
        parent::__construct($driverAvailabilityScheduleRepository);
        $this->driverAvailabilityScheduleRepository = $driverAvailabilityScheduleRepository;
        $this->driverDetailRepository = $driverDetailRepository;
    }

    public function listGroupedByDay(string $userId)
    {
        return $this->driverAvailabilityScheduleRepository
            ->getBy(criteria: ['user_id' => $userId], orderBy: ['day_of_week' => 'asc', 'start_time' => 'asc'])
            ->groupBy('day_of_week');
    }

    public function getDisplaySchedule(string $userId): array
    {
        return [
            'sameTime' => (bool)$this->driverDetails($userId)?->same_time_for_every_day,
            'schedules' => $this->listGroupedByDay($userId),
        ];
    }

    public function createDefault(string $userId): void
    {
        $this->driverAvailabilityScheduleRepository->create([
            'user_id' => $userId,
            'day_of_week' => 0,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
        ]);

        $this->driverDetailRepository->updatedBy(
            criteria: ['user_id' => $userId],
            data: ['same_time_for_every_day' => true]
        );
    }

    public function isAvailableNow(string $userId): bool
    {
        return $this->isAvailableAt($userId, now());
    }

    public function syncAvailabilityWithSchedule(): array
    {
        $day = now()->dayOfWeek;
        $time = now()->format('H:i:s');

        return [
            'offlined' => $this->driverDetailRepository->setScheduleOfflineOutsideWindow($day, $time),
            'onlined' => $this->driverDetailRepository->setScheduleOnlineInsideWindow($day, $time),
        ];
    }

    public function canGoOnline(string $userId): bool
    {
        $hasSchedule = (bool)$this->driverAvailabilityScheduleRepository->findOneBy(criteria: ['user_id' => $userId]);

        return !$hasSchedule || $this->isAvailableNow($userId);
    }

    public function isAvailableAt(string $userId, \Carbon\CarbonInterface $moment): bool
    {
        $details = $this->driverDetails($userId);

        $day = $details?->same_time_for_every_day ? 0 : $moment->dayOfWeek;
        $time = $moment->format('H:i:s');

        return $this->driverAvailabilityScheduleRepository
            ->getBy(criteria: ['user_id' => $userId, 'day_of_week' => $day])
            ->contains(fn($slot) => $slot->start_time <= $time && $slot->end_time >= $time);
    }

    public function isDriverAvailableForTrip(string $userId, Model $trip): bool
    {
        $moment = ($trip->ride_request_type == SCHEDULED && $trip->scheduled_at)
            ? Carbon::parse($trip->scheduled_at)
            : now();

        return $this->isAvailableAt($userId, $moment);
    }

    public function storeSlot(string $userId, array $data): ?Model
    {
        $details = $this->assertDriver($userId);
        $day = $this->resolveDay((int)$data['day'], (bool)$details->same_time_for_every_day);
        [$start, $end] = $this->normalizeTimes($data['start_time'], $data['end_time']);
        $this->assertNoOverlap($userId, $day, $start, $end);
        return $this->driverAvailabilityScheduleRepository->create([
            'user_id' => $userId,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    public function deleteSlot(string $userId, string $scheduleId): void
    {
        $this->driverAvailabilityScheduleRepository->delete($this->findSlot($userId, $scheduleId)->id);
    }

    public function setSameTime(string $userId, bool $sameTime): void
    {
        $this->assertDriver($userId);
        DB::transaction(function () use ($userId, $sameTime) {
            if ($sameTime) {
                $this->driverAvailabilityScheduleRepository->deleteExceptDay($userId, 0);
            } else {
                $this->fanOutToWeek($userId);
            }

            $this->driverDetailRepository->updatedBy(
                criteria: ['user_id' => $userId],
                data: ['same_time_for_every_day' => $sameTime]
            );
        });
    }

    private function driverDetails(string $userId): ?Model
    {
        return $this->driverDetailRepository->findOneBy(criteria: ['user_id' => $userId]);
    }

    private function assertDriver(string $userId): Model
    {
        $details = $this->driverDetails($userId);

        if (!$details) {
            throw ValidationException::withMessages([
                'driver' => translate('Driver not found'),
            ]);
        }

        return $details;
    }

    private function findSlot(string $userId, string $scheduleId): Model
    {
        $slot = $this->driverAvailabilityScheduleRepository
            ->findOneBy(criteria: ['id' => $scheduleId, 'user_id' => $userId]);

        if (!$slot) {
            throw ValidationException::withMessages([
                'schedule' => translate('Schedule not found'),
            ]);
        }

        return $slot;
    }

    private function fanOutToWeek(string $userId): void
    {
        $sundaySlots = $this->driverAvailabilityScheduleRepository
            ->getBy(criteria: ['user_id' => $userId, 'day_of_week' => 0]);

        $this->driverAvailabilityScheduleRepository->deleteExceptDay($userId, 0);

        for ($day = 1; $day <= 6; $day++) {
            foreach ($sundaySlots as $slot) {
                $this->driverAvailabilityScheduleRepository->create([
                    'user_id' => $userId,
                    'day_of_week' => $day,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                ]);
            }
        }
    }

    private function resolveDay(int $day, bool $sameTime): int
    {
        if ($day < 0 || $day > 6) {
            throw ValidationException::withMessages([
                'day' => translate('Invalid day'),
            ]);
        }

        if ($sameTime && $day !== 0) {
            throw ValidationException::withMessages([
                'day' => translate('Turn off same time for every day to set a specific day'),
            ]);
        }

        return $day;
    }

    private function normalizeTimes(string $startTime, string $endTime): array
    {
        try {
            $start = Carbon::parse($startTime)->format('H:i');
            $end = Carbon::parse($endTime)->format('H:i');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'schedule' => translate('Invalid time format'),
            ]);
        }

        if ($start >= $end) {
            throw ValidationException::withMessages([
                'schedule' => translate('Start time must be before end time'),
            ]);
        }

        return [$start . ':00', $end . ':59'];
    }

    private function assertNoOverlap(string $userId, int $day, string $start, string $end): void
    {
        $overlaps = $this->driverAvailabilityScheduleRepository
            ->getBy(criteria: ['user_id' => $userId, 'day_of_week' => $day])
            ->contains(fn($slot) => $start <= $slot->end_time && $end >= $slot->start_time);

        if ($overlaps) {
            throw ValidationException::withMessages([
                'schedule' => translate('Time slots cannot overlap on the same day'),
            ]);
        }
    }
}
