<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LandingCTAStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type' => ['required', Rule::in(CTA, CTA_IMAGE)],
            'title' => Rule::requiredIf(function () {
                return $this->input('type') === CTA;
            }),
            'sub_title' => Rule::requiredIf(function () {
                return $this->input('type') === CTA;
            }),
            'play_store_user_download_link' => [
                Rule::requiredIf(function () {
                    return $this->input('type') === CTA;
                }),
                'url'
            ],
            'play_store_driver_download_link' => [
                Rule::requiredIf(function () {
                    return $this->input('type') === CTA;
                }),
                'url'
            ],
            'app_store_user_download_link' => [
                Rule::requiredIf(function () {
                    return $this->input('type') === CTA;
                }),
                'url'
            ],
            'app_store_driver_download_link' => [
                Rule::requiredIf(function () {
                    return $this->input('type') === CTA;
                }),
                'url'
            ],
            'image' => [
                'image',
                'mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS),
                'max:' . convertBytesToKiloBytes(maxUploadSize('image'))
            ],
            'background_image' => [
                'image',
                'mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS),
                'max:' . convertBytesToKiloBytes(maxUploadSize('image'))
            ],
        ];
    }

    public function messages()
    {
        return [
            'image.max' => translate(key: 'The Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
            'background_image.max' => translate(key: 'The Background Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
        ];
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
