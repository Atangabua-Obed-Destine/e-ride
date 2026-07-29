<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TripFareSettingStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type' => 'required',
            'idle_fee' => [Rule::requiredIf(function () {
                return $this->input('type') === TRIP_FARE_SETTINGS;
            }), 'gt:0'],
            'delay_fee' => [Rule::requiredIf(function () {
                return $this->input('type') === TRIP_FARE_SETTINGS;
            }), 'gt:0'],
            'add_intermediate_points' => "nullable|string|in:on",
            'trip_request_active_time' => [Rule::requiredIf(function () {
                return $this->input('type') === TRIP_SETTINGS;
            }), 'gt:0', 'lte:30'],
            'trip_push_notification' => 'sometimes',
            'bidding_push_notification' => 'sometimes',
            "driver_otp_confirmation_for_trip" => "nullable|string|in:on",
            "enable_real_time_location_sharing" => "nullable|string|in:on",
            "female_only_ride_service" => "nullable|string|in:on",
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
}
