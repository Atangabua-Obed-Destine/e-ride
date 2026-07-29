<?php

namespace Modules\Gateways\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\Gateways\Traits\Processor;
use Throwable;
use Unicodeveloper\Paystack\Facades\Paystack;

class PaystackController extends Controller
{
    use Processor;

    private PaymentRequest $payment;
    private User $user;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $config = $this->paymentConfig('paystack', PAYMENT_CONFIG);
        $values = false;
        if (!is_null($config) && $config->mode == 'live') {
            $values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $values = json_decode($config->test_values);
        }

        if ($values) {
            $config = array(
                'publicKey' => env('PAYSTACK_PUBLIC_KEY', $values->public_key),
                'secretKey' => env('PAYSTACK_SECRET_KEY', $values->secret_key),
                'paymentUrl' => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),
                'merchantEmail' => env('MERCHANT_EMAIL', $values->merchant_email),
            );
            Config::set('paystack', $config);
        }

        $this->payment = $payment;
        $this->user = $user;
    }

    public function index(Request $request): View|Application|Factory|JsonResponse|\Illuminate\Contracts\Foundation\Application
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_400, null, $this->errorProcessor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($data['payer_information']);

        $reference = Paystack::genTranxRef();
        $data->transaction_id = $reference;
        $data->save();

        return view('Gateways::payment.paystack', compact('data', 'payer', 'reference'));
    }

    public function redirectToGateway(Request $request)
    {
        return Paystack::getAuthorizationUrl()->redirectNow();
    }

    public function handleGatewayCallback(Request $request): Application|JsonResponse|Redirector|\Illuminate\Contracts\Foundation\Application|RedirectResponse
    {
        try {
            $paymentDetails = Paystack::getPaymentData();
        } catch (Throwable) {
            return redirect()->route('payment-fail');
        }

        $paymentData = $paymentDetails['data'] ?? [];
        $payment = $this->resolvePaymentRequest($request, $paymentData);

        if (!isset($payment)) {
            return redirect()->route('payment-fail');
        }

        if (($paymentDetails['status'] ?? false) == true && ($paymentData['status'] ?? null) === 'success') {
            $payment->update([
                'payment_method' => 'paystack',
                'is_paid' => 1,
                'transaction_id' => $paymentData['reference'] ?? $request['trxref'],
            ]);

            if (function_exists($payment->hook)) {
                call_user_func($payment->hook, $payment);
            }
            return $this->paymentResponse($payment, 'success');
        }

        if (function_exists($payment->hook)) {
            call_user_func($payment->hook, $payment);
        }
        return $this->paymentResponse($payment, 'fail');
    }

    private function resolvePaymentRequest(Request $request, array $paymentData): ?PaymentRequest
    {
        $metadata = $paymentData['metadata'] ?? [];
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?: [];
        }

        $paymentId = $request->route('payment_id') ?? $request['payment_id'] ?? $metadata['payment_id'] ?? null;
        if ($paymentId) {
            $payment = $this->payment::where(['id' => $paymentId])->first();
            if (isset($payment)) {
                return $payment;
            }
        }

        $reference = $paymentData['reference'] ?? $request['trxref'] ?? $request['reference'] ?? null;
        if ($reference) {
            $payment = $this->payment::where(['transaction_id' => $reference])->first();
            if (isset($payment)) {
                return $payment;
            }
        }

        $attributeId = $paymentData['orderID'] ?? $metadata['attribute_id'] ?? null;
        if ($attributeId) {
            return $this->payment::where(['attribute_id' => $attributeId])->first();
        }

        return null;
    }
}
