<?php

namespace Modules\MarketManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One item of an order: what the buyer asked for, and what the seller confirmed. */
class MarketOrderLine extends Model
{
    protected $fillable = ['order_id', 'item_id', 'title', 'photo', 'colour', 'size', 'unit_price', 'wholesale', 'qty', 'qty_confirmed', 'seller_note'];

    protected $casts = [
        'unit_price' => 'float',
        'wholesale' => 'boolean',
        'qty' => 'integer',
        'qty_confirmed' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketItem::class, 'item_id')->withTrashed();
    }

    /** What counts: the confirmed quantity once the seller answered, the asked one before. */
    public function finalQty(): int
    {
        return $this->qty_confirmed ?? $this->qty;
    }

    public function stockKey(): string
    {
        return MarketItem::stockKey($this->colour, $this->size);
    }
}
