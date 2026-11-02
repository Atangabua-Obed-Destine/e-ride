<?php

namespace Modules\Gateways\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Entities\FapshiTransaction;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\Gateways\Services\FapshiPayments;
use Modules\Gateways\Traits\Processor;

/**
 * Mobile money (MTN MoMo, Orange Money) through Fapshi's hosted checkout.
 *
 *   pay      payment/fapshi/pay?payment_id=   opens (or reuses) a checkout link
 *   return   payment/fapshi/return/{ref}      where Fapshi sends the customer back
 *   check    payment/fapshi/check/{ref}       polled by the waiting page
 *   webhook  payment/fapshi/webhook           Fapshi's server-to-server notice
 *
 * None of these URLs contain "success", "fail" or "cancel": the apps' payment
 * webview closes on the first URL that does, so only the final redirect from
 * paymentResponse() may.
 */
class FapshiController extends Controller
{
    use Processor;

    protected FapshiPayments $payments;

    public function __construct()
    {
        // Resolved per request (see callAction), not injected: the router
        // keeps controller instances, and the gateway settings must be read
        // fresh every time.
    }

    public function callAction($method, $parameters)
    {
        $this->payments = app(FapshiPayments::class);
        return parent::callAction($method, $parameters);
    }

    public function pay(Request $request)
    {
        $validator = Validator::make($request->all(), ['payment_id' => 'required|uuid']);
        if ($validator->fails()) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_400, null, $this->errorProcessor($validator)), 400);
        }

        $paymentRequest = PaymentRequest::find($request->input('payment_id'));
        if (!$paymentRequest) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_204), 200);
        }
        if ($paymentRequest->is_paid) {
            return $this->paymentResponse($paymentRequest, 'success');
        }
        if (!$this->payments->client()->isActive() || !$this->payments->client()->isConfigured()) {
            return $this->page($paymentRequest, null, 'unavailable', translate('Mobile money is not available right now. Please choose another way to pay.'));
        }

        $result = $this->payments->start($paymentRequest);
        if (($result['status'] ?? null) === FapshiTransaction::SUCCESSFUL) {
            return $this->paymentResponse($paymentRequest->fresh(), 'success');
        }
        if (isset($result['error'])) {
            return $this->page($paymentRequest, null, 'unavailable', $result['error']);
        }

        return redirect()->away($result['link']);
    }

    public function return(string $reference)
    {
        $transaction = FapshiTransaction::where('external_id', $reference)->firstOrFail();
        $this->payments->sync($transaction, 3);
        $transaction->refresh();
        $paymentRequest = PaymentRequest::find($transaction->payment_request_id);
        if (!$paymentRequest) {
            abort(404);
        }

        return match ($transaction->status) {
            FapshiTransaction::SUCCESSFUL => $this->paymentResponse($paymentRequest, 'success'),
            FapshiTransaction::REFUNDABLE => $this->page($paymentRequest, $transaction, 'refundable'),
            FapshiTransaction::MISMATCHED => $this->page($paymentRequest, $transaction, 'mismatched'),
            FapshiTransaction::FAILED, FapshiTransaction::EXPIRED => $this->page($paymentRequest, $transaction, $transaction->status),
            default => $this->page($paymentRequest, $transaction, 'waiting'),
        };
    }

    /** Polled by the waiting page; asks Fapshi at most every 10 s. */
    public function check(string $reference): JsonResponse
    {
        $transaction = FapshiTransaction::where('external_id', $reference)->firstOrFail();
        $status = $this->payments->sync($transaction, 10);
        return response()->json(['status' => $status, 'done' => !in_array($status, FapshiTransaction::OPEN, true)]);
    }

    /**
     * Fapshi posts { transId, status, externalId, ... } once per change and
     * never retries. The body is only a hint: the state is re-read from the
     * authenticated payment-status endpoint, so a forged call can at most make
     * us ask Fapshi again. Always 200, so Fapshi does not log it as failed.
     */
    public function webhook(Request $request): JsonResponse
    {
        $client = $this->payments->client();
        if ($client->hasWebhookSecret() && !$client->webhookSecretMatches($request->header('x-wh-secret'))) {
            Log::warning('Fapshi webhook with a wrong secret ignored', ['trans_id' => $request->input('transId'), 'ip' => $request->ip()]);
            return response()->json(['ok' => false]);
        }

        $transId = (string)$request->input('transId', '');
        $externalId = (string)$request->input('externalId', '');
        $transaction = FapshiTransaction::query()
            ->when($transId !== '', fn($q) => $q->where('trans_id', $transId))
            ->when($transId === '' && $externalId !== '', fn($q) => $q->where('external_id', $externalId))
            ->when($transId === '' && $externalId === '', fn($q) => $q->whereRaw('1 = 0'))
            ->first();
        if (!$transaction) {
            Log::info('Fapshi webhook for an unknown transaction', ['trans_id' => $transId, 'external_id' => $externalId]);
            return response()->json(['ok' => true, 'known' => false]);
        }

        $status = $this->payments->sync($transaction);
        return response()->json(['ok' => true, 'status' => $status]);
    }

    protected function page(PaymentRequest $paymentRequest, ?FapshiTransaction $transaction, string $state, ?string $message = null)
    {
        $additional = json_decode((string)$paymentRequest->additional_data, true) ?: [];
        $quote = $transaction
            ? ['base' => $transaction->base_amount, 'fee' => $transaction->fee_amount, 'charge' => $transaction->charged_amount]
            : $this->payments->quote($paymentRequest);

        return response()->view('Gateways::payment.fapshi', [
            'state' => $state,
            'message' => $message,
            'transaction' => $transaction,
            'quote' => $quote,
            'businessName' => $additional['business_name'] ?? (businessConfig('business_name')?->value ?: 'E-Ride'),
            'businessLogo' => $additional['business_logo'] ?? null,
            'retryUrl' => route('fapshi.pay', ['payment_id' => $paymentRequest->id]),
            'returnUrl' => $transaction ? route('fapshi.return', ['reference' => $transaction->external_id]) : null,
            'checkUrl' => $transaction ? route('fapshi.check', ['reference' => $transaction->external_id]) : null,
            'leaveUrl' => $this->leaveUrl($paymentRequest, $state === 'refundable' ? 'success' : 'fail'),
        ]);
    }

    /** Where paymentResponse() would send the customer, as a link. */
    protected function leaveUrl(PaymentRequest $paymentRequest, string $flag): string
    {
        if (in_array($paymentRequest->payment_platform, ['web', 'app']) && $paymentRequest->external_redirect_link != null) {
            return $paymentRequest->external_redirect_link . '?payment_status=' . $flag;
        }
        return route('payment-' . $flag);
    }
}
