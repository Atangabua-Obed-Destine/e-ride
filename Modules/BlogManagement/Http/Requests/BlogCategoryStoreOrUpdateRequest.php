<?php

namespace Modules\BlogManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class BlogCategoryStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $id = $this->id ?? null;
        return [
            'name' => ['required', 'string', 'max:101', Rule::unique('blog_categories', 'name')->ignore($id)],
            'id' => 'nullable|exists:blog_categories'
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function failedValidation(Validator $validator)
    {
        $errors = [];
        foreach ($validator->errors()->getMessages() as $index => $error) {
            $errors[] = ['error_code' => $index, 'message' => $error[0]];
        }

        throw new HttpResponseException(response()->json(['errors' => $errors]));
    }
}
