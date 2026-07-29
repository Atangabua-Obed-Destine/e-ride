<?php

namespace Modules\PromotionManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gateways\Traits\HasUuid;
use Modules\PromotionManagement\Database\factories\SendNotificationFactory;

class SendNotification extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'description',
        'targeted_users',
        'image',
        'is_active',
    ];

    protected $casts = [
        'targeted_users' => 'array',
    ];
}
