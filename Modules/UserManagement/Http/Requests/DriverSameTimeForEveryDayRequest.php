<?php

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverSameTimeForEveryDayRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'same_time_for_every_day' => 'required|boolean',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'same_time_for_every_day' => filter_var($this->input('same_time_for_every_day'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
