<?php

namespace Modules\HotelManagement\Entities;

use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A room type as POS Innova publishes it, with its free rooms per night, mapped to an E-Ride room type. */
class HotelPosType extends Model
{
    use HasUuid;

    protected $fillable = ['link_id', 'pos_type_id', 'name', 'capacity', 'eride_price', 'rooms_total', 'free_from', 'free', 'room_type_id', 'snapshot_id', 'removed_at'];

    protected $casts = [
        'pos_type_id' => 'integer',
        'capacity' => 'integer',
        'eride_price' => 'integer',
        'rooms_total' => 'integer',
        'free_from' => 'date',
        'free' => 'array',
        'removed_at' => 'datetime',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(HotelPosLink::class, 'link_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }

    /** Rooms free on every night from $from to the night before $to; null when POS has not said for one of them. */
    public function freeFor(Carbon $from, Carbon $to): ?int
    {
        if (!$this->free_from || $this->removed_at) {
            return null;
        }
        $min = null;
        for ($d = $from->copy()->startOfDay(); $d->lt($to); $d->addDay()) {
            $i = (int)$this->free_from->diffInDays($d, false);
            if ($i < 0 || !array_key_exists($i, $this->free ?? [])) {
                return null;
            }
            $min = $min === null ? (int)$this->free[$i] : min($min, (int)$this->free[$i]);
        }
        return max(0, (int)$min);
    }
}
