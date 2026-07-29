<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReferralEarningSettingStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_type' => 'required|in:' . DRIVER . ',' . CUSTOMER,
            'customer_share_code_earning' => [Rule::requiredIf(fn() => $this->user_type == CUSTOMER ?? false), 'numeric','min:0','max:99999999'],
            'customer_first_ride_discount_status' => 'nullable',
            'customer_discount_amount' => [Rule::requiredIf(fn() => $this->customer_first_ride_discount_status ?? false),
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($this->customer_discount_amount_type === 'percentage' && $value > 100)
                    {
                        $fail(translate('The Discount Amount cannot be more than 100 when Discount Type is Percentage'));
                    }
                },
                'max:99999999'],
            'customer_discount_amount_type' => [Rule::requiredIf(fn() => $this->customer_first_ride_discount_status ?? false), 'in:amount,percentage'],
            'customer_discount_validity' => 'nullable|min:0',
            'customer_discount_validity_type' => 'nullable|in:day,week,month,year',
            'driver_share_code_earning' => [Rule::requiredIf(fn() => $this->user_type == DRIVER ?? false), 'numeric','min:0','max:99999999'],
            'driver_use_code_earning' => [Rule::requiredIf(fn() => $this->user_type == DRIVER ?? false), 'numeric','min:0','max:99999999'],
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
