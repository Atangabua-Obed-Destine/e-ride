<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's ID card or passport with a selfie, sent once to E-Ride. Pending until checked (staff or automatic
 * face match), then verified until the document expires or the profile name changes; or refused with a reason.
 */
class IdVerification extends Model
{
    use HasUuid;

    public const PENDING = 'pending';
    public const VERIFIED = 'verified';
    public const REFUSED = 'refused';
    public const ENDED = 'ended';

    public const ID_CARD = 'id_card';
    public const PASSPORT = 'passport';
    public const TYPES = [self::ID_CARD, self::PASSPORT];

    protected $fillable = [
        'user_id', 'doc_type', 'doc_number', 'full_name', 'expires_on', 'front_path', 'back_path', 'selfie_path', 'status', 'method',
        'similarity', 'reviewed_by', 'reviewed_at', 'refuse_reason', 'end_reason', 'ended_at',
    ];

    protected $casts = [
        'expires_on' => 'date',
        'similarity' => 'float',
        'reviewed_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected $hidden = ['front_path', 'back_path', 'selfie_path'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isVerified(): bool
    {
        return $this->status === self::VERIFIED;
    }

    /** "CNI 1234****89": enough to recognise it, not to copy it. */
    public function maskedNumber(): string
    {
        $n = $this->doc_number;
        return strlen($n) <= 4 ? $n : substr($n, 0, 2) . str_repeat('•', max(0, strlen($n) - 4)) . substr($n, -2);
    }
}
