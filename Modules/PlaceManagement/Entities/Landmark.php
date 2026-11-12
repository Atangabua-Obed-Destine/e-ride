<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A place Bamenda people give directions by: "Hospital Roundabout", "Mile 4 Total". */
class Landmark extends Model
{
    use HasUuid;

    protected $fillable = ['name', 'aliases', 'area', 'latitude', 'longitude', 'zone_id', 'place_id', 'is_active', 'uses'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
        'uses' => 'integer',
    ];

    /** @return string[] other names, one per line in the admin form */
    public function aliasList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)$this->aliases))));
    }

    /** "Hospital Roundabout, Nkwen" */
    public function fullName(): string
    {
        return $this->area ? $this->name . ', ' . $this->area : $this->name;
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
