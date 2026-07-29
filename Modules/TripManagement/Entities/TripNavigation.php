<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Gateways\Traits\HasUuid;

// use Modules\TripManagement\Database\Factories\TripNavigationFactory;

class TripNavigation extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'trip_request_id',
        'encoded_polyline',
    ];

    public function trip()
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }
}
