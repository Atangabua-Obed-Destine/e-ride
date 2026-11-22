<?php

namespace Modules\StoreManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Lib\StoreTeam;

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
        // A staff login: works for the owner's store, within the permissions the owner gave.
        $staff = $store ? null : StoreTeam::forUser($user);
        $store ??= $staff?->store;
        if (!$store) {
            auth()->logout();
            return redirect()->route('partner.login')->withErrors(['email' => translate('No store is linked to this account. Contact E-Ride.')]);
        }

        if ($staff) {
            $route = $request->route()?->getName();
            if (!StoreTeam::allows($staff, $route)) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => translate('The owner has not given you this.')], 403);
                }
                if ($request->isMethod('GET')) {
                    // The first page this person may open.
                    $to = collect(['partner.orders', 'partner.menu', 'partner.earnings', 'partner.share'])->first(fn($r) => StoreTeam::allows($staff, $r));
                    return $route === $to ? abort(403) : redirect()->route($to);
                }
                return back()->withErrors(['team' => translate('The owner has not given you this.')]);
            }
            if (!StoreTeam::clean($staff, $request)) {
                return back()->withErrors(['team' => translate('Only the owner adds dishes and sets prices.')]);
            }
        }

        $request->attributes->set('partnerStore', $store);
        $request->attributes->set('partnerStaff', $staff);
        view()->share('partnerStore', $store);
        view()->share('partnerStaff', $staff);
        $response = $next($request);
        if ($staff && !$request->isMethod('GET') && $response->getStatusCode() < 400 && !session()->has('errors')) {
            StoreTeam::log($staff, $request);
        }
        return $response;
    }
}
