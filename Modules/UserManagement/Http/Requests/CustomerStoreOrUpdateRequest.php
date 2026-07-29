<?php

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Modules\UserManagement\Lib\AdditionalDataForm;

class CustomerStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->id;
        return array_merge([
            'first_name' => 'required',
            'last_name' => 'sometimes',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:8|max:17|unique:users,phone,' . $id,
            'password' => !is_null($this->password) ? 'required|min:8' : 'nullable',
            'confirm_password' => [
                Rule::requiredIf(function (){
                    return $this->password != null;
                }),
                'same:password'],
            'profile_image' => 'nullable|image|mimes:'  . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS) . '|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
            'identification_type' => 'nullable|in:passport,driving_license,nid',
            'identification_number' => 'nullable',
            'identity_images' => 'nullable|array',
            'existing_documents' => 'nullable|array',
            'other_documents' => 'sometimes|array',
            'other_documents.*' => 'mimes:'
                . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS)
                . ','
                . str_replace(['.', ' '], '', FILE_ACCEPTED_EXTENSIONS)
                . '|max:' . convertBytesToKiloBytes(maxUploadSize('file')),
            'identity_images.*' => 'image|mimes:'  . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS) . '|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
            'existing_identity_images' => 'nullable|array',
        ], AdditionalDataForm::rules(CUSTOMER));
    }

    public function messages(): array
    {
        return array_merge([
            'profile_image.max' => translate(key: 'The Driver Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
            'identity_images.*.max' => translate(key: 'Each Identity Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
            'other_documents.*.max' => translate(key: 'Each Document must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('file')]),
        ], AdditionalDataForm::messages(CUSTOMER));
    }

    public function attributes(): array
    {
        return AdditionalDataForm::attributes(AdditionalDataForm::fields(CUSTOMER));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            AdditionalDataForm::validateFileFields($validator, CUSTOMER, $this->all(), $this->allFiles());
        });
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

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }
}
