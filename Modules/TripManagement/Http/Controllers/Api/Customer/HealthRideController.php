<?php

namespace Modules\TripManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TripManagement\Lib\HealthRideSettings;
use Modules\TripManagement\Service\HealthRideService;

/** The health ride screen: care places near the rider, the numbers to call, and how many rides are left today. */
class HealthRideController extends Controller
{
    public function __construct(private readonly HealthRideService $health)
    {
    }

    public function places(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        $language = substr((string)$request->header('X-Localization', 'en'), 0, 2);
        $places = HealthRideSettings::enabled() ? $this->health->places((float)$data['latitude'], (float)$data['longitude'], $language)
            : ['hospitals' => [], 'pharmacies' => []];
        return response()->json(responseFormatter(DEFAULT_200, $this->health->config() + $places + [
            'rides_left' => $this->health->ridesLeft(auth('api')->id()),
        ]));
    }
}
