<?php

namespace Modules\TripManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ParcelRefundStoreApiRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'trip_request_id' => 'required',
            'reason' => 'required',
            'parcel_approximate_price' => 'required|numeric',
            'attachments' => 'required',
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
