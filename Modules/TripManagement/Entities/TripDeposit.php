<?php

namespace Modules\TripManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/**
 * The customer's booking deposit for one accepted ride or parcel. Every
 * change goes through TripDepositService.
 */
class TripDeposit extends Model
{
    use HasUuid;

    /** Waiting for the customer to pay; the driver does not set off yet. */
    public const AWAITING = 'awaiting';
    /** Paid; held by E-Ride until the trip ends. */
    public const PAID = 'paid';
    /** Counted toward the fare at payment (any surplus returned to the wallet). */
    public const APPLIED = 'applied';
    /** Kept after a late customer cancel: shared between the driver and E-Ride. */
    public const FORFEITED = 'forfeited';
    /** Returned to the customer's wallet. */
    public const REFUNDED = 'refunded';
    /** Not paid in time; the trip was cancelled, nothing moved. */
    public const EXPIRED = 'expired';

    protected $fillable = [
        'trip_request_id', 'customer_id', 'driver_id', 'zone_id', 'vehicle_category_id', 'amount', 'status',
        'payment_method', 'payment_request_id', 'due_at', 'paid_at', 'settled_at', 'applied_amount',
        'driver_share', 'admin_share', 'refunded_amount', 'note',
    ];

    protected $casts = [
        'amount' => 'float',
        'applied_amount' => 'float',
        'driver_share' => 'float',
        'admin_share' => 'float',
        'refunded_amount' => 'float',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id')->withTrashed();
    }

    public function isAwaiting(): bool
    {
        return $this->status === self::AWAITING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }
}
