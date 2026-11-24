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
        'company_id', 'branch_label', 'region', 'name', 'slug', 'google_place_id', 'origin_city', 'address', 'lat', 'lng', 'phone', 'logo', 'about', 'status', 'pause_reason',
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

    /** The head office this branch belongs to, if any. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(BusCompany::class, 'company_id');
    }

    public function isBookable(): bool
    {
        return $this->status === self::LIVE && $this->paymentsReady();
    }

    /** Passengers can pay: the branch's own Fapshi works, or its company's when the company collects the money. */
    public function paymentsReady(): bool
    {
        $company = $this->company_id ? $this->company : null;
        return $company && $company->collectsMoney() ? $company->fapshi_ok_at !== null : $this->fapshi_ok_at !== null;
    }

    /** Agencies passengers can book: live, and able to take payment (their own account or their company's). */
    public function scopeBookable($q)
    {
        return $q->where('bus_agencies.status', self::LIVE)->where(function ($w) {
            $w->where(function ($own) {
                $own->whereNotNull('bus_agencies.fapshi_ok_at')->where(function ($c) {
                    $c->whereNull('bus_agencies.company_id')->orWhereIn('bus_agencies.company_id', BusCompany::where('money_mode', BusCompany::PER_BRANCH)->select('id'));
                });
            })->orWhereIn('bus_agencies.company_id', BusCompany::where('money_mode', BusCompany::COMPANY)->whereNotNull('fapshi_ok_at')->select('id'));
        });
    }

    /** "Vatican Express · Bamenda" for a branch of a company, else the agency's own name. */
    public function displayName(): string
    {
        $company = $this->company_id ? $this->company : null;
        return $company ? $company->name . ' · ' . $this->branchLabel() : $this->name;
    }

    public function branchLabel(): string
    {
        return $this->branch_label ?: $this->origin_city;
    }

    /** The agency's Fapshi values for FapshiClient, or null when not set. */
    public function fapshiValues(): ?array
    {
        $f = $this->fapshi;
        return is_array($f) && filled($f['api_user'] ?? null) && filled($f['api_key'] ?? null) ? $f : null;
    }
}
