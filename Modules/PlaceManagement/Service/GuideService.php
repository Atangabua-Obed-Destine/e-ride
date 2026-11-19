<?php

namespace Modules\PlaceManagement\Service;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\PlaceManagement\Entities\Place;
use Modules\PlaceManagement\Entities\PlaceCategory;
use Modules\PlaceManagement\Entities\PlaceRating;
use Modules\PlaceManagement\Entities\Tour;
use Modules\PlaceManagement\Entities\TourStop;
use Modules\PlaceManagement\Transformers\PlacePresenter;
use Modules\UserManagement\Entities\User;
use Modules\ZoneManagement\Entities\Zone;

/**
 * E-Ride as a city and tour guide: place guides, riders' ratings, tours, and
 * ride estimates between stops. Everything here reads E-Ride's own tables
 * (no Google call), so it is cheap to call from any screen.
 */
class GuideService
{
    /** Road distance is longer than a straight line; this is a city average. */
    private const ROAD_FACTOR = 1.3;
    /** Average city speed for time estimates, km/h. */
    private const CITY_KMH = 22;
    /** Time to spend at a stop when the team has not set one. */
    private const DEFAULT_STAY = 45;
    /** "Nearby" ideas around a place, km. */
    private const NEARBY_KM = 8;

    // ------------------------------------------------------------------ ratings

    /**
     * E-Ride ratings for these place keys: average, count and the newest
     * written comment, from visible ratings only.
     *
     * @param string[] $keys
     * @return array<string, array{rating: float, count: int, quote: ?string}>
     */
    public function ratingsFor(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys)));
        if (!$keys) {
            return [];
        }
        $out = [];
        $rows = PlaceRating::whereIn('place_key', $keys)->where('is_visible', 1)
            ->groupBy('place_key')->select('place_key', DB::raw('AVG(stars) as avg_stars'), DB::raw('COUNT(*) as n'))->get();
        foreach ($rows as $r) {
            $out[$r->place_key] = ['rating' => round((float)$r->avg_stars, 1), 'count' => (int)$r->n, 'quote' => null];
        }
        if ($out) {
            // Newest comment per place, a short quote for the list.
            $quotes = PlaceRating::whereIn('place_key', array_keys($out))->where('is_visible', 1)->whereNotNull('comment')->where('comment', '!=', '')
                ->orderByDesc('updated_at')->get(['place_key', 'comment']);
            foreach ($quotes as $q) {
                if ($out[$q->place_key]['quote'] === null) {
                    $out[$q->place_key]['quote'] = Str::limit($q->comment, 90);
                }
            }
        }
        return $out;
    }

    /** Ratings of a card, joining a verified place with its Google listing's ratings. */
    private static function keysOf(array $card): array
    {
        $keys = [$card['id']];
        if (($card['source'] ?? '') === 'eride' && filled($card['google_place_id'] ?? null)) {
            $keys[] = 'g:' . $card['google_place_id'];
        }
        return $keys;
    }

    /**
     * Adds E-Ride ratings and guide facts to place cards (PlacePresenter shape):
     * eride_rating, eride_ratings, eride_quote, has_guide, stay_minutes, entry_fee.
     */
    public function enrich(array $cards): array
    {
        if (!$cards) {
            return $cards;
        }
        $keys = [];
        foreach ($cards as $c) {
            array_push($keys, ...self::keysOf($c));
        }
        $ratings = $this->ratingsFor($keys);
        $verifiedIds = array_values(array_filter(array_map(fn($c) => ($c['source'] ?? '') === 'eride' ? $c['id'] : null, $cards)));
        $guides = $verifiedIds ? Place::whereIn('id', $verifiedIds)->get(['id', 'guide_status', 'stay_minutes', 'entry_fee'])->keyBy('id') : collect();

        foreach ($cards as &$c) {
            $sum = 0.0;
            $n = 0;
            $quote = null;
            foreach (self::keysOf($c) as $k) {
                if (isset($ratings[$k])) {
                    $sum += $ratings[$k]['rating'] * $ratings[$k]['count'];
                    $n += $ratings[$k]['count'];
                    $quote ??= $ratings[$k]['quote'];
                }
            }
            $c['eride_rating'] = $n ? round($sum / $n, 1) : null;
            $c['eride_ratings'] = $n;
            $c['eride_quote'] = $quote;
            $g = $guides->get($c['id']);
            $c['has_guide'] = (bool)$g?->hasGuide();
            $c['stay_minutes'] = $g?->hasGuide() ? $g->stay_minutes : null;
            $c['entry_fee'] = $g?->hasGuide() ? $g->entry_fee : null;
        }
        unset($c);
        return $cards;
    }

    /** Enriches the lists of an Explore result for a Things to do category. */
    public function enrichExplore(array $result): array
    {
        foreach (['verified', 'top_rated', 'nearby', 'on_duty'] as $list) {
            if (!empty($result[$list])) {
                $result[$list] = $this->enrich($result[$list]);
            }
        }
        return $result;
    }

    public function rate(User $user, string $placeKey, int $stars, ?string $comment, ?string $placeName): PlaceRating
    {
        $comment = $comment !== null ? trim(preg_replace('/\s+/u', ' ', strip_tags($comment))) : null;
        // Phone numbers and emails stay out of public reviews.
        if ($comment !== null) {
            $comment = preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+/u', '', $comment);
            $comment = trim(preg_replace('/(\+?\d[\d\s\-]{7,}\d)/u', '', $comment));
        }
        $rating = PlaceRating::firstOrNew(['place_key' => $placeKey, 'user_id' => $user->id]);
        $rating->fill([
            'stars' => max(1, min(5, $stars)),
            'comment' => filled($comment) ? mb_substr($comment, 0, 300) : null,
            'place_name' => $placeName ? mb_substr($placeName, 0, 150) : $rating->place_name,
        ]);
        // A changed rating goes back on show unless the team hid this person's earlier one for a reason.
        if (!$rating->exists) {
            $rating->is_visible = true;
        }
        $rating->save();
        return $rating;
    }

    /** Newest visible reviews of a place, with the reviewer's first name only. */
    public function reviews(array $keys, int $limit = 10): array
    {
        return PlaceRating::with('user:id,first_name,last_name')->whereIn('place_key', $keys)->where('is_visible', 1)
            ->orderByRaw('comment IS NULL')->orderByDesc('updated_at')->limit($limit)->get()
            ->map(fn(PlaceRating $r) => [
                'id' => $r->id,
                'name' => self::shortName($r->user),
                'stars' => $r->stars,
                'comment' => $r->comment,
                'date' => $r->updated_at?->toDateString(),
            ])->values()->all();
    }

    private static function shortName(?User $user): string
    {
        if (!$user) {
            return 'E-Ride rider';
        }
        $first = trim((string)$user->first_name);
        $last = trim((string)$user->last_name);
        return trim($first . ($last !== '' ? ' ' . mb_substr($last, 0, 1) . '.' : '')) ?: 'E-Ride rider';
    }

    // ------------------------------------------------------------------ one place

    /**
     * Everything the app shows about one place beyond its card: the guide
     * (when published), E-Ride ratings and reviews, the person's own rating,
     * tours that include it, and nearby ideas with ride estimates.
     */
    public function placeGuide(string $id, ?float $lat, ?float $lng, string $language, ?User $user): ?array
    {
        $place = null;
        if (!str_starts_with($id, 'g:')) {
            $place = Place::where('id', $id)->where('is_active', 1)->first();
            if (!$place) {
                return null;
            }
        }
        $keys = $place ? array_filter([$place->id, $place->google_place_id ? 'g:' . $place->google_place_id : null]) : [$id];
        if (!$place) {
            // A Google place E-Ride has also verified: show the verified one's guide.
            $place = Place::where('google_place_id', substr($id, 2))->where('is_active', 1)->first();
            if ($place) {
                $keys[] = $place->id;
            }
        }
        $summary = $this->ratingsFor($keys);
        $sum = 0.0;
        $n = 0;
        foreach ($summary as $s) {
            $sum += $s['rating'] * $s['count'];
            $n += $s['count'];
        }
        $mine = $user ? PlaceRating::whereIn('place_key', $keys)->where('user_id', $user->id)->orderByDesc('updated_at')->first() : null;

        $tours = [];
        if ($place) {
            $tourIds = TourStop::where('place_id', $place->id)->pluck('tour_id');
            $tours = Tour::whereIn('id', $tourIds)->where('status', Tour::PUBLISHED)->with('stops.place')->orderBy('sort')->get()
                ->map(fn(Tour $t) => $this->tourCard($t, $language))->values()->all();
        }

        $origin = $place ? [$place->latitude, $place->longitude] : [$lat, $lng];
        return [
            'id' => $id,
            'verified_id' => $place?->id,
            'guide' => $place && $place->hasGuide() ? $this->guideOf($place, $language) : null,
            'eride_rating' => $n ? round($sum / $n, 1) : null,
            'eride_ratings' => $n,
            'reviews' => $this->reviews($keys),
            'my_rating' => $mine ? ['stars' => $mine->stars, 'comment' => $mine->comment] : null,
            'tours' => $tours,
            'nearby' => $origin[0] !== null ? $this->nearbyThings($origin[0], $origin[1], $place?->id, $language) : [],
        ];
    }

    public function guideOf(Place $place, string $language): array
    {
        return [
            'story' => $place->guideText('story', $language),
            'best_time' => $place->guideText('best_time', $language),
            'tips' => $place->guideText('tips', $language),
            'stay_minutes' => $place->stay_minutes,
            'entry_fee' => $place->entry_fee,
            'checked_at' => $place->guide_checked_at?->toDateString(),
        ];
    }

    /** Verified Things to do near a point, nearest first, each with a ride estimate from there. */
    public function nearbyThings(float $lat, float $lng, ?string $excludeId, string $language, int $limit = 4): array
    {
        $zoneId = $this->zoneAt($lat, $lng)?->id;
        return Place::where('is_active', 1)->whereIn('explore_category', PlaceCategory::THINGS)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->map(function (Place $p) use ($lat, $lng, $zoneId) {
                $card = PlacePresenter::verified($p, $lat, $lng);
                $card['ride'] = $this->leg($lat, $lng, $p->latitude, $p->longitude, $zoneId);
                return $card;
            })
            ->filter(fn($c) => $c['distance_km'] !== null && $c['distance_km'] <= self::NEARBY_KM && $c['distance_km'] >= 0.15)
            ->sortBy('distance_km')->take($limit)->values()
            ->pipe(fn($c) => $this->enrich($c->all()));
    }

    /**
     * "All" of Things to do: E-Ride's own checked places in the city around
     * the point (team picks), across every Things to do category.
     */
    public function picks(?float $lat, ?float $lng, string $language): array
    {
        $zone = $lat !== null && $lng !== null ? $this->zoneAt($lat, $lng) : null;
        $cards = Place::where('is_active', 1)->whereIn('explore_category', PlaceCategory::THINGS)
            ->when($zone, fn($q) => $q->where('zone_id', $zone->id))
            ->orderByDesc('is_featured')->orderBy('sort')->orderBy('name')->limit(60)->get()
            ->map(fn(Place $p) => PlacePresenter::verified($p, $lat, $lng))->values()->all();
        return $this->enrich($cards);
    }

    /** The Things to do kinds: the active categories of the group, in order. */
    public function kinds(string $language): array
    {
        $order = array_flip(PlaceCategory::THINGS);
        return PlaceCategory::whereIn('key', PlaceCategory::THINGS)->where('is_active', 1)->get()
            ->sortBy(fn($c) => $order[$c->key] ?? 99)
            ->map(fn(PlaceCategory $c) => ['key' => $c->key, 'name' => $c->localName($language), 'icon' => $c->icon])
            ->values()->all();
    }

    // ------------------------------------------------------------------ tours

    /** Published tours of the city (zone) the point is in. No point, no zone: none. */
    public function tours(?float $lat, ?float $lng, string $language): array
    {
        $zone = $lat !== null && $lng !== null ? $this->zoneAt($lat, $lng) : null;
        if (!$zone) {
            return ['city' => null, 'tours' => []];
        }
        $tours = Tour::where('zone_id', $zone->id)->where('status', Tour::PUBLISHED)->with('stops.place')
            ->orderByDesc('is_featured')->orderBy('sort')->orderBy('title')->get()
            ->map(fn(Tour $t) => $this->tourCard($t, $language))
            ->filter(fn($t) => $t['stops_count'] > 0)->values()->all();
        return ['city' => $zone->name, 'tours' => $tours];
    }

    /** A tour as a card: title, stops, total time and the rides' estimated cost. */
    public function tourCard(Tour $tour, string $language): array
    {
        $stops = $tour->stops->filter(fn(TourStop $s) => $s->place && $s->place->is_active)->values();
        $plan = $this->plan($stops, $tour->zone_id);
        $cover = null;
        foreach ($stops as $s) {
            $cover = $s->place->photoUrls()[0] ?? null;
            if ($cover) {
                break;
            }
        }
        return [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'title' => $tour->localTitle($language),
            'intro' => $tour->localIntro($language),
            'stops_count' => $stops->count(),
            'stop_names' => $stops->map(fn($s) => $s->place->name)->values()->all(),
            'minutes' => $plan['minutes'],
            'ride_km' => $plan['km'],
            'ride_fare' => $plan['fare'],
            'cover' => $cover,
            'is_featured' => $tour->is_featured,
        ];
    }

    /** A tour with its stops (cards and guides) and the ride between each. */
    public function tour(string $id, ?float $lat, ?float $lng, string $language, ?User $user): ?array
    {
        $tour = Tour::where(fn($q) => $q->where('id', $id)->orWhere('slug', $id))->where('status', Tour::PUBLISHED)->with('stops.place')->first();
        if (!$tour) {
            return null;
        }
        $stops = $tour->stops->filter(fn(TourStop $s) => $s->place && $s->place->is_active)->values();
        $cards = $this->enrich($stops->map(fn(TourStop $s) => PlacePresenter::verified($s->place, $lat, $lng))->all());
        $out = [];
        $prev = null;
        foreach ($stops as $i => $s) {
            $p = $s->place;
            $out[] = [
                'position' => $i + 1,
                'note' => $s->localNote($language),
                'stay_minutes' => $s->stay_minutes ?? $p->stay_minutes ?? self::DEFAULT_STAY,
                'place' => $cards[$i],
                'guide' => $p->hasGuide() ? $this->guideOf($p, $language) : null,
                // The ride to this stop from the one before it (the first: from where the person is now).
                'ride' => $prev
                    ? $this->leg($prev->latitude, $prev->longitude, $p->latitude, $p->longitude, $tour->zone_id)
                    : ($lat !== null && $lng !== null ? $this->leg($lat, $lng, $p->latitude, $p->longitude, $tour->zone_id) : null),
            ];
            $prev = $p;
        }
        return $this->tourCard($tour, $language) + ['city' => $tour->zone?->name, 'stops' => $out];
    }

    /** Total time (rides and stays) and ride cost of a tour, between its stops. */
    private function plan($stops, ?string $zoneId): array
    {
        $km = 0.0;
        $fare = 0.0;
        $minutes = 0;
        $prev = null;
        foreach ($stops as $s) {
            $p = $s->place;
            $minutes += $s->stay_minutes ?? $p->stay_minutes ?? self::DEFAULT_STAY;
            if ($prev) {
                $leg = $this->leg($prev->latitude, $prev->longitude, $p->latitude, $p->longitude, $zoneId);
                $km += $leg['km'];
                $minutes += $leg['minutes'];
                $fare += $leg['fare'] ?? 0;
            }
            $prev = $p;
        }
        return ['km' => round($km, 1), 'minutes' => $minutes, 'fare' => $fare > 0 ? $fare : null];
    }

    /**
     * An estimate for one ride: road km, minutes, and the cheapest fare of the
     * zone (base fare + per km). Only an estimate: the real fare is shown when
     * the ride is booked.
     *
     * @return array{km: float, minutes: int, fare: ?float}
     */
    public function leg(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2, ?string $zoneId): array
    {
        $straight = PlacePresenter::distance($lat1, $lng1, $lat2, $lng2) ?? 0.0;
        $km = round($straight * self::ROAD_FACTOR, 1);
        $minutes = max(3, (int)round($km / self::CITY_KMH * 60));
        $fare = null;
        $rate = $zoneId ? $this->cheapestRate($zoneId) : null;
        if ($rate) {
            // Rounded up to 50, the way people pay in cash-sized amounts.
            $fare = (float)(ceil(($rate['base'] + $rate['per_km'] * $km) / 50) * 50);
        }
        return ['km' => $km, 'minutes' => $minutes, 'fare' => $fare];
    }

    /** The zone's cheapest ride: lowest base fare and per-km among active vehicle categories. */
    private function cheapestRate(string $zoneId): ?array
    {
        return Cache::remember('guide:rate:' . $zoneId, 600, function () use ($zoneId) {
            $row = DB::table('trip_fares')
                ->join('vehicle_categories', 'vehicle_categories.id', '=', 'trip_fares.vehicle_category_id')
                ->where('trip_fares.zone_id', $zoneId)
                ->where('vehicle_categories.is_active', 1)
                ->whereNull('vehicle_categories.deleted_at')
                ->orderByRaw('trip_fares.base_fare + trip_fares.base_fare_per_km * 3')
                ->first(['trip_fares.base_fare', 'trip_fares.base_fare_per_km']);
            return $row ? ['base' => (float)$row->base_fare, 'per_km' => (float)$row->base_fare_per_km] : null;
        });
    }

    public function zoneAt(float $lat, float $lng): ?Zone
    {
        return Zone::where('is_active', 1)->whereContains('coordinates', new Point($lat, $lng))->first();
    }

    /** A url-safe slug, unique among tours. */
    public static function slugFor(string $title, ?string $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'tour';
        $slug = $base;
        $i = 2;
        while (Tour::withTrashed()->where('slug', $slug)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
