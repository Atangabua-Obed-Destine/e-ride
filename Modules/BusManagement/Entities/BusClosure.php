<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A "no travel" period an agency declared: a lockdown, a ghost town, a road closed. Either a period (from .. to)
 * or a weekday that repeats ("every Monday", from .. until, between some hours). Its departures are closed (their
 * passengers moved to the next departure, or refunded) or put at risk until the agency decides.
 */
class BusClosure extends Model
{
    use HasUuid;

    public const KINDS = ['lockdown', 'ghost_town', 'road_closed', 'strike', 'other'];
    public const MOVE = 'move';
    public const CANCEL = 'cancel';
    public const AT_RISK = 'at_risk';
    public const ACTIVE = 'active';
    public const LIFTED = 'lifted';

    protected $fillable = ['agency_id', 'kind', 'note', 'starts_at', 'ends_at', 'days', 'day_from', 'day_to', 'destination_ids', 'outcome', 'decide_by',
        'status', 'safety_area_id', 'created_by', 'decided_at', 'lifted_at'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'destination_ids' => 'array',
        'days' => 'integer',
        'decide_by' => 'datetime',
        'decided_at' => 'datetime',
        'lifted_at' => 'datetime',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(BusAgency::class, 'agency_id');
    }

    public function departures(): HasMany
    {
        return $this->hasMany(BusDeparture::class, 'closure_id');
    }

    public function repeats(): bool
    {
        return $this->days !== null;
    }

    public function isAtRisk(): bool
    {
        return $this->status === self::ACTIVE && $this->outcome === self::AT_RISK;
    }

    /** Does this closure stop a departure leaving at $leaves to $destinationId? */
    public function covers(CarbonInterface $leaves, string $destinationId): bool
    {
        if ($this->status !== self::ACTIVE) {
            return false;
        }
        if ($this->destination_ids && !in_array($destinationId, $this->destination_ids, true)) {
            return false;
        }
        if (!$this->repeats()) {
            return $leaves->gte($this->starts_at) && $leaves->lt($this->ends_at);
        }
        $date = Carbon::instance($leaves)->startOfDay();
        if ($this->starts_at && $date->lt($this->starts_at->copy()->startOfDay())) {
            return false;
        }
        if ($this->ends_at && $date->gt($this->ends_at->copy()->startOfDay())) {
            return false;
        }
        if (!($this->days & (1 << ($leaves->dayOfWeekIso - 1)))) {
            return false;
        }
        $time = $leaves->format('H:i:s');
        return $time >= ($this->day_from ?? '00:00:00') && $time <= ($this->day_to ?? '23:59:59');
    }

    /** When travel can start again after this closure (for moving passengers): the end of the period or of the day. */
    public function endAfter(CarbonInterface $leaves): Carbon
    {
        if (!$this->repeats()) {
            return Carbon::instance($this->ends_at);
        }
        return Carbon::parse(Carbon::instance($leaves)->toDateString() . ' ' . ($this->day_to ?? '23:59:59'))->addSecond();
    }
}
