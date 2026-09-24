<?php

namespace Modules\UserManagement\Entities;

use App\Enums\DriverStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\UserManagement\Enums\PauseReasonEnum;

class DriverDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_online',
        'availability_status',
        'online',
        'offline',
        'online_time',
        'accepted',
        'completed',
        'start_driving',
        'on_driving_time',
        'idle_time',
        'ride_count',
        'parcel_count',
        'is_verified',
        'base_image',
        'verified_image',
        'is_paused',
        'pause_reason',
        'pause_duration',
        'paused_until',
        'trigger_verification_at',
        'service',
        'same_time_for_every_day',
        'is_offline_by_schedule',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'online_time' => 'double',
        'on_driving_time' => 'double',
        'idle_time' => 'double',
        'service' => 'array',
        'parcel_count' => 'integer',
        'ride_count' => 'integer',
        'trigger_verification_at' => 'datetime',
        'paused_until' => 'datetime',
        'is_offline_by_schedule' => 'boolean',
    ];

    public function isCurrentlyPaused(): bool
    {
        if (!$this->is_paused) {
            return false;
        }

        return is_null($this->paused_until) || now()->lt($this->paused_until);
    }

    public function isSystemPaused(): bool
    {
        return $this->isCurrentlyPaused() && PauseReasonEnum::isSystem($this->pause_reason);
    }

    public function systemPauseMessage(): ?string
    {
        if (!$this->isSystemPaused()) {
            return null;
        }

        return match ($this->pause_reason) {
            PauseReasonEnum::CASH_IN_HAND_LIMIT->value => translate('Driver is paused for exceeding the cash-in-hand limit. Collect the cash to resume.'),
            PauseReasonEnum::FACE_VERIFICATION->value => translate('Driver is paused for face verification failure. Verify the driver identity to resume.'),
            default => null,
        };
    }

    public function pauseRemainingLabel(): ?string
    {
        if (!$this->isCurrentlyPaused() || is_null($this->paused_until)) {
            return null;
        }

        $seconds = $this->paused_until->getTimestamp() - now()->getTimestamp();
        if ($seconds <= 0) {
            return null;
        }

        return formatDurationLabel((int)ceil($seconds / 60));
    }

    public function availabilitySchedules()
    {
        return $this->hasMany(DriverAvailabilitySchedule::class, 'user_id', 'user_id');
    }

    protected static function newFactory()
    {
        return \Modules\UserManagement\Database\factories\DriverDetailFactory::new();
    }
}
