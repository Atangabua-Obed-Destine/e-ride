<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One Mobile Money attempt into a business's own Fapshi account (StorePaymentService). */
class StorePayment extends Model
{
    use HasUuid;

    public const CREATED = 'created';
    public const PENDING = 'pending';
    public const SUCCESSFUL = 'successful';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';
    public const MISMATCHED = 'mismatched';
    public const OPEN = [self::CREATED, self::PENDING];

    public const ORDER = 'order';
    public const DELIVERY_FEE = 'delivery_fee';

    protected $fillable = ['store_order_id', 'store_id', 'purpose', 'environment', 'external_id', 'trans_id', 'amount', 'status',
        'medium', 'payment_link', 'raw_response', 'failure_reason', 'initiated_at', 'last_checked_at', 'paid_at'];

    protected $casts = ['amount' => 'integer', 'raw_response' => 'array', 'initiated_at' => 'datetime',
        'last_checked_at' => 'datetime', 'paid_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
