<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmergencyNumberForCallStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'choose_number_type' => 'required|in:phone,telephone,hotline',
            'emergency_govt_number_for_call_init' => Rule::requiredIf(function () {
                return $this->input('choose_number_type') === 'phone' || $this->input('choose_number_type') === 'telephone';
            }),
            'emergency_govt_number_for_call' => Rule::requiredIf(function () {
                return ($this->input('choose_number_type') === 'phone' || $this->input('choose_number_type') === 'telephone') && $this->input('emergency_govt_number_for_call_init');
            }),
            'emergency_govt_number_for_call_hotline' => Rule::requiredIf(function () {
                return $this->input('choose_number_type') === 'hotline';
            }),
            'emergency_other_number_title' => 'array',
            'emergency_other_number_init' => 'array',
            'emergency_other_number' => 'array',
            'emergency_other_number.*' => 'nullable|numeric|distinct',
            'emergency_other_number_title.*' => 'required_with:emergency_other_number_init.*|nullable|string',
            'emergency_other_number_init.*' => 'required_with:emergency_other_number_title.*|nullable|numeric',
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
            'emergency_govt_number_for_call_init.*' => 'The emergency government number is required when the number type is phone or telephone.',
            'emergency_other_number_title.*.required_with' => 'The emergency title is required when the emergency number is provided.',
            'emergency_other_number_title.*.string' => 'The emergency title must be a valid string.',
            'emergency_other_number_init.*.required_with' => 'The emergency number is required when the emergency title is provided.',
            'emergency_other_number_init.*.numeric' => 'The emergency number must be a valid numeric value.',
        ];
    }
}
