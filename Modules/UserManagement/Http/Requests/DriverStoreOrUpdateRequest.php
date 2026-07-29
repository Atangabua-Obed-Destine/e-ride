<?php

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Modules\UserManagement\Lib\AdditionalDataForm;

class DriverStoreOrUpdateRequest extends FormRequest
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
            'gender' => 'required|in:' . implode(',', GENDERS),
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:8|max:17|unique:users,phone,' . $id,
            'password' => !is_null($this->password) ? 'required|min:8' : 'nullable',
            'confirm_password' => [
                Rule::requiredIf(function (){
                    return $this->password != null;
                }),
                'same:password'],
            'profile_image' => [
                Rule::requiredIf(empty($id)),
                'image',
                'mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS),
                'max:' . convertBytesToKiloBytes(maxUploadSize('image'))
            ],
            'identification_type' => 'required|in:passport,driving_license,nid',
            'identification_number' => 'required',
            'identity_images' => 'array',
            'existing_documents' => 'nullable|array',
            'other_documents' => 'sometimes|array',
            'other_documents.*' => 'mimes:'
                . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS)
                . ','
                . str_replace(['.', ' '], '', FILE_ACCEPTED_EXTENSIONS)
                . '|max:' . convertBytesToKiloBytes(maxUploadSize('file')),
            'identity_images.*' => [
                Rule::requiredIf(empty($id)),
                'image',
                'mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS),
                'max:' . convertBytesToKiloBytes(maxUploadSize('image'))
            ],
        ], AdditionalDataForm::rules(DRIVER));
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

    public function messages(): array
    {
        return array_merge([
            'profile_image.max' => translate(key: 'The Driver Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
            'identity_images.*.max' => translate(key: 'Each Identity Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
            'other_documents.*.max' => translate(key: 'Each Document must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('file')]),
        ], AdditionalDataForm::messages(DRIVER));
    }

    public function attributes(): array
    {
        return AdditionalDataForm::attributes(AdditionalDataForm::fields(DRIVER));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            AdditionalDataForm::validateFileFields($validator, DRIVER, $this->all(), $this->allFiles());
        });
    }

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }
}
