<?php

namespace Modules\BlogManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPrioritySetupStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            CATEGORY_SORTING => ['required', 'string', Rule::in(['latest', 'oldest', 'popular', 'a2z', 'z2a'])],
            BLOG_SORTING => ['required', 'string', Rule::in(['latest', 'oldest', 'popular', 'a2z', 'z2a'])],
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
