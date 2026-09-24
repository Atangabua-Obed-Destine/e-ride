<?php

namespace Modules\AdminModule\Service;

use Illuminate\Database\Eloquent\Model;
use Modules\AdminModule\Service\Interfaces\FleetViewServiceInterface;
use Modules\TripManagement\Service\Interfaces\SafetyAlertServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\UserManagement\Service\Interfaces\CustomerServiceInterface;
use Modules\UserManagement\Service\Interfaces\DriverServiceInterface;

class FleetViewService implements FleetViewServiceInterface
{
    protected $driverService;
    protected $customerService;
    protected $safetyAlertService;
    protected $tripRequestService;

    public function __construct(
        DriverServiceInterface      $driverService,
        CustomerServiceInterface    $customerService,
        SafetyAlertServiceInterface $safetyAlertService,
        TripRequestServiceInterface $tripRequestService
    )
    {
        $this->driverService = $driverService;
        $this->customerService = $customerService;
        $this->safetyAlertService = $safetyAlertService;
        $this->tripRequestService = $tripRequestService;
    }

    public function getFleetData(?string $type, Model $zone, array $filters, bool $markersOnly = false): array
    {
        $searchCriteria = [];
        if (array_key_exists('search', $filters)) {
            $searchCriteria = [
                'fields' => ['full_name', 'first_name', 'last_name', 'phone'],
                'value' => $filters['search']
            ];
        }
        if ($type == ALL_DRIVER) {
            $driverCriteria = [
                'user_type' => DRIVER,
                'is_active' => 1,
            ];
            $driverRelations = $this->driverMarkerRelations($markersOnly);
            $driverWhereHasRelations = [
                'driverDetails' => ['is_online' => true],
                'lastLocations' => ['zone_id' => $zone->id],
            ];

            $drivers = $this->driverService->getBy(criteria: $driverCriteria, searchCriteria: $searchCriteria, whereHasRelations: $driverWhereHasRelations, relations: $driverRelations);
        } elseif ($type == DRIVER_ON_TRIP) {
            $driverCriteria = [
                'user_type' => DRIVER,
                'is_active' => 1,
            ];
            $driverRelations = $this->driverMarkerRelations($markersOnly);
            $driverWhereHasRelations = [
                'driverDetails' => ['is_online' => true],
                'lastLocations' => ['zone_id' => $zone->id],
                'driverTrips' => [
                    'type' => RIDE_REQUEST,
                    'current_status' => [ACCEPTED, OUT_FOR_PICKUP, ONGOING],
                ],
            ];
            $drivers = $this->driverService->getBy(criteria: $driverCriteria, searchCriteria: $searchCriteria, whereHasRelations: $driverWhereHasRelations, relations: $driverRelations);
        } elseif ($type == DRIVER_IDLE) {
            $driverCriteria = [
                'user_type' => DRIVER,
                'is_active' => 1,
            ];
            $driverRelations = $this->driverMarkerRelations($markersOnly);
            $driverWhereHasRelations = [
                'driverDetails' => ['is_online' => true],
                'lastLocations' => ['zone_id' => $zone->id],
            ];
            $drivers = $this->driverService->getBy(criteria: $driverCriteria, searchCriteria: $searchCriteria, whereHasRelations: $driverWhereHasRelations, relations: $driverRelations);
            $drivers = $drivers->filter(function ($driver) {
                return $driver->driverTrips
                        ->whereIn('current_status', [ACCEPTED, OUT_FOR_PICKUP, ONGOING])
                        ->where('type', RIDE_REQUEST)
                        ->count() < 1;
            })->values();
        } elseif ($type == ALL_CUSTOMER) {
            $customerCriteria = [
                'user_type' => CUSTOMER,
                'is_active' => 1,
            ];

            $customerRelations = $this->customerMarkerRelations($markersOnly);

            $customerWhereHasRelations = [
                'lastLocations' => ['zone_id' => $zone->id],
            ];

            $customers = $this->customerService->getBy(criteria: $customerCriteria, searchCriteria: $searchCriteria, whereHasRelations: $customerWhereHasRelations, relations: $customerRelations);

            $markers = $this->generateMarkers($customers, 'customer', $this->pendingSafetyAlertSet($customers));
            $markers = json_encode($markers);

            return [
                'markers' => $markers,
                'customers' => $customers];
        } else {
            abort(404);
        }
        $markers = $this->generateMarkers($drivers, 'driver', $this->pendingSafetyAlertSet($drivers));
        $markers = json_encode($markers);
        return [
            'drivers' => $drivers,
            'markers' => $markers,
        ];
    }

    public function getDriverFleetDetails(int|string $id): array
    {
        $driver = $this->driverService->findOneBy(criteria: ['user_type' => DRIVER, 'id' => $id], relations: [
            'vehicle.model', 'lastLocations', 'userAccount', 'receivedReviews', 'driverDetails'
        ]);
        $trip = $driver ? $this->tripRequestService->findOneBy(
            criteria: ['driver_id' => $id, 'type' => RIDE_REQUEST],
            whereInCriteria: ['current_status' => [ACCEPTED, OUT_FOR_PICKUP, ONGOING]]
        ) : null;
        $otherTrips = $driver ? $this->tripRequestService->getBy(
            criteria: ['driver_id' => $id, 'type' => RIDE_REQUEST],
            whereHasRelations: ['safetyAlerts' => ['status' => PENDING, 'sent_by' => $id]]
        )->filter(fn($otherTrip) => $otherTrip->id != $trip?->id)->values() : null;

        return ['driver' => $driver, 'trip' => $trip, 'otherTrips' => $otherTrips];
    }

    public function getCustomerFleetDetails(int|string $id): array
    {
        $tripRelations = ['vehicle.category', 'driver.vehicle.category', 'driver.vehicle.brand', 'driver.vehicle.model'];

        $customer = $this->customerService->findOneBy(criteria: ['user_type' => CUSTOMER, 'id' => $id], relations: [
            'userAccount', 'customerTrips'
        ]);
        $trip = $customer ? $this->tripRequestService->findOneBy(
            criteria: ['customer_id' => $id, 'type' => RIDE_REQUEST],
            whereInCriteria: ['current_status' => [ACCEPTED, OUT_FOR_PICKUP, ONGOING]],
            relations: $tripRelations
        ) : null;
        $otherTrips = $customer ? $this->tripRequestService->getBy(
            criteria: ['customer_id' => $id, 'type' => RIDE_REQUEST],
            whereHasRelations: ['safetyAlerts' => ['status' => PENDING, 'sent_by' => $id]],
            relations: $tripRelations
        )->filter(fn($otherTrip) => $otherTrip->id != $trip?->id)->values() : null;

        return ['customer' => $customer, 'trip' => $trip, 'otherTrips' => $otherTrips];
    }

    public function getSingleDriverMarker(int|string $id): array
    {
        $driver = $this->driverService->findOneBy(criteria: ['user_type' => DRIVER, 'id' => $id], relations: [
            'lastLocations',
            'driverTrips' => function ($query) {
                $query->whereIn('current_status', [ACCEPTED, OUT_FOR_PICKUP, ONGOING])
                    ->where('type', RIDE_REQUEST);
            },
        ]);

        return $this->generateMarker($driver, 'driver');
    }

    public function getSingleCustomerMarker(int|string $id): array
    {
        $customer = $this->customerService->findOneBy(criteria: ['user_type' => CUSTOMER, 'id' => $id], relations: [
            'lastLocations',
            'customerTrips' => function ($query) {
                $query->whereIn('current_status', [ACCEPTED, OUT_FOR_PICKUP, ONGOING])
                    ->where('type', RIDE_REQUEST);
            },
        ]);

        return $this->generateMarker($customer);
    }

    private function driverMarkerRelations($markersOnly): array
    {
        $trips = function ($query) use ($markersOnly) {
            $query->whereIn('current_status', [ACCEPTED, OUT_FOR_PICKUP, ONGOING])
                ->where('type', RIDE_REQUEST);
            if (!$markersOnly) {
                $query->with(['safetyAlerts' => function ($safetyAlertQuery) {
                    $safetyAlertQuery->where('status', PENDING);
                }]);
            }
        };
        if ($markersOnly) {
            return ['lastLocations', 'driverTrips' => $trips];
        }
        return ['vehicle.model', 'lastLocations', 'userAccount', 'driverDetails', 'driverTrips' => $trips];
    }

    private function customerMarkerRelations($markersOnly): array
    {
        $trips = function ($query) use ($markersOnly) {
            $query->whereIn('current_status', [ACCEPTED, OUT_FOR_PICKUP, ONGOING])
                ->where('type', RIDE_REQUEST);
            if (!$markersOnly) {
                $query->with(['safetyAlerts' => function ($safetyAlertQuery) {
                    $safetyAlertQuery->where('status', PENDING);
                }]);
            }
        };
        if ($markersOnly) {
            return ['lastLocations', 'customerTrips' => $trips];
        }
        return ['lastLocations', 'userAccount', 'customerTrips' => $trips];
    }

    private function pendingSafetyAlertSet($entities): array
    {
        $ids = $entities->pluck('id')->all();
        if (empty($ids)) {
            return [];
        }
        return array_flip($this->safetyAlertService->getBy(
            criteria: ['status' => PENDING],
            whereInCriteria: ['sent_by' => $ids]
        )->pluck('sent_by')->all());
    }

    private function markerContext($type): array
    {
        return [
            'showUrlTemplate' => route("admin.{$type}.show", ['id' => '__ENTITY_ID__']),
            'tripUrlTemplate' => route('admin.trip.show', ['type' => ALL, 'id' => '__TRIP_ID__', 'page' => 'summary']),
            'iconAlertActive' => dynamicAsset('public/assets/admin-module/img/maps/safety-alert-icon-on-active-trip.png'),
            'iconAlertIdle' => dynamicAsset('public/assets/admin-module/img/maps/safety-alert-icon-on-idle-trip.png'),
            'iconActive' => dynamicAsset('public/assets/admin-module/img/maps/trip-active.png'),
            'iconIdle' => dynamicAsset('public/assets/admin-module/img/maps/trip-idle.png'),
            'shield' => dynamicAsset('public/assets/admin-module/img/svg/shield-red.svg'),
        ];
    }

    private function generateMarker($entity, $type = 'customer', ?array $pendingAlertSet = null, ?array $ctx = null)
    {
        $ctx = $ctx ?? $this->markerContext($type);
        $trips = $type === 'customer' ? $entity?->customerTrips : $entity?->driverTrips;
        $trip = $trips?->first(function ($item) {
            return in_array($item->current_status, [ACCEPTED, OUT_FOR_PICKUP, ONGOING]) && $item->type == RIDE_REQUEST;
        });

        $hasAlert = $pendingAlertSet !== null
            ? isset($pendingAlertSet[$entity?->id])
            : $this->safetyAlertService->getBy(criteria: ['status' => PENDING], whereInCriteria: ['sent_by' => [$entity?->id]])->count() > 0;

        $icon = match (true) {
            $trip && $hasAlert => $ctx['iconAlertActive'],
            !$trip && $hasAlert => $ctx['iconAlertIdle'],
            $trip && !$hasAlert => $ctx['iconActive'],
            default => $ctx['iconIdle'],
        };

        return [
            'id' => $entity?->id,
            'position' => [
                'lat' => $entity?->lastLocations?->latitude ? (double)$entity?->lastLocations?->latitude : 0,
                'lng' => $entity?->lastLocations?->longitude ? (double)$entity?->lastLocations?->longitude : 0,
            ],
            'title' => $entity?->full_name ?? ($entity?->first_name ? $entity?->first_name . ' ' . $entity?->last_name : "N/A"),
            'subtitle' => $trip ? $trip->ref_id : null,
            "{$type}" => $entity?->id ? str_replace('__ENTITY_ID__', $entity?->id, $ctx['showUrlTemplate']) : '#',
            'trip' => $trip ? str_replace('__TRIP_ID__', $trip->id, $ctx['tripUrlTemplate']) : '#',
            'icon' => $icon,
            'safetyAlertIcon' => $hasAlert ? $ctx['shield'] : null,
        ];
    }

    private function generateMarkers($entities, $type = 'customer', ?array $pendingAlertSet = null)
    {
        $ctx = $this->markerContext($type);
        return $entities->map(fn($entity) => $this->generateMarker($entity, $type, $pendingAlertSet, $ctx));
    }
}
