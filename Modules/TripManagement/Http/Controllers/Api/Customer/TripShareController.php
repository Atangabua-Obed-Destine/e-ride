<?php

namespace Modules\TripManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Entities\TrustedContact;
use Modules\TripManagement\Service\TripShareService;

/**
 * Share trip for the customer: the live link of a trip, stop sharing, and the
 * trusted contacts offered in one tap on every trip.
 */
class TripShareController extends Controller
{
    public function __construct(protected TripShareService $shares)
    {
    }

    /** The trip's live link, from the driver's acceptance until it ends. */
    public function share(Request $request, string $tripId): JsonResponse
    {
        $trip = TripRequest::with('parcelUserInfo')->where('customer_id', auth('api')->id())->find($tripId);
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 404);
        }
        if (!TripShareService::enabled()) {
            return $this->refuse('trip_share_off', 'Trip sharing is not available right now.');
        }
        if (!in_array($trip->current_status, TripShareService::LIVE_STATUSES, true)) {
            return $this->refuse('trip_share_not_live', 'You can share the trip once a driver accepts it.');
        }
        if (!$trip->share_token) {
            $this->shares->regenerate($trip);
        }
        $this->shares->markShared($trip);

        $receiver = $trip->type === PARCEL ? $trip->parcelUserInfo->firstWhere('user_type', 'receiver') : null;
        return response()->json(responseFormatter(DEFAULT_200, [
            'url' => $this->shares->url($trip),
            'state' => $this->shares->state($trip),
            'receiver' => $receiver ? ['name' => $receiver->name, 'phone' => $receiver->contact_number] : null,
        ]));
    }

    /** Stop sharing: links already sent stop working. */
    public function stop(string $tripId): JsonResponse
    {
        $trip = TripRequest::where('customer_id', auth('api')->id())->find($tripId);
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 404);
        }
        $this->shares->regenerate($trip);
        return response()->json(responseFormatter(DEFAULT_200, [
            'url' => in_array($trip->current_status, TripShareService::LIVE_STATUSES, true) ? $this->shares->url($trip) : null,
        ]));
    }

    public function contacts(): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, $this->list()));
    }

    public function storeContact(Request $request): JsonResponse
    {
        if (TrustedContact::where('user_id', auth('api')->id())->count() >= TrustedContact::MAX_PER_CUSTOMER) {
            return $this->refuse('trusted_contacts_full', 'You can save up to ' . TrustedContact::MAX_PER_CUSTOMER . ' trusted contacts.');
        }
        $data = $this->validated($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        TrustedContact::create($data + ['user_id' => auth('api')->id()]);
        return response()->json(responseFormatter(DEFAULT_200, $this->list()));
    }

    public function updateContact(Request $request, string $contactId): JsonResponse
    {
        $contact = TrustedContact::where('user_id', auth('api')->id())->find($contactId);
        if (!$contact) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }
        $data = $this->validated($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $contact->update($data);
        return response()->json(responseFormatter(DEFAULT_200, $this->list()));
    }

    public function destroyContact(string $contactId): JsonResponse
    {
        TrustedContact::where('user_id', auth('api')->id())->where('id', $contactId)->delete();
        return response()->json(responseFormatter(DEFAULT_200, $this->list()));
    }

    /** Night sharing: this contact follows every night ride. One contact at a time. */
    public function nightShare(Request $request, string $contactId): JsonResponse
    {
        $contact = TrustedContact::where('user_id', auth('api')->id())->where('id', $contactId)->first();
        if (!$contact) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }
        $on = $request->boolean('on');
        if ($on) {
            TrustedContact::where('user_id', auth('api')->id())->where('id', '!=', $contactId)->update(['night_share' => 0]);
        }
        $contact->update(['night_share' => $on]);
        return response()->json(responseFormatter(DEFAULT_200, $this->list()));
    }

    private function list(): array
    {
        return TrustedContact::where('user_id', auth('api')->id())->orderBy('created_at')->get()
            ->map(fn(TrustedContact $contact) => ['id' => $contact->id, 'name' => $contact->name, 'phone' => $contact->phone,
                'night_share' => (bool)$contact->night_share])
            ->values()->all();
    }

    private function validated(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ]{5,24}$/'],
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        return ['name' => trim($request->input('name')), 'phone' => preg_replace('/\s+/', '', $request->input('phone'))];
    }

    private function refuse(string $code, string $message): JsonResponse
    {
        return response()->json(responseFormatter(constant: ['response_code' => $code, 'message' => translate($message)]), 403);
    }
}
