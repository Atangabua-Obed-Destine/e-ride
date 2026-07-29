<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LandingGalleryStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'key_name' => ['required', 'string', Rule::in(['card_1', 'card_2'])],
            'title' => ['required', 'string', 'max:51'],
            'subtitle' => ['required', 'string', 'max:151'],
            'image' => [
                'image',
                'mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS),
                'max:' . convertBytesToKiloBytes(maxUploadSize('image'))],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function messages()
    {
        return [
            'image.max' => translate(key: 'The Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')])
        ];
    }

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }
}
