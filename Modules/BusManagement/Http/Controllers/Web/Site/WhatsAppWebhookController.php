<?php

namespace Modules\BusManagement\Http\Controllers\Web\Site;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\BusManagement\Lib\BusMessenger;
use Modules\Gateways\Services\WhatsAppCloud;

/**
 * Meta's WhatsApp webhook (callback URL https://<site>/bus/whatsapp/webhook, field "messages").
 *   GET   Meta checks the URL with the verify token shown in the admin.
 *   POST  delivery statuses; a failed message is sent again by SMS.
 * Bodies are trusted only with a valid X-Hub-Signature-256 (the app secret).
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $token = WhatsAppCloud::settings()['verify_token'];
        // PHP turns "hub.mode" into "hub_mode".
        if ($token !== '' && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string)$request->query('hub_verify_token'))) {
            return response((string)$request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }
        return response('Forbidden', 403);
    }

    public function receive(Request $request, BusMessenger $messenger): JsonResponse
    {
        $raw = $request->getContent();
        if (!WhatsAppCloud::signatureMatches($raw, $request->header('X-Hub-Signature-256'))) {
            Log::warning('WhatsApp webhook with a wrong signature ignored', ['ip' => $request->ip()]);
            return response()->json(['ok' => false], 401);
        }
        $payload = json_decode($raw, true);
        return response()->json(['ok' => true, 'seen' => is_array($payload) ? $messenger->handleStatuses($payload) : 0]);
    }
}
