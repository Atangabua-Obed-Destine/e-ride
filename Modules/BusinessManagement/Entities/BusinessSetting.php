<?php

namespace Modules\BusinessManagement\Entities;

use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Mail;
use Modules\UserManagement\Emails\NotifyUser;

class BusinessSetting extends Model
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

    protected static function newFactory()
    {
        return \Modules\BusinessManagement\Database\factories\BusinessSettingFactory::new();
    }

    public function scopeSettingsType($query, $type)
    {
        $query->where('settings_type', $type);
    }

    protected static function boot()
    {
        parent::boot();

    }

}
