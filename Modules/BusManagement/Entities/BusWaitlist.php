<?php

namespace Modules\BusManagement\Entities;

use Illuminate\Database\Eloquent\Model;
/** Someone to tell when a seat frees up on a full departure. */
class BusWaitlist extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'bus_waitlist';

    protected $fillable = ['departure_id', 'user_id', 'notified_at'];

    protected $casts = ['notified_at' => 'datetime'];
}
