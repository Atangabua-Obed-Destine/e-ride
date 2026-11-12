<?php

namespace Modules\TripManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/**
 * Something a rider left in the car, reported from the trip details.
 * reported -> found | not_found; found -> returned. A rider can cancel an open case
 * ("I found it"), and E-Ride can close any case. See LostItemService.
 */
class LostItem extends Model
{
    use HasUuid;

    public const REPORTED = 'reported';
    public const FOUND = 'found';
    public const NOT_FOUND = 'not_found';
    public const RETURNED = 'returned';
    public const CANCELLED = 'cancelled';
    public const CLOSED = 'closed';

    /** Still being worked on. */
    public const OPEN = [self::REPORTED, self::FOUND, self::NOT_FOUND];

    public const COLLECT = 'collect';
    public const DELIVER = 'deliver';

    public const PHOTO_FOLDER = 'lost-item/';

    protected $fillable = [
        'trip_request_id', 'customer_id', 'driver_id', 'description', 'photo', 'status', 'return_method', 'return_fee',
        'driver_note', 'admin_note', 'answered_at', 'reminded_at', 'driver_returned_at', 'returned_at', 'escalated_at',
        'closed_at', 'closed_by',
    ];

    protected $casts = [
        'return_fee' => 'float',
        'answered_at' => 'datetime',
        'reminded_at' => 'datetime',
        'driver_returned_at' => 'datetime',
        'returned_at' => 'datetime',
        'escalated_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id')->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id')->withTrashed();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** Open and handed to E-Ride support (no answer in time, not found, or stuck). */
    public function needsSupport(): bool
    {
        return $this->isOpen() && $this->escalated_at !== null;
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? dynamicStorage('storage/app/public/' . rtrim(self::PHOTO_FOLDER, '/')) . '/' . $this->photo : null;
    }
}
