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
        'user_id', 'name', 'slug', 'category', 'categories', 'about', 'area_label', 'pickup_lat', 'pickup_lng', 'pickup_address', 'logo',
        'status', 'closed_reason', 'closed_at', 'verification_requested_at', 'verified_at', 'verified_by',
        'visits', 'chats', 'new_users', 'followers_notified_at',
        'taking_orders', 'fulfilment', 'pay_methods', 'open_from', 'open_to', 'min_order', 'rating', 'rating_count', 'orders_completed',
        'reply_minutes', 'demoted_until',
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
        'categories' => 'array',
        'taking_orders' => 'boolean',
        'fulfilment' => 'array',
        'pay_methods' => 'array',
        'min_order' => 'float',
        'rating' => 'float',
        'rating_count' => 'integer',
        'orders_completed' => 'integer',
        'reply_minutes' => 'integer',
        'demoted_until' => 'datetime',
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

    /** What the shop sells (several kinds allowed); shops from before keep their single category. */
    public function categoryList(): array
    {
        $list = array_values(array_filter((array)($this->categories ?? []), 'is_string'));
        return $list ?: ($this->category ? [$this->category] : []);
    }

    /** How buyers can get items: pickup, E-Ride delivery, the seller's own delivery. */
    public function fulfilmentList(): array
    {
        $list = array_values(array_intersect(MarketOrder::FULFILMENT, (array)($this->fulfilment ?? ['pickup', 'eride'])));
        return $list ?: ['pickup'];
    }

    /** How the seller takes payment. "Before delivery" only for verified shops when the admin says so. */
    public function payMethodList(?bool $beforeVerifiedOnly = null): array
    {
        $list = array_values(array_intersect(MarketOrder::PAY_METHODS, (array)($this->pay_methods ?? ['cash_delivery', 'momo_delivery', 'pickup'])));
        $beforeVerifiedOnly ??= \Modules\MarketManagement\Lib\MarketSettings::all()['before_pay_verified_only'];
        if ($beforeVerifiedOnly && !$this->isVerified()) {
            $list = array_values(array_diff($list, ['before']));
        }
        return $list ?: ['cash_delivery'];
    }

    public function acceptsOrders(): bool
    {
        return $this->isLive() && $this->taking_orders;
    }

    /** Within opening hours now (no hours set: always). */
    public function isOpenAt(\Carbon\CarbonInterface $at): bool
    {
        if (!$this->open_from || !$this->open_to) {
            return true;
        }
        $now = $at->format('H:i');
        return $this->open_from <= $this->open_to
            ? $now >= $this->open_from && $now < $this->open_to
            : $now >= $this->open_from || $now < $this->open_to;
    }

    /** When the shop next opens after $at ($at itself when open). */
    public function nextOpening(\Carbon\CarbonInterface $at): \Carbon\CarbonInterface
    {
        if ($this->isOpenAt($at)) {
            return $at;
        }
        [$h, $m] = array_map('intval', explode(':', $this->open_from));
        $open = $at->copy()->setTime($h, $m);
        return $open->lte($at) ? $open->addDay() : $open;
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? rtrim(dynamicStorage('storage/app/public/' . self::LOGO_DIR), '/') . '/' . $this->logo : null;
    }
}
