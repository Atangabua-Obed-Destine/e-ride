<?php

namespace Modules\PromotionManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SendNotificationStoreUpdateRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:101',
            'description' => 'nullable|string|max:201',
            'targeted_users' => [
                'required',
                'array',
                Rule::in(['customers', 'drivers'])
            ],
            'image' => [
                'image',
                'mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS),
                'max:' . convertBytesToKiloBytes(maxUploadSize('image'))],
        ];
    }

    public function authorize()
    {
        return Auth::check();
    }

    public function messages(): array
    {
        return [
            'image.max' => translate(key: 'The Send Notification Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')])
        ];
    }

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }
}
