<?php

namespace Modules\AdminModule\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\AdminModule\Service\Interfaces\FleetViewServiceInterface;
use Modules\TripManagement\Service\Interfaces\SafetyAlertServiceInterface;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;

class FleetMapViewController extends BaseController
{
    protected $zoneService;
    protected $safetyAlertService;
    protected $fleetViewService;

    public function __construct(ZoneServiceInterface $zoneService, SafetyAlertServiceInterface $safetyAlertService, FleetViewServiceInterface $fleetViewService)
    {
        parent::__construct($zoneService);
        $this->zoneService = $zoneService;
        $this->safetyAlertService = $safetyAlertService;
        $this->fleetViewService = $fleetViewService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        return parent::index($request, $type);
    }

    public function fleetMap(?Request $request, $type = null)
    {
        $zones = $this->zoneService->getBy(relations: ['tripRequest.safetyAlerts']);
        $safetyAlertZones = $zones->filter(function ($zone) {
            return $zone->tripRequest->contains(function ($tripRequest) {
                return $tripRequest->safetyAlerts->where('status', PENDING)->isNotEmpty();
            });
        })->pluck('id')->toArray();
        $safetyAlertCount = $this->safetyAlertService->getBy(criteria: ['status' => PENDING])->count();

        $zone = $this->resolveZone($request, $zones);
        $safetyAlertLatestUserRoute = $safetyAlertCount > 0 ? $this->safetyAlertService->safetyAlertLatestUserRoute() : 'javascript:void(0)';
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['status' => PENDING], relations: ['sentBy',], orderBy: ['created_at' => 'desc']);
        $safetyAlertUserId = $safetyAlert?->sentBy?->id ?? null;

        [$polygons, $centerLat, $centerLng] = $this->zoneGeometry($zone);
        $drivers = [];
        $customers = [];
        $markers = json_encode([[]]);
        if ($zone) {
            $data = $this->fleetViewService->getFleetData($type, $zone, $request->all());
            $drivers = $data['drivers'] ?? [];
            $customers = $data['customers'] ?? [];
            $markers = $data['markers'];
        }

        return view('adminmodule::fleet-map', compact('drivers', 'customers', 'zones', 'safetyAlertZones', 'safetyAlertCount', 'safetyAlertLatestUserRoute', 'safetyAlertUserId', 'type', 'markers', 'polygons', 'centerLat', 'centerLng'));
    }

    public function fleetMapDriverList(?Request $request, $type = null)
    {
        $zone = $this->resolveZone($request, $this->zoneService->getAll());
        $drivers = $zone ? $this->fleetViewService->getFleetData($type, $zone, $request->all())['drivers'] : [];

        return response()
            ->json(view('adminmodule::partials.fleet-map._fleet-map-driver-list', compact('drivers'))
                ->render());
    }

    public function fleetMapDriverDetails($id, Request $request)
    {
        $data = $this->fleetViewService->getDriverFleetDetails($id);

        return response()
            ->json(view('adminmodule::partials.fleet-map._fleet-map-driver-details', $data)
                ->render());
    }

    public function fleetMapCustomerList(?Request $request, $type = null)
    {
        $zone = $this->resolveZone($request, $this->zoneService->getAll());
        $customers = $zone ? $this->fleetViewService->getFleetData($type, $zone, $request->all())['customers'] : [];

        return response()
            ->json(view('adminmodule::partials.fleet-map._fleet-map-customer-list', compact('customers'))
                ->render());
    }

    public function fleetMapCustomerDetails($id, Request $request)
    {
        $data = $this->fleetViewService->getCustomerFleetDetails($id);

        return response()
            ->json(view('adminmodule::partials.fleet-map._fleet-map-customer-details', $data)
                ->render());
    }

    public function fleetMapViewUsingAjax(Request $request)
    {
        $zone = $this->resolveZone($request, $this->zoneService->getAll());
        [$polygons, $centerLat, $centerLng] = $this->zoneGeometry($zone);
        $markers = $zone
            ? $this->fleetViewService->getFleetData($request->type, $zone, $request->all(), markersOnly: true)['markers']
            : json_encode([[]]);

        return response()
            ->json(['markers' => $markers, 'polygons' => $polygons, 'centerLat' => $centerLat, 'centerLng' => $centerLng]);
    }

    public function fleetMapViewSingleDriver($id, Request $request)
    {
        $zone = $this->resolveZone($request, $this->zoneService->getAll());
        [$polygons, $centerLat, $centerLng] = $this->zoneGeometry($zone);
        $markers = $zone
            ? json_encode([$this->fleetViewService->getSingleDriverMarker($id)])
            : json_encode([[]]);

        return response()
            ->json(['markers' => $markers, 'polygons' => $polygons, 'centerLat' => $centerLat, 'centerLng' => $centerLng]);
    }

    public function fleetMapViewSingleCustomer($id, Request $request)
    {
        $zone = $this->resolveZone($request, $this->zoneService->getAll());
        [$polygons, $centerLat, $centerLng] = $this->zoneGeometry($zone);
        $markers = $zone
            ? json_encode([$this->fleetViewService->getSingleCustomerMarker($id)])
            : json_encode([[]]);

        return response()
            ->json(['markers' => $markers, 'polygons' => $polygons, 'centerLat' => $centerLat, 'centerLng' => $centerLng]);
    }

    public function fleetMapZoneMessage()
    {
        $safetyAlertCount = $this->safetyAlertService->getBy(criteria: ['status' => PENDING])->count();

        return response()->json(view('adminmodule::partials.fleet-map._safety-alert-get-zone-message', compact('safetyAlertCount'))->render());
    }

    public function fleetMapSafetyAlertIconInMap()
    {
        $safetyAlertCount = $this->safetyAlertService->getBy(criteria: ['status' => PENDING])->count();
        $safetyAlertLatestUserRoute = $safetyAlertCount > 0 ? $this->safetyAlertService->safetyAlertLatestUserRoute() : 'javascript:void(0)';
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['status' => PENDING], relations: ['sentBy',], orderBy: ['created_at' => 'desc']);
        $safetyAlertUserId = $safetyAlert?->sentBy?->id ?? null;

        return response()->json(view('adminmodule::partials.fleet-map._safety-alert-icon-in-map', compact('safetyAlertCount', 'safetyAlertLatestUserRoute', 'safetyAlertUserId'))->render());
    }

    private function resolveZone(?Request $request, $zones)
    {
        if (array_key_exists('zone_id', $request->all()) && $request['zone_id']) {
            return $this->zoneService->findOne(id: $request['zone_id']);
        }

        return count($zones) ? $this->zoneService->findOne(id: $zones[0]->id) : null;
    }

    private function zoneGeometry($zone): array
    {
        if (!$zone) {
            return [json_encode([[]]), 0, 0];
        }

        $coordinates = formatCoordinates(json_decode($zone->coordinates[0]->toJson(), true)['coordinates']);
        $latSum = 0;
        $lngSum = 0;
        $totalPoints = count($coordinates);
        foreach ($coordinates as $point) {
            $latSum += $point->lat;
            $lngSum += $point->lng;
        }

        return [
            json_encode([$coordinates]),
            $latSum / ($totalPoints == 0 ? 1 : $totalPoints),
            $lngSum / ($totalPoints == 0 ? 1 : $totalPoints),
        ];
    }
}
