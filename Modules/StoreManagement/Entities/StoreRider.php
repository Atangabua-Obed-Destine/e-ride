<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/**
 * An E-Ride rider a store chose for its deliveries ("My riders"). The store
 * adds and removes them; the rider can leave. One row per store and rider,
 * kept when it ends so adding them again is a simple switch back.
 */
class StoreRider extends Model
{
    use HasUuid;

    public const ACTIVE = 'active';
    public const LEFT = 'left';
    public const REMOVED = 'removed';

    protected $fillable = ['store_id', 'driver_id', 'status', 'ended_at'];

    protected $casts = ['ended_at' => 'datetime'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
