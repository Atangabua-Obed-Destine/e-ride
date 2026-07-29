<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LandingOurServicesSectionStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'key_name' => ['required', 'string', Rule::in([INTRO_CONTENTS, 'service_1', 'service_2', 'service_3'])],
            'title' => [
                'required',
                'string',
                Rule::when(
                    $this->key_name == INTRO_CONTENTS,
                    ['max:101'],   // intro
                    ['max:201']    // others
                ),
            ],
            'subtitle' => [Rule::requiredIf(function () { return $this->key_name == INTRO_CONTENTS; } ), 'string', 'max:256'],
            'tab_name' => [Rule::requiredIf(function () { return !($this->key_name == INTRO_CONTENTS); } ), 'string', 'max:20'],
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:on',
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
