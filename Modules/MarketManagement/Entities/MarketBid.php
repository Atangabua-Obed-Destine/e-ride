<?php

namespace Modules\MarketManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** A bid on an auction. Other bidders see the bidder as initials only. */
class MarketBid extends Model
{
    protected $fillable = ['auction_id', 'user_id', 'amount'];

    protected $casts = ['amount' => 'float'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
