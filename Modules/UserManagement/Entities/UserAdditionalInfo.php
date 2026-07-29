<?php

namespace Modules\UserManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAdditionalInfo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'additional_data',
    ];

    protected $casts = [
        'additional_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
