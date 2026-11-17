<?php

namespace Modules\StoreManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One kitchen's switch for one dish: sold out today (back at midnight) or not
 * made at this branch. No row = available there.
 */
class StoreItemState extends Model
{
    protected $fillable = ['store_id', 'store_item_id', 'sold_out_until', 'not_here'];

    protected $casts = [
        'sold_out_until' => 'datetime',
        'not_here' => 'boolean',
    ];

    public function isSoldOut(?Carbon $at = null): bool
    {
        return $this->sold_out_until !== null && $this->sold_out_until->gt($at ?? now());
    }
}
