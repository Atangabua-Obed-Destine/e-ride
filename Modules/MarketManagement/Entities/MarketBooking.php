<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/**
 * A service booked for a time, or a quote request. The provider answers (accept, another
 * time, decline, or a price), the client pays the provider after the job and gives a code.
 */
class MarketBooking extends Model
{
    use HasUuid;

    public const KIND_BOOKING = 'booking';
    public const KIND_QUOTE = 'quote';

    public const REQUESTED = 'requested';
    /** The provider proposed another time; the client chooses. */
    public const SUGGESTED = 'suggested';
    public const QUOTE_REQUESTED = 'quote_requested';
    /** The provider sent a price and a time; the client accepts or not. */
    public const QUOTED = 'quoted';
    public const CONFIRMED = 'confirmed';
    public const COMPLETED = 'completed';
    public const DECLINED = 'declined';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';
    public const NO_SHOW = 'no_show';

    public const OPEN = [self::REQUESTED, self::SUGGESTED, self::QUOTE_REQUESTED, self::QUOTED, self::CONFIRMED];
    /** Statuses that hold a time in the provider's day. */
    public const HOLDS_TIME = [self::REQUESTED, self::SUGGESTED, self::CONFIRMED];
    public const MAX_PHOTOS = 3;
    public const PHOTO_DIR = 'market/booking/';

    protected $fillable = [
        'code', 'kind', 'status', 'item_id', 'shop_id', 'client_id', 'provider_id', 'conversation_id', 'starts_at', 'ends_at',
        'suggested_starts_at', 'location', 'lat', 'lng', 'address', 'note', 'photos', 'when_text', 'extras', 'price_type', 'price',
        'quote_price', 'quote_note', 'quote_expires_at', 'final_price', 'receive_code', 'respond_by', 'decline_reason', 'cancelled_by',
        'cancel_reason', 'late_cancel', 'reminded_day', 'reminded_hour', 'confirmed_at', 'completed_at', 'closed_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'suggested_starts_at' => 'datetime',
        'quote_expires_at' => 'datetime',
        'respond_by' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'closed_at' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
        'photos' => 'array',
        'extras' => 'array',
        'price' => 'float',
        'quote_price' => 'float',
        'final_price' => 'float',
        'late_cancel' => 'boolean',
        'reminded_day' => 'boolean',
        'reminded_hour' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'item_id')->withTrashed();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(MarketShop::class, 'shop_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function review(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MarketReview::class, 'booking_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function roleOf(string $userId): ?string
    {
        return match ($userId) {
            $this->client_id => 'client',
            $this->provider_id => 'provider',
            default => null,
        };
    }

    /** The price the client sees now: the final one, the quote, or the estimate. */
    public function shownPrice(): ?float
    {
        return $this->final_price ?? $this->quote_price ?? $this->price;
    }

    /** @return string[] */
    public function photoUrls(): array
    {
        return array_map(fn($f) => rtrim(dynamicStorage('storage/app/public/' . self::PHOTO_DIR), '/') . '/' . $f, (array)($this->photos ?? []));
    }
}
