<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
            'increase_fare_amount' => Rule::requiredIf(function (){
                return request()->input('increase_fare') === 'on';
            }), 'gt:0|max:100'
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
