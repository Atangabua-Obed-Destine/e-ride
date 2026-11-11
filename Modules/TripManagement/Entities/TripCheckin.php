<?php

namespace Modules\TripManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Are you OK?" asked to a rider during a ride, and what came of it. */
class TripCheckin extends Model
{
    use HasUuid;

    public const REASONS = ['stopped', 'off_route', 'too_long'];

    public const ASKED = 'asked';
    public const OK = 'ok';
    public const HELP = 'help';
    public const NO_ANSWER = 'no_answer';
    public const CLOSED = 'closed';

    protected $fillable = [
        'trip_request_id', 'customer_id', 'driver_id', 'reason', 'status', 'latitude', 'longitude', 'asked_at', 'answered_at',
        'escalated_at', 'safety_alert_id',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'asked_at' => 'datetime',
        'answered_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    /** Plain words for staff (admin list, safety alert). Fixed strings, so each is one language key. */
    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'stopped' => translate('the car stopped for a long time'),
            'off_route' => translate('the car left the planned route'),
            'too_long' => translate('the ride took far longer than expected'),
            default => $reason,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::ASKED => translate('waiting for the answer'),
            self::OK => translate('rider is OK'),
            self::HELP => translate('rider asked for help'),
            self::NO_ANSWER => translate('no answer'),
            self::CLOSED => translate('ride ended first'),
            default => $status,
        };
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }
}
