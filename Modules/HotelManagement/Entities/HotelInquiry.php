<?php

namespace Modules\HotelManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\UserManagement\Entities\User;

/** A guest's question about rooms for some dates, and the chat with the reception that follows. */
class HotelInquiry extends Model
{
    use HasUuid;

    public const OPEN = 'open';
    public const ANSWERED = 'answered';
    public const KEPT = 'kept';
    /** Booked firmly by a hotel on POS Innova (no "until"). */
    public const BOOKED = 'booked';
    public const ARRIVED = 'arrived';
    public const CLOSED = 'closed';
    /** Still going: the guest and the reception can write. */
    public const ACTIVE = [self::OPEN, self::ANSWERED, self::KEPT, self::BOOKED, self::ARRIVED];

    public const ARRIVALS = ['afternoon', 'evening', 'late'];

    protected $fillable = [
        'hotel_id', 'customer_id', 'room_type_id', 'check_in', 'check_out', 'guests', 'rooms', 'arrival', 'status', 'close_reason',
        'price', 'first_answer_at', 'last_message_at', 'unread_hotel', 'unread_guest',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'guests' => 'integer',
        'rooms' => 'integer',
        'price' => 'integer',
        'first_answer_at' => 'datetime',
        'last_message_at' => 'datetime',
        'unread_hotel' => 'integer',
        'unread_guest' => 'integer',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HotelMessage::class, 'inquiry_id')->orderBy('id');
    }

    public function stays(): HasMany
    {
        return $this->hasMany(HotelStay::class, 'inquiry_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function nights(): int
    {
        return max(1, (int)$this->check_in->diffInDays($this->check_out));
    }
}
