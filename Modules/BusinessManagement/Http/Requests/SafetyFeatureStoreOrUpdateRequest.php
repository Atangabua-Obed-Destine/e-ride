<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SafetyFeatureStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'minimum_delay_time' => 'required|gt:0',
            'time_format' => 'required',
            'safety_feature_active_status' => 'nullable',
            'set_time' => Rule::requiredIf(function () {
                return array_key_exists('safety_feature_active_status', $this->all());
            }),'gt:0',
            'after_trip_complete_time_format' => Rule::requiredIf(function () {
                return array_key_exists('safety_feature_active_status', $this->all());
            }),
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
