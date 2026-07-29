<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DriverSettingStoreOrUpdateRequest extends FormRequest
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
            'loyalty_points.status' => 'required_if:loyalty_points,array,on',
            'loyalty_points.value' => 'required_if:loyalty_points,array|gt:0|integer',
            'maximum_parcel_request_accept_limit.status' => 'required_if:maximum_parcel_request_accept_limit,array,on',
            'maximum_parcel_request_accept_limit.value' => 'required_if:maximum_parcel_request_accept_limit,array|gt:0|integer'
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
