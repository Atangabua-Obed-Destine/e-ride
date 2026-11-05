<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\TripManagement\Service\TripShareService;

/**
 * The public Share trip page (/t/{token}) and the data it polls. No login:
 * the token is the key, and the payload holds no phone numbers or fares.
 */
class TripShareController extends Controller
{
    public function __construct(protected TripShareService $shares)
    {
    }

    public function page(Request $request, string $token)
    {
        $trip = $this->shares->findByToken($token);
        $payload = $this->shares->payload($trip);
        $lang = str_starts_with(strtolower((string)$request->getPreferredLanguage(['en', 'fr'])), 'fr') ? 'fr' : 'en';

        return response()
            ->view('trip-share', [
                'token' => $token,
                'payload' => $payload,
                'lang' => $lang,
                'mapKey' => businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null,
                'appUrl' => businessConfig('customer_app_version_control_for_android')?->value['app_url'] ?? null,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function data(string $token): JsonResponse
    {
        $trip = $this->shares->findByToken($token);
        return response()
            ->json(responseFormatter(DEFAULT_200, $this->shares->payload($trip)))
            ->header('Cache-Control', 'no-store');
    }
}
