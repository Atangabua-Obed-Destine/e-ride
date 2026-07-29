<?php

namespace Modules\VehicleManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VehicleBrandStoreUpdateRequest extends FormRequest
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
            'brand_name' => 'required|string|min:3|max:255|unique:vehicle_brands,name,' . $id,
            'short_desc' => 'required|max:900',
            'brand_logo' => [
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
            'brand_logo.max' => translate(key: 'The Brand Logo must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')])
        ];
    }

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }
}
