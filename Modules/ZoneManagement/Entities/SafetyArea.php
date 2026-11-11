<?php

namespace Modules\ZoneManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An area where E-Ride takes no trips for a while: a pause an admin set (a
 * zone or a circle, now or planned) or a no-go area (a circle, set by an admin
 * or automatically after driver reports, until an admin confirms or lifts it).
 */
class SafetyArea extends Model
{
    use HasUuid;

    public const PAUSE = 'pause';
    public const NO_GO = 'no_go';

    protected $fillable = [
        'kind', 'source', 'zone_id', 'latitude', 'longitude', 'radius_m', 'title', 'reason', 'starts_at', 'ends_at',
        'confirmed', 'lifted_at', 'created_by', 'pre_notified_at', 'notified_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_m' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'lifted_at' => 'datetime',
        'pre_notified_at' => 'datetime',
        'notified_at' => 'datetime',
        'confirmed' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(DangerReport::class, 'safety_area_id');
    }

    /** In force at $at: started, not ended, not lifted. */
    public function scopeActive(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();
        return $query->whereNull('lifted_at')->where('starts_at', '<=', $at)->where('ends_at', '>', $at);
    }

    public function isCircle(): bool
    {
        return $this->zone_id === null && $this->radius_m !== null;
    }

    public function isActive(?Carbon $at = null): bool
    {
        $at ??= now();
        return !$this->lifted_at && $this->starts_at->lte($at) && $this->ends_at->gt($at);
    }
}
