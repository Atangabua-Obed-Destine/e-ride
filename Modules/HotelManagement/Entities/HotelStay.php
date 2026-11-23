<?php

namespace Modules\HotelManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A room taken from one night to another: kept for an E-Ride guest, booked, in the house, or blocked. */
class HotelStay extends Model
{
    use HasUuid;

    public const KEPT = 'kept';
    public const BOOKED = 'booked';
    public const IN_HOUSE = 'in_house';
    public const LEFT = 'left';
    public const RELEASED = 'released';
    public const NO_SHOW = 'no_show';
    /** Statuses that hold the room's nights. */
    public const HOLDING = [self::KEPT, self::BOOKED, self::IN_HOUSE];

    public const ERIDE = 'eride';
    public const BLOCKED = 'blocked';
    /** Where a booking the reception enters came from. */
    public const SOURCES = ['phone', 'whatsapp', 'walk_in', 'other', self::BLOCKED];

    protected $fillable = [
        'hotel_id', 'room_type_id', 'room_id', 'inquiry_id', 'source', 'status', 'check_in', 'check_out', 'guests', 'guest_name',
        'kept_until', 'arrived_at', 'left_at', 'ended_at', 'created_by',
        'pos_ref', 'pos_release_due_at', 'pos_release_reason', 'pos_release_seen_at', 'room_label',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'guests' => 'integer',
        'kept_until' => 'datetime',
        'arrived_at' => 'datetime',
        'left_at' => 'datetime',
        'ended_at' => 'datetime',
        'pos_release_due_at' => 'datetime',
        'pos_release_seen_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(HotelRoom::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(HotelInquiry::class);
    }

    public function nights(): HasMany
    {
        return $this->hasMany(HotelRoomNight::class, 'stay_id');
    }

    public function holds(): bool
    {
        return in_array($this->status, self::HOLDING, true);
    }
}
