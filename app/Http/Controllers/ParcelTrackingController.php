<?php

namespace App\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;

class ParcelTrackingController extends BaseController
{
    protected $tripRequestService;

    public function __construct(TripRequestServiceInterface $tripRequestService)
    {
        parent::__construct($tripRequestService);
        $this->tripRequestService = $tripRequestService;
    }

    public function trackingParcel($id)
    {
        $trip = $this->tripRequestService->findOneBy(criteria: ['ref_id' => $id, 'type' => PARCEL],
            relations: ['coordinate', 'customer', 'driver', 'parcel.parcelCategory', 'parcelUserInfo', 'fee', 'tripStatus', 'vehicle.model', 'vehicleCategory']);
        if (!$trip) {
            Toastr::error(translate(TRIP_REQUEST_404['message']));
            return redirect()->route('index');
        }
        return view('landing-page.parcel-tracking', compact('trip'));
    }
}
