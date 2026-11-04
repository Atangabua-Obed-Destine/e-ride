<?php

namespace Modules\TripManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** A customer's report about the driver of one trip, for the admin to follow up. */
class TripReport extends Model
{
    use HasUuid;

    public const OPEN = 'open';
    public const REVIEWED = 'reviewed';
    public const RESOLVED = 'resolved';

    public const MAX_DAYS_TO_REPORT = 7;

    /** Reason keys; their labels are translated (customer app and admin). */
    public const REASONS = [
        'cancelled_for_no_reason' => 'Cancelled for no reason',
        'asked_me_to_cancel' => 'Asked me to cancel',
        'asked_for_more_money' => 'Asked for more money',
        'did_not_come' => 'Did not come to the pickup',
        'wrong_driver_or_vehicle' => 'Different driver or vehicle',
        'rude' => 'Rude or disrespectful',
        'unsafe_driving' => 'Unsafe driving',
        'other' => 'Something else',
    ];

    protected $fillable = [
        'trip_request_id', 'customer_id', 'driver_id', 'reason', 'comment', 'trip_status', 'deposit_status',
        'status', 'admin_note', 'handled_by', 'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
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

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function reasonLabel(): string
    {
        return translate(self::REASONS[$this->reason] ?? $this->reason);
    }
}
