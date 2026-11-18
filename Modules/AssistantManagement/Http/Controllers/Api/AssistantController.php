<?php

namespace Modules\AssistantManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AssistantManagement\Lib\AssistantSettings;
use Modules\AssistantManagement\Service\AssistantService;

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
        ]);
        $answer = $this->assistant->ask($request->user(), $data['question'], $data['screen'] ?? null, $this->lang($request), $data['services'] ?? []);
        return response()->json(responseFormatter(DEFAULT_200, $answer));
    }

    public function feedback(Request $request, string $question): JsonResponse
    {
        $data = $request->validate(['helped' => 'required|boolean']);
        $this->assistant->feedback($request->user(), $question, (bool)$data['helped']);
        return response()->json(responseFormatter(DEFAULT_200));
    }
}
