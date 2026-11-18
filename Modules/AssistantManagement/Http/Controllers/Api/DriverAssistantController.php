<?php

namespace Modules\AssistantManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AssistantManagement\Lib\AssistantSettings;

/**
 * Driver app: the driver info sheets, and Ask E-Ride answered from the driver
 * knowledge (Ask E-Ride admin > Knowledge pack, audience Drivers).
 */
class DriverAssistantController extends AssistantController
{
    public function help(Request $request): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, [
            'assistant' => AssistantSettings::enabled(),
            'sheets' => $this->assistant->helpSheets($this->lang($request), 'driver'),
        ]));
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => 'required|string|max:1000',
            'screen' => 'nullable|string|max:120',
        ]);
        $answer = $this->assistant->ask($request->user(), $data['question'], $data['screen'] ?? null, $this->lang($request), [], 'driver');
        return response()->json(responseFormatter(DEFAULT_200, $answer));
    }
}
