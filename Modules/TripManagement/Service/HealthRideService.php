<?php

namespace Modules\TripManagement\Service;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\PlaceManagement\Service\PlaceService;
use Modules\PlaceManagement\Transformers\PlacePresenter;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Lib\HealthRideSettings;

/**
 * Health rides: an urgent ride to a hospital, clinic or pharmacy. Not an ambulance.
 * The rider pays the normal fare with no surge or extra fare, cannot bid, and the
 * request reaches drivers over a wider radius with a red "Health ride" label.
 * At most daily_limit a day per rider; admins see every one (Trip management > Health rides).
 */
class HealthRideService
{
    /** Explore Bamenda categories that count as care, and the kind the app shows. */
    public const CATEGORIES = ['health' => 'hospital', 'pharmacy' => 'pharmacy'];

    /** How far (km) a health request reaches: never less than the normal search radius. */
    public function radiusKm(): float
    {
        $normal = (float)(get_cache('search_radius') ?? 5) ?: 5;
        $set = HealthRideSettings::all()['radius_km'];
        return $set > 0 ? max($set, $normal) : $normal * 2;
    }

    /**
     * Health rides that count toward today's limit: every one booked today, except
     * those cancelled before any driver accepted (nobody came, so nothing was used).
     */
    public function ridesToday(string $customerId): int
    {
        return TripRequest::where('customer_id', $customerId)
            ->where('is_health_ride', 1)
            ->where('created_at', '>=', now()->startOfDay())
            ->where(fn($q) => $q->where('current_status', '!=', CANCELLED)
                ->orWhereHas('tripStatus', fn($s) => $s->whereNotNull('accepted')))
            ->count();
    }

    public function ridesLeft(string $customerId): int
    {
        return max(0, HealthRideSettings::all()['daily_limit'] - $this->ridesToday($customerId));
    }

    /** Why this health ride cannot be booked, or null. */
    public function problem(string $customerId, bool $isMove = false, ?string $rideRequestType = null, bool $bid = false): ?string
    {
        if (!HealthRideSettings::enabled()) {
            return translate('Health rides are not available right now. Book a normal ride.');
        }
        if ($isMove) {
            return translate('A health ride is for a person, not a move.');
        }
        if ($rideRequestType === SCHEDULED) {
            return translate('A health ride is for right now. Schedule a normal ride instead.');
        }
        if ($bid) {
            return translate('A health ride uses the normal fare, so there is no bidding.');
        }
        if ($this->ridesLeft($customerId) <= 0) {
            return translate(key: 'You have used your {n} health rides for today. Book a normal ride, or call the emergency numbers if it is an emergency.',
                replace: ['n' => HealthRideSettings::all()['daily_limit']]);
        }
        return null;
    }

    /** The estimate list for a health ride: the normal fare with no surge or extra fare, and the flag. */
    public function applyToEstimate(Collection $estimates): Collection
    {
        return $estimates->map(function ($entry) {
            $entry = (array)$entry;
            if (!empty($entry['is_move'])) {
                return $entry + ['is_health_ride' => false];
            }
            return array_merge($entry, [
                'extra_estimated_fare' => 0,
                'extra_discount_fare' => 0,
                'extra_discount_amount' => 0,
                'extra_fare_amount' => 0,
                'extra_fare_fee' => 0,
                'extra_fare_reason' => '',
                'surge_multiplier' => 0,
                'is_health_ride' => true,
            ]);
        });
    }

    /**
     * Care places near the rider, nearest first: E-Ride's verified places and Google's,
     * hospitals and clinics apart from pharmacies. Cached for 10 minutes per ~1 km
     * square, so a rider opening the screen again does not spend Google searches.
     */
    public function places(float $latitude, float $longitude, string $language = 'en'): array
    {
        $key = sprintf('health_places:%.2f:%.2f:%s', $latitude, $longitude, $language);
        $raw = Cache::remember($key, 600, function () use ($latitude, $longitude, $language) {
            $out = [];
            foreach (self::CATEGORIES as $category => $kind) {
                $result = app(PlaceService::class)->explore($category, $latitude, $longitude, $language);
                foreach (array_merge($result['verified'] ?? [], $result['nearby'] ?? []) as $p) {
                    if (!isset($p['latitude'], $p['longitude']) || $p['latitude'] === null || $p['longitude'] === null) {
                        continue;
                    }
                    $out[$p['id']] = [
                        'id' => (string)$p['id'],
                        'kind' => $kind,
                        'name' => (string)$p['name'],
                        'address' => (string)($p['address'] ?? ''),
                        'latitude' => (float)$p['latitude'],
                        'longitude' => (float)$p['longitude'],
                        'phone' => $p['phone'] ?? null,
                        'open_now' => $p['open_now'] ?? null,
                        'on_duty' => (bool)($p['on_duty'] ?? false),
                        'verified' => ($p['source'] ?? '') === 'eride',
                    ];
                }
            }
            return array_values($out);
        });

        $places = collect($raw)
            ->map(fn($p) => $p + ['distance_km' => PlacePresenter::distance($latitude, $longitude, $p['latitude'], $p['longitude'])])
            ->sortBy(fn($p) => $p['distance_km'] ?? PHP_INT_MAX)
            ->values();
        return [
            'hospitals' => $places->where('kind', 'hospital')->take(15)->values()->all(),
            // Pharmacies on duty tonight (de garde) first.
            'pharmacies' => $places->where('kind', 'pharmacy')->sortBy(fn($p) => [$p['on_duty'] ? 0 : 1, $p['distance_km'] ?? PHP_INT_MAX])->take(10)->values()->all(),
        ];
    }

    /** What the app needs before booking: the switch, the limit and the numbers to call. */
    public function config(): array
    {
        $s = HealthRideSettings::all();
        return [
            'status' => $s['enabled'],
            'daily_limit' => $s['daily_limit'],
            'emergency_numbers' => HealthRideSettings::emergencyNumbers(),
        ];
    }
}
