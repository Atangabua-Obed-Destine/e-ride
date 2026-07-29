<?php

namespace Modules\BusinessManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gateways\Traits\HasUuid;

class SafetyAlertReason extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'reason',
        'reason_for_whom',
        'is_active',
    ];
}
