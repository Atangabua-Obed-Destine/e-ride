<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/**
 * A seeker's visit to a listing. The owner and seeker see each other's number
 * only once the visit is accepted: no contact before the owner agrees.
 */
class ListingVisit extends Model
{
    use HasUuid;

    public const WINDOWS = ['morning', 'afternoon', 'evening'];
    public const OUTCOMES = ['as_described', 'not_as_described', 'taken', 'no_show'];

    public const REQUESTED = 'requested';
    public const PROPOSED = 'proposed';
    public const ACCEPTED = 'accepted';
    public const DECLINED = 'declined';
    public const CANCELLED = 'cancelled';
    public const DONE = 'done';

    /** Still waiting for one side: counts as one open visit per seeker and listing. */
    public const OPEN = [self::REQUESTED, self::PROPOSED, self::ACCEPTED];

    protected $fillable = [
        'listing_id', 'seeker_id', 'owner_id', 'visit_date', 'time_window', 'status',
        'proposed_date', 'proposed_window', 'outcome', 'outcome_note', 'taking', 'reminded_at',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'proposed_date' => 'date',
        'taking' => 'boolean',
        'reminded_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seeker_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contactsShared(): bool
    {
        return in_array($this->status, [self::ACCEPTED, self::DONE], true);
    }
}
