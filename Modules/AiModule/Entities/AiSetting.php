<?php

namespace Modules\AiModule\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class AiSetting extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'ai_name', 'api_key', 'organization_id', 'status', 'base_url'
    ];

}
