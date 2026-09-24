<?php

namespace Modules\UserManagement\Http\Requests;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DriverStatusUpdateRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

    public function rules(): array
    {
        return [
            'status' => 'required|boolean',
            'pause_duration' => 'required_if:status,false|integer|min:1',
            'pause_duration_type' => 'required_if:status,false|in:hour,day',
            'pause_reason' => 'nullable|string|max:100',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        Toastr::error($validator->errors()->first());

        throw new HttpResponseException(back());
    }
}
