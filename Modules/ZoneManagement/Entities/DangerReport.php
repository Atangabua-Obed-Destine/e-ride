<?php

namespace Modules\ZoneManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

/** Something dangerous a driver or a rider saw, at their position. Warns; two drivers can block the spot. */
class DangerReport extends Model
{
    use HasUuid;

    public const TYPES = ['gunshots', 'roadblock', 'protest', 'accident', 'flooding', 'other'];

    public const OPEN = 'open';
    public const CONFIRMED = 'confirmed';
    public const FALSE = 'false';
    public const EXPIRED = 'expired';

    protected $fillable = [
        'user_id', 'role', 'type', 'place', 'note', 'latitude', 'longitude', 'zone_id', 'trip_request_id', 'status',
        'safety_area_id', 'handled_by', 'handled_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'handled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(SafetyArea::class, 'safety_area_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** "Gunshots or fighting", for the admin; the apps have their own words. */
    public static function label(string $type): string
    {
        return match ($type) {
            'gunshots' => translate('Gunshots or fighting'),
            'roadblock' => translate('Roadblock or checkpoint'),
            'protest' => translate('Protest or crowd'),
            'accident' => translate('Accident'),
            'flooding' => translate('Road flooded or blocked'),
            default => translate('Something else'),
        };
    }
}
