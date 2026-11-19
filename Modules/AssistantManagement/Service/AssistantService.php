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
        'health_ride' => ['service' => 'health', 'what' => 'emergency ride (course d\'urgence): urgent ride to a hospital, clinic or pharmacy'],
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

    /** Buttons the driver app knows how to open. */
    public const DRIVER_ACTIONS = [
        'home' => ['service' => null, 'what' => 'Home, where the driver goes online or offline'],
        'requests' => ['service' => null, 'what' => 'ride requests waiting for a driver'],
        'trips' => ['service' => null, 'what' => "the driver's trip history"],
        'wallet' => ['service' => null, 'what' => 'earnings, balance, cash to pay E-Ride, withdrawals'],
        'withdraw_info' => ['service' => null, 'what' => 'Mobile Money details for withdrawals'],
        'face_verify' => ['service' => null, 'what' => 'verify their face with a selfie'],
        'vehicle' => ['service' => null, 'what' => "the driver's vehicle details"],
        'availability' => ['service' => null, 'what' => 'when the driver is available'],
        'lost_items' => ['service' => null, 'what' => 'items customers left in the car'],
        'safety' => ['service' => null, 'what' => 'safety settings and emergency contacts'],
        'leaderboard' => ['service' => null, 'what' => 'the drivers leaderboard'],
        'support' => ['service' => null, 'what' => 'E-Ride support on WhatsApp'],
    ];

    public const MAX_REPLY = 400;

    /**
     * Buttons a "Did you know?" card may carry: a service, or one Explore
     * category as "explore:<key>" (pharmacy, health, bank, stay, homes...).
     */
    public const TIP_ACTIONS = ['ride', 'ride_later', 'health_ride', 'parcel', 'move', 'food', 'bus', 'hotels', 'explore', 'market', 'jobs', 'id_verify'];

    /** Where an entry's search words lead when it has no tip button: its service's screen. */
    public const SERVICE_ACTIONS = [
        'rides' => 'ride', 'ride' => 'ride', 'parcel' => 'parcel', 'move' => 'move', 'food' => 'food', 'bus' => 'bus',
        'hotels' => 'explore:stay', 'explore' => 'explore', 'market' => 'market', 'jobs' => 'jobs', 'account' => 'id_verify',
    ];

    public static function validTipAction(?string $action): bool
    {
        return $action !== null && (in_array($action, self::TIP_ACTIONS, true) || preg_match('/^explore:[a-z_]{2,30}$/', $action) === 1);
    }

    /** @var array<string, ChatProvider> */
    protected array $clients;

    /** Who is asking: a customer or a driver (each has its own knowledge and buttons). */
    protected string $audience = 'customer';

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
    public function helpSheets(string $lang, string $audience = 'customer'): array
    {
        return AssistantEntry::where('is_active', true)->where('audience', $audience)->whereNotNull('help_key')->orderBy('sort')->get()
            ->map(fn(AssistantEntry $e) => [
                'key' => $e->help_key,
                'title' => $e->title($lang),
                'steps' => array_values(array_filter(array_map('trim', preg_split('/\R/', $e->body($lang))))),
            ])->values()->all();
    }

    /** "Did you know?" cards for Home: a headline, one line, and the button. The app picks which to show. */
    public function tips(string $lang): array
    {
        return AssistantEntry::where('is_active', true)->where('audience', 'customer')->whereNotNull('tip_action')->orderBy('sort')->get()
            ->filter(fn(AssistantEntry $e) => self::validTipAction($e->tip_action))
            ->map(function (AssistantEntry $e) use ($lang) {
                [$type, $id] = array_pad(explode(':', $e->tip_action, 2), 2, null);
                $label = trim((string)($lang === 'fr' ? $e->tip_label_fr : $e->tip_label_en));
                return array_filter([
                    'key' => $e->id,
                    'service' => $e->service,
                    'title' => $e->title($lang),
                    'body' => trim(preg_split('/\R/', trim($e->body($lang)))[0] ?? ''),
                    'action' => array_filter(['type' => $type, 'id' => $id, 'label' => $label !== '' ? $label : $e->title($lang)], fn($v) => $v !== null),
                ], fn($v) => $v !== null);
            })->values()->all();
    }

    /**
     * Words the admin added so the app's search finds a service: each set leads
     * to the entry's tip button, or else its service's screen.
     */
    public function searchWords(): array
    {
        return AssistantEntry::where('is_active', true)->where('audience', 'customer')->whereNotNull('search_words')->orderBy('sort')->get()
            ->map(function (AssistantEntry $e) {
                $target = self::validTipAction($e->tip_action) ? $e->tip_action : (self::SERVICE_ACTIONS[$e->service] ?? null);
                $words = collect(preg_split('/[,\n]+/', (string)$e->search_words))
                    ->map(fn($w) => mb_strtolower(trim($w)))->filter(fn($w) => mb_strlen($w) >= 2)->unique()->take(60)->values()->all();
                if ($target === null || $words === []) {
                    return null;
                }
                [$type, $id] = array_pad(explode(':', $target, 2), 2, null);
                return array_filter(['type' => $type, 'id' => $id, 'words' => $words], fn($v) => $v !== null);
            })->filter()->values()->all();
    }

    /**
     * @param array $services the services switched on in the customer's app (ride, food, bus...)
     * @return array{id: ?string, status: string, reply: string, actions: array}
     */
    public function ask(User $user, string $question, ?string $screen, string $lang, array $services, string $audience = 'customer'): array
    {
        $this->audience = $audience === 'driver' ? 'driver' : 'customer';
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

        if ($this->audience === 'driver') {
            // Drivers: the driver knowledge and the driver app's buttons; no customer activity.
            $activity = [];
            $services = [];
            $system = $this->driverPrompt($screen);
        } else {
            $activity = app(ActivityController::class)->itemsFor($user->id);
            $services = array_values(array_intersect($services, array_filter(array_column(self::ACTIONS, 'service'))));
            $system = $this->systemPrompt($services, $activity, $screen);
        }

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
        $facts = $this->facts('customer');
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
- Offer 1 to 3 buttons, only of the types in BUTTONS. Button labels: 2 to 4 words, in the customer's language. Types order, bus_booking and hotel_chat need the id of an item in CUSTOMER ACTIVITY.
- Every answer about a service carries the button that opens it, even when the customer only asked how it works or how to pay (for example: paying for food -> "food" button, "Order food"; bus tickets -> "bus"). If an item in CUSTOMER ACTIVITY is what they ask about, put its button first. Give no button only when nothing in BUTTONS fits.
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

    /** The knowledge pack for customers or for drivers, as the prompt's FACTS. */
    protected function facts(string $audience): string
    {
        return AssistantEntry::where('is_active', true)->where('audience', $audience)->orderBy('sort')->get()
            ->map(fn(AssistantEntry $e) => '## ' . $e->title_en . "\n" . $e->body_en)->implode("\n\n");
    }

    protected function driverPrompt(?string $screen): string
    {
        $facts = $this->facts('driver');
        $buttons = collect(self::DRIVER_ACTIONS)->map(fn($a, $type) => '- ' . $type . ': ' . $a['what'])->implode("\n");
        return <<<PROMPT
You are "Ask E-Ride", the helper inside the E-Ride DRIVER app in Cameroon. You help drivers understand how driving with E-Ride works: going online, requests, trips, deposits, earnings and withdrawals, safety.

RULES
- Answer only from FACTS below. If they do not cover the question, set "answered" to false and say support can help.
- Never invent prices, commissions, amounts, times, places, people or promises. You cannot do anything yourself: you only offer buttons that open the app.
- Reply in the driver's language (English, French or Cameroonian Pidgin), in one or two short sentences, at most 280 characters, plain text, no emoji, no markdown.
- Money problems, a blocked account, complaints, accidents or danger: answered false, and offer the "support" button.
- Offer 1 to 3 buttons, only of the types in BUTTONS, with labels of 2 to 4 words in the driver's language. Every answer about a screen carries the button that opens it. Give no button only when nothing in BUTTONS fits.
- Answer with JSON only: {"reply": "...", "answered": true, "actions": [{"type": "...", "label": "..."}]}

BUTTONS
{$buttons}

SCREEN THE DRIVER IS ON: {$screen}
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
            $spec = ($this->audience === 'driver' ? self::DRIVER_ACTIONS : self::ACTIONS)[$type] ?? null;
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
                'user_id' => $user->id, 'audience' => $this->audience, 'question' => $question, 'screen' => $screen ? mb_substr($screen, 0, 80) : null, 'language' => $lang,
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
