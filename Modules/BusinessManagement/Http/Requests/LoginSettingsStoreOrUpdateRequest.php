<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class LoginSettingsStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'customer.manual_login' => 'nullable|in:on',
            'customer.otp_login' => 'nullable|in:on',
            'driver.manual_login' => 'nullable|in:on',
            'driver.otp_login' => 'nullable|in:on',
            'driver.biometric_login' => 'nullable|in:on',
            'customer_verification' => 'nullable|in:on',
            'driver_verification' => 'nullable|in:on',
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

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $customer = $this->input('customer', []);
            $driver   = $this->input('driver', []);

            if (empty($customer['manual_login']) && empty($customer['otp_login'])) {
                $validator->errors()->add(
                    'customer',
                    translate('At least one customer login option must be enabled.')
                );
            }

            if (empty($driver['manual_login']) && empty($driver['otp_login']) && empty($driver['biometric_login'])) {
                $validator->errors()->add(
                    'driver',
                   translate('At least one driver login option must be enabled.')
                );
            }

            if (!empty($driver['biometric_login']) && empty($driver['manual_login']) && empty($driver['otp_login'])) {
                $validator->errors()->add(
                    'driver_biometric_login',
                    translate('Manual login or OTP login must be enabled when biometric login is enabled.')
                );
            }
        });
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(['errors' => errorProcessor($validator)]));
    }
}
