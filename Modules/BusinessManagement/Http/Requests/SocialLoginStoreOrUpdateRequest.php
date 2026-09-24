<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class SocialLoginStoreOrUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $hasStoredFile = !empty(businessConfig('apple_login', SOCIAL_LOGIN)?->value['service_file'] ?? null);

        return [
            'name' => 'required|in:apple_login',
            'status' => 'nullable|in:1',
            'bundle_id' => 'required_if:status,1|string',
            'client_secret' => 'required_if:status,1|string',
            'team_id' => 'required_if:status,1|string',
            'service_file' => [
                $hasStoredFile ? 'nullable' : 'required_if:status,1',
                'file',
                'extensions:p8',
                'max:8',
                function ($attribute, $value, $fail) {
                    if ($value instanceof UploadedFile && !str_contains((string) $value->get(), 'PRIVATE KEY')) {
                        $fail(translate('The uploaded file is not a valid AuthKey (.p8) private key file.'));
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'bundle_id.required_if' => translate('The Bundle ID is required when Apple login is active.'),
            'client_secret.required_if' => translate('The Key ID is required when Apple login is active.'),
            'team_id.required_if' => translate('The Team ID is required when Apple login is active.'),
            'service_file.required_if' => translate('The AuthKey (.p8) file is required when Apple login is active.'),
            'service_file.extensions' => translate('The uploaded file must be a .p8 AuthKey file.'),
        ];
    }

    public function authorize(): bool
    {
        return Auth::check();
    }
}
