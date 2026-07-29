<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class OtpAndLoginAttemptsStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "temporary_block_time" => "required|numeric|gt:0",
            "otp_resend_time" => "required|numeric|gt:0",
            "maximum_otp_hit" => "required|numeric|gt:0",
            "temporary_login_block_time" => "required|numeric|gt:0",
            "maximum_login_hit" => "required|numeric|gt:0",
        ];
    }

    public function messages()
    {
        return [
            'maximum_otp_hit.required' => translate('Maximum OTP Submit Attempts field is required'),
            'temporary_block_time.required' => translate('Temporary OTP Block Time field is required'),
            'maximum_login_hit.required' => trans('Maximum Login Attempts field is required'),
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

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(['errors' => errorProcessor($validator)]));
    }
}
