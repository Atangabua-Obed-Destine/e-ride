<?php

namespace Modules\BusManagement\Entities;

use Illuminate\Database\Eloquent\Model;
/** A seat taken on a departure. The unique (departure, seat) key is what makes a double sale impossible. */
class BusSeatClaim extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['departure_id', 'seat', 'ticket_id'];
}
