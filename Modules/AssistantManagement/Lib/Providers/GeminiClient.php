<?php

namespace Modules\AssistantManagement\Lib\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini (generateContent), asked for a JSON answer.
 *
 * When the chosen model is overloaded (503/500) or too slow, it tries a light
 * stable model once before giving up, so one busy model does not silence the
 * assistant. Both tries together stay inside the app's 30 second wait.
 */
class GeminiClient implements ChatProvider
{
    public const BACKUP_MODEL = 'gemini-3.5-flash-lite';

    public function complete(string $system, string $user, string $key, string $model): string
    {
        if ($key === '') {
            throw new ProviderUnavailable('Gemini: no API key', 1800);
        }
        $backup = $model !== self::BACKUP_MODEL;
        try {
            return $this->once($system, $user, $key, $model, $backup ? 13 : 25);
        } catch (ProviderUnavailable $e) {
            if (!$backup || !$e->busy) {
                throw $e;
            }
            try {
                return $this->once($system, $user, $key, self::BACKUP_MODEL, 13);
            } catch (ProviderUnavailable $second) {
                // A backup that is refused (unknown model, say) should not hide why the main one failed.
                throw $second->busy ? $second : $e;
            }
        }
    }

    protected function once(string $system, string $user, string $key, string $model, int $timeout): string
    {
        try {
            $r = Http::timeout($timeout)->acceptJson()->post(
                'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($key),
                [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                    'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.3, 'maxOutputTokens' => 600],
                ]
            );
        } catch (ConnectionException $e) {
            throw self::busy('Gemini: unreachable (' . $model . ')', 60);
        }
        return $this->read($r, $model);
    }

    protected function read(Response $r, string $model): string
    {
        if ($r->successful()) {
            $text = data_get($r->json(), 'candidates.0.content.parts.0.text');
            if (!is_string($text) || trim($text) === '') {
                throw self::busy('Gemini: empty answer (' . $model . ')', 30);
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
            throw new ProviderUnavailable('Gemini: request refused (' . $model . '): ' . mb_substr($message, 0, 160), 1800);
        }
        throw self::busy('Gemini: error ' . $r->status() . ' (' . $model . ')', 60);
    }

    /** Overloaded, slow or empty: worth trying the backup model. */
    protected static function busy(string $message, int $retryAfter): ProviderUnavailable
    {
        $e = new ProviderUnavailable($message, $retryAfter);
        $e->busy = true;
        return $e;
    }
}
