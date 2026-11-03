<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

/**
 * A customer's prepaid order from a partner store. Moves only through
 * StoreOrderService. Delivery is the parcel trip in trip_request_id.
 *
 * Status values are mirrored by the customer app's AppConstants.
 */
class StoreOrder extends Model
{
    use HasUuid;

    public const AWAITING_PAYMENT = 'awaiting_payment';
    public const PLACED = 'placed';
    public const ACCEPTED = 'accepted';
    public const RIDER_REQUESTED = 'rider_requested';
    public const PICKED_UP = 'picked_up';
    public const DELIVERED = 'delivered';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    public const DELIVERY_FAILED = 'delivery_failed';

    /** Partner still has to act or is cooking: shown on the portal board. */
    public const ACTIVE = [self::PLACED, self::ACCEPTED, self::RIDER_REQUESTED, self::PICKED_UP];
    public const FINAL = [self::DELIVERED, self::REJECTED, self::CANCELLED, self::EXPIRED, self::DELIVERY_FAILED];

    public const UNPAID = 'unpaid';
    public const PAID = 'paid';
    public const REFUNDED = 'refunded';

    protected $fillable = [
        'customer_id', 'store_id', 'zone_id', 'status', 'items_subtotal', 'delivery_fee', 'total',
        'payment_method', 'payment_status', 'payment_request_id', 'paid_at', 'refunded_amount', 'refunded_at',
        'drop_address', 'drop_latitude', 'drop_longitude', 'receiver_name', 'receiver_phone', 'note',
        'delivery_quote', 'quote_expires_at', 'prep_minutes', 'ready_at', 'trip_request_id', 'rider_attempts',
        'partner_earning', 'placed_at', 'accepted_at', 'rider_requested_at', 'picked_up_at', 'delivered_at',
        'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected $casts = [
        'ref_id' => 'integer',
        'items_subtotal' => 'float',
        'delivery_fee' => 'float',
        'total' => 'float',
        'refunded_amount' => 'float',
        'partner_earning' => 'float',
        'drop_latitude' => 'float',
        'drop_longitude' => 'float',
        'delivery_quote' => 'array',
        'prep_minutes' => 'integer',
        'rider_attempts' => 'integer',
        'quote_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'ready_at' => 'datetime',
        'placed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rider_requested_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function (StoreOrder $order) {
            $order->ref_id = (static::max('ref_id') ?? 1000) + 1;
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }

    public function review(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StoreReview::class);
    }

    /** Delivered, not rated yet, and recent enough to still ask. */
    public function canBeReviewed(): bool
    {
        return $this->status === self::DELIVERED
            && $this->delivered_at?->gt(now()->subDays(StoreReview::MAX_DAYS_TO_REVIEW))
            && !$this->review()->exists();
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    /** "2x Jollof rice, 1x Chapman" */
    public function itemsSummary(): string
    {
        return $this->items->map(fn(StoreOrderItem $i) => $i->quantity . 'x ' . $i->name)->implode(', ');
    }
}
