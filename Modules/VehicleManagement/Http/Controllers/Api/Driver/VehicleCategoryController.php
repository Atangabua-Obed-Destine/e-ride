<?php

namespace Modules\VehicleManagement\Http\Controllers\Api\Driver;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\VehicleManagement\Service\Interfaces\VehicleCategoryServiceInterface;
use Modules\VehicleManagement\Transformers\VehicleCategoryResource;

class VehicleCategoryController extends Controller
{

    protected $vehicleCategoryService;
    public function __construct(VehicleCategoryServiceInterface $vehicleCategoryService)
    {
        $this->vehicleCategoryService = $vehicleCategoryService;
    }

    public function list(Request $request)
    {
        $categories = $this->vehicleCategoryService->getBy(criteria: ['is_active'=>1], limit: $request['limit'], offset: $request['offset']);
        $data = VehicleCategoryResource::collection($categories);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $data, limit: $request['limit'], offset: $request['offset']), 200);
    }
}
