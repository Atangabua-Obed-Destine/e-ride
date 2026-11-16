<?php

namespace Modules\BusManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** A Mobile Money payment of a booking, through the agency's own Fapshi account. */
class BusPayment extends Model
{
    public const CREATED = 'created';
    public const PENDING = 'pending';
    public const SUCCESSFUL = 'successful';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';
    public const MISMATCHED = 'mismatched';
    public const OPEN = [self::CREATED, self::PENDING];

    protected $fillable = [
        'booking_id', 'agency_id', 'environment', 'external_id', 'trans_id', 'amount', 'status', 'medium', 'payment_link', 'failure_reason',
        'raw_response', 'initiated_at', 'paid_at', 'last_checked_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'raw_response' => 'array',
        'initiated_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(BusBooking::class, 'booking_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(BusAgency::class, 'agency_id');
    }
}
