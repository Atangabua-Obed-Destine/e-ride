<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class DeepLinkingSetupStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $validate = [];

        foreach (['customer_app', 'driver_app'] as $value) {
            $validate[$value . '.package_name'] = 'required|string';
            $validate[$value . '.sha256_fingerprint'] = 'required|string';
            $validate[$value . '.bundle_id'] = 'required|string';
            $validate[$value . '.team_id'] = 'required|string';
        }

        return $validate;
    }

    public function messages()
    {
        $message = [];

        foreach (['customer_app', 'driver_app'] as $value) {
            $message[$value. '.package_name.required'] = translate('The package name of ' . $value . ' is required');
            $message[$value. '.sha256_fingerprint.required'] = translate('The sha256_fingerprint of ' . $value . ' is required');
            $message[$value. '.bundle_id.required'] = translate('The bundle_id of ' . $value . ' is required');
            $message[$value. '.team_id.required'] = translate('The team_id of ' . $value . ' is required');
        }

        return $message;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(['errors' => errorProcessor($validator)]));
    }
}
