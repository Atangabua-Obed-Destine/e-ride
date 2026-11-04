<?php

namespace Modules\TripManagement\Http\Controllers\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Service\TripDepositService;

/** E-Ride Move for the driver: "We couldn't agree" on the price at the pickup. */
class MoveController extends Controller
{
    public function __construct(protected TripDepositService $deposits)
    {
    }

    public function noAgreement(string $tripId): JsonResponse
    {
        $trip = TripRequest::whereKey($tripId)->where('driver_id', auth('api')->id())->first();
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        try {
            $this->deposits->noAgreement($trip, 'driver');
        } catch (ValidationException $e) {
            return response()->json(responseFormatter(DEFAULT_400, errors: [[
                'error_code' => 'move',
                'message' => collect($e->errors())->flatten()->first(),
            ]]), 403);
        }
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, ['id' => $trip->id, 'current_status' => CANCELLED]));
    }
}
