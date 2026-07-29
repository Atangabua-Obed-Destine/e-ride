<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MaximumParcelWeightStoreOrUpdate extends FormRequest
{
    public function rules()
    {
        return [
            'max_parcel_weight_status' => 'nullable',
            'max_parcel_weight' => 'required_if:max_parcel_weight_status,array|gt:0|numeric',
        ];
    }

    public function authorize()
    {
        return Auth::check();
    }
}
