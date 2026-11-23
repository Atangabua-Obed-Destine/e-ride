<?php

namespace Modules\StoreManagement\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StorePayment;
use Modules\StoreManagement\Service\StorePaymentService;

/**
 * Mobile money into a business's own Fapshi account.
 *
 *   webhook  POST store/fapshi/{code}            the business's Fapshi says something changed
 *   return   GET  store/pay/{reference}/return    where Fapshi sends the customer back
 *   check    GET  store/pay/{reference}/check     polled by the waiting page
 *
 * None of these URLs contain "success" or "fail": the app's payment screen closes on E-Ride's
 * payment-success / payment-fail pages, where they end.
 */
class StorePayController extends Controller
{
    public function __construct(protected StorePaymentService $payments)
    {
    }

    /** The body is only a hint: the state is read again from Fapshi. Always 200. */
    public function webhook(Request $request, string $code): JsonResponse
    {
        $brand = Store::where('fapshi_code', $code)->first();
        $client = $brand ? $this->payments->client($brand) : null;
        if (!$client || !$client->webhookSecretMatches($request->header('x-wh-secret'))) {
            Log::warning('Store Fapshi webhook ignored (unknown business or wrong secret)', ['ip' => $request->ip()]);
            return response()->json(['ok' => false]);
        }
        $transId = (string)$request->input('transId', '');
        $externalId = (string)$request->input('externalId', '');
        if ($transId === '' && $externalId === '') {
            return response()->json(['ok' => true, 'known' => false]);
        }
        $payment = StorePayment::where('store_id', $brand->id)
            ->when($transId !== '', fn($q) => $q->where('trans_id', $transId), fn($q) => $q->where('external_id', $externalId))
            ->first();
        return response()->json($payment ? ['ok' => true, 'status' => $this->payments->sync($payment)] : ['ok' => true, 'known' => false]);
    }

    public function return(string $reference)
    {
        $payment = StorePayment::where('external_id', $reference)->firstOrFail();
        $status = $this->payments->sync($payment, 3);
        if (!in_array($status, StorePayment::OPEN, true)) {
            return redirect()->route($status === StorePayment::SUCCESSFUL ? 'payment-success' : 'payment-fail');
        }
        return response()->view('storemanagement::pay-waiting', [
            'checkUrl' => route('store.pay.check', ['reference' => $reference]),
            'store' => $payment->store?->name,
        ]);
    }

    /** Asks Fapshi at most every 10 s. */
    public function check(string $reference): JsonResponse
    {
        $payment = StorePayment::where('external_id', $reference)->firstOrFail();
        $status = $this->payments->sync($payment, 10);
        return response()->json([
            'done' => !in_array($status, StorePayment::OPEN, true),
            'next' => route($status === StorePayment::SUCCESSFUL ? 'payment-success' : 'payment-fail'),
        ]);
    }
}
