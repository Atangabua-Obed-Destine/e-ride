<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** An E-Ride staff visit to a listing: where the staff member stood, what was checked, the result. */
class ListingVerification extends Model
{
    use HasUuid;

    /** The checklist, in display order. Labels in the admin view and the app. */
    public const CHECKS = ['at_pin', 'owner_id', 'units', 'water', 'light', 'toilet_kitchen', 'price'];

    public const REQUESTED = 'requested';
    public const SCHEDULED = 'scheduled';
    public const PASSED = 'passed';
    public const FIXABLE = 'fixable';
    public const FAILED = 'failed';

    protected $fillable = [
        'listing_id', 'staff_id', 'scheduled_for', 'status', 'latitude', 'longitude', 'distance_m', 'checks', 'note', 'completed_at',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'completed_at' => 'datetime',
        'checks' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'distance_m' => 'integer',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::REQUESTED, self::SCHEDULED], true);
    }
}
