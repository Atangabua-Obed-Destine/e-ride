<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/**
 * One person applying to one job, with their Work Card and answers. The employer sees it,
 * may shortlist it, and sends the next step (an interview time and place, or instructions).
 * E-Ride stops there: no interviews in the app, no hire tracking.
 */
class JobApplication extends Model
{
    use HasUuid;

    public const SENT = 'sent';
    public const SEEN = 'seen';
    public const SHORTLISTED = 'shortlisted';
    public const NEXT_STEP = 'next_step';
    public const NOT_SELECTED = 'not_selected';
    public const WITHDRAWN = 'withdrawn';
    /** The job closed, or nobody answered in time: a kind note, nobody waits forever. */
    public const CLOSED = 'closed';

    /** Still waiting for the employer. */
    public const WAITING = [self::SENT, self::SEEN, self::SHORTLISTED];
    public const ACTIVE = [self::SENT, self::SEEN, self::SHORTLISTED, self::NEXT_STEP];

    protected $fillable = [
        'job_id', 'user_id', 'answers', 'message', 'share_phone', 'status', 'employer_note', 'next_step', 'next_step_at', 'next_step_place',
        'next_step_sent_at', 'next_step_read_at', 'seen_at', 'shortlisted_at', 'decided_at', 'closed_reason',
    ];

    protected $casts = [
        'answers' => 'array',
        'share_phone' => 'boolean',
        'next_step_at' => 'datetime',
        'next_step_sent_at' => 'datetime',
        'next_step_read_at' => 'datetime',
        'seen_at' => 'datetime',
        'shortlisted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPost::class, 'job_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** How many of the job's questions were answered "yes". */
    public function yesCount(): int
    {
        return count(array_filter((array)($this->answers ?? []), fn($a) => $a === true || $a === 1 || $a === '1' || $a === 'yes'));
    }
}
