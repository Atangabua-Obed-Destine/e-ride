<?php

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\UserManagement\Lib\AdditionalDataForm;

class UserProfileUpdateApiRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->user()->id;
        $driverRoute = str_contains($this->route()->getPrefix(), 'driver');
        $rules = [
            'first_name' => 'required',
            'last_name' => 'sometimes',
            'email' => [
                Rule::requiredIf(function () use ($driverRoute) {
                    return $driverRoute;
                }),
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'password' => !is_null($this->password) ? 'required|min:8' : 'nullable',
            'confirm_password' => [
                Rule::requiredIf(function (){
                    return $this->password != null;
                }),
                'same:password'],
            'profile_image' => 'image|mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS) . '|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
            'identification_type' => 'in:nid,passport,driving_license',
            'identification_number' => 'sometimes',
            'identity_images' => 'sometimes|array',
            'identity_images.*' => 'image|mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS) . '|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
            'other_documents' => 'sometimes|array',
            'other_documents.*' => 'mimes:'
                . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS)
                . ','
                . str_replace(['.', ' '], '', FILE_ACCEPTED_EXTENSIONS)
                . '|max:' . convertBytesToKiloBytes(maxUploadSize('file')),
            'service' => [
                Rule::requiredIf(function () use ($driverRoute) {
                    return $driverRoute;
                })
            ]
        ];

        if ($driverRoute) {
            $rules['gender'] = 'required|in:' . implode(',', GENDERS);
        }

        return array_merge($rules, AdditionalDataForm::rules($driverRoute ? DRIVER : CUSTOMER));

    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    public function messages()
    {
        return [
            'profile_image.max' => translate(key: 'The Profile Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
            'identity_images.*.max' => translate(key: 'Each Identity Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
            'other_documents.*.max' => translate(key: 'Each document must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
        ];
    }

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }

    protected function failedValidation(Validator $validator)
    {
        $error = $validator->errors()->toArray();
        $key = key($error);
        $message = $error[$key][0] ?? null;
        $fieldName = str_contains($key, '.') ? explode('.', $key)[0] : $key;

        throw new HttpResponseException(response()->json([
            'errors' => [['error_code' => $fieldName, 'message' => $message]],
        ], 403));
    }
}
