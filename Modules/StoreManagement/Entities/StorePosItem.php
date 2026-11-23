<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product variation POS Innova publishes for a linked kitchen, and the menu item it is linked to. */
class StorePosItem extends Model
{
    use HasUuid;

    protected $fillable = ['link_id', 'variation_id', 'product_id', 'sku', 'name', 'variation_name', 'description', 'category', 'sub_category',
        'image_sha1', 'unit_id', 'unit', 'price', 'track_stock', 'qty_available', 'low_stock_threshold', 'next_expiry', 'pos_updated_at',
        'store_item_id', 'snapshot_id', 'removed_at'];

    protected $casts = ['price' => 'integer', 'track_stock' => 'boolean', 'qty_available' => 'float', 'low_stock_threshold' => 'float',
        'next_expiry' => 'date', 'pos_updated_at' => 'datetime', 'removed_at' => 'datetime', 'variation_id' => 'integer', 'unit_id' => 'integer'];

    public function link(): BelongsTo
    {
        return $this->belongsTo(StorePosLink::class, 'link_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }

    /** Can be sold on E-Ride: not removed, and stock above the threshold (or no stock kept). */
    public function sellable(): bool
    {
        return $this->removed_at === null && (!$this->track_stock || (float)$this->qty_available > (float)$this->low_stock_threshold);
    }

    public function fullName(): string
    {
        return trim($this->name . ($this->variation_name ? ' · ' . $this->variation_name : ''));
    }
}
