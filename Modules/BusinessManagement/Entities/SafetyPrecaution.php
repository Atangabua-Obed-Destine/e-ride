<?php

namespace Modules\BusinessManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\BusinessManagement\Database\factories\SafetyPrecautionFactory;
use Modules\Gateways\Traits\HasUuid;

class SafetyPrecaution extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'for_whom',
        'title',
        'description',
        'is_active',
    ];

    protected $casts = [
        'for_whom' => 'array',
    ];

    protected static function newFactory(): SafetyPrecautionFactory
    {
        //return SafetyPrecautionFactory::new();
    }
}
