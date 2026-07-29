<?php

namespace Modules\Gateways\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\Gateways\Traits\Processor;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Throwable;

class RazorPayController extends Controller
{
    use Processor;

    private PaymentRequest $payment;
    private User $user;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $config = $this->paymentConfig('razor_pay', PAYMENT_CONFIG);
        $razor = false;
        if (!is_null($config) && $config->mode == 'live') {
            $razor = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $razor = json_decode($config->test_values);
        }

        if ($razor) {
            $config = array(
                'api_key' => $razor->api_key,
                'api_secret' => $razor->api_secret
            );
            Config::set('razor_config', $config);
        }

        $this->payment = $payment;
        $this->user = $user;
    }

    public function index(Request $request): View|Factory|JsonResponse|Application|Redirector|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_400, null, $this->errorProcessor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id'], 'is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_204), 200);
        }

        $apiKey = config('razor_config.api_key');
        $apiSecret = config('razor_config.api_secret');
        if (!$apiKey || !$apiSecret) {
            return $this->paymentResponse($data, 'fail');
        }

        $payer = json_decode($data['payer_information']);

        $businessName = businessConfig('business_name')?->value ?? "my_business";
        $favicon = businessConfig('favicon')?->value;
        $businessLogo = $favicon ? dynamicStorage('storage/app/public/business') . '/' . $favicon : null;

        $additional = $data['additional_data'] != null ? (json_decode($data['additional_data'], true) ?: []) : [];
        $business_name = $additional['business_name'] ?? $businessName;
        $business_logo = $additional['business_logo'] ?? $businessLogo;

        try {
            $api = new Api($apiKey, $apiSecret);
            $razorpayOrder = $api->order->create([
                'receipt' => 'pr_' . substr(str_replace('-', '', $data->id), 0, 30),
                'amount' => (int) round(((float) $data->payment_amount) * 100),
                'currency' => $data->currency_code,
                'payment_capture' => 1,
            ]);
        } catch (Throwable $e) {
            Log::error('Razorpay order create failed', ['payment_id' => $data->id, 'error' => $e->getMessage()]);
            return $this->paymentResponse($data, 'fail');
        }

        $additional['razorpay_order_id'] = $razorpayOrder['id'];
        $data->additional_data = json_encode($additional);
        $data->save();

        return view('Gateways::payment.razor-pay', [
            'data' => $data,
            'payer' => $payer,
            'business_name' => $business_name,
            'business_logo' => $business_logo,
            'api_key' => $apiKey,
            'order_id' => $razorpayOrder['id'],
            'amount' => $razorpayOrder['amount'],
            'currency' => $razorpayOrder['currency'],
        ]);
    }

    public function callback(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $paymentRequestId = $request->input('payment_request_id', $request->input('payment_id'));
        $data = $this->payment::where('id', $paymentRequestId)->first();

        if (!$data) {
            return redirect()->route('payment-fail');
        }

        $razorpayPaymentId = $request->input('razorpay_payment_id');
        $razorpayOrderId = $request->input('razorpay_order_id');
        $razorpaySignature = $request->input('razorpay_signature');

        if (empty($razorpayPaymentId) || empty($razorpayOrderId) || empty($razorpaySignature)) {
            return $this->paymentResponse($data, 'fail');
        }

        $additional = $data->additional_data ? (json_decode($data->additional_data, true) ?: []) : [];
        $expectedOrderId = $additional['razorpay_order_id'] ?? null;
        if ($expectedOrderId && $expectedOrderId !== $razorpayOrderId) {
            Log::warning('Razorpay order id mismatch', [
                'payment_id' => $data->id,
                'expected' => $expectedOrderId,
                'received' => $razorpayOrderId,
            ]);
            return $this->paymentResponse($data, 'fail');
        }

        try {
            $api = new Api(config('razor_config.api_key'), config('razor_config.api_secret'));
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $razorpaySignature,
            ]);

            $payment = $api->payment->fetch($razorpayPaymentId);
            if (!$payment || ($payment['status'] ?? null) !== 'captured') {
                return $this->paymentResponse($data, 'fail');
            }
        } catch (SignatureVerificationError $e) {
            Log::warning('Razorpay signature verification failed', ['payment_id' => $data->id, 'error' => $e->getMessage()]);
            return $this->paymentResponse($data, 'fail');
        } catch (Throwable $e) {
            Log::error('Razorpay callback error', ['payment_id' => $data->id, 'error' => $e->getMessage()]);
            return $this->paymentResponse($data, 'fail');
        }

        $data->payment_method = 'razor_pay';
        $data->is_paid = 1;
        $data->transaction_id = $razorpayPaymentId;
        $data->save();

        if (isset($data->hook) && function_exists($data->hook)) {
            call_user_func($data->hook, $data);
        }

        return $this->paymentResponse($data, 'success');
    }

    public function cancel(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        return $this->paymentResponse($payment_data, 'fail');
    }
}
