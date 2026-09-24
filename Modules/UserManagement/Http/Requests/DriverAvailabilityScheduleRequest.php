<?php

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverAvailabilityScheduleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'day' => 'required|integer|between:0,6',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
