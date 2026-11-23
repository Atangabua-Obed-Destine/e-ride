<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A week of a business that takes payments in its own Fapshi: what it owes E-Ride for the orders
 * delivered that week (E-Ride riders' delivery fees, resellers, any share). StoreStatementService.
 */
class StoreStatement extends Model
{
    use HasUuid;

    public const DUE = 'due';
    public const PAID = 'paid';

    protected $fillable = ['store_id', 'period_from', 'period_to', 'orders_count', 'delivery_fees', 'other', 'amount', 'due_at',
        'status', 'paid_at', 'reference', 'paid_by'];

    protected $casts = ['period_from' => 'date', 'period_to' => 'date', 'due_at' => 'datetime', 'paid_at' => 'datetime',
        'delivery_fees' => 'float', 'other' => 'float', 'amount' => 'float', 'orders_count' => 'integer'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(StoreOrder::class, 'statement_id');
    }

    public function isLate(): bool
    {
        return $this->status === self::DUE && $this->due_at->isPast();
    }
}
