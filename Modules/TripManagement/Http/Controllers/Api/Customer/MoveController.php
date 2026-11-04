<?php

namespace Modules\TripManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\TripManagement\Entities\MoveDirectoryContact;
use Modules\TripManagement\Entities\MoveDirectoryListing;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Lib\MoveSettings;
use Modules\TripManagement\Service\MoveTripService;
use Modules\TripManagement\Service\TripDepositService;

/**
 * E-Ride Move for the customer: photos of the load before booking, the
 * directory of truck owners, and "We couldn't agree" at the pickup. The move
 * itself is booked through the normal ride endpoints.
 */
class MoveController extends Controller
{
    public function __construct(protected MoveTripService $moves, protected TripDepositService $deposits)
    {
    }

    /** Up to 3 photos of the load, sent before the request so drivers see them while it rings. */
    public function photos(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photos' => 'required|array|min:1|max:' . MoveTripService::MAX_PHOTOS,
            'photos.*' => 'image|mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS) . '|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $names = $this->moves->storePhotos((array)$request->file('photos'));
        return response()->json(responseFormatter(DEFAULT_200, [
            'photos' => $names,
            'urls' => $this->moves->photoUrls($names),
        ]));
    }

    /** Truck owners in the customer's zone, the chosen size first. */
    public function directory(Request $request): JsonResponse
    {
        $settings = MoveSettings::all();
        $zoneId = $request->header('zoneId');
        if (!$settings['status'] || !$settings['directory_status'] || !$zoneId) {
            return response()->json(responseFormatter(DEFAULT_200, []));
        }
        $categoryId = (string)$request->query('vehicle_category_id', '');
        $listings = MoveDirectoryListing::with('vehicleCategory')
            ->where('zone_id', $zoneId)
            ->where('is_active', true)
            ->whereNotNull('consent_at')
            ->get()
            ->sortBy(fn($listing) => [$listing->vehicle_category_id === $categoryId ? 0 : 1, mb_strtolower($listing->owner_name)])
            ->values();
        return response()->json(responseFormatter(DEFAULT_200, $listings->map(fn($listing) => [
            'id' => $listing->id,
            'owner_name' => $listing->owner_name,
            'phone' => $listing->phone,
            'whatsapp' => $listing->whatsapp ?: $listing->phone,
            'areas' => $listing->areas,
            'note' => $listing->note,
            'photo' => $listing->photoUrl(),
            'on_eride' => (bool)$listing->user_id,
            'vehicle_category' => $listing->vehicleCategory ? [
                'id' => $listing->vehicleCategory->id,
                'name' => $listing->vehicleCategory->name,
                'type' => $listing->vehicleCategory->type,
                'image' => $listing->vehicleCategory->image,
            ] : null,
        ])));
    }

    /** The customer tapped Call or WhatsApp on a listing. */
    public function contact(Request $request, string $listingId): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(MoveDirectoryContact::CHANNELS)]]);
        if (!MoveDirectoryListing::whereKey($listingId)->exists()) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        MoveDirectoryContact::create([
            'listing_id' => $listingId,
            'customer_id' => auth('api')->id(),
            'channel' => $data['channel'],
        ]);
        return response()->json(responseFormatter(DEFAULT_200));
    }

    /** "We couldn't agree" on the price at the pickup. */
    public function noAgreement(string $tripId): JsonResponse
    {
        $trip = TripRequest::whereKey($tripId)->where('customer_id', auth('api')->id())->first();
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        try {
            $this->deposits->noAgreement($trip, 'customer');
        } catch (ValidationException $e) {
            return response()->json(responseFormatter(DEFAULT_400, errors: [[
                'error_code' => 'move',
                'message' => collect($e->errors())->flatten()->first(),
            ]]), 403);
        }
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, ['id' => $trip->id, 'current_status' => CANCELLED]));
    }
}
