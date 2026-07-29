<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BusinessPageStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type' => 'required|in:about_us,privacy_policy,terms_and_conditions,legal,refund_policy',
            'name' => 'nullable',
            'short_description' => 'required|max:900',
            'long_description' => 'required',
            'image' => 'sometimes|mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS) . '|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
        ];
    }

    public function messages()
    {
        return [
            'image.max' => translate(key: 'The Page Banner must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')])
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
    }
}
