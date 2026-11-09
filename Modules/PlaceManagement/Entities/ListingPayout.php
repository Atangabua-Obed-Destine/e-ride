<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/**
 * Money E-Ride owes a customer on mobile money: an owner's released
 * reservation, or a seeker's refund they asked for on mobile money. An admin
 * sends it and records the reference (until Fapshi payouts are enabled).
 */
class ListingPayout extends Model
{
    use HasUuid;

    public const OWNER = 'owner';
    public const REFUND = 'refund';

    public const PENDING = 'pending';
    public const PAID = 'paid';

    protected $fillable = ['user_id', 'reservation_id', 'kind', 'amount', 'phone', 'name', 'status', 'reference', 'paid_by', 'paid_at'];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ListingReservation::class, 'reservation_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
