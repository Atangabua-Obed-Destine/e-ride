<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** One person on a Market shop's team (MarketManagement\Lib\ShopTeam). */
class MarketShopStaff extends Model
{
    use HasUuid;

    protected $table = 'market_shop_staff';

    public const INVITED = 'invited';
    public const ACTIVE = 'active';

    protected $fillable = ['shop_id', 'user_id', 'role', 'permissions', 'status', 'on_duty', 'accepted_at', 'ended_at'];

    protected $casts = ['permissions' => 'array', 'on_duty' => 'boolean', 'accepted_at' => 'datetime', 'ended_at' => 'datetime'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(MarketShop::class, 'shop_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function can(string $permission): bool
    {
        return $this->isActive() && in_array($permission, (array)$this->permissions, true);
    }
}
