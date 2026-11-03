<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** The customer's rating of a delivered order's food and store. One per order. */
class StoreReview extends Model
{
    use HasUuid;

    public const MAX_DAYS_TO_REVIEW = 14;

    protected $fillable = ['store_order_id', 'store_id', 'customer_id', 'rating', 'comment', 'is_hidden'];

    protected $casts = [
        'rating' => 'integer',
        'is_hidden' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }
}
