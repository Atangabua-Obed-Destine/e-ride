<?php

namespace Modules\BusinessManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\BusinessManagement\Database\factories\ReferralEarningSettingFactory;

class ReferralEarningSetting extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'key_name',
        'value',
        'settings_type',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'value' => 'array',
    ];

    protected static function newFactory(): ReferralEarningSettingFactory
    {
        //return ReferralEarningSettingFactory::new();
    }
}
