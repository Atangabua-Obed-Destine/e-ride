<?php

namespace Modules\StoreManagement\Http\Controllers\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\StoreManagement\Lib\StoreRiders;

/**
 * Driver app, "Shops I deliver for": the stores that chose this rider for
 * their deliveries ("My riders" in the partner portal). The rider can leave.
 */
class StoreTeamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, ['stores' => StoreRiders::forDriver(auth('api')->user())]));
    }

    public function leave(string $id): JsonResponse
    {
        if (!StoreRiders::leave(auth('api')->user(), $id)) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }
        return response()->json(responseFormatter(DEFAULT_200, ['stores' => StoreRiders::forDriver(auth('api')->user())]));
    }
}
