<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/** A city an agency goes to from its own city, with its drop-off points. */
class BusDestination extends Model
{
    use HasUuid;

    protected $fillable = ['agency_id', 'city', 'duration_minutes', 'active', 'sort'];

    protected $casts = ['active' => 'boolean', 'duration_minutes' => 'integer'];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(BusAgency::class, 'agency_id');
    }

    public function dropPoints(): HasMany
    {
        return $this->hasMany(BusDropPoint::class, 'destination_id')->orderBy('sort');
    }
}
