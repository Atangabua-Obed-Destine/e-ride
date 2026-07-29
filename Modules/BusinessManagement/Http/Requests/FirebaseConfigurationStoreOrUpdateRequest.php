<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FirebaseConfigurationStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'server_key' => 'required',
            'api_key' => 'nullable',
            'auth_domain' => 'nullable',
            'project_id' => 'nullable',
            'storage_bucket' => 'nullable',
            'messaging_sender_id' => 'nullable',
            'app_id' => 'nullable',
            'measurement_id' => 'nullable',
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
