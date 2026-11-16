<?php

namespace Modules\HotelManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\HotelManagement\Entities\HotelStaff;

/**
 * Admits someone who works for a partner hotel, and shares the hotel and their staff row with the
 * request ("hotel", "hotelStaff"). With roles (HotelPartner::class . ':manager'), only those.
 */
class HotelPartner
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = auth()->user();
        $deny = function (?string $message = null) use ($request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $message ?? 'unauthenticated'], 401);
            }
            $redirect = redirect()->route('hotel-partner.login');
            return $message ? $redirect->withErrors(['login' => $message]) : $redirect;
        };
        if (!$user || $user->user_type !== PARTNER_USER_TYPE || !$user->is_active) {
            return $deny();
        }
        $staff = HotelStaff::with('hotel.place')->where('user_id', $user->id)->where('active', true)->first();
        if (!$staff || !$staff->hotel) {
            auth()->logout();
            return $deny(translate('This account is not linked to a hotel on E-Ride. Contact E-Ride.'));
        }
        if ($roles && !in_array($staff->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => translate('Your role cannot do this.')], 403);
            }
            return redirect()->route('hotel-partner.rooms')->with('toast', translate('Your role cannot open that page.'));
        }
        $request->attributes->set('hotel', $staff->hotel);
        $request->attributes->set('hotelStaff', $staff);
        view()->share('hotel', $staff->hotel);
        view()->share('hotelStaff', $staff);
        return $next($request);
    }
}
