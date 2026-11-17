<?php

namespace Modules\AssistantManagement\Lib;

use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Ask E-Ride switches, limits and AI providers (admin > Ask E-Ride), with defaults.
 * Providers are tried in order; keys are stored encrypted.
 */
class AssistantSettings
{
    public const TYPE = 'assistant_settings';

    /** Providers this module can call, and the model each starts with (admin can change it). */
    public const PROVIDERS = [
        'gemini' => ['label' => 'Google Gemini', 'model' => 'gemini-flash-latest'],
        'grok' => ['label' => 'xAI Grok', 'model' => 'grok-3-mini'],
    ];

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        $order = array_values(array_filter(explode(',', (string)$get('assistant_order', 'gemini,grok')), fn($p) => isset(self::PROVIDERS[$p])));
        foreach (array_keys(self::PROVIDERS) as $name) {
            if (!in_array($name, $order, true)) {
                $order[] = $name;
            }
        }
        $providers = [];
        foreach ($order as $name) {
            $providers[] = [
                'name' => $name,
                'label' => self::PROVIDERS[$name]['label'],
                'enabled' => (bool)(int)$get('assistant_' . $name . '_enabled', 1),
                'model' => trim((string)$get('assistant_' . $name . '_model', '')) ?: self::PROVIDERS[$name]['model'],
                'key' => self::decrypt((string)$get('assistant_' . $name . '_key', '')),
            ];
        }
        return [
            'enabled' => (bool)(int)$get('assistant_enabled', 0),
            'per_user_daily' => min(200, max(1, (int)$get('assistant_per_user_daily', 20))),
            'app_daily' => min(100000, max(10, (int)$get('assistant_app_daily', 1000))),
            'providers' => $providers,
        ];
    }

    public static function enabled(): bool
    {
        return self::all()['enabled'];
    }

    public static function encrypt(string $key): string
    {
        return $key === '' ? '' : Crypt::encryptString($key);
    }

    protected static function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return '';
        }
    }
}
