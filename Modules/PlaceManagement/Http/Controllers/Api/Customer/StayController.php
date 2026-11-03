<?php

namespace Modules\PlaceManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PlaceManagement\Lib\GooglePlacesClient;
use Modules\PlaceManagement\Lib\PlaceSettings;
use Modules\PlaceManagement\Service\Interfaces\PlaceServiceInterface;

/** Customer app: places to stay near the customer, and the Google photo proxy. */
class StayController extends Controller
{
    public function __construct(protected PlaceServiceInterface $places)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        if (!PlaceSettings::enabled()) {
            return response()->json(responseFormatter(DEFAULT_200, ['enabled' => false, 'verified' => [], 'top_rated' => [], 'nearby' => [], 'google_available' => false]));
        }
        $lat = $request->filled('latitude') ? (float)$request->query('latitude') : null;
        $lng = $request->filled('longitude') ? (float)$request->query('longitude') : null;
        $language = substr((string)$request->header('X-Localization', 'en'), 0, 2);
        return response()->json(responseFormatter(DEFAULT_200, $this->places->stay($lat, $lng, $language)));
    }

    /**
     * Signed image link for a Google photo. Redirects to Google's own image URL,
     * which carries no key. Unsigned, expired or capped requests get 404 and the
     * app shows its placeholder.
     */
    public function photo(Request $request, GooglePlacesClient $google): RedirectResponse
    {
        abort_unless($request->hasValidRelativeSignature(), 403);
        $name = (string)$request->query('name');
        abort_unless(preg_match('#^places/[A-Za-z0-9_-]+/photos/[A-Za-z0-9_-]+$#', $name) === 1, 404);
        $width = min(max((int)$request->query('w', 480), 100), 1600);
        $uri = $google->photoUri($name, $width);
        abort_unless($uri, 404);
        return redirect()->away($uri)->header('Cache-Control', 'private, max-age=3600');
    }
}
