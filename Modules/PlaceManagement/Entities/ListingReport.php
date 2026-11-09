<?php

namespace Modules\PlaceManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** A seeker's report on a listing, decided by admin: upheld (a strike), blocked (a scam) or dismissed. */
class ListingReport extends Model
{
    public const REASONS = ['taken', 'not_as_described', 'money_before_visit', 'fake', 'no_show', 'other'];

    public const OPEN = 'open';
    public const UPHELD = 'upheld';
    public const BLOCKED = 'blocked';
    public const DISMISSED = 'dismissed';

    protected $fillable = ['listing_id', 'reporter_id', 'visit_id', 'reason', 'note', 'status', 'handled_by', 'handled_at'];

    protected $casts = ['handled_at' => 'datetime'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
