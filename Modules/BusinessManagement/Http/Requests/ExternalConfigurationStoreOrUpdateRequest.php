<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ExternalConfigurationStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "activation_mode" => "nullable",
            "mart_base_url" => [
                Rule::requiredIf(function () {
                    return $this->activation_mode;
                }),
                "url"
            ],
            "mart_token" => [
                Rule::requiredIf(function () {
                    return $this->activation_mode;
                }),
                "min:64",
                "max:64"
            ],
            "system_self_token" => [
                Rule::requiredIf(function () {
                    return $this->activation_mode;
                }),
                "min:64",
                "max:64"
            ]
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
}
