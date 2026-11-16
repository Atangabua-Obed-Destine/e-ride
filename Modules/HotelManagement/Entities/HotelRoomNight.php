<?php

namespace Modules\HotelManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** A room taken for one night. Unique per room and night, so a room can never be given twice. */
class HotelRoomNight extends Model
{
    public $timestamps = false;

    protected $fillable = ['stay_id', 'room_id', 'night'];

    protected $casts = ['night' => 'date'];
}
