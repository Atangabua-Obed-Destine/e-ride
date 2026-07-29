<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LandingOurSolutionsStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'key_name' => 'required|string|in:' . SOLUTIONS,
            'title' => 'required|string|max:51',
            'description' => 'required|string|max:101',
            'image' => 'sometimes|image|mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS) . '|max:' . convertBytesToKiloBytes(maxUploadSize('image'))
        ];
    }

    public function messages()
    {
        return [
            'image.max' => translate(key: 'The Icon/Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')])
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }
}
