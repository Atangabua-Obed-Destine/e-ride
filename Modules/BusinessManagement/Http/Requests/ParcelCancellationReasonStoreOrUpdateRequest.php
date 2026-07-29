<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ParcelCancellationReasonStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $id = $this->id;
        return [
            'title' => 'required|string|max:255|unique:parcel_cancellation_reasons,title,' . $id,
            'cancellation_type' => 'required',
            'user_type' => 'required',
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
