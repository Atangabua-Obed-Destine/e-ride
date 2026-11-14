<?php

namespace Modules\MarketManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** A buyer who blocked a shop: it no longer shows to them, and they cannot be messaged about it. */
class MarketBlock extends Model
{
    protected $table = 'market_blocks';

    protected $fillable = ['user_id', 'shop_id'];
}
