<?php

namespace Modules\HotelManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One room, by its number. Its own state (ready, to clean, out of order); who is in it comes from stays. */
class HotelRoom extends Model
{
    use HasUuid;

    public const READY = 'ready';
    public const TO_CLEAN = 'to_clean';
    public const OUT_OF_ORDER = 'out_of_order';
    public const STATES = [self::READY, self::TO_CLEAN, self::OUT_OF_ORDER];

    protected $fillable = ['hotel_id', 'room_type_id', 'number', 'floor', 'state', 'state_note', 'state_at', 'active'];

    protected $casts = ['state_at' => 'datetime', 'active' => 'boolean'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }
}
