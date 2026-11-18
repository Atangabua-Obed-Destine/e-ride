<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * One change at a time per trip.
 *
 * A driver accepting, the customer cancelling, a second driver accepting, a
 * double-tapped "complete" or "cash received": each of these reads the trip,
 * decides, then writes. Run side by side they each decide on the same old
 * state and the last write wins (a cancelled trip comes back as accepted, a
 * trip is paid twice). This middleware makes them take turns, with a MySQL
 * named lock, so every one of them reads the state the previous one left.
 *
 * MySQL named locks belong to the database session, not the transaction, so
 * they work around controllers that open and commit their own transactions,
 * across all PHP-FPM workers. They are released when the request ends, and by
 * MySQL itself if the worker dies.
 *
 * Usage: ->middleware('trip.lock') locks the trip in trip_request_id (input or
 * route parameter) or tripId. ->middleware('trip.lock:driver') also locks the
 * signed-in driver (one accept at a time), ->middleware('trip.lock:customer')
 * the signed-in customer (one new booking at a time).
 */
class TripStateLock
{
    /** Long enough for an accept (it asks Google for the route), short enough that nobody waits forever. */
    private const WAIT_SECONDS = 8;

    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $names = [];
        $tripId = $request->input('trip_request_id')
            ?? $request->route('trip_request_id')
            ?? $request->route('tripId');
        if ($tripId) {
            $names[] = 'eride:trip:' . $tripId;
        }
        $userId = auth('api')->id();
        if ($userId) {
            if (in_array('driver', $scopes, true)) {
                $names[] = 'eride:driver:' . $userId;
            }
            if (in_array('customer', $scopes, true) && !$tripId) {
                $names[] = 'eride:customer-booking:' . $userId;
            }
        }
        if (!$names) {
            return $next($request);
        }

        $held = [];
        try {
            foreach ($names as $name) {
                $got = DB::selectOne('SELECT GET_LOCK(?, ?) AS l', [$name, self::WAIT_SECONDS]);
                if ((int)($got->l ?? 0) !== 1) {
                    Log::warning('Trip lock busy', ['lock' => $name, 'path' => $request->path()]);
                    return response()->json(responseFormatter(constant: [
                        'response_code' => 'trip_busy_409',
                        'message' => translate('This trip is being updated. Please try again.'),
                    ]), 409);
                }
                $held[] = $name;
            }
            return $next($request);
        } finally {
            foreach (array_reverse($held) as $name) {
                try {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS l', [$name]);
                } catch (\Throwable) {
                    // The session ends with the request; MySQL frees the lock then.
                }
            }
        }
    }
}
