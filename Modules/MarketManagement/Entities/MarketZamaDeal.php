<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/**
 * A zama deal: a reseller agreed a price with a buyer and asks the owner for the item.
 *
 * Two ways: "deliver" (the reseller collects it, pays the owner the owner's price and brings
 * it to the buyer) or "send_buyer" (the buyer goes to the shop with a code and pays the
 * agreed price there; the owner then owes the reseller the margin, by Mobile Money).
 * E-Ride holds no money: it records the deal so nobody forgets the price or who brought whom.
 */
class MarketZamaDeal extends Model
{
    use HasUuid;

    public const ASKED = 'asked';
    public const KEPT = 'kept';
    public const DONE = 'done';
    public const DECLINED = 'declined';
    public const RELEASED = 'released';
    public const CANCELLED = 'cancelled';
    public const OPEN = [self::ASKED, self::KEPT];

    public const DELIVER = 'deliver';
    public const SEND_BUYER = 'send_buyer';
    public const WAYS = [self::DELIVER, self::SEND_BUYER];

    public const DECLINE_REASONS = ['sold_out', 'price_changed', 'not_now', 'other'];

    protected $fillable = [
        'code', 'copy_id', 'source_id', 'owner_shop_id', 'owner_id', 'reseller_id', 'buyer_id', 'conversation_id', 'owner_conversation_id',
        'quote_message_id', 'quantity', 'colour', 'size', 'price', 'owner_price', 'margin', 'way', 'status', 'close_reason', 'stock_held',
        'kept_at', 'kept_until', 'done_at', 'buyer_bought_at', 'margin_status', 'margin_paid_at', 'margin_received_at', 'margin_reminded_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'owner_price' => 'float',
        'margin' => 'float',
        'stock_held' => 'boolean',
        'kept_at' => 'datetime',
        'kept_until' => 'datetime',
        'done_at' => 'datetime',
        'buyer_bought_at' => 'datetime',
        'margin_paid_at' => 'datetime',
        'margin_received_at' => 'datetime',
        'margin_reminded_at' => 'datetime',
    ];

    public function copy(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'copy_id')->withTrashed();
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'source_id')->withTrashed();
    }

    public function ownerShop(): BelongsTo
    {
        return $this->belongsTo(MarketShop::class, 'owner_shop_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function roleOf(string $userId): ?string
    {
        return match ($userId) {
            $this->owner_id => 'owner',
            $this->reseller_id => 'reseller',
            (string)$this->buyer_id => 'buyer',
            default => null,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function stockKey(): string
    {
        return MarketItem::stockKey($this->colour, $this->size);
    }
}
