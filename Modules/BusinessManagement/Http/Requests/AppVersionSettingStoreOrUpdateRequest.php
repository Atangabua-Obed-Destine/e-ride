<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AppVersionSettingStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'minimum_customer_app_version_for_android' => 'required|numeric',
            'customer_app_url_for_android' => 'required|url',
            'minimum_customer_app_version_for_ios' => 'required|numeric',
            'customer_app_url_for_ios' => 'required|url',
            'minimum_driver_app_version_for_android' => 'required|numeric',
            'driver_app_url_for_android' => 'required|url',
            'minimum_driver_app_version_for_ios' => 'required|numeric',
            'driver_app_url_for_ios' => 'required|url',
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
