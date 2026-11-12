<?php

namespace Modules\PlaceManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\PlaceManagement\Service\LandmarkService;

/** The place search before anything is typed: the rider's saved places and the most used landmarks. */
class LandmarkController extends Controller
{
    public function start(LandmarkService $landmarks): JsonResponse
    {
        // Google's autocomplete shape, so the app shows it with the same list.
        return response()->json(responseFormatter(DEFAULT_200, ['suggestions' => $landmarks->startList(auth('api')->id())]));
    }
}
