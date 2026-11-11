<?php

namespace Modules\ZoneManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\ZoneManagement\Entities\DangerReport;
use Modules\ZoneManagement\Lib\SafetySettings;
use Modules\ZoneManagement\Service\SafetyService;

/** Both apps: report danger, and the alerts near the person (banners). */
class SafetyController extends Controller
{
    public function __construct(protected SafetyService $safety)
    {
    }

    /** What the apps need: on or off, the report types. */
    public function meta(): JsonResponse
    {
        $s = SafetySettings::all();
        return response()->json(responseFormatter(DEFAULT_200, [
            'enabled' => $s['enabled'],
            'types' => DangerReport::TYPES,
            'reports_per_hour' => $s['reports_per_hour'],
        ]));
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(DangerReport::TYPES)],
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'place' => 'nullable|string|max:191',
            'note' => 'nullable|string|max:500',
            'trip_request_id' => 'nullable|uuid',
        ]);
        try {
            $report = $this->safety->report($request->user(), $data['type'], (float)$data['latitude'], (float)$data['longitude'],
                $data['place'] ?? null, $data['note'] ?? null, $data['trip_request_id'] ?? null);
        } catch (ValidationException $e) {
            return response()->json(responseFormatter(DEFAULT_400, errors: [[
                'error_code' => 'safety', 'message' => collect($e->errors())->flatten()->first(),
            ]]), 403);
        }
        return response()->json(responseFormatter(DEFAULT_STORE_200, [
            'id' => $report->id,
            'blocked' => (bool)$report->safety_area_id,
            'message' => translate('Thank you. E-Ride and drivers nearby have been warned.'),
        ]));
    }

    /** Areas in force, pauses planned in the next hours, and fresh reports near a point. */
    public function nearby(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        return response()->json(responseFormatter(DEFAULT_200,
            $this->safety->nearby((float)$data['latitude'], (float)$data['longitude'], $request->user()->user_type === DRIVER)));
    }
}
