<?php

namespace Modules\AssistantManagement\Lib\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Google Gemini (generateContent), asked for a JSON answer. */
class GeminiClient implements ChatProvider
{
    public function complete(string $system, string $user, string $key, string $model): string
    {
        if ($key === '') {
            throw new ProviderUnavailable('Gemini: no API key', 1800);
        }
        try {
            $r = Http::timeout(25)->acceptJson()->post(
                'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($key),
                [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                    'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.3, 'maxOutputTokens' => 600],
                ]
            );
        } catch (ConnectionException $e) {
            throw new ProviderUnavailable('Gemini: unreachable', 60);
        }
        if ($r->successful()) {
            $text = data_get($r->json(), 'candidates.0.content.parts.0.text');
            if (!is_string($text) || trim($text) === '') {
                throw new ProviderUnavailable('Gemini: empty answer', 30);
            }
            return $text;
        }
        $message = (string)data_get($r->json(), 'error.message', $r->body());
        if ($r->status() === 429) {
            // A daily free quota used up comes back tomorrow; a per-minute limit in a moment.
            if (stripos($message, 'per day') !== false || stripos($message, 'PerDay') !== false || stripos($message, 'daily') !== false) {
                throw ProviderUnavailable::untilTomorrow('Gemini: daily quota used up');
            }
            throw new ProviderUnavailable('Gemini: rate limited', 120);
        }
        if (in_array($r->status(), [401, 403], true)) {
            throw new ProviderUnavailable('Gemini: key refused (' . $r->status() . ')', 1800);
        }
        if ($r->status() === 400 || $r->status() === 404) {
            throw new ProviderUnavailable('Gemini: request refused: ' . mb_substr($message, 0, 160), 1800);
        }
        throw new ProviderUnavailable('Gemini: error ' . $r->status(), 60);
    }
}
