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
            "driver_identity_verification" => "nullable|string|in:on",
            "driver_identity_verification_message" => "nullable|required_if:driver_identity_verification,on|string|max:255",
            "smart_rebooking" => "nullable|string|in:on",
            "auto_arrival_notification" => "nullable|string|in:on",
            "auto_arrival_notification_time" => "nullable|required_if:auto_arrival_notification,on|integer|gt:0",
            "auto_arrival_notification_customer_message" => "nullable|required_if:auto_arrival_notification,on|string|max:255",
            "auto_arrival_notification_driver_message" => "nullable|required_if:auto_arrival_notification,on|string|max:255",
        ];
    }

    /**
     * Custom validation messages for the upcoming-feature toggles.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'driver_identity_verification_message.required_if' => translate('Please add a message before enabling Driver Identity Verification.'),
            'auto_arrival_notification_time.required_if' => translate('Please set the notification time before enabling Auto Arrival Notification.'),
            'auto_arrival_notification_customer_message.required_if' => translate('Please add the customer message before enabling Auto Arrival Notification.'),
            'auto_arrival_notification_driver_message.required_if' => translate('Please add the driver message before enabling Auto Arrival Notification.'),
        ];
    }

    /**
     * Normalize blank optional inputs to null so numeric/in rules skip them.
     */
    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'driver_identity_verification_message',
            'auto_arrival_notification_time',
            'auto_arrival_notification_customer_message',
            'auto_arrival_notification_driver_message',
        ];
        $merge = [];
        foreach ($nullableFields as $field) {
            $value = $this->input($field);
            if (is_string($value) && trim($value) === '') {
                $merge[$field] = null;
            }
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
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
