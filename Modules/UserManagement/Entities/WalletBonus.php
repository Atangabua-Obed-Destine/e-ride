<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletBonus extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'wallet_bonuses';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'description', 'bonus_amount', 'amount_type', 'min_add_amount', 'max_bonus_amount', 'start_date', 'end_date', 'user_type', 'is_active'];

    protected $casts = ['bonus_amount' => 'float', 'min_add_amount' => 'float', 'max_bonus_amount' => 'float', 'start_date' => 'date', 'end_date' => 'date', 'user_type' => 'array'];

}
