<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Gateways\Entities\Setting;

class PaymentConfigSetupStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if($this['gateway'] == 'paymob_accept' && !$this['supported_country'] && !$this['secret_key']) {
            Setting::updateOrCreate(['key_name' => 'paymob_accept', 'settings_type' => 'payment_config'], [
                'key_name' => 'paymob_accept',
                'live_values' => [
                    'gateway' => "",
                    'mode' => "live",
                    'status' => "0",
                    'supported_country' => "",
                    'public_key' => "",
                    'secret_key' => "",
                    'integration_id' => "",
                    'hmac' => "",
                ],
                'test_values' => [
                    'gateway' => "",
                    'mode' => "test",
                    'status' => "0",
                    'supported_country' => "",
                    'public_key' => "",
                    'secret_key' => "",
                    'integration_id' => "",
                    'hmac' => "",
                ],
                'settings_type' => 'payment_config',
                'mode' => 'test',
                'is_active' => 0 ,
                'additional_data' => null,
            ]);
        }

        return [
            'gateway' => 'required|in:ssl_commerz,sixcash,worldpay,payfast,swish,esewa,maxicash,hubtel,viva_wallet,tap,thawani,moncash,pvit,ccavenue,foloosi,iyzi_pay,xendit,fatoorah,hyper_pay,amazon_pay,paypal,stripe,razor_pay,senang_pay,paytabs,paystack,paymob_accept,paytm,flutterwave,liqpay,bkash,mercadopago,cash_after_service,digital_payment,momo,fapshi',
            'mode' => 'required|in:live,test',
            'gateway_image' => 'nullable|mimes:png|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
            'gateway_title' => Rule::requiredIf(function () {
                return $this->input('status') == 1;
            }),
            'status' => [
                'required_if:gateway,ssl_commerz,sixcash,worldpay,payfast,swish,esewa,maxicash,hubtel,viva_wallet,tap,thawani,moncash,pvit,ccavenue,foloosi,iyzi_pay,xendit,fatoorah,hyper_pay,amazon_pay,paypal,stripe,razor_pay,senang_pay,paytabs,paystack,paymob_accept,paytm,flutterwave,liqpay,bkash,mercadopago,cash_after_service,digital_payment,momo,fapshi',
                Rule::in([1, 0])
            ],
            #fapshi (secrets left blank keep the stored value, see prepareForValidation)
            'api_user' => [
                Rule::requiredIf(fn() => $this->input('status') == 1 && $this->input('gateway') == 'fapshi'),
                'nullable', 'string', 'max:100',
            ],
            'api_key' => [
                Rule::requiredIf(fn() => $this->input('status') == 1 && $this->input('gateway') == 'fapshi'),
                'nullable', 'string', 'max:255',
            ],
            'webhook_secret' => 'nullable|string|max:255',
            'fee_percent' => 'nullable|numeric|min:0|max:20',
            'fee_fixed' => 'nullable|numeric|min:0|max:1000',
            #SSl_Commerz
            'store_id' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'ssl_commerz');
                })
            ],
            'store_password' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'ssl_commerz');
                })
            ],
            #Paypal
            'client_id' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'paypal');
                })
            ],
            'client_secret' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'paypal');
                })
            ],
            #stripe,razor_pay
            'api_key' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 &&
                        ($this->input('gateway') == 'stripe' || $this->input('gateway') == 'razor_pay'));
                })
            ],
            'published_key' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'stripe');
                })
            ],
            'api_secret' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'razor_pay');
                })
            ],
            #senang_pay
            'callback_url' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 &&
                        ($this->input('gateway') == 'senang_pay'));
                })
            ],
            'secret_key' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 &&
                        ($this->input('gateway') == 'senang_pay' || $this->input('gateway') == 'flutterwave'
                            || $this->input('gateway') == 'paystack' || $this->input('gateway') == 'paymob_accept'));
                })
            ],
            'merchant_id' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 &&
                        ($this->input('gateway') == 'senang_pay' || $this->input('gateway') == 'paytm'));
                })
            ],
            #paytabs
            'profile_id' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'paytabs');
                })
            ],
            'server_key' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'paytabs');
                })
            ],
            'base_url' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'paytabs');
                })
            ],
            #paystack
            'public_key' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 &&
                        ($this->input('gateway') == 'paystack' || $this->input('gateway') == 'mercadopago'
                            || $this->input('gateway') == 'liqpay' || $this->input('gateway') == 'flutterwave' || $this->input('gateway') == 'paymob_accept'));
                })
            ],
            'merchant_email' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'paystack');
                })
            ],
            #paymob_accept
            'supported_country' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'paymob_accept');
                })
            ],
            'integration_id' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'paymob_accept');
                })
            ],
            'hmac' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'paymob_accept');
                })
            ],
            #mercadopago
            'access_token' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'mercadopago');
                })
            ],
            #liqpay
            'private_key' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'liqpay');
                })
            ],
            #flutterwave
            'hash' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'flutterwave');
                })
            ],
            #paytm
            'merchant_key' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'paytm');
                })
            ],
            'merchant_website_link' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'paytm');
                })
            ],
            #bkash
            'app_key' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'bkash');
                })
            ],
            'app_secret' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'bkash');
                })
            ],
            'username' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'bkash');
                })
            ],
            'password' => [
                Rule::requiredIf(function () {
                    return ($this->input('status') == 1 && $this->input('gateway') == 'bkash');
                })
            ]
        ];
    }

    public function messages()
    {
        return [
            'gateway_image.max' => translate(key: 'The Gateway Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')])
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());

        if ($this->input('gateway') == 'fapshi') {
            // The key and secret are never sent back to the browser, so an
            // empty field means "keep what is stored".
            $stored = Setting::where(['key_name' => 'fapshi', 'settings_type' => 'payment_config'])->first()?->live_values ?? [];
            $merge = [];
            foreach (['api_key', 'webhook_secret'] as $secret) {
                if (!filled($this->input($secret)) && filled($stored[$secret] ?? null)) {
                    $merge[$secret] = $stored[$secret];
                }
            }
            foreach (['api_user', 'api_key', 'webhook_secret'] as $field) {
                if (is_string($merge[$field] ?? $this->input($field))) {
                    $merge[$field] = trim($merge[$field] ?? $this->input($field));
                }
            }
            $merge['fee_percent'] = $this->input('fee_percent', 0) ?: 0;
            $merge['fee_fixed'] = $this->input('fee_fixed', 0) ?: 0;
            $this->merge($merge);
        }
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('gateway') != 'fapshi' || !filled($this->input('api_key'))) {
                return;
            }
            $client = new \Modules\Gateways\Services\FapshiClient($this->all(), $this->input('mode'));
            if ($looksLike = $client->credentialEnvironmentMismatch()) {
                $validator->errors()->add('api_key', $looksLike === 'sandbox'
                    ? translate(key: 'This is a sandbox key (it starts with {prefix}) but the mode is Live. Paste the live key or switch the mode to Test.', replace: ['prefix' => 'FAK_TEST_'])
                    : translate(key: 'This is a live key but the mode is Test. Paste the sandbox key (it starts with {prefix}) or switch the mode to Live.', replace: ['prefix' => 'FAK_TEST_']));
            }
        });
    }
}
