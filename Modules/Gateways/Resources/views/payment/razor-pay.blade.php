@extends('Gateways::payment.layouts.master')

@section('content')

    <div class="razorpay-container">
        <h1 class="text-center">{{ "Please do not refresh this page..." }}</h1>

        <div class="razorpay-button-container">
            <button type="button" id="rzp-button1">Pay</button>
            <button type="button" class="razorpay-cancel-button" id="razorpay-cancel-button">Cancel</button>
        </div>
    </div>

    <script type="text/javascript">
        "use strict";
        document.getElementById('razorpay-cancel-button').onclick = function () {
            window.location.href = '{{ route('razor-pay.cancel', ['payment_id' => $data->id]) }}';
        };
    </script>
@endsection

@push('script')
    <style>
        .razorpay-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            min-height: 100vh;
            gap: 1rem;
        }

        .razorpay-button-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
        }

        .razorpay-button-container button {
            --background-color: 69, 160, 73;
            --background-opacity: .8;
            background-color: rgba(var(--background-color), var(--background-opacity));
            color: white;
            border: none;
            padding: .5rem 2.5rem;
            font-size: .85rem;
            cursor: pointer;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .razorpay-button-container button:last-child {
            --background-color: 235, 20, 20;
        }

        .razorpay-button-container button:hover,
        .razorpay-button-container button:focus {
            outline: none;
            --background-opacity: 1;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        iframe {
            transform: scale(.8) !important;
        }
    </style>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            "use strict";

            const rzpButton = document.getElementById('rzp-button1');
            if (!rzpButton) {
                console.error("Button with ID 'rzp-button1' not found!");
                return;
            }

            const contact = String(@json($payer?->phone ?? '')).replace(/^\+/, '');

            const options = {
                key: @json($api_key),
                amount: @json($amount),
                currency: @json($currency),
                order_id: @json($order_id),
                name: @json(mb_substr((string) $business_name, 0, 30)),
                description: @json((string) $data->payment_amount),
                @if (!empty($business_logo))
                image: @json($business_logo),
                @endif

                // UPI Intent support inside mobile WebViews. The host app must
                // override shouldOverrideUrlLoading for intent:// / upi:// URLs.
                webview_intent: true,

                // Confirm via server-side redirect — UPI Intent often does not
                // fire the JS handler reliably in WebViews, so we rely on this.
                callback_url: @json(route('razor-pay.callback', ['payment_request_id' => $data->id])),
                redirect: true,

                prefill: {
                    name: @json($payer?->name ?? ''),
                    email: @json($payer?->email ?? ''),
                    contact: contact
                },

                theme: {
                    color: "#ff7529"
                },

                modal: {
                    ondismiss: function () {
                        console.log("Razorpay checkout closed.");
                    }
                }
            };

            const rzp = new Razorpay(options);

            rzp.on('payment.failed', function (response) {
                console.error("Razorpay payment failed:", response.error);
                window.location.href = '{{ route('razor-pay.cancel', ['payment_id' => $data->id]) }}';
            });

            rzpButton.addEventListener('click', function (e) {
                e.preventDefault();
                rzp.open();
            });

            // Best-effort auto-open. Some mobile WebViews require a real user
            // gesture; if that's the case the user can tap the Pay button.
            try {
                rzp.open();
            } catch (err) {
                console.warn("Razorpay auto-open blocked; user must tap Pay.", err);
            }
        });
    </script>
@endpush
