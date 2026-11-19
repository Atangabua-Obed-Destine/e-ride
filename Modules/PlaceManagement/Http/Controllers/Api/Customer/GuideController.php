<?php

namespace Modules\PlaceManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PlaceManagement\Lib\PlaceSettings;
use Modules\PlaceManagement\Service\GuideService;

/**
 * Customer app: the city and tour guide. Things to do (E-Ride's picks across
 * eat, swim, party, sights and markets), a place's guide and riders' ratings,
 * rating a place, and the city's tours. No Google call here.
 */
class GuideController extends Controller
{
    public function __construct(protected GuideService $guide)
    {
    }

    private static function point(Request $request): array
    {
        $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        return [
            $request->filled('latitude') ? (float)$request->query('latitude') : null,
            $request->filled('longitude') ? (float)$request->query('longitude') : null,
        ];
    }

    private static function language(Request $request): string
    {
        return substr((string)$request->header('X-Localization', 'en'), 0, 2) === 'fr' ? 'fr' : 'en';
    }

    /** Things to do home: the kinds, E-Ride's picks and the city's tours. */
    public function things(Request $request): JsonResponse
    {
        [$lat, $lng] = self::point($request);
        $language = self::language($request);
        if (!PlaceSettings::enabled()) {
            return response()->json(responseFormatter(DEFAULT_200, ['enabled' => false, 'kinds' => [], 'picks' => [], 'tours' => [], 'city' => null]));
        }
        $tours = $this->guide->tours($lat, $lng, $language);
        return response()->json(responseFormatter(DEFAULT_200, [
            'enabled' => true,
            'kinds' => $this->guide->kinds($language),
            'picks' => $this->guide->picks($lat, $lng, $language),
            'tours' => $tours['tours'],
            'city' => $tours['city'],
        ]));
    }

    public function place(Request $request, string $id): JsonResponse
    {
        [$lat, $lng] = self::point($request);
        $data = $this->guide->placeGuide($id, $lat, $lng, self::language($request), $request->user());
        if (!$data) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }
        return response()->json(responseFormatter(DEFAULT_200, $data));
    }

    public function rate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'stars' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:300',
            'name' => 'nullable|string|max:150',
        ]);
        if (!str_starts_with($id, 'g:') && !\Modules\PlaceManagement\Entities\Place::where('id', $id)->exists()) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }
        if (str_starts_with($id, 'g:') && preg_match('/^g:[A-Za-z0-9_-]{10,200}$/', $id) !== 1) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }
        $this->guide->rate($request->user(), $id, (int)$data['stars'], $data['comment'] ?? null, $data['name'] ?? null);
        return response()->json(responseFormatter(DEFAULT_200, $this->guide->placeGuide($id, null, null, self::language($request), $request->user())));
    }

    public function tours(Request $request): JsonResponse
    {
        [$lat, $lng] = self::point($request);
        return response()->json(responseFormatter(DEFAULT_200, $this->guide->tours($lat, $lng, self::language($request))));
    }

    public function tour(Request $request, string $id): JsonResponse
    {
        [$lat, $lng] = self::point($request);
        $data = $this->guide->tour($id, $lat, $lng, self::language($request), $request->user());
        if (!$data) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }
        return response()->json(responseFormatter(DEFAULT_200, $data));
    }
}
