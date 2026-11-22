<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/**
 * Someone who shares partner stores' links and earns a commission on the orders
 * they bring (StoreResellers). Paid by E-Ride by Mobile Money to momo_phone.
 */
class StoreReseller extends Model
{
    use HasUuid;

    public const ACTIVE = 'active';
    public const PAUSED = 'paused';

    protected $fillable = ['user_id', 'code', 'momo_phone', 'momo_name', 'status', 'pause_reason', 'taps'];

    protected $casts = ['taps' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
