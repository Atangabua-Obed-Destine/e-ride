<?php

namespace Modules\ZoneManagement\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;
use Modules\ZoneManagement\Transformers\ZoneResource;

class ZoneController extends Controller
{
    protected $zoneService;
    public function __construct(ZoneServiceInterface $zoneService)
    {
        $this->zoneService = $zoneService;
    }
    public function list(Request $request): JsonResponse
    {
        $criteria['is_active'] =  1;
        $zones = $this->zoneService->getBy(criteria: $criteria, orderBy: ['created_at' => 'desc'], limit: $request['limit'], offset: $request['offset']);
        $zoneList = ZoneResource::collection($zones);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $zoneList, limit: $request['limit'], offset: $request['offset']), 200);
    }
}
