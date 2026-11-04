<?php

namespace Modules\TripManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\TripManagement\Entities\TripDeposit;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Service\TripDepositService;
use Throwable;

/** The customer pays the booking deposit after a driver accepts. */
class TripDepositController extends Controller
{
    public function __construct(protected TripDepositService $deposits)
    {
    }

    /** The deposit of one of the customer's trips (status, time left, cancel terms). */
    public function show(string $tripId): JsonResponse
    {
        $trip = $this->ownTrip($tripId);
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        return response()->json(responseFormatter(DEFAULT_200, [
            'trip_id' => $trip->id,
            'trip_status' => $trip->current_status,
            'deposit' => $this->deposits->present($trip->deposit, $trip),
        ]));
    }

    public function payWithWallet(string $tripId): JsonResponse
    {
        $trip = $this->ownTrip($tripId);
        if (!$trip?->deposit) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        try {
            $deposit = $this->deposits->payWithWallet($trip->deposit, auth('api')->user());
        } catch (ValidationException $e) {
            return $this->problem($e);
        }
        return response()->json(responseFormatter(DEFAULT_200, [
            'trip_id' => $trip->id,
            'deposit' => $this->deposits->present($deposit, $trip),
        ]));
    }

    /** A link for the app's payment webview, for a gateway (MoMo through Fapshi). */
    public function payUrl(Request $request, string $tripId): JsonResponse
    {
        $data = $request->validate(['payment_method' => 'required|string|max:40']);
        $trip = $this->ownTrip($tripId);
        $deposit = $trip?->deposit;
        if (!$deposit) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        if ($deposit->status !== TripDeposit::AWAITING) {
            return response()->json(responseFormatter(DEFAULT_400, errors: [[
                'error_code' => 'deposit',
                'message' => translate('This deposit can no longer be paid.'),
            ]]), 403);
        }
        return response()->json(responseFormatter(DEFAULT_200, [
            'pay_url' => $this->deposits->payUrl($deposit, $data['payment_method']),
        ]));
    }

    /** Opened in the app's webview (signed link, no auth header there). */
    public function pay(Request $request, TripDeposit $deposit): RedirectResponse
    {
        if ($deposit->status !== TripDeposit::AWAITING) {
            return redirect()->route($deposit->status === TripDeposit::PAID ? 'payment-success' : 'payment-fail');
        }
        try {
            return redirect($this->deposits->paymentLink($deposit, (string)$request->query('payment_method', '')));
        } catch (Throwable) {
            return redirect()->route('payment-fail');
        }
    }

    protected function ownTrip(string $tripId): ?TripRequest
    {
        return TripRequest::with(['deposit', 'time', 'coordinate'])
            ->whereKey($tripId)
            ->where('customer_id', auth('api')->id())
            ->first();
    }

    protected function problem(ValidationException $e): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [[
            'error_code' => 'deposit',
            'message' => collect($e->errors())->flatten()->first(),
        ]]), 403);
    }
}
