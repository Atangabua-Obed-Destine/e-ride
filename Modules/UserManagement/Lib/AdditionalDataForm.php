<?php

namespace Modules\UserManagement\Lib;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use Modules\BusinessManagement\Lib\AdditionalDataFieldNormalizer;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAdditionalInfo;

class AdditionalDataForm
{
    public const STORAGE_PATH = 'user/additional-data/';

    public static function fields(string $userType): array
    {
        $fields = businessConfig($userType . '_additional_registration_form_fields', ADDITIONAL_DATA_SETUP)?->value ?? [];
        if (!is_array($fields)) {
            return [];
        }

        $fields = array_values(array_filter(array_map(
            fn ($field) => is_array($field) ? AdditionalDataFieldNormalizer::normalizeField($field) : null,
            $fields
        ), fn ($field) => is_array($field) && !empty($field['title']) && !empty($field['type'])));

        usort($fields, fn ($firstField, $secondField) => strnatcmp($firstField['id'] ?? '', $secondField['id'] ?? ''));

        return $fields;
    }

    public static function rules(string $userType): array
    {
        $rules = [
            'additional_data' => 'nullable|array',
            'existing_additional_data' => 'nullable|array',
            'existing_additional_data.*' => 'nullable|array',
            'existing_additional_data.*.*' => 'string',
        ];

        foreach (self::fields($userType) as $field) {
            $title = $field['title'];
            $required = (int) ($field['is_required'] ?? 0) === 1 ? 'required' : 'nullable';
            $key = "additional_data.$title";

            switch ($field['type']) {
                case 'text':
                case 'textarea':
                case 'phone':
                    $rules[$key] = "$required|string";
                    break;
                case 'number':
                    $rules[$key] = "$required|numeric";
                    break;
                case 'email':
                    $rules[$key] = "$required|email";
                    break;
                case 'date':
                    $rules[$key] = "$required|date";
                    break;
                case 'radio':
                case 'select':
                    $rules[$key] = "$required|string|in:" . implode(',', array_values($field['options'] ?? []));
                    break;
                case 'checkbox':
                    $rules[$key] = "$required|array";
                    $rules[$key . '.*'] = 'string|in:' . implode(',', array_values($field['options'] ?? []));
                    break;
                case 'file':
                    $formats = array_values($field['file_format'] ?? []);
                    $mimes = self::mimesForFormats($formats);
                    $maxKb = convertBytesToKiloBytes(maxUploadSize(in_array('image', $formats, true) && count($formats) === 1 ? 'image' : 'file'));
                    $rules[$key] = 'nullable|array';
                    $rules[$key . '.*'] = 'file|mimes:' . implode(',', $mimes) . '|max:' . $maxKb;
                    break;
            }
        }

        return $rules;
    }

    public static function messages(?string $userType = null): array
    {
        $messages = [
            'additional_data.*.*.file' => translate('The uploaded file is invalid'),
        ];

        if ($userType === null) {
            $messages['additional_data.*.required'] = translate('The :attribute field is required.');
            return $messages;
        }

        foreach (self::fields($userType) as $field) {
            if (!empty($field['title'])) {
                $messages['additional_data.' . $field['title'] . '.required'] = self::requiredMessage($field['title']);
            }
        }

        return $messages;
    }

    public static function validateFileFields(Validator $validator, string $userType, array $input, array $files): void
    {
        foreach (self::fields($userType) as $field) {
            if (($field['type'] ?? null) !== 'file') {
                continue;
            }

            $title = $field['title'];
            $existing = Arr::wrap(Arr::get($input, "existing_additional_data.$title", []));
            $uploaded = Arr::wrap(Arr::get($files, "additional_data.$title", []));
            $uploaded = array_values(array_filter($uploaded, fn ($file) => $file instanceof UploadedFile));
            $total = count($existing) + count($uploaded);
            $quantity = max(1, min(5, (int) ($field['quantity'] ?? 1)));

            if ((int) ($field['is_required'] ?? 0) === 1 && $total < 1) {
                $validator->errors()->add("additional_data.$title", self::requiredMessage($title));
                continue;
            }

            if ($total > $quantity) {
                $validator->errors()->add(
                    "additional_data.$title",
                    translate('The number of uploaded files exceeds the configured quantity.')
                );
            }
        }
    }

    public static function store(User|Model $user, array $data, string $userType): void
    {
        $payload = is_array($data['additional_data'] ?? null) ? $data['additional_data'] : [];
        $existingPayload = is_array($data['existing_additional_data'] ?? null) ? $data['existing_additional_data'] : [];
        $stored = [];
        $fileFieldTitles = [];

        foreach (self::fields($userType) as $field) {
            $title = $field['title'];
            $type = $field['type'];

            if ($type === 'file') {
                $fileFieldTitles[] = $title;

                if (!Arr::has($payload, $title) && !Arr::has($existingPayload, $title)) {
                    continue;
                }

                $files = Arr::wrap(Arr::get($payload, $title, []));
                $existingFiles = array_values(array_filter(Arr::wrap(Arr::get($existingPayload, $title, []))));
                $newFiles = [];

                foreach ($files as $file) {
                    if (!$file instanceof UploadedFile) {
                        continue;
                    }

                    $newFiles[] = fileUploader(self::STORAGE_PATH, self::uploadFormat($file), $file);
                }

                $stored[$title] = array_values(array_filter(array_merge($existingFiles, $newFiles)));
                continue;
            }

            if (array_key_exists($title, $payload)) {
                $stored[$title] = $payload[$title];
            }
        }

        $additionalInfo = UserAdditionalInfo::withTrashed()->firstOrNew([
            'user_id' => $user->id,
        ]);
        $previousData = is_array($additionalInfo->additional_data) ? $additionalInfo->additional_data : [];
        $removedFiles = self::removedStoredFiles($previousData, $stored, $fileFieldTitles);

        $additionalInfo->additional_data = $stored;
        if ($additionalInfo->exists && $additionalInfo->trashed()) {
            $additionalInfo->restore();
        }

        $additionalInfo->save();
        self::deleteStoredFilesAfterCommit($removedFiles);
    }

    private static function removedStoredFiles(array $previousData, array $storedData, array $fileFieldTitles): array
    {
        $removedFiles = [];

        foreach ($fileFieldTitles as $title) {
            $previousFiles = array_values(array_filter(Arr::wrap($previousData[$title] ?? [])));
            if (empty($previousFiles)) {
                continue;
            }

            $currentFiles = array_values(array_filter(Arr::wrap($storedData[$title] ?? [])));
            $removedFiles = array_merge($removedFiles, array_diff($previousFiles, $currentFiles));
        }

        return array_values(array_unique($removedFiles));
    }

    private static function deleteStoredFiles(array $files): void
    {
        foreach ($files as $file) {
            if (!is_string($file) || $file === '' || str_contains($file, '/') || str_contains($file, '\\')) {
                continue;
            }

            Storage::disk('public')->delete(self::STORAGE_PATH . $file);
        }
    }

    private static function deleteStoredFilesAfterCommit(array $files): void
    {
        if (empty($files)) {
            return;
        }

        if (DB::transactionLevel() < 1) {
            self::deleteStoredFiles($files);
            return;
        }

        DB::afterCommit(fn () => self::deleteStoredFiles($files));
    }

    public static function validateRequest(Request $request, string $userType): array
    {
        $fields = self::fields($userType);
        if (empty($fields)) {
            return [];
        }

        $validator = ValidatorFacade::make($request->all(), self::rules($userType), self::messages($userType));
        $validator->setAttributeNames(self::attributes($fields));
        $validator->after(function (Validator $validator) use ($request, $userType) {
            self::validateFileFields($validator, $userType, $request->all(), $request->allFiles());
        });

        if ($validator->fails()) {
            throw new HttpResponseException(
                response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403)
            );
        }

        return $fields;
    }

    public static function storeFromRequest(User|Model|null $user, Request $request, string $userType): void
    {
        if (!$user) {
            return;
        }

        $payload = $request->input('additional_data', []);
        $payload = is_array($payload) ? $payload : [];
        $storedPayload = [];

        foreach (self::fields($userType) as $field) {
            $title = $field['title'] ?? null;
            if (!is_string($title) || trim($title) === '') {
                continue;
            }

            if (($field['type'] ?? null) === 'file') {
                $files = $request->file('additional_data.' . $title);
                if (!empty($files)) {
                    $storedPayload[$title] = $files;
                }
                continue;
            }

            if (!array_key_exists($title, $payload)) {
                continue;
            }

            $value = $payload[$title];
            if (($field['type'] ?? null) === 'checkbox') {
                if (!is_array($value)) {
                    continue;
                }
                $value = array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
                if (empty($value)) {
                    continue;
                }
            } elseif ($value === null || $value === '') {
                continue;
            }

            $storedPayload[$title] = $value;
        }

        if (empty($storedPayload)) {
            return;
        }

        self::store($user, ['additional_data' => $storedPayload], $userType);
    }

    public static function pruneRemovedFields(User|Model|null $user, string $userType, ?array $fields = null): array
    {
        if (!$user?->additionalInfo) {
            return [];
        }

        $storedData = is_array($user->additionalInfo->additional_data)
            ? $user->additionalInfo->additional_data
            : [];

        $configuredFields = [];
        foreach (($fields ?? self::fields($userType)) as $field) {
            if (is_array($field) && !empty($field['title'])) {
                $configuredFields[$field['title']] = $field;
            }
        }

        $prunedData = self::pruneRemovedOptions(
            array_intersect_key($storedData, $configuredFields),
            $configuredFields
        );
        if ($prunedData !== $storedData) {
            $user->additionalInfo->additional_data = $prunedData;
            $user->additionalInfo->save();
        }

        return $prunedData;
    }

    private static function pruneRemovedOptions(array $storedData, array $configuredFields): array
    {
        foreach ($storedData as $title => $value) {
            $field = $configuredFields[$title] ?? null;
            if (!is_array($field)) {
                continue;
            }

            $type = $field['type'] ?? null;
            if (!in_array($type, ['checkbox', 'radio', 'select'], true)) {
                continue;
            }

            $options = array_values($field['options'] ?? []);
            if ($type === 'checkbox') {
                if (!is_array($value)) {
                    unset($storedData[$title]);
                    continue;
                }

                $storedData[$title] = array_values(array_filter(
                    $value,
                    fn ($option) => in_array($option, $options, true)
                ));

                if (empty($storedData[$title])) {
                    unset($storedData[$title]);
                }

                continue;
            }

            if (!in_array($value, $options, true)) {
                unset($storedData[$title]);
            }
        }

        return $storedData;
    }

    public static function attributes(array $fields): array
    {
        $attributes = [];
        foreach ($fields as $field) {
            if (is_array($field) && !empty($field['title'])) {
                $attributes['additional_data.' . $field['title']] = self::fieldLabel($field['title']);
            }
        }

        return $attributes;
    }

    public static function requiredMessage(string $title): string
    {
        return translate(key: 'The {field} field is required.', replace: [
            'field' => self::fieldLabel($title),
        ]);
    }

    public static function fieldLabel(string $title): string
    {
        return AdditionalDataFieldNormalizer::humanizeFieldKey($title);
    }

    public static function mimesForFormats(array $formats): array
    {
        $map = [
            'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            'pdf' => ['pdf'],
            'document' => ['doc', 'docx'],
        ];

        $mimes = [];
        foreach ($formats as $format) {
            if (isset($map[$format])) {
                $mimes = array_merge($mimes, $map[$format]);
            }
        }

        return array_values(array_unique($mimes)) ?: ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx'];
    }

    public static function uploadFormat(UploadedFile $file): string
    {
        return str_starts_with((string) $file->getMimeType(), 'image/')
            ? APPLICATION_IMAGE_FORMAT
            : $file->getClientOriginalExtension();
    }
}
