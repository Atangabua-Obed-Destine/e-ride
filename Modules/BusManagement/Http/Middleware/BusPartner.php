<?php

namespace Modules\BusManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusStaff;

/**
 * Admits someone who works for a bus agency E-Ride verified, and shares the agency and their staff
 * row with the request ("busAgency", "busStaff"). With roles (bus.partner:manager,counter), only those.
 */
class BusPartner
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = auth()->user();
        $deny = function (?string $message = null) use ($request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $message ?? 'unauthenticated'], 401);
            }
            $redirect = redirect()->route('bus-partner.login');
            return $message ? $redirect->withErrors(['login' => $message]) : $redirect;
        };
        if (!$user || $user->user_type !== PARTNER_USER_TYPE || !$user->is_active) {
            return $deny();
        }
        $staff = BusStaff::with('agency')->where('user_id', $user->id)->where('active', true)->first();
        if (!$staff || !$staff->agency || $staff->agency->status === BusAgency::CHECKING) {
            auth()->logout();
            return $deny(translate('This account is not linked to a bus agency open on E-Ride. Contact E-Ride.'));
        }
        if ($roles && !in_array($staff->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => translate('Your role cannot do this.')], 403);
            }
            return redirect()->route(self::home($staff->role))->with('toast', translate('Your role cannot open that page.'));
        }
        $request->attributes->set('busAgency', $staff->agency);
        $request->attributes->set('busStaff', $staff);
        view()->share('busAgency', $staff->agency);
        view()->share('busStaff', $staff);
        return $next($request);
    }

    /** The first page of each role. */
    public static function home(string $role): string
    {
        return match ($role) {
            BusStaff::MANAGER, BusStaff::COUNTER => 'bus-partner.today',
            BusStaff::ACCOUNTANT => 'bus-partner.accounting',
            default => 'bus-partner.boarding',
        };
    }
}
