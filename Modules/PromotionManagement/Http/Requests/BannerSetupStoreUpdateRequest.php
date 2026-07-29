<?php

namespace Modules\PromotionManagement\Http\Requests;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use JetBrains\PhpStorm\NoReturn;

class BannerSetupStoreUpdateRequest extends FormRequest
{

    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id = $this->id;
        return [
            'banner_title' => 'required|max:255',
            'short_desc' => 'required|max:900',
            'time_period' => 'required',
            'redirect_link' => 'required|max:255',
            'start_date' => 'exclude_if:time_period,all_time|required|after_or_equal:today',
            'end_date' => 'exclude_if:time_period,all_time|required|after_or_equal:start_date',
            'banner_image' => [
                Rule::requiredIf(empty($id)),
                'image',
                'mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS),
                'max:' . convertBytesToKiloBytes(maxUploadSize('image'))]
        ];
    }

    public function messages()
    {
        return [
            'banner_image.max' => translate('The Banner Image must be less than ' . readableUploadMaxFileSize('image'))
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
