<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BusinessInfoStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "business_contact_phone" => "nullable",
            "business_contact_email" => "nullable|email",
            "business_support_phone" => "nullable",
            "business_support_email" => "nullable|email",
            "copyright_text" => "nullable|string",
            "business_name" => "nullable|string",
            "business_address" => "nullable|string",
            "trade_licence_number" => "nullable|string",
            "country_code" => "required",
            "currency_symbol_position" => 'nullable|in:left,right',
            "currency_decimal_point" => "nullable|integer|gte:0|lt:11",
            "website_color" => "nullable|array",
            "text_color" => "nullable|array",
            "header_logo" => "nullable|mimes:png|max:" . convertBytesToKiloBytes(maxUploadSize('image')),
            "footer_logo" => "nullable|mimes:png|max:" . convertBytesToKiloBytes(maxUploadSize('image')),
            "favicon" => "nullable|mimes:png|max:" . convertBytesToKiloBytes(maxUploadSize('image')),
            "preloader" => "nullable|mimes:gif|max:" . convertBytesToKiloBytes(maxUploadSize('image')),
            "app_logo" => "nullable|mimes:png|max:" . convertBytesToKiloBytes(maxUploadSize('image')),
            "time_zone" => "nullable|string",
            "time_format" => "nullable|string",
            'currency_code' => 'sometimes'
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

    public function messages()
    {
        return [
            'header_logo' => translate(key: 'The header logo must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
            'footer_logo' => translate(key: 'The footer logo must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
            'favicon' => translate(key: 'The favicon must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
            'preloader' => translate(key: 'The preloader must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
        ];
    }

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }
}
