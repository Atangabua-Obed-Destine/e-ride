<?php

namespace Modules\UserManagement\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\UserManagement\Transformers\DriverLeaderBoardResourse;

class DriverActivityController extends Controller
{
    protected $tripRequestService;

    public function __construct(TripRequestServiceInterface $tripRequestService)
    {
        $this->tripRequestService = $tripRequestService;
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return JsonResponse
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filter' => ['required', Rule::in([TODAY, THIS_WEEK, THIS_MONTH])],
            'limit' => 'required|numeric',
            'offset' => 'required|numeric'
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $attributes = [
            'user_type' => DRIVER,
            'data' => $request?->filter
        ];
        $leadDriver = $this->tripRequestService->getLeaderBoard(data: $attributes, limit: $request->limit, offset: $request->offset);
        $leadDriver = DriverLeaderBoardResourse::collection($leadDriver);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $leadDriver, limit: $request->limit, offset: $request->offset));
    }

    /**
     * @return JsonResponse
     */
    public function dailyIncome(): JsonResponse
    {
//        $total_income =  [
//            'column' => 'driver_id',
//            'value' => auth('api')->id(),
//            'sum' => 'paid_fare',
//            'from' => now()->startOfDay(),
//            'to' => now()->endOfDay(),
//        ];
//        $totalTrip =  [
//            'column' => 'driver_id',
//            'value' => auth('api')->id(),
//            'count' => 'id',
//            'from' => now()->startOfDay(),
//            'to' => now()->endOfDay(),
//        ];
        $attributes = [
            'user_type' => DRIVER,
            'driver_id' => auth('api')->id(),
            'data' => TODAY
        ];
        $todayIncomeTrip = $this->tripRequestService->getLeaderBoard(data: $attributes);
        if (count($todayIncomeTrip) > 0) {
            $totalIncome = $todayIncomeTrip[0]?->income ?? 0;
            $totalTrip = $todayIncomeTrip[0]?->total_records ?? 0;
        }else{
            $totalIncome = 0;
            $totalTrip = 0;
        }


        return response()->json([
                'total_income' => $totalIncome,
                'total_trip' => $totalTrip,
            ]
        );
    }
}
