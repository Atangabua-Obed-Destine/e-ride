<?php

namespace Modules\TripManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GetEstimatedFaresOrNotRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'pickup_coordinates' => 'required',
            'destination_coordinates' => 'required',
            'pickup_address' => 'required',
            'destination_address' => 'required',
            'type' => 'required|in:parcel,ride_request',
            'parcel_weight' => 'required_if:type,parcel',
            'ride_request_type' => [Rule::requiredIf($this->input('type') === RIDE_REQUEST), 'nullable', Rule::in(['regular', 'scheduled'])],
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

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json(
                responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)),
                403
            )
        );
    }
}
