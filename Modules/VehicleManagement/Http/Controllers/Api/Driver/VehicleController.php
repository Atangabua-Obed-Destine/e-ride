<?php

namespace Modules\VehicleManagement\Http\Controllers\Api\Driver;

use Illuminate\Routing\Controller;
use Modules\VehicleManagement\Http\Requests\VehicleApiStoreUpdateRequest;
use Modules\VehicleManagement\Service\Interfaces\VehicleServiceInterface;

class VehicleController extends Controller
{
    protected $vehicleService;


    public function __construct(VehicleServiceInterface $vehicleService)
    {
        $this->vehicleService = $vehicleService;
    }

    public function store(VehicleApiStoreUpdateRequest $request)
    {
        if ($this->vehicleService->findOneBy(['driver_id' => $request->driver_id])) {
            return response()->json(responseFormatter(constant: VEHICLE_DRIVER_EXISTS_403), 403);
        }
        $data = array_merge($request->validated(), ['vehicle_request_status' => PENDING]);
        $this->vehicleService->create(data: $data);
        return response()->json(responseFormatter(VEHICLE_CREATE_200), 200);
    }

    public function update(int|string $id, VehicleApiStoreUpdateRequest $request)
    {
        $vehicle = $this->vehicleService->updatedByDriver(id:$id, data: $request->validated());

        if (empty($vehicle->draft)) return response()->json(responseFormatter(VEHICLE_UNCHANGED_200), 200);

        if ($vehicle?->vehicle_request_status == APPROVED && $vehicle?->draft) {
            return response()->json(responseFormatter(VEHICLE_REQUEST_200), 200);
        }

        return response()->json(responseFormatter(VEHICLE_UPDATE_200), 200);
    }
}
