<?php

namespace Modules\VehicleManagement\Http\Controllers\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\VehicleManagement\Service\Interfaces\VehicleBrandServiceInterface;
use Modules\VehicleManagement\Transformers\VehicleBrandResource;

class VehicleBrandController extends Controller
{

    protected $vehicleBrandService;
    public function __construct(VehicleBrandServiceInterface $vehicleBrandService)
    {
        $this->vehicleBrandService = $vehicleBrandService;
    }

    public function brandList(Request $request): JsonResponse
    {
        $criteria['is_active'] =  1;
        $relations = [
            'vehicleModels' => [
                ['is_active', '=', 1]
            ]
        ];

        $brands = $this->vehicleBrandService->getBy(criteria: $criteria, relations: $relations, orderBy: ['created_at' => 'desc'], limit: $request['limit'], offset: $request['offset']);
        $brandList = VehicleBrandResource::collection($brands);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $brandList, limit: $request['limit'], offset: $request['offset']), 200);
    }
}
