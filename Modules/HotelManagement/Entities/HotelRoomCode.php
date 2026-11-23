<?php

namespace Modules\HotelManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/** A room's QR card for room service: a short token (never the plain room number) that opens the restaurant. */
class HotelRoomCode extends Model
{
    use HasUuid;

    protected $fillable = ['hotel_id', 'room_label', 'token', 'revoked_at'];

    protected $casts = ['revoked_at' => 'datetime'];

    public function url(): string
    {
        return url('r/' . $this->token);
    }
}
