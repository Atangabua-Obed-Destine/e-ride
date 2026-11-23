<?php

namespace Modules\HotelManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Rooms board actions are refused for a hotel on POS Innova: POS owns the rooms and the reservations. */
class HotelNotOnPos
{
    public function handle(Request $request, Closure $next)
    {
        $hotel = $request->attributes->get('hotel');
        if ($hotel && $hotel->usesPos()) {
            $message = translate('Your rooms are in POS Innova: do this in POS. E-Ride follows it.');
            return $request->expectsJson()
                ? response()->json(['message' => $message, 'errors' => ['pos' => [$message]]], 422)
                : back()->withErrors(['pos' => $message]);
        }
        return $next($request);
    }
}
