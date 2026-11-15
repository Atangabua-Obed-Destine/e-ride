<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** Someone reports a job: it asks for money, it looks fake, the pay is not what was said... */
class JobReport extends Model
{
    use HasUuid;

    public const REASONS = ['fee', 'fake', 'pay', 'rude', 'other'];

    protected $fillable = ['job_id', 'reporter_id', 'reason', 'note', 'status'];

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPost::class, 'job_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
