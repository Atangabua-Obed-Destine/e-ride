<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ParcelSettingStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'parcel_return_time_fee_status' => 'nullable',
            'return_time_for_driver' => [Rule::requiredIf(function () {
                return ($this->input('type') === PARCEL_SETTINGS && ($this->customer_referral_earning_status ?? false));
            }), 'integer'],
            'return_time_type_for_driver' => [Rule::requiredIf(function () {
                return ($this->input('type') === PARCEL_SETTINGS && ($this->customer_referral_earning_status ?? false));
            }), 'string'],
            'return_fee_for_driver_time_exceed' => [Rule::requiredIf(function () {
                return ($this->input('type') === PARCEL_SETTINGS && ($this->customer_referral_earning_status ?? false));
            }), 'numeric'],
            'do_not_charge_customer_return_fee' => 'sometimes|in:on'
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
