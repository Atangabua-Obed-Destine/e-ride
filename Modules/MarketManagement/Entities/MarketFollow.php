<?php

namespace Modules\MarketManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** A rider following a shop: told (at most once a day) when it posts, unless they turned that off. */
class MarketFollow extends Model
{
    protected $table = 'market_follows';

    protected $fillable = ['user_id', 'shop_id', 'notify'];

    protected $casts = ['notify' => 'boolean'];
}
