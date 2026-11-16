<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** Where passengers can get off on the way to a destination, with the fare to there per class. */
class BusDropPoint extends Model
{
    use HasUuid;

    protected $fillable = ['destination_id', 'name', 'lat', 'lng', 'fare_classic', 'fare_vip', 'fare_executive', 'minutes_from_start', 'sort', 'active'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'fare_classic' => 'float',
        'fare_vip' => 'float',
        'fare_executive' => 'float',
        'active' => 'boolean',
    ];

    public function destination(): BelongsTo
    {
        return $this->belongsTo(BusDestination::class, 'destination_id');
    }

    /** The fare for a class, or null when that class does not stop here. */
    public function fareFor(string $class): ?float
    {
        $fare = $this->{'fare_' . $class} ?? null;
        return $fare !== null && $fare > 0 ? (float)$fare : null;
    }
}
