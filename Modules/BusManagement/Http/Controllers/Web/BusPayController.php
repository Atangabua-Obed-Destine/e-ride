<?php

namespace Modules\BusManagement\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusPayment;
use Modules\BusManagement\Service\AgencyService;
use Modules\BusManagement\Service\BusPaymentService;

/**
 * Mobile money for bus tickets, through each agency's own Fapshi account.
 *
 *   webhook  POST bus/fapshi/{webhook_code}       the agency's Fapshi tells us something changed
 *   return   GET  bus/pay/{reference}/return      where Fapshi sends the passenger back
 *   check    GET  bus/pay/{reference}/check       polled by the waiting page
 *   end      GET  bus/pay/{reference}/success|fail   the app's payment screen closes on these
 */
class BusPayController extends Controller
{
    public function __construct(protected BusPaymentService $payments)
    {
    }

    /**
     * The body is only a hint: the state is read again from the agency's Fapshi, so a forged call can at
     * most make us ask again. Always 200, so Fapshi does not count it as failed.
     */
    public function webhook(Request $request, string $code): JsonResponse
    {
        // A branch's own account, or a company account that collects for all its branches.
        $account = BusAgency::where('webhook_code', $code)->first() ?? \Modules\BusManagement\Entities\BusCompany::where('webhook_code', $code)->first();
        if (!$account) {
            return response()->json(['ok' => false]);
        }
        $client = app(AgencyService::class)->fapshiClient($account);
        if (!$client || !$client->webhookSecretMatches($request->header('x-wh-secret'))) {
            Log::warning('Bus Fapshi webhook with a wrong secret ignored', ['account' => $account->id, 'ip' => $request->ip()]);
            return response()->json(['ok' => false]);
        }
        $agencyIds = $account instanceof BusAgency ? [$account->id] : $account->branches()->pluck('id')->all();
        $transId = (string)$request->input('transId', '');
        $externalId = (string)$request->input('externalId', '');
        if ($transId === '' && $externalId === '') {
            return response()->json(['ok' => true, 'known' => false]);
        }
        $payment = BusPayment::whereIn('agency_id', $agencyIds)
            ->when($transId !== '', fn($q) => $q->where('trans_id', $transId), fn($q) => $q->where('external_id', $externalId))
            ->first();
        if (!$payment) {
            return response()->json(['ok' => true, 'known' => false]);
        }
        return response()->json(['ok' => true, 'status' => $this->payments->sync($payment)]);
    }

    public function return(string $reference)
    {
        $payment = BusPayment::where('external_id', $reference)->firstOrFail();
        $status = $this->payments->sync($payment, 3);
        return match ($status) {
            BusPayment::SUCCESSFUL => redirect()->route('bus.pay.end', ['reference' => $reference, 'outcome' => 'success']),
            BusPayment::FAILED, BusPayment::EXPIRED, BusPayment::MISMATCHED => redirect()->route('bus.pay.end', ['reference' => $reference, 'outcome' => 'fail']),
            default => $this->page($payment, 'waiting'),
        };
    }

    /** Asks Fapshi at most every 10 s. */
    public function check(string $reference): JsonResponse
    {
        $payment = BusPayment::where('external_id', $reference)->firstOrFail();
        $status = $this->payments->sync($payment, 10);
        return response()->json([
            'done' => !in_array($status, BusPayment::OPEN, true),
            'next' => route('bus.pay.end', ['reference' => $reference, 'outcome' => $status === BusPayment::SUCCESSFUL ? 'success' : 'fail']),
        ]);
    }

    public function end(string $reference, string $outcome)
    {
        $payment = BusPayment::where('external_id', $reference)->firstOrFail();
        // Booked on the website: back to the booking's page, which shows the ticket or what went wrong.
        $booking = BusBooking::find($payment->booking_id);
        if ($booking && $booking->via === BusBooking::WEB) {
            return redirect()->route('bus.web.booking', ['code' => $booking->code, 'pay' => $outcome === 'success' ? 'done' : 'failed']);
        }
        return $this->page($payment, $outcome === 'success' ? ($payment->failure_reason ? 'late' : 'paid') : 'failed');
    }

    protected function page(BusPayment $payment, string $state)
    {
        $booking = BusBooking::with(['agency', 'departure.destination'])->find($payment->booking_id);
        return response()->view('busmanagement::pay', [
            'state' => $state,
            'payment' => $payment,
            'booking' => $booking,
            'checkUrl' => route('bus.pay.check', ['reference' => $payment->external_id]),
        ]);
    }
}
