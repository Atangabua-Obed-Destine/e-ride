<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\BusinessManagement\Rules\ContainsPlaceholders;

class ParcelTrackingSettingStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'parcel_tracking_status' => 'nullable',
            'parcel_tracking_message' =>
                [Rule::requiredIf(function () {
                    return ($this->input('type') === PARCEL_SETTINGS && ($this->parcel_tracking_status ?? false));
                }), 'string', 'max:201',new ContainsPlaceholders],
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
