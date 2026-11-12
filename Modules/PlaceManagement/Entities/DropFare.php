<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\VehicleManagement\Entities\VehicleCategory;

/** A fixed price between two landmarks for one vehicle category, like a taxi "drop". */
class DropFare extends Model
{
    use HasUuid;

    protected $fillable = [
        'from_landmark_id', 'to_landmark_id', 'vehicle_category_id', 'price', 'both_ways', 'radius_m', 'hour_from', 'hour_to',
        'is_active', 'uses',
    ];

    protected $casts = [
        'price' => 'float',
        'both_ways' => 'boolean',
        'radius_m' => 'integer',
        'hour_from' => 'integer',
        'hour_to' => 'integer',
        'is_active' => 'boolean',
        'uses' => 'integer',
    ];

    public function from(): BelongsTo
    {
        return $this->belongsTo(Landmark::class, 'from_landmark_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Landmark::class, 'to_landmark_id');
    }

    public function vehicleCategory(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class);
    }

    /** Within its hours (none set: always). Hours may run past midnight, e.g. 20 to 6. */
    public function openAt(Carbon $at): bool
    {
        if ($this->hour_from === null || $this->hour_to === null || $this->hour_from === $this->hour_to) {
            return true;
        }
        $h = $at->hour;
        return $this->hour_from < $this->hour_to ? ($h >= $this->hour_from && $h < $this->hour_to) : ($h >= $this->hour_from || $h < $this->hour_to);
    }

    /** "Commercial Avenue to Food Market" */
    public function label(): string
    {
        return ($this->from?->name ?? '?') . ($this->both_ways ? ' ⇄ ' : ' → ') . ($this->to?->name ?? '?');
    }
}
