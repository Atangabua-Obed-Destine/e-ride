<?php

namespace Modules\AssistantManagement\Lib\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini (generateContent), asked for a JSON answer.
 *
 * Free-tier traffic is the first Google turns away when a model is overloaded
 * (503), so one busy model should not silence the assistant: it tries the
 * admin's model, then light stable models, all inside one time budget that
 * fits the app's 30 second wait. The model that answered last goes first, and
 * a model that just failed rests for a minute.
 */
class GeminiClient implements ChatProvider
{
    public const BACKUP_MODELS = ['gemini-3.5-flash-lite', 'gemini-2.5-flash-lite'];
    public const BUDGET_SECONDS = 25;
    public const TRY_SECONDS = 12;
    protected const GOOD = 'assistant_gemini_good';
    protected const REST = 'assistant_gemini_rest_';

    public function complete(string $system, string $user, string $key, string $model): string
    {
        return $this->ask($system, [['text' => $user]], $key, $model);
    }

    /** Listens to a recording (base64) and answers $instruction about it, as JSON. */
    public function completeAudio(string $system, string $instruction, string $audioBase64, string $mime, string $key, string $model): string
    {
        return $this->ask($system, [['inline_data' => ['mime_type' => $mime, 'data' => $audioBase64]], ['text' => $instruction]], $key, $model);
    }

    protected function ask(string $system, array $parts, string $key, string $model): string
    {
        if ($key === '') {
            throw new ProviderUnavailable('Gemini: no API key', 1800);
        }
        $deadline = microtime(true) + self::BUDGET_SECONDS;
        $first = null;
        foreach ($this->order($model) as $m) {
            $left = (int)floor($deadline - microtime(true));
            if ($left < 4) {
                break;
            }
            try {
                $text = $this->once($system, $parts, $key, $m, min(self::TRY_SECONDS, $left));
                Cache::put(self::GOOD, $m, 600);
                return $text;
            } catch (ProviderUnavailable $e) {
                if (!$e->busy) {
                    // A refused backup (unknown model, say) is skipped; a refused key or quota stops here.
                    if ($m !== $model && str_contains($e->getMessage(), 'request refused')) {
                        Cache::put(self::REST . $m, true, 3600);
                        continue;
                    }
                    throw $e;
                }
                Cache::put(self::REST . $m, true, 60);
                $first ??= $e;
            }
        }
        $e = new ProviderUnavailable($first?->getMessage() ?? 'Gemini: all models resting', 20);
        $e->busy = true;
        throw $e;
    }

    /** The admin's model, then the backups; the last one that answered first; resting ones last. */
    protected function order(string $model): array
    {
        $models = $this->models($model);
        $good = Cache::get(self::GOOD);
        if ($good && in_array($good, $models, true)) {
            $models = array_values(array_unique(array_merge([$good], $models)));
        }
        $awake = array_values(array_filter($models, fn($m) => !Cache::has(self::REST . $m)));
        return $awake ?: $models;
    }

    protected function models(string $model): array
    {
        return array_values(array_unique(array_merge([$model], self::BACKUP_MODELS)));
    }

    protected function once(string $system, array $parts, string $key, string $model, int $timeout): string
    {
        try {
            $r = Http::timeout($timeout)->acceptJson()->post(
                'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($key),
                [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => $parts]],
                    'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.3, 'maxOutputTokens' => 1200],
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

    /** Overloaded, slow or empty: worth trying another model. */
    protected static function busy(string $message, int $retryAfter): ProviderUnavailable
    {
        $e = new ProviderUnavailable($message, $retryAfter);
        $e->busy = true;
        return $e;
    }
}
