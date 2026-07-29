<?php

namespace Modules\BusinessManagement\Lib;

class AdditionalDataFieldNormalizer
{
    public const TYPE_MAP = [
        'check_boxes' => 'checkbox',
        'radio_group' => 'radio',
        'file_upload' => 'file',
    ];

    public const SUPPORTED_TYPES = [
        'text', 'number', 'date', 'email', 'phone',
        'textarea', 'checkbox', 'radio', 'select', 'file',
    ];

    public const PLACEHOLDER_TYPES = ['text', 'number', 'date', 'email', 'phone', 'textarea'];
    public const OPTION_TYPES = ['checkbox', 'radio', 'select'];

    public static function toFieldKey(?string $value): string
    {
        $value = (string) ($value ?? '');
        $normalized = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '_', $value);
        if ($normalized === null) {
            $normalized = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? '';
        }
        $value = $normalized;
        $value = trim($value, '_');

        return mb_strtolower($value, 'UTF-8');
    }

    public static function humanizeFieldKey(?string $key): string
    {
        $key = (string) ($key ?? '');
        $key = trim(str_replace('_', ' ', $key));
        if ($key === '') {
            return '';
        }

        return mb_convert_case($key, MB_CASE_TITLE, 'UTF-8');
    }

    public static function normalizeType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return self::TYPE_MAP[$type] ?? $type;
    }

    public static function normalizeField(array $field): array
    {
        if (isset($field['type'])) {
            $field['type'] = self::normalizeType($field['type']);
        }
        if (isset($field['title']) && is_string($field['title'])) {
            $field['title'] = self::toFieldKey($field['title']);
        }
        $normalized = array_intersect_key($field, array_flip(['id', 'type', 'title', 'is_required']));
        $type = $normalized['type'] ?? null;
        if (in_array($type, self::PLACEHOLDER_TYPES, true) && array_key_exists('placeholder', $field)) {
            $normalized['placeholder'] = $field['placeholder'];
        }

        if (in_array($type, self::OPTION_TYPES, true) && array_key_exists('options', $field)) {
            $normalized['options'] = $field['options'];
        }

        if ($type === 'file') {
            $normalized['file_format'] = array_values($field['file_format'] ?? []);
            $normalized['quantity'] = max(1, min(5, (int) ($field['quantity'] ?? 1)));
        }

        return $normalized;
    }
}
