<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * An Explore Bamenda category: what the app lists (verified places with this
 * explore_category) and which Google place types it searches.
 */
class PlaceCategory extends Model
{
    use HasUuid;

    /** Icons the app can draw. Keep in step with the app's ExploreIcons. */
    public const ICONS = [
        'pharmacy', 'hospital', 'bank', 'bus', 'bed', 'store', 'fuel', 'police', 'school', 'church',
        'restaurant', 'atm', 'money', 'market', 'place', 'home', 'landmark', 'swim', 'party',
    ];

    /**
     * Explore categories grouped under "Things to do" in the app (the tour guide),
     * in display order. The app shows them as one tile, not one tile each.
     */
    public const THINGS = ['eat', 'swim', 'party', 'visit', 'market'];

    /**
     * Categories searched with a Google text search instead of place types,
     * because Google has no type for them: a pool is mostly part of a hotel.
     */
    public const TEXT_SEARCH = ['swim' => 'swimming pool'];

    /** Place types an admin can pick for a listing, per category key. Unknown categories use "other". */
    public const TYPES = [
        'stay' => ['hotel', 'guest_house', 'lodging'],
        'pharmacy' => ['pharmacy'],
        'health' => ['hospital', 'clinic', 'laboratory'],
        'bank' => ['bank', 'atm', 'microfinance'],
        'bus' => ['bus_agency'],
        'visit' => ['sight', 'museum', 'park', 'waterfall', 'viewpoint', 'art_gallery', 'monument'],
        'eat' => ['restaurant', 'cafe', 'snack_bar', 'bakery'],
        'swim' => ['swimming_pool', 'hotel_pool', 'water_park'],
        'party' => ['night_club', 'bar', 'lounge'],
        'market' => ['market', 'shopping_mall', 'craft_shop'],
    ];

    protected $fillable = ['key', 'name', 'name_fr', 'icon', 'google_types', 'has_price', 'is_active', 'sort'];

    protected $casts = [
        'google_types' => 'array',
        'has_price' => 'boolean',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public static function typesFor(string $key): array
    {
        return self::TYPES[$key] ?? ['other'];
    }

    /** Every listing type, for validation. */
    public static function allTypes(): array
    {
        return array_values(array_unique(array_merge(array_merge(...array_values(self::TYPES)), ['other'])));
    }

    public function localName(?string $language): string
    {
        return $language === 'fr' && filled($this->name_fr) ? $this->name_fr : $this->name;
    }
}
