<?php

namespace Modules\BlogManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\BlogManagement\Database\Factories\BlogSettingFactory;

class BlogSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'key_name', 'value', 'settings_type'
    ];

    protected $casts = [
        'value' => 'array'
    ];

}
