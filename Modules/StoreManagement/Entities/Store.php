<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\UserManagement\Entities\User;
use Modules\ZoneManagement\Entities\Zone;

/**
 * A partner restaurant or shop. Its owner logs into the /partner portal with
 * the linked user (user_type partner).
 */
class Store extends Model
{
    use HasUuid, SoftDeletes;

    public const TYPES = ['restaurant', 'shop'];
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    protected $fillable = [
        'user_id', 'name', 'slug', 'type', 'phone', 'whatsapp', 'address', 'latitude', 'longitude', 'zone_id',
        'logo', 'cover', 'description', 'opening_hours', 'is_open', 'default_prep_minutes', 'min_order_amount',
        'is_active', 'sort', 'payout_method', 'payout_number', 'payout_name',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'opening_hours' => 'array',
        'is_open' => 'boolean',
        'is_active' => 'boolean',
        'default_prep_minutes' => 'integer',
        'min_order_amount' => 'float',
        'sort' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(StoreMenuCategory::class)->orderBy('sort')->orderBy('name');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreItem::class)->orderBy('sort')->orderBy('name');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(StorePayout::class);
    }

    /** Ratings customers can see: hidden ones (abuse, mistakes) are left out. */
    public function reviews(): HasMany
    {
        return $this->hasMany(StoreReview::class)->where('is_hidden', false);
    }

    /** Average rating (one decimal) and count, from withAvg/withCount when loaded. */
    public function ratingSummary(): array
    {
        $count = (int)($this->reviews_count ?? $this->reviews()->count());
        $avg = $this->reviews_avg_rating ?? ($count ? $this->reviews()->avg('rating') : null);
        return ['rating' => $avg !== null ? round((float)$avg, 1) : null, 'rating_count' => $count];
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? dynamicStorage('storage/app/public/store/logo') . '/' . $this->logo : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover ? dynamicStorage('storage/app/public/store/cover') . '/' . $this->cover : null;
    }

    /** Today's hours as [open, close] ("08:00", "22:00"), or null when closed all day. */
    public function hoursFor(Carbon $at): ?array
    {
        $hours = $this->opening_hours ?? [];
        if (empty($hours)) {
            return ['00:00', '23:59'];
        }
        $day = $hours[self::DAYS[$at->dayOfWeekIso - 1]] ?? null;
        if (!$day || !empty($day['closed']) || empty($day['open']) || empty($day['close'])) {
            return null;
        }
        return [$day['open'], $day['close']];
    }

    /**
     * Taking orders right now: active, switched open by the partner, and
     * inside today's hours. Hours past midnight ("18:00" to "02:00") are
     * not supported: close is clamped to the end of the day.
     */
    public function isOpenNow(?Carbon $at = null): bool
    {
        if (!$this->is_active || !$this->is_open) {
            return false;
        }
        $at = $at ?? now();
        $hours = $this->hoursFor($at);
        if (!$hours) {
            return false;
        }
        $time = $at->format('H:i');
        return $time >= $hours[0] && $time <= $hours[1];
    }

    /** Partner's unpaid balance: delivered food earnings minus recorded payouts. */
    public function balance(): float
    {
        $earned = (float)$this->orders()->where('status', StoreOrder::DELIVERED)->sum('partner_earning');
        $paid = (float)$this->payouts()->sum('amount');
        return round($earned - $paid, 2);
    }
}
