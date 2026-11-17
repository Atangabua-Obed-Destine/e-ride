<?php

namespace Modules\AssistantManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** A question a customer asked, what answered it, and whether it helped. */
class AssistantQuestion extends Model
{
    use HasUuid;

    public const ANSWERED = 'answered';
    public const NO_ANSWER = 'no_answer';
    public const LIMITED = 'limited';
    public const BUSY = 'busy';

    protected $fillable = ['user_id', 'question', 'screen', 'language', 'status', 'provider', 'reply', 'actions', 'helped', 'ms'];

    protected $casts = ['actions' => 'array', 'helped' => 'boolean', 'ms' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
