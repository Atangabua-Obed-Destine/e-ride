<?php

namespace Modules\ParcelManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ParcelCategoryStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->id;
        return [
            'category_name' => 'required|max:255|unique:parcel_categories,name,' . $id,
            'short_desc' => 'required|max:900',
            'category_icon' => [
                Rule::requiredIf(empty($id)),
                'image',
                'mimes:png',
                'max:' . convertBytesToKiloBytes(maxUploadSize('image'))],
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
            'category_icon.max' => translate(key: 'The Parcel Category Icon must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')])
        ];
    }

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }
}
