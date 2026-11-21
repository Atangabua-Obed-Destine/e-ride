<?php

namespace Modules\AssistantManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AssistantManagement\Lib\AssistantSettings;
use Modules\AssistantManagement\Service\AssistantService;
use Modules\TripManagement\Service\TripVoiceNoteService;

/** Customer app: the info sheets, and Ask E-Ride. */
class AssistantController extends Controller
{
    public function __construct(protected AssistantService $assistant)
    {
    }

    protected function lang(Request $request): string
    {
        return str_starts_with(strtolower((string)$request->header('X-Localization', app()->getLocale())), 'fr') ? 'fr' : 'en';
    }

    /**
     * Whether Ask E-Ride is on, every info sheet by key, the "Did you know?"
     * cards and the admin's words for the smart search, in the customer's language.
     */
    public function help(Request $request): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, [
            'assistant' => AssistantSettings::enabled(),
            'sheets' => $this->assistant->helpSheets($this->lang($request)),
            'tips' => $this->assistant->tips($this->lang($request)),
            'search_words' => $this->assistant->searchWords(),
        ]));
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => 'required|string|max:1000',
            'screen' => 'nullable|string|max:120',
            'services' => 'nullable|array|max:20',
            'services.*' => 'string|max:20',
            // Where the customer is, for distances in the suggestions.
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            // The conversation so far, so "how much?" follows the last answer.
            'history' => 'nullable|array|max:8',
            'history.*.q' => 'nullable|string|max:1000',
            'history.*.a' => 'nullable|string|max:1500',
        ]);
        $zone = $request->header('zoneId');
        $answer = $this->assistant->ask($request->user(), $data['question'], $data['screen'] ?? null, $this->lang($request), $data['services'] ?? [],
            'customer', isset($data['latitude']) ? (float)$data['latitude'] : null, isset($data['longitude']) ? (float)$data['longitude'] : null,
            $data['history'] ?? [], is_string($zone) && preg_match('/^[0-9a-f-]{36}$/i', $zone) ? $zone : null);
        return response()->json(responseFormatter(DEFAULT_200, $answer));
    }

    /**
     * A voice note recorded while booking a ride or a parcel: kept for the
     * driver, and (when the AI can) written out, with where to find the
     * customer, where they go and what they send, to fill the booking.
     */
    public function voiceTrip(Request $request): JsonResponse
    {
        $data = $request->validate([
            'audio' => 'required|file|max:3072',
            'seconds' => 'nullable|integer|min:1|max:600',
            'kind' => 'nullable|in:ride,parcel',
            'categories' => 'nullable|array|max:30',
            'categories.*' => 'string|max:60',
        ]);
        $voice = app(TripVoiceNoteService::class);
        $name = $voice->store($request->file('audio'), (string)$request->user()->id, (int)($data['seconds'] ?? 0));
        if ($name === null) {
            return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => 'voice_note', 'message' => translate('The voice note could not be saved. Try again.')]]), 403);
        }
        $heard = $this->assistant->understandVoice($request->user(), $voice->path($name), $voice->mime($name), $data['kind'] ?? 'ride',
            $this->lang($request), $data['categories'] ?? []);
        if ($heard['transcript'] !== '') {
            $voice->setText($name, $heard['transcript']);
        }
        return response()->json(responseFormatter(DEFAULT_200, [
            'voice_note' => $name,
            'url' => $voice->url($name),
            'seconds' => max(1, min(TripVoiceNoteService::MAX_SECONDS, (int)($data['seconds'] ?? 0))),
        ] + $heard));
    }

    public function feedback(Request $request, string $question): JsonResponse
    {
        $data = $request->validate(['helped' => 'required|boolean']);
        $this->assistant->feedback($request->user(), $question, (bool)$data['helped']);
        return response()->json(responseFormatter(DEFAULT_200));
    }
}
