<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\FareManagement\Service\Interfaces\TripFareServiceInterface;
use Modules\FareManagement\Service\SurgePricingService;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;

/**
 * Public helpers for the landing page's "Book on WhatsApp" sheet: place
 * search, "use my location", and an approximate fare per ride type. The
 * booking itself is sent by the visitor as a WhatsApp message; nothing is
 * stored here.
 *
 * Only answers while WhatsApp booking is switched on in Landing Page Setup,
 * and every route is rate-limited (they call paid Google APIs).
 */
class WhatsAppBookingController extends Controller
{
    public function __construct(
        protected ZoneServiceInterface $zoneService,
        protected TripFareServiceInterface $tripFareService,
    )
    {
    }

    /** Booking settings, with defaults: on, business phone, no custom line. */
    public static function settings(): array
    {
        $value = landingPageConfig(key: WHATSAPP_BOOKING_KEY, settingsType: WHATSAPP_BOOKING)?->value ?? [];
        $number = preg_replace('/\D+/', '', (string)($value['number'] ?? ''));
        if ($number === '') {
            $number = preg_replace('/\D+/', '', (string)getSession('business_contact_phone'));
        }
        return [
            'status' => (bool)($value['status'] ?? true),
            'number' => $number,
            'greeting' => trim((string)($value['greeting'] ?? '')),
        ];
    }

    private function enabled(): bool
    {
        $settings = self::settings();
        return $settings['status'] && $settings['number'] !== '';
    }

    private function mapKey(): ?string
    {
        return businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;
    }

    /** Place suggestions, nearby first. */
    public function places(Request $request): JsonResponse
    {
        abort_unless($this->enabled(), 404);
        $request->validate([
            'q' => 'required|string|min:2|max:120',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $body = ['input' => $request->input('q')];
        if ($request->filled(['lat', 'lng'])) {
            $body['locationBias'] = ['circle' => [
                'center' => ['latitude' => (float)$request->input('lat'), 'longitude' => (float)$request->input('lng')],
                'radius' => 50000.0,
            ]];
        }
        $response = Http::timeout(10)->withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $this->mapKey(),
            'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.structuredFormat',
        ])->post('https://places.googleapis.com/v1/places:autocomplete', $body);

        $items = collect($response->json('suggestions') ?? [])
            ->map(fn($s) => $s['placePrediction'] ?? null)
            ->filter()
            ->map(fn($p) => [
                'id' => $p['placeId'] ?? null,
                'main' => $p['structuredFormat']['mainText']['text'] ?? '',
                'secondary' => $p['structuredFormat']['secondaryText']['text'] ?? '',
            ])
            ->filter(fn($p) => $p['id'])
            ->take(6)
            ->values();

        return response()->json(['places' => $items]);
    }

    /** Coordinates and address of a chosen suggestion. */
    public function place(Request $request): JsonResponse
    {
        abort_unless($this->enabled(), 404);
        $request->validate(['id' => ['required', 'string', 'max:300', 'regex:/^[A-Za-z0-9_\-]+$/']]);

        $response = Http::timeout(10)->withHeaders([
            'X-Goog-Api-Key' => $this->mapKey(),
            'X-Goog-FieldMask' => 'location,formattedAddress,displayName',
        ])->get('https://places.googleapis.com/v1/places/' . $request->input('id'));

        $location = $response->json('location');
        if (!$location) {
            return response()->json(['message' => translate('Place not found')], 404);
        }
        $name = $response->json('displayName.text') ?? '';
        $address = $response->json('formattedAddress') ?? '';

        return response()->json([
            'lat' => $location['latitude'],
            'lng' => $location['longitude'],
            'name' => $name,
            'address' => $name && !str_starts_with($address, $name) ? $name . ', ' . $address : $address,
        ]);
    }

    /** Address for the visitor's current location. */
    public function reverse(Request $request): JsonResponse
    {
        abort_unless($this->enabled(), 404);
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $response = Http::timeout(10)->get(MAP_API_BASE_URI . '/geocode/json', [
            'latlng' => $request->input('lat') . ',' . $request->input('lng'),
            'key' => $this->mapKey(),
        ]);
        $address = $response->json('results.0.formatted_address');

        return response()->json([
            'lat' => (float)$request->input('lat'),
            'lng' => (float)$request->input('lng'),
            'address' => $address ?: translate('My current location'),
        ]);
    }

    /**
     * Distance, time and an approximate fare for each ride type in the
     * pickup's zone. Same formula as the app's estimate (base fare + per-km,
     * zone extra fare, surge, VAT), without user-specific discounts.
     */
    public function quote(Request $request): JsonResponse
    {
        abort_unless($this->enabled(), 404);
        $request->validate([
            'plat' => 'required|numeric|between:-90,90',
            'plng' => 'required|numeric|between:-180,180',
            'dlat' => 'required|numeric|between:-90,90',
            'dlng' => 'required|numeric|between:-180,180',
            'scheduled' => 'nullable|boolean',
        ]);
        $pickup = [(float)$request->input('plat'), (float)$request->input('plng')];
        $drop = [(float)$request->input('dlat'), (float)$request->input('dlng')];

        $zone = $this->zoneService->getByPoints(new Point($pickup[0], $pickup[1]))->where('is_active', 1)->first();
        $inZone = $zone && $this->zoneService->getZoneContainingBothPoints($zone->id, new Point($pickup[0], $pickup[1]), new Point($drop[0], $drop[1]));

        $routes = getRoutes(originCoordinates: $pickup, destinationCoordinates: $drop, drivingMode: ['DRIVE', 'TWO_WHEELER']);
        if (array_key_exists('error', $routes)) {
            return response()->json(['message' => translate('No route found between these places')], 422);
        }
        $byMode = collect($routes)->keyBy('drive_mode');
        $drive = $byMode->get('DRIVE') ?? $byMode->first();

        $result = [
            'in_zone' => (bool)$inZone,
            'distance_km' => round((float)($drive['distance'] ?? 0), 1),
            'duration_min' => max(1, (int)ceil(($drive['duration_sec'] ?? 0) / 60)),
            'options' => [],
        ];
        if (!$inZone) {
            return response()->json($result);
        }

        $vat = (double)(get_cache('vat_percent') ?? 0);
        $extraFee = ($zone->extra_fare_status && $zone->extra_fare_fee > 0) ? (double)$zone->extra_fare_fee : 0;
        $schedulePct = 0;
        if ($request->boolean('scheduled') && businessConfig('schedule_trip_status')?->value && businessConfig('increase_fare')?->value) {
            $schedulePct = (double)(businessConfig('increase_fare_amount')?->value ?? 0);
        }
        $surgeService = app()->make(SurgePricingService::class);
        $surges = $surgeService->fetchSurgesForZone($zone->id, null);

        $fares = $this->tripFareService->getBy(criteria: ['zone_id' => $zone->id], relations: ['vehicleCategory'])
            ->filter(fn($f) => $f->vehicleCategory && $f->vehicleCategory->is_active);

        foreach ($fares as $fare) {
            $category = $fare->vehicleCategory;
            $route = $category->type === 'car' ? $byMode->get('DRIVE') : ($byMode->get('TWO_WHEELER') ?? $byMode->get('DRIVE'));
            if (!$route) {
                continue;
            }
            $perKm = $fare->base_fare_per_km * (1 + $schedulePct / 100);
            $base = $fare->base_fare * (1 + $schedulePct / 100);
            $amount = $base + $perKm * (double)$route['distance'];
            $amount += $amount * $extraFee / 100;
            $surge = $surgeService->evaluateSurgeForCategory(surges: $surges, tripType: RIDE_REQUEST, vehicleCategoryId: $fare->vehicle_category_id, scheduledAt: null);
            if (!empty($surge)) {
                $amount += $amount * (double)$surge['surge_multiplier'] / 100;
            }
            $amount += $amount * $vat / 100;

            $result['options'][] = [
                'id' => $fare->vehicle_category_id,
                'name' => $category->name,
                'type' => $category->type,
                'image' => $category->image ? dynamicStorage('storage/app/public/vehicle/category') . '/' . $category->image : null,
                'fare' => round($amount),
                'fare_text' => getCurrencyFormat(round($amount)),
                'minutes' => max(1, (int)ceil(($route['duration_sec'] ?? 0) / 60)),
            ];
        }
        usort($result['options'], fn($a, $b) => $a['fare'] <=> $b['fare']);

        return response()->json($result);
    }
}
