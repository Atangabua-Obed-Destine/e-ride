<?php

namespace Modules\MarketManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** An auction winner who did not arrange payment. Enough recent strikes stop the person bidding. */
class MarketStrike extends Model
{
    protected $fillable = ['user_id', 'auction_id', 'reason'];
}
