<?php

namespace Modules\StoreManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\StoreManagement\Entities\Store;

/**
 * Admits an active partner whose store exists, and shares that store with the
 * request as "partnerStore". Mirrors AdminModule's AdminMiddleware.
 */
class PartnerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if (!$user || $user->user_type !== PARTNER_USER_TYPE || !$user->is_active) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'unauthenticated'], 401);
            }
            return redirect()->route('partner.login');
        }

        $store = Store::where('user_id', $user->id)->first();
        if (!$store) {
            auth()->logout();
            return redirect()->route('partner.login')->withErrors(['email' => translate('No store is linked to this account. Contact E-Ride.')]);
        }

        $request->attributes->set('partnerStore', $store);
        view()->share('partnerStore', $store);
        return $next($request);
    }
}
