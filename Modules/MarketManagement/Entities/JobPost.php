<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A job on E-Ride Jobs. Checked by E-Ride before it goes live; the pay is always shown.
 * Closes by itself after a number of days, or when the employer closes it.
 */
class JobPost extends Model
{
    use HasUuid;

    public const CATEGORIES = ['sales', 'shop_restaurant', 'cleaning', 'driving', 'office', 'teaching', 'health', 'building', 'security',
        'beauty', 'tech', 'other'];
    public const TYPES = ['full_time', 'part_time', 'day_job', 'internship'];
    public const PERIODS = ['month', 'week', 'day', 'hour', 'job'];
    public const MAX_QUESTIONS = 3;
    public const MAX_SKILLS = 8;

    public const REVIEW = 'review';
    public const LIVE = 'live';
    /** E-Ride asked for a change (the reason is in review_note); the employer fixes it and sends again. */
    public const SENT_BACK = 'sent_back';
    public const REJECTED = 'rejected';
    public const CLOSED = 'closed';
    /** Taken down after reports, until an admin decides. */
    public const HIDDEN = 'hidden';

    protected $fillable = [
        'code', 'employer_id', 'category', 'type', 'title', 'description', 'requirements', 'skills', 'pay_min', 'pay_max', 'pay_period',
        'schedule', 'places', 'area_label', 'lat', 'lng', 'address', 'questions', 'status', 'review_note', 'flags', 'reviewed_by',
        'submitted_at', 'reviewed_at', 'live_at', 'closes_at', 'closed_at', 'closed_reason', 'applications_count', 'views', 'alerted',
        'report_count', 'required_links', 'hired_count',
    ];

    protected $casts = [
        'skills' => 'array',
        'questions' => 'array',
        'flags' => 'array',
        'required_links' => 'array',
        'hired_count' => 'integer',
        'pay_min' => 'float',
        'pay_max' => 'float',
        'lat' => 'float',
        'lng' => 'float',
        'places' => 'integer',
        'applications_count' => 'integer',
        'views' => 'integer',
        'alerted' => 'integer',
        'report_count' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'live_at' => 'datetime',
        'closes_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function employer(): BelongsTo
    {
        return $this->belongsTo(JobEmployer::class, 'employer_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'job_id');
    }

    public function isLive(): bool
    {
        return $this->status === self::LIVE && (!$this->closes_at || $this->closes_at->isFuture());
    }

    /** Can be seen by anyone with the link (live, or closed: "this job is closed"). */
    public function isPublic(): bool
    {
        return in_array($this->status, [self::LIVE, self::CLOSED], true);
    }

    /** @return string[] link types an applicant must have on their Work Card */
    public function requiredLinks(): array
    {
        return array_values(array_intersect(JobWorkCard::LINK_TYPES, (array)($this->required_links ?? [])));
    }

    /** @return string[] */
    public function questionList(): array
    {
        return array_values(array_filter(array_map(fn($q) => trim((string)$q), (array)($this->questions ?? [])), fn($q) => $q !== ''));
    }
}
