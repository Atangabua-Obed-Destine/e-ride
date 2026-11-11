<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** What the check-in watcher remembers about a ride between its one-minute rounds. */
class TripWatch extends Model
{
    protected $primaryKey = 'trip_request_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'trip_request_id', 'anchor_latitude', 'anchor_longitude', 'anchor_at', 'off_route_hits', 'quiet_until', 'too_long_asked',
        'night_shared_at',
    ];

    protected $casts = [
        'anchor_latitude' => 'float',
        'anchor_longitude' => 'float',
        'anchor_at' => 'datetime',
        'quiet_until' => 'datetime',
        'too_long_asked' => 'boolean',
        'night_shared_at' => 'datetime',
        'off_route_hits' => 'integer',
    ];
}
