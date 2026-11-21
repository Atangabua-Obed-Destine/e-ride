<?php

namespace Modules\AssistantManagement\Service;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\AssistantManagement\Entities\AssistantEntry;
use Modules\AssistantManagement\Entities\AssistantQuestion;
use Modules\AssistantManagement\Lib\AssistantSettings;
use Modules\AssistantManagement\Lib\CatalogSearch;
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
        'ride' => ['service' => 'ride', 'what' => 'book a ride now; id = the place they want to go, as they named it (e.g. "Up Station"), to fill the destination'],
        'ride_later' => ['service' => 'ride', 'what' => 'book a ride for later (1 hour to 3 days ahead); id = the place, if named'],
        'health_ride' => ['service' => 'health', 'what' => 'emergency ride (course d\'urgence): urgent ride to a hospital, clinic or pharmacy'],
        'parcel' => ['service' => 'parcel', 'what' => 'send a parcel; id = where it goes, if named'],
        'move' => ['service' => 'move', 'what' => 'book a truck to move goods; id = where to, if named'],
        'guide' => ['service' => null, 'what' => 'the getting-started guide: first steps in E-Ride, for new customers or anyone asking how to start'],
        'food' => ['service' => 'food', 'what' => 'restaurants and shops that deliver'],
        'bus' => ['service' => 'bus', 'what' => 'bus tickets with verified agencies'],
        'hotels' => ['service' => 'hotels', 'what' => 'hotels and guest houses'],
        'explore' => ['service' => 'explore', 'what' => 'Explore: one category when id is its key (pharmacy, health, bank, stay, homes, bus, eat, swim, party, visit, market)'],
        'things' => ['service' => 'explore', 'what' => 'Things to do and tours of the city: places to eat, swim, party, sights, markets, rated by riders'],
        'market' => ['service' => 'market', 'what' => 'E-Ride Market (items, digital products, services); id = words to search, e.g. "ps4"'],
        'jobs' => ['service' => 'jobs', 'what' => 'jobs from verified employers; id = words to search'],
        'safety' => ['service' => null, 'what' => 'safety: trusted contacts who see the trip live'],
        'refer' => ['service' => null, 'what' => 'invite friends and earn'],
        'coupons' => ['service' => null, 'what' => "the customer's coupons and offers"],
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
    public const TIP_ACTIONS = ['ride', 'ride_later', 'health_ride', 'parcel', 'move', 'food', 'bus', 'hotels', 'explore', 'things', 'market', 'jobs', 'id_verify', 'safety', 'refer', 'coupons'];

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
    /** Catalog matches for this question (customers only): the model suggests from these, the app shows them as cards. */
    protected array $catalog = ['terms' => [], 'results' => [], 'categories' => []];

    /** The conversation so far (oldest first, at most 4 turns), so follow-ups make sense. */
    protected array $history = [];

    /** Where the customer's saved address is: the zone, for "in Bamenda". */
    protected ?string $zoneId = null;

    /**
     * @param array $history earlier turns of this conversation: [['q' => ..., 'a' => ...], ...]
     */
    public function ask(User $user, string $question, ?string $screen, string $lang, array $services, string $audience = 'customer', ?float $lat = null, ?float $lng = null, array $history = [], ?string $zoneId = null): array
    {
        $this->history = [];
        foreach (array_slice($history, -4) as $turn) {
            $q = self::clean((string)($turn['q'] ?? ''));
            $a = mb_substr(trim(strip_tags((string)($turn['a'] ?? ''))), 0, 500);
            if ($q !== '') {
                $this->history[] = ['q' => $q, 'a' => $a];
            }
        }
        $this->zoneId = $zoneId;
        $this->catalog = ['terms' => [], 'results' => [], 'categories' => []];
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
            try {
                $this->catalog = CatalogSearch::search($question, $lat, $lng, $services, $lang);
            } catch (\Throwable $e) {
                report($e);
            }
            $system = $this->systemPrompt($services, $activity, $screen, $this->about($user));
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
                $p['name'], $answer['reply'], $answer['actions'], $started, true, $answer['cards'], $answer['steps'], $answer['follow_ups']);
        }
        // No AI right now: what E-Ride has for the words is still useful.
        if ($this->audience === 'customer' && ($this->catalog['results'] || $this->catalog['categories'])) {
            $cards = array_slice($this->catalog['results'], 0, 4);
            $actions = [];
            foreach (array_slice($this->catalog['categories'], 0, 2) as $c) {
                $actions[] = ['type' => 'explore', 'label' => mb_substr($c['name'], 0, 40), 'id' => $c['key']];
            }
            if (in_array('market', $services, true) && $this->catalog['terms'] && count($actions) < 3) {
                $actions[] = ['type' => 'market', 'label' => $lang === 'fr' ? 'Chercher au Market' : 'Search Market', 'id' => implode(' ', $this->catalog['terms'])];
            }
            return $this->finish($user, $question, $screen, $lang, AssistantQuestion::ANSWERED, null, self::say('found', $lang), $actions, $started, true, $cards);
        }
        return $this->finish($user, $question, $screen, $lang, AssistantQuestion::BUSY, null, self::say('busy', $lang), [self::support($lang)], $started);
    }

    public function feedback(User $user, string $id, bool $helped): bool
    {
        return (bool)AssistantQuestion::where('id', $id)->where('user_id', $user->id)->update(['helped' => $helped]);
    }

    /**
     * A one-off JSON answer from the first provider that gives one, for admin
     * tools (e.g. drafting a place guide). Uses the same providers, keys and
     * skip rules as Ask E-Ride. Null when every provider failed or answered
     * something that is not JSON.
     */
    public function draftJson(string $system, string $prompt): ?array
    {
        foreach (AssistantSettings::all()['providers'] as $p) {
            if (!$p['enabled'] || $p['key'] === '' || Cache::has('assistant_skip_' . $p['name']) || !isset($this->clients[$p['name']])) {
                continue;
            }
            try {
                $raw = $this->clients[$p['name']]->complete($system, $prompt, $p['key'], $p['model']);
            } catch (ProviderUnavailable $e) {
                Cache::put('assistant_skip_' . $p['name'], $e->getMessage(), $e->retryAfter);
                continue;
            }
            $data = json_decode(self::jsonPart($raw), true);
            if (is_array($data)) {
                return $data + ['_provider' => $p['name']];
            }
            Log::warning('AI draft: unreadable answer', ['provider' => $p['name'], 'raw' => mb_substr($raw, 0, 300)]);
        }
        return null;
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

    /**
     * What helps to answer this customer well, without who they are: no name,
     * phone or address ever reaches the AI. How long they have used E-Ride,
     * what they have done, and what is set up.
     */
    protected function about(User $user): string
    {
        $lines = [];
        try {
            $days = $user->created_at ? (int)$user->created_at->diffInDays(now()) : null;
            $trips = \Modules\TripManagement\Entities\TripRequest::where('customer_id', $user->id)->where('current_status', 'completed')
                ->selectRaw("SUM(type = 'parcel') as parcels, SUM(type <> 'parcel') as rides")->first();
            $rides = (int)($trips->rides ?? 0);
            $parcels = (int)($trips->parcels ?? 0);
            $new = ($days !== null && $days < 14) || ($rides + $parcels) === 0;
            $lines[] = '- with E-Ride for ' . ($days === null ? 'an unknown time' : $days . ' days') . '; completed rides: ' . $rides . '; parcels sent: ' . $parcels;
            $lines[] = '- new customer: ' . ($new ? 'yes (explain simply, step by step, and offer the "guide" button when it fits)' : 'no');
            $labels = \Modules\UserManagement\Entities\UserAddress::where('user_id', $user->id)->pluck('address_label')->map(fn($l) => strtolower((string)$l))->all();
            $lines[] = '- saved places: home ' . (in_array('home', $labels, true) ? 'yes' : 'no') . ', work ' . (in_array('office', $labels, true) || in_array('work', $labels, true) ? 'yes' : 'no') . ', ' . count($labels) . ' in all';
            $lines[] = '- trusted contacts for safety: ' . \Modules\TripManagement\Entities\TrustedContact::where('user_id', $user->id)->count();
            $id = \Modules\UserManagement\Entities\IdVerification::where('user_id', $user->id)->latest()->first();
            $lines[] = '- E-Ride ID verified: ' . ($id?->isVerified() ? 'yes' : ($id ? 'sent, ' . $id->status : 'no'));
            if ($this->zoneId) {
                $zone = \Modules\ZoneManagement\Entities\Zone::whereKey($this->zoneId)->value('name');
                $zone && $lines[] = '- area: ' . $zone;
            }
        } catch (\Throwable $e) {
            report($e);
        }
        return implode("\n", $lines) ?: '- nothing known';
    }

    /**
     * Quarters and landmarks of the launch city plus the admin's landmarks
     * (most used first), so a spoken "Nkwen" is not written "inquin".
     */
    protected function placeNames(): string
    {
        $names = ['Bamenda', 'Nkwen', 'Up Station', 'Commercial Avenue', 'Hospital Roundabout', 'Food Market', 'Main Market', 'Mile 2', 'Mile 3', 'Mile 4',
            'Mile 90', 'Ntarikon', 'Mankon', 'Bambili', 'Bambui', 'Nkwen Market', 'City Chemist Roundabout', 'Old Town', 'Ntamulung', 'Azire', 'Atuakom',
            'Foncha Street', 'Sonac Street', 'Finance Junction', 'Cow Street', 'Hospital Street', 'Small Mankon', 'Ngomgham', 'Bayelle', 'Alakuma', 'Mbengwi Road', 'Bali'];
        try {
            $names = array_merge($names, Cache::remember('assistant_voice_places', 3600, fn() => \Modules\PlaceManagement\Entities\Landmark::where('is_active', 1)
                ->orderByDesc('uses')->limit(120)->get(['name', 'area'])
                ->flatMap(fn($l) => [$l->name, $l->area])->filter()->map(fn($n) => trim((string)$n))->all()));
        } catch (\Throwable $e) {
            report($e);
        }
        return implode(', ', array_slice(array_values(array_unique($names)), 0, 150));
    }

    protected function conversation(): string
    {
        if (!$this->history) {
            return '- this is the first question';
        }
        return collect($this->history)->map(fn($t) => 'Customer: ' . $t['q'] . "\nAsk E-Ride: " . ($t['a'] ?: '(no answer)'))->implode("\n");
    }

    protected function systemPrompt(array $services, array $activity, ?string $screen, string $about = '- nothing known'): string
    {
        $conversation = $this->conversation();
        $facts = $this->facts('customer');
        $buttons = collect(self::ACTIONS)
            ->filter(fn($a) => $a['service'] === null || in_array($a['service'], $services, true))
            ->map(fn($a, $type) => '- ' . $type . ': ' . $a['what'])->implode("\n");
        $mine = collect($activity)->map(fn($i) => '- ' . $i['kind'] . ' (id ' . $i['id'] . ', ' . $i['group'] . ')'
            . ($i['deadline'] ? ', deadline ' . Carbon::parse($i['deadline'])->format('H:i') : '')
            . ': ' . json_encode($i['params'], JSON_UNESCAPED_UNICODE))->implode("\n") ?: '- nothing in progress';
        $terms = $this->catalog['terms'] ? implode(' ', $this->catalog['terms']) : 'nothing to search';
        $found = collect($this->catalog['results'])->map(fn($r) => '- ' . $r['ref'] . ' [' . $r['source'] . '] ' . $r['title']
            . ($r['subtitle'] ? ' (' . $r['subtitle'] . ')' : '') . ($r['price'] !== null ? ', ' . number_format($r['price'], 0, '.', ' ') . ' FCFA' : ''))->implode("\n");
        foreach ($this->catalog['categories'] as $c) {
            $found .= ($found ? "\n" : '') . '- Explore category "' . $c['key'] . '": ' . $c['name'] . ' (button explore, id ' . $c['key'] . ')';
        }
        $found = $found ?: '- nothing found in E-Ride';

        return <<<PROMPT
You are "Ask E-Ride", the helper inside the E-Ride app in Cameroon. You help customers find the right service and understand how things work.

RULES
- Answer only from FACTS and CUSTOMER ACTIVITY below. If they do not cover the question, set "answered" to false and say support can help.
- Never invent prices, times, places, people or promises. You cannot do anything yourself: you only offer buttons that open the app.
- Reply in the customer's language (English, French or Cameroonian Pidgin), in one or two short sentences, at most 280 characters, plain text, no emoji, no markdown.
- Refunds, complaints, lost money, accidents or danger: answered false, and offer the "support" button.
- Offer 1 to 3 buttons, only of the types in BUTTONS. Button labels: 2 to 4 words, in the customer's language. Types order, bus_booking and hotel_chat need the id of an item in CUSTOMER ACTIVITY.
- Every answer about a service carries the button that opens it, even when the customer only asked how it works or how to pay (for example: paying for food -> "food" button, "Order food"; bus tickets -> "bus"). If an item in CUSTOMER ACTIVITY is what they ask about, put its button first. Give no button only when nothing in BUTTONS fits.
- When the customer is looking for something (a product, a service, food, a place, a job, a home, a bus), suggest only from WHAT E-RIDE HAS. Name the best one or two, and list the refs to show as cards in "cards" (for example ["r1", "r2"], at most 4, best first). Never mention anything that is not in that list, and show only what is really what they asked for (a phone repair service is not a phone to buy).
- If WHAT E-RIDE HAS is empty or nothing fits, say plainly that E-Ride does not have it yet (that is still an answer: "answered" true), then offer the closest button: "market" with id = the one or two main words to search, as a seller would write them (e.g. "phone", "ps4") (also where they can post what they want), an Explore category ("explore" with id = its key), "food" or "things". Never pretend something exists.
- Read CONVERSATION SO FAR: a short follow-up ("how much?", "and for tomorrow?", "e dey far?") is about the last topic. Do not repeat what you already said.
- When the customer wants to go somewhere or send something there and names the place, give the "ride" (or "ride_later", "parcel", "move") button with id = that place as they said it, label like "Ride to Up Station".
- For "how do I..." questions, put the way in "steps": 2 to 4 short steps (each under 90 characters) in the customer's language, and keep "reply" to one sentence. Otherwise "steps" is [].
- "follow_ups": 2 or 3 short next questions this customer would likely ask now (each under 40 characters, their language, from FACTS only). [] when the answer closes the topic.
- Answer with JSON only: {"reply": "...", "answered": true, "steps": [], "actions": [{"type": "...", "label": "...", "id": "..."}], "cards": ["r1"], "follow_ups": []}

BUTTONS
{$buttons}

WHAT E-RIDE HAS FOR THIS QUESTION (searched for: {$terms})
{$found}

CUSTOMER ACTIVITY (things in progress or needing the customer; the kind says what: food_*, bus_*, hotel_*, ride_*, parcel_*, market_*, home_*, job_*, lost_*, id_*, tour_*)
{$mine}

ABOUT THIS CUSTOMER
{$about}

CONVERSATION SO FAR
{$conversation}

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
            if (in_array($type, ['explore', 'market', 'jobs'], true) && is_scalar($a['id'] ?? null) && trim((string)$a['id']) !== '') {
                $raw = trim(strip_tags((string)$a['id']));
                // An Explore category key, or the words to search in Market or Jobs.
                $id = $type === 'explore' ? (preg_match('/^[a-z_]{2,30}$/', $raw) ? $raw : null) : mb_substr($raw, 0, 60);
            }
            if (in_array($type, ['ride', 'ride_later', 'parcel', 'move'], true) && is_scalar($a['id'] ?? null)) {
                // The place to fill in as the destination, as the customer named it.
                $place = trim(self::clean(strip_tags((string)$a['id'])));
                $id = ($place !== '' && !str_contains($place, '[phone]') && !str_contains($place, '[email]')) ? mb_substr($place, 0, 80) : null;
            }
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
        // Cards: only refs from this question's catalog matches.
        $byRef = collect($this->catalog['results'])->keyBy('ref');
        $cards = [];
        foreach ((array)($data['cards'] ?? []) as $ref) {
            if (is_string($ref) && $byRef->has($ref) && count($cards) < 4) {
                $cards[$ref] = $byRef->get($ref);
            }
        }
        $lines = function ($list, int $max, int $len): array {
            $out = [];
            foreach (is_array($list) ? $list : [] as $line) {
                $line = is_scalar($line) ? trim(strip_tags((string)$line)) : '';
                if ($line !== '' && count($out) < $max) {
                    $out[] = mb_substr($line, 0, $len);
                }
            }
            return $out;
        };
        return ['reply' => $reply, 'answered' => $answered, 'actions' => array_values($actions), 'cards' => array_values($cards),
            'steps' => $lines($data['steps'] ?? [], 4, 140), 'follow_ups' => $answered ? $lines($data['follow_ups'] ?? [], 3, 60) : []];
    }

    protected function finish(User $user, string $question, ?string $screen, string $lang, string $status, ?string $provider, string $reply, array $actions, float $started, bool $log = true, array $cards = [], array $steps = [], array $followUps = []): array
    {
        // The log keeps the steps with the reply, so the admin sees the whole answer.
        $logged = $steps ? $reply . "\n" . implode("\n", array_map(fn($s, $i) => ($i + 1) . '. ' . $s, $steps, array_keys($steps))) : $reply;
        $id = null;
        if ($log) {
            $q = AssistantQuestion::create([
                'user_id' => $user->id, 'audience' => $this->audience, 'question' => $question, 'screen' => $screen ? mb_substr($screen, 0, 80) : null, 'language' => $lang,
                'status' => $status, 'provider' => $provider, 'reply' => $logged,
                // What was shown, for the admin's log: the buttons, then the cards.
                'actions' => $cards ? array_merge($actions, array_map(fn($c) => ['type' => 'card', 'label' => $c['title'], 'id' => $c['type'] . ':' . $c['id']], $cards)) : $actions,
                'ms' => (int)round((microtime(true) - $started) * 1000),
            ]);
            $id = $q->id;
        }
        return ['id' => $id, 'status' => $status, 'reply' => $reply, 'actions' => $actions, 'cards' => $cards, 'steps' => $steps, 'follow_ups' => $followUps];
    }

    // ------------------------------------------------------------------ voice notes

    /**
     * Listens to the voice note a customer recorded while booking a ride or a
     * parcel, and writes out what they said, where to find them, where they go
     * and (parcels) what is sent. Only Gemini hears audio. Never blocks the
     * booking: when the AI is off, busy or out of quota, the note still goes
     * to the driver, unread.
     *
     * @return array{understood: bool, transcript: string, pickup: string, destination: string, note: string, parcel: ?array, why?: string}
     */
    public function understandVoice(User $user, string $path, string $mime, string $kind, string $lang, array $parcelCategories = []): array
    {
        $nothing = ['understood' => false, 'transcript' => '', 'pickup' => '', 'destination' => '', 'note' => '', 'parcel' => null];
        $settings = AssistantSettings::all();
        $lang = $lang === 'fr' ? 'fr' : 'en';
        $kind = $kind === 'parcel' ? 'parcel' : 'ride';
        if (!$settings['enabled']) {
            return $nothing + ['why' => 'off'];
        }
        $today = AssistantQuestion::where('created_at', '>=', now()->startOfDay())->whereIn('status', [AssistantQuestion::ANSWERED, AssistantQuestion::NO_ANSWER]);
        if ((clone $today)->where('user_id', $user->id)->count() >= $settings['per_user_daily'] + 10 || (clone $today)->count() >= $settings['app_daily']) {
            return $nothing + ['why' => 'limit'];
        }
        $audio = @file_get_contents($path);
        if ($audio === false || strlen($audio) < 400) {
            return $nothing + ['why' => 'empty'];
        }
        $categories = implode(', ', array_map(fn($c) => '"' . mb_substr(trim((string)$c), 0, 60) . '"', array_slice($parcelCategories, 0, 30)));
        $what = $kind === 'parcel' ? 'sending a parcel' : 'booking a ride';
        $places = $this->placeNames();
        $system = <<<PROMPT
You listen to a voice note that a customer of E-Ride, a ride-hailing and delivery app in Cameroon, recorded while {$what}. The note is for the driver. People speak English, French, Cameroonian Pidgin, or mix them, and name local places: quarters, junctions, churches, schools, markets, shops, filling stations.
Answer with JSON only:
{"understood": true, "transcript": "...", "pickup": "...", "destination": "...", "note": "...", "parcel": null}
- transcript: what they said, word for word, in the language they used (write Pidgin as spoken), at most 400 characters.
- pickup: how the driver finds them where they are now (landmarks, the gate, colours, what is opposite, which side of the road), in their own words and language, at most 200 characters. "" if they did not say.
- destination: where they want to go (a ride) or where the parcel goes, as words to search on a map: the place and its area, e.g. "Up Station" or "Total Nkwen" or "Hospital Roundabout". "" if they did not say. Never guess.
- note: anything else the driver should know (bags, waiting inside a shop, call on arrival), at most 120 characters, their language. "" if nothing.
- parcel: only when sending a parcel, else null: {"what": "what is sent", "weight_kg": number or null, "fragile": true or false, "receiver_name": "" , "receiver_phone": "", "category": one of [{$categories}] or ""}.
- understood: false when the recording is silent, only noise, or not about the trip; then every other field is "" or null.
- Local place names people use, spelled the way to write them (when you hear one of these, write it like this): {$places}
PROMPT;
        foreach ($settings['providers'] as $p) {
            if ($p['name'] !== 'gemini' || !$p['enabled'] || $p['key'] === '' || Cache::has('assistant_skip_gemini') || !($this->clients['gemini'] instanceof GeminiClient)) {
                continue;
            }
            $started = microtime(true);
            try {
                $raw = $this->clients['gemini']->completeAudio($system, 'Listen to the voice note and answer with the JSON.', base64_encode($audio), $mime, $p['key'], $p['model']);
            } catch (ProviderUnavailable $e) {
                // Out of quota or a refused key stops Gemini for chat too; a refused
                // recording (its format, say) concerns this note only.
                if (!$e->busy && !str_contains($e->getMessage(), 'request refused')) {
                    Cache::put('assistant_skip_gemini', $e->getMessage(), $e->retryAfter);
                }
                Log::info('Voice note: not understood', ['why' => $e->getMessage()]);
                return $nothing + ['why' => 'busy'];
            }
            $data = json_decode(self::jsonPart($raw), true);
            if (!is_array($data)) {
                Log::warning('Voice note: unreadable answer', ['raw' => mb_substr($raw, 0, 300)]);
                return $nothing + ['why' => 'busy'];
            }
            $text = fn($v, int $len) => is_scalar($v) ? mb_substr(trim(strip_tags((string)$v)), 0, $len) : '';
            $understood = ($data['understood'] ?? false) === true && $text($data['transcript'] ?? '', 400) !== '';
            $parcel = null;
            if ($kind === 'parcel' && $understood && is_array($data['parcel'] ?? null)) {
                $pd = $data['parcel'];
                $category = $text($pd['category'] ?? '', 60);
                $parcel = [
                    'what' => $text($pd['what'] ?? '', 120),
                    'weight_kg' => is_numeric($pd['weight_kg'] ?? null) && $pd['weight_kg'] > 0 ? round((float)$pd['weight_kg'], 1) : null,
                    'fragile' => ($pd['fragile'] ?? false) === true,
                    'receiver_name' => $text($pd['receiver_name'] ?? '', 60),
                    'receiver_phone' => preg_replace('/[^0-9+]/', '', $text($pd['receiver_phone'] ?? '', 20)),
                    'category' => in_array($category, $parcelCategories, true) ? $category : '',
                ];
            }
            $answer = [
                'understood' => $understood,
                'transcript' => $understood ? $text($data['transcript'] ?? '', 400) : '',
                'pickup' => $understood ? $text($data['pickup'] ?? '', 200) : '',
                'destination' => $understood ? $text($data['destination'] ?? '', 80) : '',
                'note' => $understood ? $text($data['note'] ?? '', 120) : '',
                'parcel' => $parcel,
            ];
            // In the admin's log like a question, so voice notes count in the daily limits and can be checked.
            AssistantQuestion::create([
                'user_id' => $user->id, 'audience' => 'customer', 'question' => '[voice note, ' . $kind . '] ' . ($answer['transcript'] ?: '(not understood)'),
                'screen' => 'voice_' . $kind, 'language' => $lang, 'status' => $understood ? AssistantQuestion::ANSWERED : AssistantQuestion::NO_ANSWER,
                'provider' => 'gemini', 'reply' => trim('Pickup: ' . $answer['pickup'] . "\nTo: " . $answer['destination'] . "\nNote: " . $answer['note']),
                'actions' => [], 'ms' => (int)round((microtime(true) - $started) * 1000),
            ]);
            return $answer;
        }
        return $nothing + ['why' => 'busy'];
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
            'found' => ['en' => 'Here is what I found on E-Ride for you.', 'fr' => "Voici ce que j'ai trouvé sur E-Ride pour vous."],
            'busy' => ['en' => 'Ask E-Ride is busy right now. Try again in a moment, or talk to our support team on WhatsApp.', 'fr' => 'Ask E-Ride est occupé pour le moment. Réessayez dans un instant, ou parlez à notre équipe sur WhatsApp.'],
        ];
        return $text[$what][$lang];
    }
}
