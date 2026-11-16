<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * One departure of a bus from the agency's city. Ends at "departed": nothing is tracked on the road.
 * Its seat plan is kept as it was when it opened, so seat numbers never move under sold tickets.
 */
class BusDeparture extends Model
{
    use HasUuid;

    public const SCHEDULED = 'scheduled';
    public const BOARDING = 'boarding';
    public const DEPARTED = 'departed';
    public const CANCELLED = 'cancelled';
    /** Still selling. */
    public const OPEN = [self::SCHEDULED, self::BOARDING];

    protected $fillable = [
        'agency_id', 'schedule_id', 'destination_id', 'class', 'kind', 'date', 'time', 'vehicle_id', 'seat_plan', 'capacity', 'auto_accept',
        'status', 'delayed_to', 'boarding_at', 'departed_at', 'cancelled_at', 'cancel_reason', 'closure_id',
    ];

    protected $casts = [
        'date' => 'date',
        'seat_plan' => 'array',
        'capacity' => 'integer',
        'auto_accept' => 'boolean',
        'boarding_at' => 'datetime',
        'departed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(BusAgency::class, 'agency_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(BusDestination::class, 'destination_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(BusVehicle::class, 'vehicle_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(BusTicket::class, 'departure_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(BusSeatClaim::class, 'departure_id');
    }

    /** When it leaves (the delayed time when it is delayed). */
    public function leavesAt(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse($this->date->toDateString() . ' ' . ($this->delayed_to ?? $this->time));
    }

    public function closure(): BelongsTo
    {
        return $this->belongsTo(BusClosure::class, 'closure_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
