<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParcelRefundSettingStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'parcel_refund_status' => 'nullable',
            'parcel_refund_validity' => [Rule::requiredIf(function () {
                return ($this->input('type') === PARCEL_SETTINGS && ($this->parcel_refund_status ?? false));
            }), 'integer'],
            'parcel_refund_validity_type' => [Rule::requiredIf(function () {
                return ($this->input('type') === PARCEL_SETTINGS && ($this->parcel_refund_status ?? false));
            }), 'string']
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
