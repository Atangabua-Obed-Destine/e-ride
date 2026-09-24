<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ScheduleTripStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'minimum_schedule_book_time' => 'required|int|gt:0',
            'minimum_schedule_book_time_type' => 'required|in:minute,hour,day',
            'advance_schedule_book_time' => 'required|int|gt:0',
            'advance_schedule_book_time_type' => 'required|in:minute,hour,day',
            'driver_request_notify_time' => 'required|int|gt:0',
            'driver_request_notify_time_type' => 'required|in:minute,hour,day',
            'increase_fare' => 'nullable|string|in:on',
            'increase_fare_amount' => 'nullable|required_if:increase_fare,on|integer|gt:0|max:100',
        ];
    }

    /**
     * Normalize a blank fare amount to null so the numeric rules skip it when disabled.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('increase_fare_amount')) && trim($this->input('increase_fare_amount')) === '') {
            $this->merge(['increase_fare_amount' => null]);
        }
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'increase_fare_amount.required_if' => translate('Please set the increase fare amount before enabling fare increase.'),
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }
}
