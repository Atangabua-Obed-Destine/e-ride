<?php

namespace Modules\BusManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Pages of the bus website that need the traveller signed in: to the sign-in page, then back here. */
class BusWebAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('bus_web')->user();
        if (!$user || $user->user_type !== CUSTOMER || !$user->is_active) {
            if ($user) {
                Auth::guard('bus_web')->logout();
            }
            if ($request->expectsJson()) {
                return response()->json(['state' => null], 401);
            }
            $request->session()->put('url.intended', $request->isMethod('GET') ? $request->fullUrl() : url()->previous());
            return redirect()->route('bus.web.login');
        }
        return $next($request);
    }
}
