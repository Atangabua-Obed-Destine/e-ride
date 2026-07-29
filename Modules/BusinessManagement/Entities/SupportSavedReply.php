<?php

namespace Modules\BusinessManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\BusinessManagement\Database\factories\SupportSavedReplyFactory;
use Modules\Gateways\Traits\HasUuid;

class SupportSavedReply extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'topic',
        'answer',
        'is_active',
    ];

    protected static function newFactory(): SupportSavedReplyFactory
    {
        //return SupportSavedReplyFactory::new();
    }
}
