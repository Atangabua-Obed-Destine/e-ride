<?php

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WithdrawRequestActionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => Rule::in([APPROVED,DENIED,SETTLED,'reverse']),
            'approval_note'=>[
                Rule::requiredIf($this->status == APPROVED),
                'max:1600'
            ],
            'denied_note'=>[
                Rule::requiredIf($this->status == DENIED),
                'max:1600'
            ],
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
