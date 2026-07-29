<?php

namespace Modules\BusinessManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\BusinessManagement\Database\factories\CancellationReasonFactory;


class CancellationReason extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'cancellation_type',
        'user_type',
        'is_active'
    ];

    protected $casts = [
      'is_active' => 'boolean'
    ];

    protected static function newFactory()
    {
        return \Modules\BusinessManagement\Database\factories\CancellationReasonFactory::new();
    }
}
