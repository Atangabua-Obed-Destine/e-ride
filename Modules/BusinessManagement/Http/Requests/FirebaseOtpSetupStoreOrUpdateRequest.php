<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FirebaseOtpSetupStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'firebase_otp_verification_status' => 'nullable',
            'firebase_otp_web_api_key' => [
                'nullable',
                Rule::requiredIf(function () {
                    return array_key_exists('firebase_otp_verification_status', $this->request->all());
                }),'string'
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }
}
