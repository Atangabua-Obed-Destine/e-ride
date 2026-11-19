<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One stop of a tour: a verified place, with an optional note for this tour. */
class TourStop extends Model
{
    use HasUuid;

    protected $fillable = ['tour_id', 'place_id', 'position', 'note', 'note_fr', 'stay_minutes'];

    protected $casts = [
        'position' => 'integer',
        'stay_minutes' => 'integer',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function localNote(?string $language): ?string
    {
        $v = $language === 'fr' && filled($this->note_fr) ? $this->note_fr : $this->note;
        return filled($v) ? trim($v) : null;
    }
}
