<?php

namespace Modules\VehicleManagement\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VehicleApiStoreUpdateRequest extends FormRequest
{
    public function rules()
    {
        $id = $this->id;

        return [
            'brand_id' => 'required',
            'model_id' => 'required',
            'category_id' => 'required',
            'driver_id' => Rule::requiredIf(empty($id)),
            'ownership' => Rule::requiredIf(empty($id)),
            'licence_plate_number' => 'required',
            'licence_expire_date' => 'required|date',
            'vin_number' => 'sometimes',
            'transmission' => 'sometimes',
            'parcel_weight_capacity' => 'sometimes',
            'fuel_type' => Rule::requiredIf(empty($id)),
            'other_documents' => [Rule::requiredIf(empty($id)), 'array'],
            'other_documents.*' => 'mimes:'
                . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS)
                . ','
                . str_replace(['.', ' '], '', FILE_ACCEPTED_EXTENSIONS)
                . '|max:' . convertBytesToKiloBytes(maxUploadSize('file')),
        ];
    }

    public function authorize()
    {
        return Auth::check();
    }

    public function messages()
    {
        return [
            'other_documents.*.max' => translate(key: 'Each document must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
        ];
    }

    public function prepareForValidation()
    {
        if ($this->licence_expire_date) {
            $this->merge([
                'licence_expire_date' => Carbon::parse($this->licence_expire_date)->toDateString(),
            ]);
        }

        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }

    protected function failedValidation(Validator $validator)
    {
        $error = $validator->errors()->toArray();
        $key = key($error);
        $message = $error[$key][0] ?? null;
        $fieldName = str_contains($key, '.') ? explode('.', $key)[0] : $key;

        throw new HttpResponseException(response()->json([
            'errors' => [['error_code' => $fieldName, 'message' => $message]],
        ], 403));
    }

}
