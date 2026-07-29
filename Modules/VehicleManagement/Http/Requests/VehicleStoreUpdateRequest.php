<?php

namespace Modules\VehicleManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VehicleStoreUpdateRequest extends FormRequest
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
            'brand_id' => 'required',
            'model_id' => 'required',
            'category_id' => 'required',
            'licence_plate_number' => 'required|unique:vehicles,licence_plate_number,' . $id,
            'licence_expire_date' => 'required|date',
            'vin_number' => 'nullable|unique:vehicles,vin_number,' . $id,
            'transmission' => 'nullable',
            'parcel_weight_capacity' => 'nullable',
            'fuel_type' => 'required',
            'ownership' => 'required|in:admin,driver',
            'driver_id' => 'required|unique:vehicles,driver_id,' . $id,
            'existing_documents' => 'nullable|array',
            'deleted_documents' => 'nullable|array',
            'other_documents' => 'sometimes|array',
            'other_documents.*' => 'mimes:'
                . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS)
                . ','
                . str_replace(['.', ' '], '', FILE_ACCEPTED_EXTENSIONS)
                . '|max:' . convertBytesToKiloBytes(maxUploadSize('file')),
            'type' => 'nullable'
        ];
    }

    public function messages(): array
    {
        return [
            'other_documents.*.max' => translate(key: 'Each Document must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('file')]),
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
