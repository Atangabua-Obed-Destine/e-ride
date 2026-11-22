<?php

namespace Modules\StoreManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What E-Ride sent a reseller by Mobile Money, for the delivered orders it covers. */
class StoreResellerPayout extends Model
{
    use HasUuid;

    protected $fillable = ['reseller_id', 'amount', 'orders', 'reference', 'paid_by', 'paid_at'];

    protected $casts = ['amount' => 'float', 'orders' => 'integer', 'paid_at' => 'datetime'];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(StoreReseller::class, 'reseller_id');
    }
}
