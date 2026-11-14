<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\UserManagement\Entities\User;

/**
 * An order request from a buyer to one shop. The seller confirms each line or declines;
 * when something changed the buyer agrees; once confirmed the stock is held, the buyer
 * pays the seller directly (E-Ride never holds the money) and gives the receive code,
 * which completes the order.
 */
class MarketOrder extends Model
{
    use HasUuid;

    public const REQUESTED = 'requested';
    public const CHANGES = 'changes';
    public const CONFIRMED = 'confirmed';
    public const READY = 'ready';
    public const ON_THE_WAY = 'on_the_way';
    public const COMPLETED = 'completed';
    public const DECLINED = 'declined';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';

    /** Waiting on someone. */
    public const OPEN = [self::REQUESTED, self::CHANGES, self::CONFIRMED, self::READY, self::ON_THE_WAY];
    /** The seller has kept the stock. */
    public const HELD = [self::CONFIRMED, self::READY, self::ON_THE_WAY];
    public const FINAL = [self::COMPLETED, self::DECLINED, self::EXPIRED, self::CANCELLED];

    public const FULFILMENT = ['pickup', 'eride', 'seller'];
    public const PAY_METHODS = ['cash_delivery', 'momo_delivery', 'pickup', 'before'];
    public const DECLINE_REASONS = ['sold_out', 'no_delivery_area', 'price_changed', 'closed_today', 'other'];

    protected $fillable = [
        'code', 'shop_id', 'buyer_id', 'seller_id', 'conversation_id', 'auction_id', 'payment_due_at', 'payment_arranged_at', 'status', 'fulfilment', 'pay_method', 'drop_lat', 'drop_lng',
        'drop_address', 'note', 'requested_total', 'confirmed_total', 'receive_code', 'decline_reason', 'decline_note', 'cancel_reason',
        'cancelled_by', 'trip_id', 'respond_by', 'agree_by', 'answered_at', 'confirmed_at', 'ready_at', 'completed_at', 'closed_at', 'stock_held',
    ];

    protected $casts = [
        'drop_lat' => 'float',
        'drop_lng' => 'float',
        'requested_total' => 'float',
        'confirmed_total' => 'float',
        'respond_by' => 'datetime',
        'payment_due_at' => 'datetime',
        'payment_arranged_at' => 'datetime',
        'agree_by' => 'datetime',
        'answered_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'ready_at' => 'datetime',
        'completed_at' => 'datetime',
        'closed_at' => 'datetime',
        'stock_held' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(MarketShop::class, 'shop_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MarketOrderLine::class, 'order_id')->orderBy('id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(MarketReview::class, 'order_id');
    }

    public function roleOf(string $userId): ?string
    {
        return $userId === $this->buyer_id ? 'buyer' : ($userId === $this->seller_id ? 'seller' : null);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
