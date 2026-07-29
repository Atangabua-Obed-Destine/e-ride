<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Traits\Payment;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;

class PaymentRecordController extends Controller
{
    use Payment;
    protected $tripRequestService;

    public function __construct( TripRequestServiceInterface $tripRequestService )
    {
        $this->tripRequestService = $tripRequestService;
    }

    public function index(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'payment_method' => 'required|in:ssl_commerz,wallet,cash,stripe,paypal,razor_pay,paystack,senang_pay,paymob_accept,flutterwave,paytm,paytabs,liqpay,mercadopago,bkash,fatoorah,xendit,amazon_pay,iyzi_pay,hyper_pay,foloosi,ccavenue,pvit,moncash,thawani,tap,viva_wallet,hubtel,maxicash,esewa,swish,momo,payfast,worldpay,sixcash,""ssl_commerz,stripe,paypal,razor_pay,paystack,senang_pay,paymob_accept,flutterwave,paytm,paytabs,liqpay,mercadopago,bkash,fatoorah,xendit,amazon_pay,iyzi_pay,hyper_pay,foloosi,ccavenue,pvit,moncash,thawani,tap,viva_wallet,hubtel,maxicash,esewa,swish,momo,payfast,worldpay,sixcash'
        ]);
        if ($validator->fails()) {

            return response()->json(errorProcessor($validator), 400);
        }

        $trip = $this->tripRequestService->findOne(id: $request->trip_request_id,relations: ['customer', 'driver']);
        if (!$trip) {
            return response()->json(['message' => 'trip id not valid'], 403);
        }
        $customer = $trip->customer;

        $payer = new Payer(
            name: $customer?->first_name,
            email: $customer->email,
            phone: $customer->phone,
            address: '');

        $businessLogo = dynamicStorage('storage/app/public/business') . '/' . businessConfig('header_logo')?->value ?? '';
        $businessName = businessConfig('business_name')?->value;

        $additionalData = [
            'business_name' => $businessName,
            'business_logo' => $businessLogo
        ];

        //hook is look for a autoloaded function to perform action after payment
        $paymentInfo = new PaymentInfo(
            hook: 'tripRequestUpdate',
            currencyCode: businessConfig('currency_code')?->value ?? 'USD',
            paymentMethod: $request->payment_method,
            paymentPlatform: 'mono',
            payerId: $customer->id,
            receiverId: '100',
            additionalData: $additionalData,
            paymentAmount: $trip->paid_fare,
            externalRedirectLink: null,
            attribute: 'order',
            attributeId: $request->trip_request_id
        );
        $receiver_info = new Receiver('receiver_name','example.png');

        $redirect_link = $this->generate_link($payer, $paymentInfo, $receiver_info);

        return redirect($redirect_link);
    }

    public function success()
    {
        return response()->json(['message' => 'Payment succeeded'], 200);
    }

    public function fail()
    {
        return response()->json(['message' => 'Payment failed'], 403);
    }

    public function cancel()
    {
        return response()->json(['message' => 'Payment canceled'], 405);
    }

    public function gatewayInactive()
    {
        return response()->json(['message' => 'Gateway inactive'], 405);
    }

}
