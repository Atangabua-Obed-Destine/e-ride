<?php

namespace Modules\AuthManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegistrationFromSocialStoreRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

    public function rules()
    {
        return [
            'first_name' => 'required',
            'last_name' => 'sometimes',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:8|max:17|unique:users,phone',
            'medium' => 'required|in:google,facebook,apple',
            'referral_code' => 'sometimes',
            'fcm_token' => 'sometimes',
            'refresh_token' => 'sometimes',
        ];
    }

    public function authorize()
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json(
                responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)),
                403
            )
        );
    }
}
