<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\UserManagement\Entities\User;

/** A rider's shop on E-Ride Market. One per rider; a closed shop stays closed (its owner cannot open another). */
class MarketShop extends Model
{
    use HasUuid;

    public const LIVE = 'live';
    public const CLOSED = 'closed';
    public const LOGO_DIR = 'market/shop/';

    protected $fillable = [
        'user_id', 'name', 'slug', 'category', 'about', 'area_label', 'pickup_lat', 'pickup_lng', 'pickup_address', 'logo',
        'status', 'closed_reason', 'closed_at', 'verification_requested_at', 'verified_at', 'verified_by',
        'visits', 'chats', 'new_users', 'followers_notified_at',
    ];

    protected $casts = [
        'pickup_lat' => 'float',
        'pickup_lng' => 'float',
        'closed_at' => 'datetime',
        'verification_requested_at' => 'datetime',
        'verified_at' => 'datetime',
        'followers_notified_at' => 'datetime',
        'visits' => 'integer',
        'chats' => 'integer',
        'new_users' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketItem::class, 'shop_id');
    }

    public function follows(): HasMany
    {
        return $this->hasMany(MarketFollow::class, 'shop_id');
    }

    public function isLive(): bool
    {
        return $this->status === self::LIVE;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** New: under 30 days old. Digital products of new, unverified shops carry a stronger warning. */
    public function isNew(): bool
    {
        return $this->created_at && $this->created_at->gt(now()->subDays(30));
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? rtrim(dynamicStorage('storage/app/public/' . self::LOGO_DIR), '/') . '/' . $this->logo : null;
    }
}
