<?php

namespace Modules\AssistantManagement\Lib\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** xAI Grok (OpenAI-style chat completions), asked for a JSON answer. */
class GrokClient implements ChatProvider
{
    public function complete(string $system, string $user, string $key, string $model): string
    {
        if ($key === '') {
            throw new ProviderUnavailable('Grok: no API key', 1800);
        }
        try {
            $r = Http::timeout(25)->acceptJson()->withToken($key)->post('https://api.x.ai/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.3,
                'max_tokens' => 600,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);
        } catch (ConnectionException $e) {
            throw new ProviderUnavailable('Grok: unreachable', 60);
        }
        if ($r->successful()) {
            $text = data_get($r->json(), 'choices.0.message.content');
            if (!is_string($text) || trim($text) === '') {
                throw new ProviderUnavailable('Grok: empty answer', 30);
            }
            return $text;
        }
        $message = strtolower((string)(data_get($r->json(), 'error.message') ?? data_get($r->json(), 'error') ?? $r->body()));
        // No credits left (or the monthly spending limit reached): back tomorrow at the earliest.
        if ($r->status() === 402 || str_contains($message, 'credit') || str_contains($message, 'spending limit')) {
            throw ProviderUnavailable::untilTomorrow('Grok: out of credits');
        }
        if ($r->status() === 429) {
            throw new ProviderUnavailable('Grok: rate limited', 120);
        }
        if (in_array($r->status(), [401, 403], true)) {
            throw new ProviderUnavailable('Grok: key refused (' . $r->status() . ')', 1800);
        }
        if ($r->status() === 400 || $r->status() === 404) {
            throw new ProviderUnavailable('Grok: request refused: ' . mb_substr($message, 0, 160), 1800);
        }
        throw new ProviderUnavailable('Grok: error ' . $r->status(), 60);
    }
}
