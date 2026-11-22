<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** A staff login of a partner store (StoreManagement\Lib\StoreTeam). */
class StoreStaff extends Model
{
    use HasUuid;

    protected $table = 'store_staff';

    protected $fillable = ['store_id', 'user_id', 'role', 'permissions', 'status', 'ended_at'];

    protected $casts = ['permissions' => 'array', 'ended_at' => 'datetime'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function can(string $permission): bool
    {
        return $this->status === 'active' && in_array($permission, (array)$this->permissions, true);
    }
}
