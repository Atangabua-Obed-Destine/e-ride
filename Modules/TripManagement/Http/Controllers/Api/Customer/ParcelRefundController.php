<?php

namespace Modules\TripManagement\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Http\Requests\StoreParcelRefundRequest;
use Modules\TripManagement\Service\Interfaces\ParcelRefundServiceInterface;

class ParcelRefundController extends Controller
{
    protected $parcelRefundService;

    public function __construct(ParcelRefundServiceInterface $parcelRefundService)
    {
        $this->parcelRefundService = $parcelRefundService;
    }

    public function createParcelRefundRequest(StoreParcelRefundRequest $request)
    {
        $parcelRefund = $this->parcelRefundService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        if ($parcelRefund) {
            return response()->json(responseFormatter(PARCEL_REFUND_ALREADY_EXIST_200), 403);
        }
        $parcelRefund = $this->parcelRefundService->create(data: $request->all());
        if ($parcelRefund?->tripRequest?->driver?->fcm_token) {
            try {
                $push = getNotification('parcel_amount_deducted');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->driver?->fcm_token,
                    title: translate(key: $push['title'], locale: $parcelRefund?->tripRequest?->driver?->current_language_key),
                    description: textVariableDataFormat(value: $push['description'], parcelId: $parcelRefund?->tripRequest?->ref_id, approximateAmount: getCurrencyFormat($parcelRefund->parcel_approximate_price), locale: $parcelRefund?->tripRequest?->driver?->current_language_key),
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    notification_type: 'parcel',
                    action: $push['action'],
                    user_id: $parcelRefund?->tripRequest?->driver?->id
                );
            } catch (\Exception $exception) {

            }
        }

        return response()->json(responseFormatter(PARCEL_REFUND_CREATE_200), 200);
    }
}
