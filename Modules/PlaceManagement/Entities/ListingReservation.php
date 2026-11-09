<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\UserManagement\Entities\User;

/**
 * Reservation money: E-Ride holds what the seeker paid until the move-in.
 * awaiting (paying) → held (owner has a deadline) → reserved (until move-in)
 * → released (owner paid through a payout); or lapsed (never paid), refunded,
 * or problem (frozen until an admin decides). See ListingReservationService.
 */
class ListingReservation extends Model
{
    use HasUuid;

    public const AWAITING = 'awaiting';
    public const HELD = 'held';
    public const RESERVED = 'reserved';
    public const PROBLEM = 'problem';
    public const RELEASED = 'released';
    public const REFUNDED = 'refunded';
    public const LAPSED = 'lapsed';

    /** E-Ride holds the money (or is about to). */
    public const ACTIVE = [self::AWAITING, self::HELD, self::RESERVED, self::PROBLEM];
    /** Money is with E-Ride right now. */
    public const HOLDING = [self::HELD, self::RESERVED, self::PROBLEM];

    /** What a seeker or an owner can report while the money is held. */
    public const SEEKER_PROBLEMS = ['cancel', 'given_away', 'not_as_described', 'other'];
    public const OWNER_PROBLEMS = ['no_show', 'other'];

    protected $fillable = [
        'listing_id', 'visit_id', 'seeker_id', 'owner_id', 'amount', 'fee', 'owner_amount', 'refunded_amount', 'status',
        'payment_method', 'payment_request_id', 'move_in_date', 'pay_due_at', 'paid_at', 'accept_due_at', 'accepted_at',
        'release_due_at', 'settled_at', 'move_in_pushed_at', 'move_in_reminded_at', 'problem_by', 'problem_reason',
        'problem_note', 'problem_from', 'decided_by', 'decision_note', 'closed_reason',
    ];

    protected $casts = [
        'amount' => 'float',
        'fee' => 'float',
        'owner_amount' => 'float',
        'refunded_amount' => 'float',
        'move_in_date' => 'date',
        'pay_due_at' => 'datetime',
        'paid_at' => 'datetime',
        'accept_due_at' => 'datetime',
        'accepted_at' => 'datetime',
        'release_due_at' => 'datetime',
        'settled_at' => 'datetime',
        'move_in_pushed_at' => 'datetime',
        'move_in_reminded_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ListingVisit::class);
    }

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seeker_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(ListingPayout::class, 'reservation_id');
    }

    public function isHolding(): bool
    {
        return in_array($this->status, self::HOLDING, true);
    }
}
