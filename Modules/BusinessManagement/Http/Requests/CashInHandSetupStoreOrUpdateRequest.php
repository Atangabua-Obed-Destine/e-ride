<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CashInHandSetupStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'max_amount_to_hold_cash' => 'required|numeric|gt:0',
            'min_amount_to_pay' => 'required|numeric|gt:0|lt:' . $this->input('max_amount_to_hold_cash'),
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
