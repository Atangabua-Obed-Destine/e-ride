<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** A line of an agency's weekly timetable. Its departures open some days ahead, by themselves. */
class BusSchedule extends Model
{
    use HasUuid;

    public const FIXED = 'fixed';
    /** From the time, leaves when full. */
    public const WHEN_FULL = 'when_full';
    public const DAY_BITS = ['mon' => 1, 'tue' => 2, 'wed' => 4, 'thu' => 8, 'fri' => 16, 'sat' => 32, 'sun' => 64];

    protected $fillable = ['agency_id', 'destination_id', 'class', 'kind', 'time', 'days', 'vehicle_id', 'auto_accept', 'active'];

    protected $casts = ['days' => 'integer', 'auto_accept' => 'boolean', 'active' => 'boolean'];

    public function destination(): BelongsTo
    {
        return $this->belongsTo(BusDestination::class, 'destination_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(BusVehicle::class, 'vehicle_id');
    }

    /** Runs on this date (by its weekday). */
    public function runsOn(\Carbon\CarbonInterface $date): bool
    {
        return (bool)($this->days & (1 << ($date->dayOfWeekIso - 1)));
    }
}
