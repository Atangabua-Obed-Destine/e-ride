<?php

namespace Modules\UserManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Facades\DB;
use Modules\UserManagement\Entities\DriverDetail;
use Modules\UserManagement\Repository\DriverDetailRepositoryInterface;

class DriverDetailRepository extends BaseRepository implements DriverDetailRepositoryInterface
{
    public function __construct(DriverDetail $model)
    {
        parent::__construct($model);
    }

    public function pausePayload(?string $reason = null, ?int $minutes = null): array
    {
        return [
            'is_paused' => 1,
            'pause_reason' => $reason,
            'pause_duration' => $minutes,
            'paused_until' => $minutes ? now()->addMinutes($minutes) : null,
            'is_online' => 0,
            'availability_status' => 'unavailable',
        ];
    }

    public function resumePayload(): array
    {
        return [
            'is_paused' => 0,
            'pause_reason' => null,
            'pause_duration' => null,
            'paused_until' => null,
            'is_online' => 1,
            'availability_status' => 'available',
        ];
    }

    public function suspendOfflinePayload(): array
    {
        return [
            'is_online' => 0,
            'availability_status' => 'unavailable',
            'is_offline_by_schedule' => 0,
        ];
    }

    public function clearPausePayload(): array
    {
        return [
            'is_paused' => 0,
            'pause_reason' => null,
            'pause_duration' => null,
            'paused_until' => null,
        ];
    }

    public function scheduleOfflinePayload(): array
    {
        return [
            'is_online' => 0,
            'availability_status' => 'unavailable',
            'is_offline_by_schedule' => 1,
        ];
    }

    public function scheduleOnlinePayload(): array
    {
        return [
            'is_online' => 1,
            'availability_status' => 'available',
            'is_offline_by_schedule' => 0,
        ];
    }

    public function resumeExpiredPauses(): int
    {
        return $this->model->newQuery()
            ->where('is_paused', 1)
            ->whereNotNull('paused_until')
            ->where('paused_until', '<=', now())
            ->whereExists($this->activeUserQuery())
            ->update($this->resumePayload());
    }

    public function setScheduleOfflineOutsideWindow(int $day, string $time): int
    {
        return $this->model->newQuery()
            ->where('is_online', 1)
            ->where('availability_status', 'available')
            ->where('is_paused', 0)
            ->whereExists($this->anyScheduleQuery())
            ->whereNotExists($this->scheduleWindowQuery($day, $time))
            ->update($this->scheduleOfflinePayload());
    }

    public function setScheduleOnlineInsideWindow(int $day, string $time): int
    {
        return $this->model->newQuery()
            ->where('is_offline_by_schedule', 1)
            ->where('is_paused', 0)
            ->where('availability_status', 'unavailable')
            ->whereExists($this->activeUserQuery())
            ->whereExists($this->scheduleWindowQuery($day, $time))
            ->update($this->scheduleOnlinePayload());
    }

    private function scheduleWindowQuery(int $day, string $time): \Closure
    {
        return function ($query) use ($day, $time) {
            $query->select(DB::raw(1))
                ->from('driver_availability_schedules as das')
                ->whereColumn('das.user_id', 'driver_details.user_id')
                ->where('das.start_time', '<=', $time)
                ->where('das.end_time', '>=', $time)
                ->where(function ($q) use ($day) {
                    $q->where(function ($same) {
                        $same->where('driver_details.same_time_for_every_day', 1)
                            ->where('das.day_of_week', 0);
                    })->orWhere(function ($perDay) use ($day) {
                        $perDay->where('driver_details.same_time_for_every_day', 0)
                            ->where('das.day_of_week', $day);
                    });
                });
        };
    }

    private function anyScheduleQuery(): \Closure
    {
        return function ($query) {
            $query->select(DB::raw(1))
                ->from('driver_availability_schedules as das_any')
                ->whereColumn('das_any.user_id', 'driver_details.user_id');
        };
    }

    private function activeUserQuery(): \Closure
    {
        return function ($query) {
            $query->select(DB::raw(1))
                ->from('users')
                ->whereColumn('users.id', 'driver_details.user_id')
                ->where('users.is_active', 1);
        };
    }
}
