<?php

namespace App\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Modules\TripManagement\Entities\TripNavigation;
use Modules\TripManagement\Service\Interfaces\TripNavigationServiceInterface;
use Modules\TripManagement\Transformers\TrackUserResource;
use Modules\UserManagement\Service\Interfaces\UserServiceInterface;

class RealTimeLocationSharingController extends Controller
{
    protected $tripNavigationService;
    protected $userService;

    public function __construct(TripNavigationServiceInterface $tripNavigationService, UserServiceInterface $userService)
    {
        $this->tripNavigationService = $tripNavigationService;
        $this->userService = $userService;
    }

    public function index($userId, $token)
    {
        $user = $this->userService->findOneBy(criteria: ['id' => $userId]);
        if (!$user)
        {
            Toastr::error(
                message: translate('The tracking link is invalid or has expired.'),
                title: translate('Invalid tracking link')
            );

            return redirect('/');
        }

        $tripNavigation = $this->tripNavigationService->findOneBy(['id' => $token], relations: ['trip.vehicleCategory', 'trip.driver', 'trip.vehicle.model', 'trip.customer']);
        if (!$tripNavigation) {
            Toastr::error(
                message: translate('The tracking link is invalid or has expired.'),
                title: translate('Invalid tracking link')
            );

            return redirect('/');
        }

        if ($tripNavigation->trip->current_status != ONGOING) {
            Toastr::error(
                message: translate('This trip is no longer active.'),
                title: translate('Trip not active')
            );

            return redirect('/');
        }

        $tripPolyline = $tripNavigation->trip->encoded_polyline;
        $tripPoints = decodePolyline($tripPolyline);
        $pickupPoint = $tripPoints[0];
        $polyline = $tripNavigation->encoded_polyline;
        $points = decodePolyline($polyline);
        $driverLastLocation = $points[0];
        $destinationPoint = end($points);
        $driver = $tripNavigation->trip->driver;
        $customer = $tripNavigation->trip->customer;
        $tripInfo = [
            'driver_name' => $driver?->first_name . ' ' . $driver?->last_name,
            'driver_profile_image' => $driver->profile_image,
            'customer_name' => $customer?->first_name . ' ' . $customer?->last_name,
            'customer_profile_image' => $customer->profile_image,
            'licence_plate_number' => $tripNavigation->trip->vehicle->licence_plate_number,
            'vehicle_type' => $tripNavigation->trip->vehicleCategory->type,
            'vehicle_image' => $tripNavigation->trip->vehicleCategory->image,
            'vehicle_model' => $tripNavigation->trip->vehicle->model->name
        ];


        return view('track-user', compact('polyline', 'pickupPoint', 'destinationPoint', 'driverLastLocation', 'token', 'user', 'tripInfo'));
    }

    public function updatePolyline($token)
    {
        $tripNavigation = $this->tripNavigationService->findOneBy(
            criteria: ['id' => $token],
            relations: ['trip.driver.lastLocations', 'trip.vehicleCategory', 'trip.vehicle.model'],
            withTrashed: true
        );

        if (!$tripNavigation) {
            return response()->json(responseFormatter(constant: [
                'response_code' => 'trip_navigation_not_found',
                'message' => translate('Tracking information not found.')
            ]), 404);
        }

        if ($tripNavigation->trip->current_status !== 'ongoing') {
            return response()->json(responseFormatter([
                'response_code' => 'trip_not_ongoing',
                'message' => 'Tracking is only available for ongoing trips.'
            ]), 409);
        }

        return response()->json(
            responseFormatter(
                constant: DEFAULT_200,
                content: new TrackUserResource($tripNavigation)
            )
        );
    }
}
