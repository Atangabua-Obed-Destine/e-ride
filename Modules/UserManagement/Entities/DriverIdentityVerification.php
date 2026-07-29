<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\UserManagement\Database\Factories\DriverIdentityVerificationFactory;

class DriverIdentityVerification extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'driver_id', 'attempt_details', 'current_status'
    ];

    protected $casts = [
        'attempt_details' => 'array'
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id')->withTrashed();
    }
}
