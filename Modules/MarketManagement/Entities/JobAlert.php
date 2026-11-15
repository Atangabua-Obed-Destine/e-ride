<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** "Tell me when a job fits": kinds of work and job types. Empty means any. A few pushes a day at most. */
class JobAlert extends Model
{
    use HasUuid;

    protected $fillable = ['user_id', 'categories', 'types', 'enabled', 'sent_on', 'sent_count'];

    protected $casts = [
        'categories' => 'array',
        'types' => 'array',
        'enabled' => 'boolean',
        'sent_on' => 'date',
        'sent_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(JobPost $job): bool
    {
        $cats = (array)($this->categories ?? []);
        $types = (array)($this->types ?? []);
        return ($cats === [] || in_array($job->category, $cats, true)) && ($types === [] || in_array($job->type, $types, true));
    }
}
