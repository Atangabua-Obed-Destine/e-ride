<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerSettingStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type' => 'required|in:wallet,loyalty_point',
            'loyalty_points.status' => 'required_if:loyalty_points,array,on',
            'loyalty_points.value' => 'required_if:loyalty_points,array|gt:0|integer',
            'customer_wallet.add_fund_status' => 'required_if:customer_wallet,array,on',
            'customer_wallet.min_deposit_limit' => 'required_if:customer_wallet,array|gt:0|integer'
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
