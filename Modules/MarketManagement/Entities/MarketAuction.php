<?php

namespace Modules\MarketManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One auction of an item. The highest bid at or above the (hidden) reserve wins when it
 * ends and the winner gets a normal, confirmed order: E-Ride never holds the money. A late
 * bid extends the end. After a no-show the seller can offer it to the next bidder.
 */
class MarketAuction extends Model
{
    public const LIVE = 'live';
    public const SOLD = 'sold';
    public const UNSOLD = 'unsold';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'item_id', 'seller_id', 'start_price', 'step', 'reserve_price', 'ends_at', 'status', 'current_bid', 'bid_count', 'leader_id',
        'winner_id', 'winner_bid', 'order_id', 'offered_to', 'offered_price', 'offer_expires_at', 'ending_notified', 'ended_at',
    ];

    protected $casts = [
        'start_price' => 'float',
        'step' => 'float',
        'reserve_price' => 'float',
        'current_bid' => 'float',
        'winner_bid' => 'float',
        'offered_price' => 'float',
        'bid_count' => 'integer',
        'ends_at' => 'datetime',
        'offer_expires_at' => 'datetime',
        'ended_at' => 'datetime',
        'ending_notified' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'item_id')->withTrashed();
    }

    public function bids(): HasMany
    {
        return $this->hasMany(MarketBid::class, 'auction_id')->orderByDesc('amount')->orderBy('id');
    }

    public function isLive(): bool
    {
        return $this->status === self::LIVE && $this->ends_at->isFuture();
    }

    /** The lowest bid accepted now. */
    public function minNext(): float
    {
        return $this->current_bid === null ? (float)$this->start_price : (float)$this->current_bid + (float)$this->step;
    }

    /** Null when there is no reserve. */
    public function reserveMet(): ?bool
    {
        if ($this->reserve_price === null) {
            return null;
        }
        return $this->current_bid !== null && $this->current_bid >= $this->reserve_price;
    }
}
