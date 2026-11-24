<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\UserManagement\Entities\User;
/**
 * Seats asked for together. In the app: requested, the agency accepts (or declines), the passenger pays
 * within minutes. At the counter: paid at once. A cancelled paid booking may owe a refund, which the
 * agency sends from its own money (refund_status due, then sent).
 */
class BusBooking extends Model
{
    use HasUuid;

    public const REQUESTED = 'requested';
    public const ACCEPTED = 'accepted';
    public const PAID = 'paid';
    public const DECLINED = 'declined';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';
    /** Seats are held. */
    public const HOLDING = [self::REQUESTED, self::ACCEPTED];

    public const APP = 'app';
    public const COUNTER = 'counter';
    /** via: booked on the website (source stays app: an online booking at the app commission). */
    public const WEB = 'web';

    protected $fillable = [
        'code', 'agency_id', 'departure_id', 'customer_id', 'source', 'via', 'notify_channel', 'reminded_at', 'eve_reminded_at', 'staff_user_id', 'drop_point_id', 'status', 'respond_by', 'accepted_at',
        'pay_by', 'amount', 'commission_pct', 'commission', 'payment_method', 'paid_at', 'decline_reason', 'cancelled_at', 'cancelled_by',
        'refund_amount', 'extra_refund', 'refund_owed', 'refund_status', 'refund_due_by', 'refund_ref', 'refund_sent_at', 'phone',
        'booker_id_type', 'booker_id_number', 'booker_verification_id', 'booker_verified', 'id_checked_by', 'id_checked_at', 'collect_reminded_at',
        'closure_id', 'moved_from_departure_id', 'moved_at', 'move_kept_at', 'paper_sale', 'payer_only',
    ];

    protected $casts = [
        'amount' => 'float',
        'paper_sale' => 'boolean',
        'payer_only' => 'boolean',
        'commission_pct' => 'float',
        'commission' => 'float',
        'refund_amount' => 'float',
        'extra_refund' => 'float',
        'refund_owed' => 'float',
        'respond_by' => 'datetime',
        'accepted_at' => 'datetime',
        'pay_by' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refund_due_by' => 'datetime',
        'refund_sent_at' => 'datetime',
        'booker_verified' => 'boolean',
        'id_checked_at' => 'datetime',
        'collect_reminded_at' => 'datetime',
        'reminded_at' => 'datetime',
        'eve_reminded_at' => 'datetime',
        'moved_at' => 'datetime',
        'move_kept_at' => 'datetime',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(BusAgency::class, 'agency_id');
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(BusDeparture::class, 'departure_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function dropPoint(): BelongsTo
    {
        return $this->belongsTo(BusDropPoint::class, 'drop_point_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(BusTicket::class, 'booking_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BusPayment::class, 'booking_id');
    }
}
