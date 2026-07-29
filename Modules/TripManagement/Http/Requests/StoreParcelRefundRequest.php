<?php

namespace Modules\TripManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreParcelRefundRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'trip_request_id' => 'required',
            'reason' => 'nullable|max:255',
            'parcel_approximate_price' => 'required|numeric',
            'attachments' => 'sometimes|array',
            'attachments.*' => 'mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS) . ',mp4,mkv,avi,mov,webm|max:' . convertBytesToKiloBytes(maxUploadSize('file')),
            'customer_note' => 'nullable|max:255',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function messages()
    {
        return [
            'attachments.*.max' => translate(key: 'Each Identity Image must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
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
