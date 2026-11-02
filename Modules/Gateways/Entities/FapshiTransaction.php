<?php

namespace Modules\Gateways\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FapshiTransaction extends Model
{
    public const CREATED = 'created';
    public const PENDING = 'pending';
    public const SUCCESSFUL = 'successful';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';
    /** Fapshi says paid, but for another payment or for less than we charged. */
    public const MISMATCHED = 'mismatched';
    /** Paid, but the payment request was already settled (paid twice). */
    public const REFUNDABLE = 'refundable';

    public const OPEN = [self::CREATED, self::PENDING];

    protected $guarded = ['id'];

    protected $casts = [
        'base_amount' => 'float',
        'fee_amount' => 'integer',
        'charged_amount' => 'integer',
        'amount_received' => 'integer',
        'revenue' => 'integer',
        'raw_request' => 'array',
        'raw_response' => 'array',
        'initiated_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class, 'payment_request_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
