<?php

namespace Modules\BusinessManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\BusinessManagement\Database\factories\ExternalConfigurationFactory;

class ExternalConfiguration extends Model
{
    use HasFactory,HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'key',
        'value'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'value' => 'array',
    ];

    protected static function newFactory(): ExternalConfigurationFactory
    {
        //return ExternalConfigurationFactory::new();
    }
}
