<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LandingBusinessStatisticsStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'key_name' => 'required|in:total_download,complete_ride,happy_customer,support',
            'title' => 'required|string|max:21',
            'content' => 'required|string|max:41',
            'image' => 'sometimes|image|mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS) . '|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
            'status' => 'nullable|in:on'
        ];
    }

    public function messages()
    {
        return [
            'image.max' => translate(key: 'The Total Download Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
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
