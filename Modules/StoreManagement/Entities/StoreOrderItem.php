<?php

namespace Modules\StoreManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line of an order, snapshotted: later menu edits never change it. */
class StoreOrderItem extends Model
{
    protected $fillable = ['store_order_id', 'store_item_id', 'name', 'unit_price', 'quantity', 'line_total'];

    protected $casts = [
        'unit_price' => 'float',
        'quantity' => 'integer',
        'line_total' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }
}
