<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * A bus agency E-Ride verified. Every departure leaves from its one city (origin_city).
 * Passengers pay it through its own Fapshi account (fapshi, encrypted); E-Ride bills a % commission monthly.
 */
class BusAgency extends Model
{
    use HasUuid;

    public const CHECKING = 'checking';
    /** The portal is open; not bookable until Fapshi works and a departure is open. */
    public const SETUP = 'setup';
    public const LIVE = 'live';
    public const PAUSED = 'paused';

    protected $fillable = [
        'name', 'slug', 'google_place_id', 'origin_city', 'address', 'lat', 'lng', 'phone', 'logo', 'about', 'status', 'pause_reason',
        'pause_note', 'checks', 'licence_file', 'verified_by', 'verified_at', 'commission_app_pct', 'commission_counter_pct', 'fapshi',
        'fapshi_ok_at', 'webhook_code', 'rules', 'rating', 'rating_count', 'answer_seconds_avg',
        'online_paused_at', 'online_paused_by', 'online_pause_auto', 'unanswered_streak',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'checks' => 'array',
        'rules' => 'array',
        'fapshi' => 'encrypted:array',
        'commission_app_pct' => 'float',
        'commission_counter_pct' => 'float',
        'rating' => 'float',
        'verified_at' => 'datetime',
        'fapshi_ok_at' => 'datetime',
        'online_paused_at' => 'datetime',
        'online_pause_auto' => 'boolean',
        'unanswered_streak' => 'integer',
    ];

    protected $hidden = ['fapshi'];

    public function staff(): HasMany
    {
        return $this->hasMany(BusStaff::class, 'agency_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(BusVehicle::class, 'agency_id');
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(BusDestination::class, 'agency_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BusSchedule::class, 'agency_id');
    }

    public function departures(): HasMany
    {
        return $this->hasMany(BusDeparture::class, 'agency_id');
    }

    public function isBookable(): bool
    {
        return $this->status === self::LIVE && $this->fapshi_ok_at !== null;
    }

    /** The agency's Fapshi values for FapshiClient, or null when not set. */
    public function fapshiValues(): ?array
    {
        $f = $this->fapshi;
        return is_array($f) && filled($f['api_user'] ?? null) && filled($f['api_key'] ?? null) ? $f : null;
    }
}
