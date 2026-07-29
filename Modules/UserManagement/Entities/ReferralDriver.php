<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\UserManagement\Database\factories\ReferralDriverFactory;

class ReferralDriver extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'driver_id',
        'ref_by',
        'ref_by_earning_amount',
        'driver_earning_amount',
        'is_used'
    ];

    protected static function newFactory(): ReferralDriverFactory
    {
        //return ReferralDriverFactory::new();
    }
}
