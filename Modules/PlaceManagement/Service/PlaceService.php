<?php

namespace Modules\PlaceManagement\Service;

use App\Service\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\PlaceManagement\Entities\GoogleHiddenPlace;
use Modules\PlaceManagement\Entities\Place;
use Modules\PlaceManagement\Entities\PlaceCategory;
use Modules\PlaceManagement\Lib\GooglePlacesClient;
use Modules\PlaceManagement\Lib\PlaceSettings;
use Modules\PlaceManagement\Repository\PlaceRepositoryInterface;
use Modules\PlaceManagement\Service\Interfaces\PlaceServiceInterface;
use Modules\PlaceManagement\Transformers\PlacePresenter;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;

class PlaceService extends BaseService implements PlaceServiceInterface
{
    /** Top rated: how many, and the review count that pulls a rating toward the average. */
    private const TOP_RATED_COUNT = 8;
    private const RATING_WEIGHT_REVIEWS = 20;

    public function __construct(
        protected PlaceRepositoryInterface $placeRepository,
        protected ZoneServiceInterface $zoneService,
        protected GooglePlacesClient $google,
    )
    {
        parent::__construct($placeRepository);
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Place::query()
            ->with('zone')
            ->when(($filters['search'] ?? '') !== '', function ($q) use ($filters) {
                $q->where(fn($w) => $w->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('phone', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('address', 'like', '%' . $filters['search'] . '%'));
            })
            ->when(($filters['explore'] ?? 'all') !== 'all', fn($q) => $q->where('explore_category', $filters['explore']))
            ->when(in_array($filters['status'] ?? 'all', ['active', 'inactive'], true), fn($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->orderByDesc('is_featured')->orderBy('sort')->orderBy('name')
            ->paginate($perPage)
            ->appends($filters);
    }

    public function save(Place $place, array $data): Place
    {
        $place->fill([
            'name' => trim($data['name']),
            'explore_category' => $data['explore_category'] ?? ($place->explore_category ?? 'stay'),
            'category' => $data['category'],
            'google_place_id' => filled($data['google_place_id'] ?? null) ? trim($data['google_place_id']) : null,
            'description' => $data['description'] ?? null,
            'address' => trim($data['address']),
            'latitude' => (float)$data['latitude'],
            'longitude' => (float)$data['longitude'],
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'website' => $data['website'] ?? null,
            'price_min' => filled($data['price_min'] ?? null) ? (float)$data['price_min'] : null,
            'price_max' => filled($data['price_max'] ?? null) ? (float)$data['price_max'] : null,
            'amenities' => array_values(array_intersect(Place::AMENITIES, $data['amenities'] ?? [])),
            'check_in' => $data['check_in'] ?? null,
            'check_out' => $data['check_out'] ?? null,
            'reception_24h' => (bool)($data['reception_24h'] ?? false),
            'on_duty_from' => $data['on_duty_from'] ?? null,
            'on_duty_until' => $data['on_duty_until'] ?? null,
            'is_featured' => (bool)($data['is_featured'] ?? false),
            'is_active' => (bool)($data['is_active'] ?? false),
            'sort' => (int)($data['sort'] ?? 0),
        ]);
        $place->zone_id = $this->zoneService->getByPoints(new Point($place->latitude, $place->longitude))
            ->where('is_active', 1)->first()?->id;

        // Photos: drop the ticked ones, then append uploads up to the limit.
        $photos = $place->photos ?? [];
        $remove = $data['remove_photos'] ?? [];
        foreach ($photos as $file) {
            if (in_array($file, $remove, true)) {
                fileRemover('place/photo/', $file);
            }
        }
        $photos = array_values(array_diff($photos, $remove));
        foreach ($data['new_photos'] ?? [] as $upload) {
            if (count($photos) >= Place::MAX_PHOTOS) {
                break;
            }
            $file = fileUploader('place/photo/', APPLICATION_IMAGE_FORMAT, $upload);
            if ($file) {
                $photos[] = $file;
            }
        }
        // An optional cover choice moves that photo to the front.
        if (filled($data['cover_photo'] ?? null) && in_array($data['cover_photo'], $photos, true)) {
            $photos = array_values(array_unique(array_merge([$data['cover_photo']], $photos)));
        }
        $place->photos = $photos;
        $place->save();
        return $place;
    }

    /** Active Explore categories, in display order. */
    public function categories(string $language = 'en'): array
    {
        return PlaceCategory::where('is_active', 1)->orderBy('sort')->orderBy('name')->get()
            ->map(fn(PlaceCategory $c) => [
                'key' => $c->key,
                'name' => $c->localName($language),
                'icon' => $c->icon,
                'has_price' => $c->has_price,
            ])->values()->all();
    }

    /** The Hotels screen of app builds from before Explore. */
    public function stay(?float $latitude, ?float $longitude, string $language = 'en'): array
    {
        return $this->explore('stay', $latitude, $longitude, $language);
    }

    public function explore(string $categoryKey, ?float $latitude, ?float $longitude, string $language = 'en'): array
    {
        $settings = PlaceSettings::all();
        $category = PlaceCategory::where('key', $categoryKey)->where('is_active', 1)->first();
        $verified = Place::where('is_active', 1)->where('explore_category', $categoryKey)->orderBy('sort')->orderBy('name')->get()
            ->map(fn(Place $p) => PlacePresenter::verified($p, $latitude, $longitude))
            // On duty first (pharmacies), then featured, then nearest. Unknown distance goes last.
            ->sortBy(fn($p) => [$p['on_duty'] ? 0 : 1, $p['is_featured'] ? 0 : 1, $p['distance_km'] ?? PHP_INT_MAX])
            ->values()->all();

        $google = null;
        if ($category && $settings['google_status'] && $latitude !== null && $longitude !== null) {
            $google = $this->google->nearby($category->google_types ?? [], $latitude, $longitude, $settings['radius_km'], $language);
        }

        $topRated = [];
        $nearby = [];
        if ($google !== null) {
            $hidden = GoogleHiddenPlace::pluck('google_place_id')->flip();
            $linked = [];
            foreach ($verified as $i => $place) {
                if ($place['google_place_id']) {
                    $linked[$place['google_place_id']] = $i;
                }
            }
            $withPhotos = GooglePlacesClient::photosAvailable();
            foreach ($google as $result) {
                $id = $result['id'] ?? null;
                if (!$id || isset($hidden[$id])) {
                    continue;
                }
                if (($result['businessStatus'] ?? 'OPERATIONAL') !== 'OPERATIONAL') {
                    continue;
                }
                $card = PlacePresenter::google($result, $latitude, $longitude, $withPhotos, $categoryKey);
                if (isset($linked[$id])) {
                    // Already listed by E-Ride: show it once, with Google's live rating and hours.
                    $i = $linked[$id];
                    $verified[$i]['rating'] = $card['rating'];
                    $verified[$i]['reviews'] = $card['reviews'];
                    $verified[$i]['maps_url'] = $card['maps_url'];
                    $verified[$i]['hours'] = $card['hours'];
                    $verified[$i]['open_now'] = $verified[$i]['open_now'] ?? $card['open_now'];
                    continue;
                }
                $nearby[] = $card;
            }
            $topRated = $this->topRated($nearby, $settings['min_reviews']);
            usort($nearby, fn($a, $b) => ($a['distance_km'] ?? PHP_INT_MAX) <=> ($b['distance_km'] ?? PHP_INT_MAX));
        }

        return [
            'enabled' => $settings['status'] && $category !== null,
            'label' => $settings['label'],
            'category' => $category ? [
                'key' => $category->key, 'name' => $category->localName($language), 'icon' => $category->icon, 'has_price' => $category->has_price,
            ] : null,
            // Pharmacies on duty today (pharmacie de garde), from E-Ride's own listings.
            'on_duty' => array_values(array_filter($verified, fn($p) => $p['on_duty'])),
            // Shown on the health category when the admin set one, e.g. emergency numbers.
            'notice' => $categoryKey === 'health' && filled($settings['emergency_note']) ? $settings['emergency_note'] : null,
            'verified' => $verified,
            'top_rated' => $topRated,
            'nearby' => $nearby,
            // False when Google was off, capped or failed: the app says so instead of showing an empty list.
            'google_available' => $google !== null,
            'radius_km' => $settings['radius_km'],
        ];
    }

    /** Admin: the on-duty (garde) pharmacies for a date range. Pharmacies left out lose any current dates. */
    public function setOnDuty(array $placeIds, string $from, string $until): int
    {
        Place::where('explore_category', 'pharmacy')->whereNotIn('id', $placeIds)
            ->whereNotNull('on_duty_until')->where('on_duty_until', '>=', now()->toDateString())
            ->update(['on_duty_from' => null, 'on_duty_until' => null]);
        return Place::where('explore_category', 'pharmacy')->whereIn('id', $placeIds)
            ->update(['on_duty_from' => $from, 'on_duty_until' => $until]);
    }

    /**
     * Rating weighted by review count (a Bayesian average), so 4.3 stars from
     * 80 reviews beats 5 stars from 2. Places under the review minimum are left out.
     */
    private function topRated(array $places, int $minReviews): array
    {
        $rated = array_values(array_filter($places, fn($p) => $p['rating'] !== null && $p['reviews'] >= max(1, $minReviews)));
        if (!$rated) {
            return [];
        }
        $mean = array_sum(array_column($rated, 'rating')) / count($rated);
        $m = self::RATING_WEIGHT_REVIEWS;
        foreach ($rated as &$p) {
            $p['score'] = ($p['rating'] * $p['reviews'] + $mean * $m) / ($p['reviews'] + $m);
        }
        unset($p);
        usort($rated, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_map(function ($p) {
            unset($p['score']);
            return $p;
        }, array_slice($rated, 0, self::TOP_RATED_COUNT));
    }

    public function googlePreview(float $latitude, float $longitude, string $categoryKey = 'stay'): ?array
    {
        $settings = PlaceSettings::all();
        $types = PlaceCategory::where('key', $categoryKey)->first()?->google_types ?? ['lodging'];
        $results = $this->google->nearby($types, $latitude, $longitude, $settings['radius_km'], 'en');
        if ($results === null) {
            return null;
        }
        $hidden = GoogleHiddenPlace::pluck('reason', 'google_place_id');
        $linked = Place::whereNotNull('google_place_id')->get(['id', 'name', 'google_place_id'])->keyBy('google_place_id');
        return collect($results)->map(function ($g) use ($latitude, $longitude, $hidden, $linked, $settings, $categoryKey) {
            $card = PlacePresenter::google($g, $latitude, $longitude, false, $categoryKey);
            $id = $g['id'] ?? '';
            $status = match (true) {
                $linked->has($id) => 'linked',
                $hidden->has($id) => 'hidden',
                ($g['businessStatus'] ?? 'OPERATIONAL') !== 'OPERATIONAL' => 'closed',
                default => 'shown',
            };
            return $card + [
                'status' => $status,
                'hidden_reason' => $hidden[$id] ?? null,
                'linked_place' => $linked->get($id),
                'business_status' => $g['businessStatus'] ?? null,
                'in_top_rated' => $card['rating'] !== null && $card['reviews'] >= max(1, $settings['min_reviews']),
            ];
        })->sortBy('distance_km')->values()->all();
    }

    public function hideGooglePlace(string $googlePlaceId, ?string $label, ?string $reason, ?string $adminId): void
    {
        GoogleHiddenPlace::updateOrCreate(
            ['google_place_id' => $googlePlaceId],
            ['label' => $label ? mb_substr($label, 0, 150) : null, 'reason' => $reason, 'created_by' => $adminId],
        );
    }

    public function unhideGooglePlace(string $googlePlaceId): void
    {
        GoogleHiddenPlace::where('google_place_id', $googlePlaceId)->delete();
    }
}
