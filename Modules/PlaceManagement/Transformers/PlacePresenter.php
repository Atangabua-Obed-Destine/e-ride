<?php

namespace Modules\PlaceManagement\Transformers;

use Illuminate\Support\Facades\URL;
use Modules\PlaceManagement\Entities\Place;

/**
 * One card shape for both sources, mirrored by the app's StayPlace model.
 * `source` is "eride" (a verified listing) or "google" (live Places result).
 */
class PlacePresenter
{
    private const HOTEL_TYPES = ['hotel', 'resort_hotel', 'motel', 'extended_stay_hotel'];
    private const GUEST_HOUSE_TYPES = ['guest_house', 'bed_and_breakfast', 'hostel', 'inn', 'private_guest_room', 'cottage', 'farmstay'];

    /** Google type -> our listing type, for the other Explore categories. */
    private const GOOGLE_TO_TYPE = [
        'pharmacy' => 'pharmacy', 'drugstore' => 'pharmacy',
        'hospital' => 'hospital', 'doctor' => 'clinic', 'medical_lab' => 'laboratory', 'dental_clinic' => 'clinic',
        'bank' => 'bank', 'atm' => 'atm',
        'bus_station' => 'bus_agency', 'transit_station' => 'bus_agency', 'travel_agency' => 'bus_agency',
    ];

    /** Signed links stay the same for a whole day, so the phone's image cache keeps working. */
    private const PHOTO_LINK_DAYS = 1;

    public static function verified(Place $place, ?float $lat = null, ?float $lng = null): array
    {
        return [
            'id' => $place->id,
            'source' => 'eride',
            'google_place_id' => $place->google_place_id,
            'name' => $place->name,
            'explore_category' => $place->explore_category,
            'category' => $place->category,
            'description' => $place->description,
            'address' => $place->address,
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'distance_km' => self::distance($lat, $lng, $place->latitude, $place->longitude),
            'phone' => $place->phone,
            'whatsapp' => $place->whatsapp,
            'website' => $place->website,
            'maps_url' => null,
            'rating' => null,
            'reviews' => null,
            'price_min' => $place->price_min,
            'price_max' => $place->price_max,
            'amenities' => array_values(array_intersect(Place::AMENITIES, $place->amenities ?? [])),
            'photos' => array_map(fn($url) => ['url' => $url, 'thumb' => $url, 'author' => null, 'author_uri' => null], $place->photoUrls()),
            'check_in' => $place->check_in,
            'check_out' => $place->check_out,
            'reception_24h' => $place->reception_24h,
            // Open around the clock counts as open now; otherwise unknown for our own listings.
            'open_now' => $place->reception_24h ? true : null,
            'hours' => [],
            'on_duty' => $place->isOnDuty(),
            'on_duty_until' => $place->isOnDuty() ? $place->on_duty_until?->toDateString() : null,
            'is_featured' => $place->is_featured,
        ];
    }

    /** A Places API (New) result. Its rating and reviews come straight from Google. */
    public static function google(array $g, ?float $lat = null, ?float $lng = null, bool $withPhotos = true, string $exploreCategory = 'stay'): array
    {
        $location = $g['location'] ?? [];
        $photos = $withPhotos ? array_slice($g['photos'] ?? [], 0, 5) : [];
        return [
            'id' => 'g:' . $g['id'],
            'source' => 'google',
            'google_place_id' => $g['id'],
            'name' => $g['displayName']['text'] ?? '',
            'explore_category' => $exploreCategory,
            'category' => self::category($g['primaryType'] ?? null, $g['types'] ?? [], $exploreCategory),
            'description' => null,
            'address' => $g['shortFormattedAddress'] ?? ($g['formattedAddress'] ?? ''),
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'distance_km' => isset($location['latitude'], $location['longitude'])
                ? self::distance($lat, $lng, (float)$location['latitude'], (float)$location['longitude'])
                : null,
            'phone' => $g['internationalPhoneNumber'] ?? ($g['nationalPhoneNumber'] ?? null),
            'whatsapp' => null,
            'website' => $g['websiteUri'] ?? null,
            'maps_url' => $g['googleMapsUri'] ?? null,
            'rating' => isset($g['rating']) ? (float)$g['rating'] : null,
            'reviews' => (int)($g['userRatingCount'] ?? 0),
            'price_min' => null,
            'price_max' => null,
            'amenities' => [],
            'photos' => array_values(array_filter(array_map(function ($photo) {
                if (empty($photo['name'])) {
                    return null;
                }
                $author = $photo['authorAttributions'][0] ?? [];
                return [
                    'url' => self::photoUrl($photo['name'], 1200),
                    'thumb' => self::photoUrl($photo['name'], 480),
                    // Google requires showing who took the photo.
                    'author' => $author['displayName'] ?? null,
                    'author_uri' => $author['uri'] ?? null,
                ];
            }, $photos))),
            'check_in' => null,
            'check_out' => null,
            'reception_24h' => false,
            'open_now' => $g['currentOpeningHours']['openNow'] ?? null,
            // "Monday: 8:00 AM - 6:00 PM", in the customer's language.
            'hours' => array_values($g['regularOpeningHours']['weekdayDescriptions'] ?? []),
            'on_duty' => false,
            'on_duty_until' => null,
            'is_featured' => false,
        ];
    }

    public static function category(?string $primaryType, array $types = [], string $exploreCategory = 'stay'): string
    {
        if ($exploreCategory !== 'stay') {
            foreach (array_merge([$primaryType], $types) as $type) {
                if (isset(self::GOOGLE_TO_TYPE[$type])) {
                    return self::GOOGLE_TO_TYPE[$type];
                }
            }
            return 'other';
        }
        foreach (array_merge([$primaryType], $types) as $type) {
            if (in_array($type, self::HOTEL_TYPES, true)) {
                return 'hotel';
            }
            if (in_array($type, self::GUEST_HOUSE_TYPES, true)) {
                return 'guest_house';
            }
        }
        return 'lodging';
    }

    /**
     * Path (no host) of our photo proxy, signed so it can be used as a plain
     * image URL without exposing the key or an open proxy. The app puts its
     * own base URL in front.
     */
    public static function photoUrl(string $photoName, int $width): string
    {
        return URL::temporarySignedRoute(
            'stay.photo',
            now()->addDays(self::PHOTO_LINK_DAYS)->endOfDay(),
            ['name' => $photoName, 'w' => $width],
            absolute: false,
        );
    }

    public static function distance(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return null;
        }
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)), 1);
    }
}
