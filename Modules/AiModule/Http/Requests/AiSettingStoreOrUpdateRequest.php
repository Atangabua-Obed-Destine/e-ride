<?php

namespace Modules\AiModule\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiSettingStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'ai_name' => 'required|string',
            'api_key' => 'required',
            'organization_id' => 'required',
            'status' => 'sometimes'
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
