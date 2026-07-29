<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FaceVerificationApiStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => 'nullable|in:on',
            'access_key' => 'required',
            'secret_access_key' => 'required',
            'region' => 'required|in:' . implode(',' ,array_keys(AWS_REGIONS))
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
