<?php

namespace Modules\BusinessManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LandingPageSection extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'key_name',
        'value',
        'settings_type'
    ];

    protected $casts = [
        'value' => 'array',
    ];
}
