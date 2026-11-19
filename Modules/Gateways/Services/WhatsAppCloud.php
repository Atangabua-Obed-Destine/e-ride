<?php

namespace Modules\Gateways\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp messages from E-Ride's business number, through Meta's WhatsApp Cloud API.
 *
 * Messages that start a conversation must be templates Meta approved (Business Manager > WhatsApp >
 * Message templates). Delivery is told later by Meta's webhook: a message Meta accepted can still fail
 * (the number has no WhatsApp, for one), which the webhook reports as status "failed".
 *
 * Settings (admin E-Ride Bus > Web booking and messages), business_settings key whatsapp_cloud:
 * enabled, phone_number_id, token and app_secret (encrypted), verify_token, api_version.
 */
class WhatsAppCloud
{
    public const KEY = 'whatsapp_cloud';
    public const DEFAULT_VERSION = 'v21.0';

    public static function settings(): array
    {
        $v = businessConfig(self::KEY, self::KEY)?->value;
        $v = is_array($v) ? $v : [];
        $secret = function (?string $value): string {
            if (!filled($value)) {
                return '';
            }
            try {
                return Crypt::decryptString($value);
            } catch (Throwable) {
                return '';
            }
        };
        return [
            'enabled' => (bool)($v['enabled'] ?? false),
            'phone_number_id' => trim((string)($v['phone_number_id'] ?? '')),
            'token' => $secret($v['token'] ?? null),
            'app_secret' => $secret($v['app_secret'] ?? null),
            'verify_token' => trim((string)($v['verify_token'] ?? '')),
            'api_version' => preg_match('/^v\d+\.\d+$/', (string)($v['api_version'] ?? '')) ? $v['api_version'] : self::DEFAULT_VERSION,
        ];
    }

    /** Saves the settings; an empty token or app secret keeps the one saved. */
    public static function save(array $input): void
    {
        $old = businessConfig(self::KEY, self::KEY)?->value;
        $old = is_array($old) ? $old : [];
        $value = [
            'enabled' => !empty($input['enabled']),
            'phone_number_id' => preg_replace('/\D+/', '', (string)($input['phone_number_id'] ?? '')),
            'token' => filled($input['token'] ?? null) ? Crypt::encryptString(trim($input['token'])) : ($old['token'] ?? null),
            'app_secret' => filled($input['app_secret'] ?? null) ? Crypt::encryptString(trim($input['app_secret'])) : ($old['app_secret'] ?? null),
            'verify_token' => filled($old['verify_token'] ?? null) ? $old['verify_token'] : bin2hex(random_bytes(16)),
            'api_version' => preg_match('/^v\d+\.\d+$/', (string)($input['api_version'] ?? '')) ? $input['api_version'] : self::DEFAULT_VERSION,
        ];
        updateBusinessSetting(keyName: self::KEY, settingType: self::KEY, value: $value);
        \App\Lib\BusinessSettingCache::flush();
    }

    public static function ready(): bool
    {
        $s = self::settings();
        return $s['enabled'] && $s['phone_number_id'] !== '' && $s['token'] !== '';
    }

    /** "+237 6 77 12 34 56" to "237677123456". */
    public static function number(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone);
    }

    /**
     * Sends an approved template. $body: the body's {{1}}, {{2}}... in order; $buttonSuffix: the end of
     * the first button's URL (a URL button with a variable), or null.
     * @return array{ok: bool, id?: string, error?: string}
     */
    public function sendTemplate(string $to, string $template, string $language, array $body, ?string $buttonSuffix = null): array
    {
        $s = self::settings();
        if (!self::ready()) {
            return ['ok' => false, 'error' => 'WhatsApp is not connected'];
        }
        $components = [];
        if ($body) {
            $components[] = ['type' => 'body', 'parameters' => array_map(fn($t) => ['type' => 'text', 'text' => self::param($t)], array_values($body))];
        }
        if ($buttonSuffix !== null) {
            $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $buttonSuffix]]];
        }
        try {
            $response = Http::withToken($s['token'])->acceptJson()->timeout(15)
                ->post("https://graph.facebook.com/{$s['api_version']}/{$s['phone_number_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => self::number($to),
                    'type' => 'template',
                    'template' => ['name' => $template, 'language' => ['code' => $language], 'components' => $components],
                ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 250)];
        }
        $id = $response->json('messages.0.id');
        if ($response->successful() && $id) {
            return ['ok' => true, 'id' => (string)$id];
        }
        $error = $response->json('error.message') ?? ('HTTP ' . $response->status());
        $code = $response->json('error.code');
        return ['ok' => false, 'error' => mb_substr(($code ? $code . ': ' : '') . $error, 0, 250)];
    }

    /** Meta's webhook signature: sha256 HMAC of the raw body with the app secret. */
    public static function signatureMatches(string $rawBody, ?string $header): bool
    {
        $secret = self::settings()['app_secret'];
        if ($secret === '' || !$header || !str_starts_with($header, 'sha256=')) {
            return false;
        }
        return hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $secret), $header);
    }

    /** Template parameters cannot hold new lines, tabs or more than 4 spaces in a row. */
    protected static function param(string $text): string
    {
        $text = preg_replace('/[\r\n\t]+/', ' ', $text);
        $text = preg_replace('/ {4,}/', '   ', $text);
        return mb_substr(trim($text), 0, 900);
    }
}
