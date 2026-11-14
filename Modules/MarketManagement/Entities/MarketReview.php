<?php

namespace Modules\MarketManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** A buyer's rating of a shop, only from a completed order (one per order). */
class MarketReview extends Model
{
    public const TAGS = ['as_described', 'fast_reply', 'good_price', 'well_packed', 'problem'];

    protected $fillable = ['order_id', 'shop_id', 'buyer_id', 'stars', 'tags', 'comment', 'hidden'];

    protected $casts = [
        'stars' => 'integer',
        'tags' => 'array',
        'hidden' => 'boolean',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
