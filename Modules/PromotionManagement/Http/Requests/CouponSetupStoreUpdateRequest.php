<?php

namespace Modules\PromotionManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CouponSetupStoreUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $id = $this->id;
        $data = $this;
        return [
            'coupon_title' => 'required|max:50',
            'short_desc' => 'required|max:900',
            'coupon_code' => [
                Rule::requiredIf(empty($id)),
                'max:30',
                'unique:coupon_setups,coupon_code,' . $id
            ],
            'zone_coupon_type'=>'required|array',
            'customer_level_coupon_type'=>'required|array',
            'customer_coupon_type'=>'required|array',
            'category_coupon_type'=>'required|array',
            'limit_same_user' => 'required|gt:0',
            'coupon_type' => 'required',
            'amount_type' => 'required',
            'coupon' => [
                'required',
                function ($attribute, $value, $fail) use ($data) {
                    $amountType = $data['amount_type'];
                    $minTripAmount = $data['minimum_trip_amount'];
                    $couponAmount = $data['coupon'];
                    if ($amountType === AMOUNT && $value <= 0) {
                        $fail('The coupon amount  value must be gather than 0 ');
                    }
                    if ($amountType === PERCENTAGE && $value <= 0) {
                        $fail('The coupon percent value must be gather than 0 ');
                    }

                    if ($amountType === PERCENTAGE && $value > 100) {
                        $fail('The coupon percent value must be less than 100% ');
                    }
                    if ($amountType !== PERCENTAGE && $couponAmount >= $minTripAmount) {
                        $fail('Coupon amount is not equal or more than minimum trip amount');
                    }
                },
            ],
            'minimum_trip_amount' => 'required|gt:0',
            'max_coupon_amount' => $this->amount_type === PERCENTAGE ? 'required|numeric|gt:0' : '',
            'start_date' => 'required',
            'end_date' => 'required|after_or_equal:today',
            'categories' => 'required_if:coupon_rules,vehicle_category_wise'
        ];
    }

    public function messages(): array
    {
        return [
            'coupon_code.required_if' => translate('The_coupon_code_is_required'),
            'coupon_code.max' => translate('The_coupon_code_must_not_be_greater_than_30_characters.'),
            'coupon_code.unique' => translate('The_coupon_code_has_already_been_taken.'),
            'coupon_rules.required_if' => translate('Please_select_a_coupon_rule.'),
            'coupon_rules.in' => translate('The_selected_coupon_rule_is_invalid.'),
            'categories.required_if' => translate('Please_select_at_least_one_category_for_vehicle_category_wise_coupon_rule.'),
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
