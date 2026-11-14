<?php

namespace Modules\MarketManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** One visitor (hashed IP on the web, user id in the app) seeing an item or a shop on a day: counted once. */
class MarketVisit extends Model
{
    public $timestamps = false;

    protected $fillable = ['day', 'subject', 'subject_id', 'visitor'];
}
