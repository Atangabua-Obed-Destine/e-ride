<?php

namespace Modules\MarketManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** A new rider who installed E-Ride through a shop or item link: counted once, for the shop's stats. */
class MarketReferral extends Model
{
    protected $fillable = ['user_id', 'shop_id', 'item_id'];
}
