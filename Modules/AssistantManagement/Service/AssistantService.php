<?php

namespace Modules\AssistantManagement\Service;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\AssistantManagement\Entities\AssistantEntry;
use Modules\AssistantManagement\Entities\AssistantQuestion;
use Modules\AssistantManagement\Lib\AssistantSettings;
use Modules\AssistantManagement\Lib\Providers\ChatProvider;
use Modules\AssistantManagement\Lib\Providers\GeminiClient;
use Modules\AssistantManagement\Lib\Providers\GrokClient;
use Modules\AssistantManagement\Lib\Providers\ProviderUnavailable;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Http\Controllers\Api\Customer\ActivityController;

/**
 * Ask E-Ride. Answers in a line or two from the knowledge pack, the customer's
 * own activity and the services on in their app, and offers buttons into the
 * app from a fixed list. It never acts on its own. Providers are tried in the
 * admin's order; one out of quota or credits is skipped until it comes back.
 */
class AssistantService
{
    /** Buttons the app knows how to open, and the service each needs. */
    public const ACTIONS = [
        'ride' => ['service' => 'ride', 'what' => 'book a ride now'],
        'ride_later' => ['service' => 'ride', 'what' => 'book a ride for later (1 hour to 3 days ahead)'],
        'health_ride' => ['service' => 'health', 'what' => 'urgent ride to a hospital, clinic or pharmacy'],
        'parcel' => ['service' => 'parcel', 'what' => 'send a parcel'],
        'move' => ['service' => 'move', 'what' => 'book a truck to move goods'],
        'food' => ['service' => 'food', 'what' => 'restaurants and shops that deliver'],
        'bus' => ['service' => 'bus', 'what' => 'bus tickets with verified agencies'],
        'hotels' => ['service' => 'hotels', 'what' => 'hotels and guest houses'],
        'explore' => ['service' => 'explore', 'what' => 'places near you: pharmacies, banks, clinics'],
        'market' => ['service' => 'market', 'what' => 'buy and sell'],
        'jobs' => ['service' => 'jobs', 'what' => 'jobs from verified employers'],
        'activity' => ['service' => null, 'what' => "the customer's orders, bookings and cart"],
        'order' => ['service' => 'food', 'what' => 'one food order (needs its id)', 'id' => true],
        'bus_booking' => ['service' => 'bus', 'what' => 'one bus booking (needs its id)', 'id' => true],
        'hotel_chat' => ['service' => 'hotels', 'what' => 'one hotel conversation (needs its id)', 'id' => true],
        'id_verify' => ['service' => null, 'what' => 'get E-Ride ID verified'],
        'support' => ['service' => null, 'what' => 'E-Ride support on WhatsApp'],
    ];

    public const MAX_REPLY = 400;

    /** @var array<string, ChatProvider> */
    protected array $clients;

    public function __construct()
    {
        $this->clients = ['gemini' => new GeminiClient(), 'grok' => new GrokClient()];
    }

    /** For tests: swap a provider's client. */
    public function useClient(string $name, ChatProvider $client): void
    {
        $this->clients[$name] = $client;
    }

    /** Info sheets for the app, by help key, in the customer's language. */
    public function helpSheets(string $lang): array
    {
        return AssistantEntry::where('is_active', true)->whereNotNull('help_key')->orderBy('sort')->get()
            ->map(fn(AssistantEntry $e) => [
                'key' => $e->help_key,
                'title' => $e->title($lang),
                'steps' => array_values(array_filter(array_map('trim', preg_split('/\R/', $e->body($lang))))),
            ])->values()->all();
    }

    /**
     * @param array $services the services switched on in the customer's app (ride, food, bus...)
     * @return array{id: ?string, status: string, reply: string, actions: array}
     */
    public function ask(User $user, string $question, ?string $screen, string $lang, array $services): array
    {
        $settings = AssistantSettings::all();
        $lang = $lang === 'fr' ? 'fr' : 'en';
        $question = self::clean($question);
        $started = microtime(true);

        if (!$settings['enabled']) {
            return $this->finish($user, $question, $screen, $lang, AssistantQuestion::BUSY, null, self::say('off', $lang), [self::support($lang)], $started, false);
        }
        $today = AssistantQuestion::where('created_at', '>=', now()->startOfDay());
        if ((clone $today)->where('user_id', $user->id)->whereIn('status', [AssistantQuestion::ANSWERED, AssistantQuestion::NO_ANSWER])->count() >= $settings['per_user_daily']) {
            return $this->finish($user, $question, $screen, $lang, AssistantQuestion::LIMITED, null, self::say('limit', $lang), [self::support($lang)], $started);
        }
        if ((clone $today)->whereIn('status', [AssistantQuestion::ANSWERED, AssistantQuestion::NO_ANSWER])->count() >= $settings['app_daily']) {
            return $this->finish($user, $question, $screen, $lang, AssistantQuestion::BUSY, null, self::say('busy', $lang), [self::support($lang)], $started);
        }

        $activity = app(ActivityController::class)->itemsFor($user->id);
        $services = array_values(array_intersect($services, array_filter(array_column(self::ACTIONS, 'service'))));
        $system = $this->systemPrompt($services, $activity, $screen);

        foreach ($settings['providers'] as $p) {
            if (!$p['enabled'] || $p['key'] === '' || Cache::has('assistant_skip_' . $p['name'])) {
                continue;
            }
            try {
                $raw = $this->clients[$p['name']]->complete($system, $question, $p['key'], $p['model']);
            } catch (ProviderUnavailable $e) {
                Cache::put('assistant_skip_' . $p['name'], $e->getMessage(), $e->retryAfter);
                Log::info('Ask E-Ride: provider skipped', ['provider' => $p['name'], 'why' => $e->getMessage(), 'seconds' => $e->retryAfter]);
                continue;
            }
            $answer = $this->parse($raw, $services, $activity, $lang);
            if ($answer === null) {
                Log::warning('Ask E-Ride: unreadable answer', ['provider' => $p['name'], 'raw' => mb_substr($raw, 0, 300)]);
                continue;
            }
            return $this->finish($user, $question, $screen, $lang, $answer['answered'] ? AssistantQuestion::ANSWERED : AssistantQuestion::NO_ANSWER,
                $p['name'], $answer['reply'], $answer['actions'], $started);
        }
        return $this->finish($user, $question, $screen, $lang, AssistantQuestion::BUSY, null, self::say('busy', $lang), [self::support($lang)], $started);
    }

    public function feedback(User $user, string $id, bool $helped): bool
    {
        return (bool)AssistantQuestion::where('id', $id)->where('user_id', $user->id)->update(['helped' => $helped]);
    }

    /** Asks one provider a tiny question, for the admin's Test button. */
    public function test(string $name): array
    {
        $p = collect(AssistantSettings::all()['providers'])->firstWhere('name', $name);
        if (!$p) {
            return ['ok' => false, 'message' => 'Unknown provider'];
        }
        $started = microtime(true);
        try {
            $raw = $this->clients[$name]->complete('Answer with JSON only: {"reply": "..."}', 'Say hello in French, in three words.', $p['key'], $p['model']);
            Cache::forget('assistant_skip_' . $name);
            $reply = data_get(json_decode(self::jsonPart($raw), true), 'reply', mb_substr($raw, 0, 80));
            return ['ok' => true, 'message' => 'Answered in ' . round((microtime(true) - $started) * 1000) . ' ms: ' . $reply];
        } catch (ProviderUnavailable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------ prompt

    protected function systemPrompt(array $services, array $activity, ?string $screen): string
    {
        $facts = AssistantEntry::where('is_active', true)->orderBy('sort')->get()
            ->map(fn(AssistantEntry $e) => '## ' . $e->title_en . "\n" . $e->body_en)->implode("\n\n");
        $buttons = collect(self::ACTIONS)
            ->filter(fn($a) => $a['service'] === null || in_array($a['service'], $services, true))
            ->map(fn($a, $type) => '- ' . $type . ': ' . $a['what'])->implode("\n");
        $mine = collect($activity)->map(fn($i) => '- ' . $i['kind'] . ' (id ' . $i['id'] . ', ' . $i['group'] . ')'
            . ($i['deadline'] ? ', deadline ' . Carbon::parse($i['deadline'])->format('H:i') : '')
            . ': ' . json_encode($i['params'], JSON_UNESCAPED_UNICODE))->implode("\n") ?: '- nothing in progress';

        return <<<PROMPT
You are "Ask E-Ride", the helper inside the E-Ride app in Cameroon. You help customers find the right service and understand how things work.

RULES
- Answer only from FACTS and CUSTOMER ACTIVITY below. If they do not cover the question, set "answered" to false and say support can help.
- Never invent prices, times, places, people or promises. You cannot do anything yourself: you only offer buttons that open the app.
- Reply in the customer's language (English, French or Cameroonian Pidgin), in one or two short sentences, at most 280 characters, plain text, no emoji, no markdown.
- Refunds, complaints, lost money, accidents or danger: answered false, and offer the "support" button.
- Offer 0 to 3 buttons, only of the types in BUTTONS. Button labels: 2 to 4 words, in the customer's language. Types order, bus_booking and hotel_chat need the id of an item in CUSTOMER ACTIVITY.
- Answer with JSON only: {"reply": "...", "answered": true, "actions": [{"type": "...", "label": "...", "id": "..."}]}

BUTTONS
{$buttons}

CUSTOMER ACTIVITY (orders, bookings and chats in progress; kinds: food_waiting = restaurant to confirm, food_changes = changes to answer, food_pay = pay now, bus_pay = pay for seats, hotel_reply = reception answered, hotel_kept = room kept)
{$mine}

SCREEN THE CUSTOMER IS ON: {$screen}
TIME NOW: {$this->now()}

FACTS
{$facts}
PROMPT;
    }

    protected function now(): string
    {
        return now()->format('l j F Y, H:i');
    }

    // ------------------------------------------------------------------ answer

    /** The model's JSON, made safe: short text, known buttons only, ids only from this customer's activity. */
    protected function parse(string $raw, array $services, array $activity, string $lang): ?array
    {
        $data = json_decode(self::jsonPart($raw), true);
        if (!is_array($data) || !is_string($data['reply'] ?? null) || trim($data['reply']) === '') {
            return null;
        }
        $reply = mb_substr(trim(strip_tags($data['reply'])), 0, self::MAX_REPLY);
        $answered = ($data['answered'] ?? true) !== false;
        $ids = collect($activity)->mapWithKeys(fn($i) => [$i['id'] => $i['kind']]);
        $actions = [];
        foreach ((array)($data['actions'] ?? []) as $a) {
            $type = is_array($a) ? ($a['type'] ?? null) : null;
            $spec = self::ACTIONS[$type] ?? null;
            if (!$spec || ($spec['service'] !== null && !in_array($spec['service'], $services, true))) {
                continue;
            }
            $id = null;
            if (!empty($spec['id'])) {
                $id = (string)($a['id'] ?? '');
                $kind = $ids->get($id);
                $prefix = ['order' => 'food_', 'bus_booking' => 'bus_', 'hotel_chat' => 'hotel_'][$type];
                if (!$kind || !str_starts_with($kind, $prefix)) {
                    continue;
                }
            }
            $label = mb_substr(trim(strip_tags((string)($a['label'] ?? ''))), 0, 40);
            if ($type === 'support') {
                $actions[] = self::support($lang, $label ?: null);
            } elseif ($label !== '') {
                $actions[] = array_filter(['type' => $type, 'label' => $label, 'id' => $id], fn($v) => $v !== null);
            }
            if (count($actions) === 3) {
                break;
            }
        }
        if (!$answered && !collect($actions)->contains('type', 'support')) {
            $actions[] = self::support($lang);
        }
        return ['reply' => $reply, 'answered' => $answered, 'actions' => array_values($actions)];
    }

    protected function finish(User $user, string $question, ?string $screen, string $lang, string $status, ?string $provider, string $reply, array $actions, float $started, bool $log = true): array
    {
        $id = null;
        if ($log) {
            $q = AssistantQuestion::create([
                'user_id' => $user->id, 'question' => $question, 'screen' => $screen ? mb_substr($screen, 0, 80) : null, 'language' => $lang,
                'status' => $status, 'provider' => $provider, 'reply' => $reply, 'actions' => $actions,
                'ms' => (int)round((microtime(true) - $started) * 1000),
            ]);
            $id = $q->id;
        }
        return ['id' => $id, 'status' => $status, 'reply' => $reply, 'actions' => $actions];
    }

    // ------------------------------------------------------------------ helpers

    /** Phone numbers and emails never reach the AI. */
    public static function clean(string $question): string
    {
        $q = trim(preg_replace('/\s+/u', ' ', strip_tags($question)));
        $q = preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+/u', '[email]', $q);
        $q = preg_replace('/(\+?\d[\d\s\-]{7,}\d)/u', '[phone]', $q);
        return mb_substr($q, 0, 500);
    }

    protected static function jsonPart(string $raw): string
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        return ($start !== false && $end !== false && $end > $start) ? substr($raw, $start, $end - $start + 1) : $raw;
    }

    protected static function support(string $lang, ?string $label = null): array
    {
        return ['type' => 'support', 'label' => $label ?: ($lang === 'fr' ? 'Support WhatsApp' : 'WhatsApp support')];
    }

    protected static function say(string $what, string $lang): string
    {
        $text = [
            'off' => ['en' => 'Ask E-Ride is not available right now. Our support team can help you on WhatsApp.', 'fr' => "Ask E-Ride n'est pas disponible pour le moment. Notre équipe peut vous aider sur WhatsApp."],
            'limit' => ['en' => "You have asked a lot today. Ask again tomorrow, or talk to our support team on WhatsApp.", 'fr' => "Vous avez posé beaucoup de questions aujourd'hui. Revenez demain, ou parlez à notre équipe sur WhatsApp."],
            'busy' => ['en' => 'Ask E-Ride is busy right now. Try again in a moment, or talk to our support team on WhatsApp.', 'fr' => 'Ask E-Ride est occupé pour le moment. Réessayez dans un instant, ou parlez à notre équipe sur WhatsApp.'],
        ];
        return $text[$what][$lang];
    }
}
