<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ZoneManagement\Entities\Zone;

/**
 * A ready-made route in one city (a service zone): verified places in order.
 * Self-guided: each leg is a normal E-Ride ride, booked one stop at a time.
 * Drafted (often by the AI), checked by the team, then published.
 */
class Tour extends Model
{
    use HasUuid, SoftDeletes;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const HIDDEN = 'hidden';
    public const STATUSES = [self::DRAFT, self::PUBLISHED, self::HIDDEN];

    public const MAX_STOPS = 8;

    protected $fillable = ['zone_id', 'slug', 'title', 'title_fr', 'intro', 'intro_fr', 'status', 'is_featured', 'sort', 'created_by', 'published_at'];

    protected $casts = [
        'is_featured' => 'boolean',
        'sort' => 'integer',
        'published_at' => 'datetime',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TourStop::class)->orderBy('position');
    }

    public function localTitle(?string $language): string
    {
        return $language === 'fr' && filled($this->title_fr) ? $this->title_fr : $this->title;
    }

    public function localIntro(?string $language): ?string
    {
        $v = $language === 'fr' && filled($this->intro_fr) ? $this->intro_fr : $this->intro;
        return filled($v) ? trim($v) : null;
    }
}
