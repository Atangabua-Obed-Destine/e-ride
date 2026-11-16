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

    protected $fillable = [
        'code', 'agency_id', 'departure_id', 'customer_id', 'source', 'staff_user_id', 'drop_point_id', 'status', 'respond_by', 'accepted_at',
        'pay_by', 'amount', 'commission_pct', 'commission', 'payment_method', 'paid_at', 'decline_reason', 'cancelled_at', 'cancelled_by',
        'refund_amount', 'refund_status', 'refund_due_by', 'refund_ref', 'refund_sent_at', 'phone',
    ];

    protected $casts = [
        'amount' => 'float',
        'commission_pct' => 'float',
        'commission' => 'float',
        'refund_amount' => 'float',
        'respond_by' => 'datetime',
        'accepted_at' => 'datetime',
        'pay_by' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refund_due_by' => 'datetime',
        'refund_sent_at' => 'datetime',
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
