<?php

namespace Modules\PlaceManagement\Lib;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Places API (New), server side so the key never reaches the app.
 *
 * Google's terms allow keeping place IDs only, so nothing here is cached: every
 * search and photo is a live call. Each call is counted per month first, and a
 * call that would pass the admin's cap is not made (the app then shows E-Ride
 * listings only, or a placeholder photo).
 */
class GooglePlacesClient
{
    public const SEARCH = 'search';
    public const PHOTO = 'photo';

    /** One Nearby Search returns at most 20 places. */
    public const MAX_RESULTS = 20;

    private const FIELDS = [
        'places.id', 'places.displayName', 'places.primaryType', 'places.types', 'places.rating',
        'places.userRatingCount', 'places.nationalPhoneNumber', 'places.internationalPhoneNumber',
        'places.formattedAddress', 'places.shortFormattedAddress', 'places.location', 'places.photos',
        'places.businessStatus', 'places.googleMapsUri', 'places.websiteUri',
        'places.currentOpeningHours.openNow', 'places.regularOpeningHours.weekdayDescriptions',
    ];

    private function key(): ?string
    {
        $key = businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;
        return filled($key) ? $key : null;
    }

    /** Places to stay around a point (the Explore "stay" category). */
    public function nearbyLodging(float $latitude, float $longitude, float $radiusKm, string $language = 'en'): ?array
    {
        return $this->nearby(['lodging'], $latitude, $longitude, $radiusKm, $language);
    }

    /**
     * Places of the given Google types around a point, most prominent first,
     * as Google returns them. Null when Google is unavailable: no key, no
     * types, monthly cap reached, or an error (e.g. a type Google rejects).
     */
    public function nearby(array $types, float $latitude, float $longitude, float $radiusKm, string $language = 'en'): ?array
    {
        $types = array_values(array_filter(array_map('trim', $types)));
        $key = $this->key();
        if (!$types || !$key || !$this->spend(self::SEARCH, PlaceSettings::all()['search_cap'])) {
            return null;
        }
        try {
            $response = Http::timeout(8)
                ->withHeaders(['X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => implode(',', self::FIELDS)])
                ->post('https://places.googleapis.com/v1/places:searchNearby', [
                    'includedTypes' => $types,
                    'maxResultCount' => self::MAX_RESULTS,
                    'languageCode' => in_array($language, ['en', 'fr'], true) ? $language : 'en',
                    'locationRestriction' => ['circle' => [
                        'center' => ['latitude' => $latitude, 'longitude' => $longitude],
                        // Google allows up to 50 km.
                        'radius' => min(max($radiusKm, 1), 50) * 1000.0,
                    ]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Google Places search failed', ['error' => $e->getMessage()]);
            return null;
        }
        if (!$response->ok()) {
            Log::warning('Google Places search refused', ['status' => $response->status(), 'error' => $response->json('error.message')]);
            return null;
        }
        return $response->json('places') ?? [];
    }

    /** A short-lived image URL on Google's servers (no key in it) for a photo name from a search. */
    public function photoUri(string $photoName, int $maxWidth): ?string
    {
        $key = $this->key();
        if (!$key || !$this->spend(self::PHOTO, PlaceSettings::all()['photo_cap'])) {
            return null;
        }
        try {
            $response = Http::timeout(8)
                ->withHeaders(['X-Goog-Api-Key' => $key])
                ->get('https://places.googleapis.com/v1/' . $photoName . '/media', [
                    'maxWidthPx' => $maxWidth,
                    'skipHttpRedirect' => 'true',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Google Places photo failed', ['error' => $e->getMessage()]);
            return null;
        }
        return $response->ok() ? $response->json('photoUri') : null;
    }

    public static function usage(string $kind, ?string $month = null): int
    {
        return (int)DB::table('place_google_usage')->where('month', $month ?? now()->format('Y-m'))->where('kind', $kind)->value('count');
    }

    /** Photos are dropped from results once this month's photo cap is used up. */
    public static function photosAvailable(): bool
    {
        return self::usage(self::PHOTO) < PlaceSettings::all()['photo_cap'];
    }

    /** Counts one call, or refuses it when the cap is reached. Atomic, so parallel requests can't overshoot. */
    private function spend(string $kind, int $cap): bool
    {
        if ($cap <= 0) {
            return false;
        }
        $month = now()->format('Y-m');
        DB::table('place_google_usage')->insertOrIgnore(['month' => $month, 'kind' => $kind, 'count' => 0]);
        return DB::table('place_google_usage')
                ->where('month', $month)->where('kind', $kind)->where('count', '<', $cap)
                ->increment('count') === 1;
    }
}
