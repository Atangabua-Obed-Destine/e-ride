<?php

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawMethodStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->id;
        return [
            'method_name' => 'required|unique:withdraw_methods,method_name,' . $id,
            'is_default' => 'nullable',
            'field_type' => ['required', 'array', 'min:1'],
            'field_type.*' => ['required', 'in:string,number,date,password,email,phone'],

            'field_name' => ['required', 'array'],
            'field_name.*' => ['required', 'string', 'max:255'],

            'placeholder_text' => ['required', 'array'],
            'placeholder_text.*' => ['required', 'string', 'max:255'],

            'is_required' => ['sometimes', 'array'],
            'is_required.*' => ['in:1'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
}
