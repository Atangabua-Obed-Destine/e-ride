<?php

namespace Modules\BusManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** A seat an agent confirmed is pencilled on the departure's paper sheet. */
class BusPaperSeat extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['departure_id', 'seat', 'by_user_id'];
}
