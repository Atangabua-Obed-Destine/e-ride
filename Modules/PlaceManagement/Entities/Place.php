<?php

namespace Modules\PlaceManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ZoneManagement\Entities\Zone;

/**
 * A place E-Ride has checked, in one Explore Bamenda category
 * (explore_category, e.g. "stay", "pharmacy"): real photos, contacts and,
 * for places to stay, a price range per night and amenities. Shown above the
 * Google results, and may be linked to its Google listing (google_place_id)
 * to borrow the live rating. `category` is the place's type within its
 * explore category (hotel, guest_house, pharmacy, hospital, atm...).
 */
class Place extends Model
{
    use HasUuid, SoftDeletes;

    /** Types of a place to stay; see PlaceCategory::TYPES for every category. */
    public const CATEGORIES = ['hotel', 'guest_house', 'lodging'];

    /** Amenity keys, in display order. Labels and app icons are keyed by these. */
    public const AMENITIES = [
        'wifi', 'backup_power', 'hot_water', 'parking', 'breakfast', 'restaurant', 'bar',
        'air_conditioning', 'tv', 'security', 'laundry', 'conference_room', 'swimming_pool', 'gym',
    ];

    public const MAX_PHOTOS = 8;

    /** Admin labels for AMENITIES. The app has its own, translated, with icons. */
    public static function amenityLabels(): array
    {
        return [
            'wifi' => translate('Wi-Fi'),
            'backup_power' => translate('Generator or backup power'),
            'hot_water' => translate('Hot water'),
            'parking' => translate('Parking'),
            'breakfast' => translate('Breakfast'),
            'restaurant' => translate('Restaurant'),
            'bar' => translate('Bar'),
            'air_conditioning' => translate('Air conditioning'),
            'tv' => translate('TV'),
            'security' => translate('Security guard'),
            'laundry' => translate('Laundry'),
            'conference_room' => translate('Conference room'),
            'swimming_pool' => translate('Swimming pool'),
            'gym' => translate('Gym'),
        ];
    }

    protected $fillable = [
        'name', 'category', 'explore_category', 'google_place_id', 'description', 'address', 'latitude', 'longitude', 'zone_id',
        'phone', 'whatsapp', 'website', 'price_min', 'price_max', 'amenities', 'photos', 'check_in', 'check_out',
        'reception_24h', 'on_duty_from', 'on_duty_until', 'is_featured', 'is_active', 'sort',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'price_min' => 'float',
        'price_max' => 'float',
        'amenities' => 'array',
        'photos' => 'array',
        'reception_24h' => 'boolean',
        'on_duty_from' => 'date',
        'on_duty_until' => 'date',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** A pharmacy on its duty (garde) dates. Ends on its own when the dates pass. */
    public function isOnDuty(?\Illuminate\Support\Carbon $today = null): bool
    {
        $today = ($today ?? now())->startOfDay();
        return $this->on_duty_from && $this->on_duty_until
            && $this->on_duty_from->lte($today) && $this->on_duty_until->gte($today);
    }

    /** @return string[] */
    public function photoUrls(): array
    {
        $base = dynamicStorage('storage/app/public/place/photo');
        return array_values(array_map(fn($file) => $base . '/' . $file, $this->photos ?? []));
    }
}
