<?php

namespace Modules\BlogManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'blog_category_id' => 'nullable|exists:blog_categories,id',
            'writer' => 'nullable|string|max:101',
            'thumbnail' => [
                Rule::requiredIf(function() use($id) { return !$id;}),
                'image',
                'mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS),
                'max:' . convertBytesToKiloBytes(maxUploadSize('image'))],
            'title' => 'required|string|max:255',
            'description' => ['required', function ($attribute, $value, $fail) {
                if (empty(strip_tags($value))) {
                    $fail(translate('description field can not be empty'));
                }
            }],
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:255',
            'meta_image' => [
                'sometimes',
                'image',
                'mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS),
                'max:' . convertBytesToKiloBytes(maxUploadSize('image'))],
            'remove_meta_image' => 'nullable|boolean',
            'published_at' => 'required|date|date_format:Y-m-d'
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
            'thumbnail.max' => translate(key: 'The Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
            'meta_image.max' => translate(key: 'The Background Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
        ];
    }

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }
}
