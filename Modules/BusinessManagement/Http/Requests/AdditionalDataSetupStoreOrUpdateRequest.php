<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Modules\BusinessManagement\Lib\AdditionalDataFieldNormalizer;

class AdditionalDataSetupStoreOrUpdateRequest extends FormRequest
{
    public static function defaultFieldsFor(?string $userType): array
    {
        $common = [
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Password',
            'Confirm Password',
            'Profile Image',
            'Identification Type',
            'Identification Number',
            'Identity Images',
            'Other Documents',
            'Existing Documents',
            'FCM Token',
            'Referral Code',
            'Address',
            'Existing Additional Data',
            'Additional Data'
        ];

        return match ($userType) {
            'driver' => array_merge($common, ['Service', 'Gender',]),
            default => array_merge($common, [
                'Existing Identity Images',
            ]),
        };
    }

    public function rules(): array
    {
        $supportedTypes = AdditionalDataFieldNormalizer::SUPPORTED_TYPES;
        $optionTypes = AdditionalDataFieldNormalizer::OPTION_TYPES;
        $placeholderTypes = AdditionalDataFieldNormalizer::PLACEHOLDER_TYPES;
        $legacyTypes = array_keys(AdditionalDataFieldNormalizer::TYPE_MAP);

        $rules = [
            'user_type' => 'required|in:customer,driver',
            'fields' => 'nullable|array|max:20',
            'fields.*.id' => ['nullable', 'string', 'regex:/^field_\d+$/'],
            'fields.*.type' => ['required', 'string', 'in:' . implode(',', array_merge($supportedTypes, $legacyTypes))],
            'fields.*.title' => 'required|string|max:191',
            'fields.*.is_required' => 'nullable|boolean',
        ];

        $fields = $this->input('fields', []);
        if (is_array($fields)) {
            foreach ($fields as $index => $field) {
                $type = AdditionalDataFieldNormalizer::normalizeType($field['type'] ?? null);

                if (in_array($type, $placeholderTypes, true)) {
                    $rules["fields.$index.placeholder"] = 'required|string|max:191';
                }

                if (in_array($type, $optionTypes, true)) {
                    $rules["fields.$index.options"] = 'required|array|min:2';
                    $rules["fields.$index.options.*"] = 'required|string|max:191';
                }

                if ($type === 'file') {
                    $rules["fields.$index.file_format"] = 'required|array|min:1';
                    $rules["fields.$index.file_format.*"] = 'required|string|in:image,pdf,document';
                    $rules["fields.$index.quantity"] = 'required|integer|min:1|max:5';
                }
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'fields.*.type.required' => translate('The field type is required'),
            'fields.max' => translate('Maximum 20 fields are allowed.') . ' ' . translate('If you want to add a new field, you need to remove field first.'),
            'fields.*.type.in' => translate('The selected field type is invalid'),
            'fields.*.title.required' => translate('The field title is required'),
            'fields.*.placeholder.required' => translate('The placeholder is required for this field type'),
            'fields.*.options.required' => translate('At least two option is required'),
            'fields.*.options.min' => translate('At least two options are required'),
            'fields.*.options.*.required' => translate('Option name cannot be empty'),
            'fields.*.file_format.required' => translate('At least one file format must be selected'),
            'fields.*.file_format.min' => translate('At least one file format must be selected'),
            'fields.*.quantity.required' => translate('The quantity field is required'),
            'fields.*.quantity.max' => translate('The quantity value may not be greater than 5'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $fields = $this->input('fields', []);
            if (!is_array($fields)) {
                return;
            }
            ksort($fields, SORT_NUMERIC);

            $reserved = array_map(
                fn ($title) => AdditionalDataFieldNormalizer::toFieldKey($title),
                self::defaultFieldsFor($this->input('user_type'))
            );

            $seen = [];
            foreach ($fields as $i => $field) {
                $type = AdditionalDataFieldNormalizer::normalizeType($field['type'] ?? null);
                if (in_array($type, AdditionalDataFieldNormalizer::OPTION_TYPES, true) && is_array($field['options'] ?? null)) {
                    $seenOptions = [];
                    foreach ($field['options'] as $optionIndex => $option) {
                        if (!is_string($option) || trim($option) === '') {
                            continue;
                        }

                        $optionKey = AdditionalDataFieldNormalizer::toFieldKey($option);
                        if ($optionKey === '') {
                            $validator->errors()->add(
                                "fields.$i.options.$optionIndex",
                                translate('Please use letters or numbers in the option name.')
                            );
                            continue;
                        }

                        if (isset($seenOptions[$optionKey])) {
                            $validator->errors()->add(
                                "fields.$i.options.$optionIndex",
                                translate('This option name is already used in this field.')
                            );
                            continue;
                        }

                        $seenOptions[$optionKey] = true;
                    }
                }

                $title = $field['title'] ?? null;
                if (!is_string($title) || trim($title) === '') {
                    continue;
                }
                $key = AdditionalDataFieldNormalizer::toFieldKey($title);

                if ($key === '') {
                    $validator->errors()->add(
                        "fields.$i.title",
                        translate('Please use letters or numbers in the field title.')
                    );
                    continue;
                }

                if (in_array($key, $reserved, true)) {
                    $validator->errors()->add(
                        "fields.$i.title",
                        translate('This title is reserved for a default registration field and cannot be used.')
                    );
                    continue;
                }

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "fields.$i.title",
                        translate('This title is already used by another custom field.')
                    );
                    continue;
                }

                $seen[$key] = true;
            }
        });
    }

    public function authorize(): bool
    {
        return Auth::check();
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(['errors' => errorProcessor($validator)]));
    }
}
