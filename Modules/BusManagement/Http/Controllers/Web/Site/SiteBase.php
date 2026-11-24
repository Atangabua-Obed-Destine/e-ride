<?php

namespace Modules\BusManagement\Http\Controllers\Web\Site;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Lib\BusSettings;
use Modules\BusManagement\Lib\BusWebWords;
use Modules\UserManagement\Entities\User;

/** What every page of the bus website shares: the language, the signed-in traveller, the page frame. */
abstract class SiteBase extends Controller
{
    /** English or French: ?lang= (remembered), else the browser's language. */
    protected function words(Request $request): BusWebWords
    {
        $lang = $request->query('lang');
        if (in_array($lang, BusWebWords::LANGS, true)) {
            $request->session()->put('bus_lang', $lang);
        } else {
            $lang = $request->session()->get('bus_lang');
        }
        if (!in_array($lang, BusWebWords::LANGS, true)) {
            $lang = str_starts_with(strtolower((string)$request->getPreferredLanguage(['en', 'fr'])), 'fr') ? 'fr' : 'en';
        }
        // Messages from the booking rules (translate()) follow the page's language too.
        app()->setLocale($lang);
        return BusWebWords::for($lang);
    }

    protected function user(): ?User
    {
        $user = Auth::guard('bus_web')->user();
        return $user && $user->user_type === CUSTOMER && $user->is_active ? $user : null;
    }

    /** A page, with what the frame needs. $index: whether search engines may list it. */
    protected function page(Request $request, string $view, array $data = [], int $status = 200, bool $index = false): Response
    {
        $t = $data['t'] ?? $this->words($request);
        $response = response()->view('busmanagement::site.' . $view, $data + [
            't' => $t,
            'me' => $this->user(),
            'web' => BusSettings::web(),
            'busOn' => BusSettings::enabled(),
        ], $status);
        if (!$index || !BusSettings::web()['index']) {
            $response->header('X-Robots-Tag', 'noindex');
        }
        return $response;
    }

    protected function missing(Request $request, string $key, int $status = 404): Response
    {
        $t = $this->words($request);
        return $this->page($request, 'message', ['t' => $t, 'title' => $t($key), 'body' => null], $status);
    }

    protected function live()
    {
        return BusAgency::bookable();
    }
}
