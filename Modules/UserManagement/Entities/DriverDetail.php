<?php

namespace Modules\UserManagement\Entities;

use App\Enums\DriverStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'is_suspended',
        'suspend_reason',
        'trigger_verification_at',
        'service',
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
        'trigger_verification_at' => 'datetime'
    ];

    protected static function newFactory()
    {
        return \Modules\UserManagement\Database\factories\DriverDetailFactory::new();
    }
}
