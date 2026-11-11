<?php

namespace Modules\TripManagement\Service;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\TripManagement\Entities\SafetyAlert;
use Modules\TripManagement\Entities\TripCheckin;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Entities\TripWatch;
use Modules\TripManagement\Entities\TrustedContact;
use Modules\TripManagement\Lib\CheckinSettings;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserLastLocation;
use Throwable;

/**
 * Trip check-ins: once a minute the server looks at every ride in progress
 * through the driver's live position. Stopped too long, off the planned route,
 * or far longer than estimated: the rider is asked "Are you OK?" (the driver is
 * not told). "I need help", or no answer in time, opens a safety alert (the SOS
 * list) and tells the rider's trusted contacts. Night sharing: a chosen contact
 * follows every ride accepted at night.
 */
class TripCheckinService
{
    /** A driver position older than this says nothing about the ride now. */
    public const FRESH_MINUTES = 3;
    /** Stopped near the destination is arriving, not a problem. */
    public const NEAR_DESTINATION_M = 300;

    public function tick(?Carbon $now = null): array
    {
        $now ??= now();
        $c = ['asked' => 0, 'escalated' => 0, 'night_shared' => 0, 'closed' => 0];
        $s = CheckinSettings::all();
        if (!$s['enabled']) {
            return $c;
        }

        $rides = TripRequest::with(['tripStatus', 'coordinate', 'time', 'customer', 'driver'])
            ->where('type', RIDE_REQUEST)->where('current_status', ONGOING)->whereNotNull('driver_id')->get();
        foreach ($rides as $trip) {
            $this->safely(function () use ($trip, $now, $s, &$c) {
                if ($this->watch($trip, $now, $s)) {
                    $c['asked']++;
                }
            });
        }

        // No answer in time.
        foreach (TripCheckin::where('status', TripCheckin::ASKED)->where('asked_at', '<=', $now->copy()->subMinutes($s['answer_minutes']))->get() as $checkin) {
            $this->safely(function () use ($checkin, $now, &$c) {
                $trip = TripRequest::find($checkin->trip_request_id);
                // The ride ended before the time ran out: nothing to escalate.
                if (!$trip || $trip->current_status !== ONGOING) {
                    $checkin->update(['status' => TripCheckin::CLOSED]);
                    $c['closed']++;
                    return;
                }
                $checkin->update(['status' => TripCheckin::NO_ANSWER]);
                $this->escalate($checkin, 'no_answer', $now);
                $c['escalated']++;
            });
        }

        // Night sharing: rides accepted at night, shared once with the chosen contact.
        if (CheckinSettings::isNight($now)) {
            $night = TripRequest::with('customer')->where('type', RIDE_REQUEST)
                ->whereIn('current_status', [ACCEPTED, OUT_FOR_PICKUP, ONGOING])->whereNotNull('driver_id')->get();
            foreach ($night as $trip) {
                $this->safely(function () use ($trip, $now, &$c) {
                    if ($this->nightShare($trip, $now)) {
                        $c['night_shared']++;
                    }
                });
            }
        }
        return $c;
    }

    /** One ride, one minute: remember where the car is, and ask when something looks wrong. True if asked. */
    protected function watch(TripRequest $trip, Carbon $now, array $s): bool
    {
        $loc = UserLastLocation::where('user_id', $trip->driver_id)->where('updated_at', '>=', $now->copy()->subMinutes(self::FRESH_MINUTES))->first();
        if (!$loc || !is_numeric($loc->latitude) || !is_numeric($loc->longitude)) {
            return false;
        }
        $lat = (float)$loc->latitude;
        $lng = (float)$loc->longitude;
        $watch = TripWatch::firstOrCreate(['trip_request_id' => $trip->id], [
            'anchor_latitude' => $lat, 'anchor_longitude' => $lng, 'anchor_at' => $now,
        ]);

        // Stopped: the car has stayed within stop_m of where it last moved from.
        if (haversineDistance($watch->anchor_latitude, $watch->anchor_longitude, $lat, $lng) > $s['stop_m']) {
            $watch->fill(['anchor_latitude' => $lat, 'anchor_longitude' => $lng, 'anchor_at' => $now]);
        }
        $destination = $trip->coordinate?->destination_coordinates;
        $farFromEnd = !$destination || haversineDistance($destination->latitude, $destination->longitude, $lat, $lng) > self::NEAR_DESTINATION_M;
        $stopped = $farFromEnd && $watch->anchor_at && $watch->anchor_at->lte($now->copy()->subMinutes($s['stop_minutes']));

        // Off route: compared with the route planned at booking (the live route re-plans itself).
        $offRoute = false;
        // A route of fewer than two points is "infinitely far": not a route to judge by.
        if ($trip->encoded_polyline && count(decodePolyline($trip->encoded_polyline)) >= 2) {
            $watch->off_route_hits = isDriverDeviated($trip->encoded_polyline, $lat, $lng, $s['off_route_m']) ? $watch->off_route_hits + 1 : 0;
            $offRoute = $watch->off_route_hits >= $s['off_route_times'];
        }

        // Far too long: twice the estimate (at least 10 minutes past it).
        $startedAt = $trip->tripStatus?->ongoing ? Carbon::parse($trip->tripStatus->ongoing) : null;
        $estimate = (float)($trip->time?->estimated_time ?? 0); // minutes, in trip_request_times
        $tooLong = !$watch->too_long_asked && $startedAt && $estimate > 0
            && $now->gte($startedAt->copy()->addMinutes((int)max($estimate * $s['long_factor'], $estimate + 10)));

        $quiet = ($watch->quiet_until && $watch->quiet_until->gt($now))
            || TripCheckin::where('trip_request_id', $trip->id)->whereIn('status', [TripCheckin::ASKED, TripCheckin::HELP, TripCheckin::NO_ANSWER])->exists();
        $reason = !$quiet ? ($stopped ? 'stopped' : ($offRoute ? 'off_route' : ($tooLong ? 'too_long' : null))) : null;
        if ($reason === 'stopped') {
            $watch->anchor_at = $now; // the next question needs another full stop
        } elseif ($reason === 'off_route') {
            $watch->off_route_hits = 0;
        } elseif ($reason === 'too_long') {
            $watch->too_long_asked = true;
        }
        $watch->save();

        if (!$reason) {
            return false;
        }
        TripCheckin::create([
            'trip_request_id' => $trip->id, 'customer_id' => $trip->customer_id, 'driver_id' => $trip->driver_id,
            'reason' => $reason, 'status' => TripCheckin::ASKED, 'latitude' => $lat, 'longitude' => $lng, 'asked_at' => $now,
        ]);
        $this->push($trip->customer, $trip, 'trip_checkin', translate('Is everything OK on your trip'),
            translate('Tap to tell E-Ride you are OK, or to ask for help. Without an answer, E-Ride and your trusted contacts are alerted.'));
        return true;
    }

    /** The rider's answer. "help" raises the alert at once. */
    public function answer(TripCheckin $checkin, string $answer): TripCheckin
    {
        // "I'm OK" after the alert went out: noted on the alert for staff, and the contacts told.
        if ($checkin->status === TripCheckin::NO_ANSWER && $answer === 'ok' && !$checkin->answered_at) {
            $checkin->update(['status' => TripCheckin::OK, 'answered_at' => now()]);
            TripWatch::where('trip_request_id', $checkin->trip_request_id)->update(['quiet_until' => now()->addMinutes(CheckinSettings::all()['quiet_minutes'])]);
            $alert = $checkin->safety_alert_id ? SafetyAlert::find($checkin->safety_alert_id) : null;
            if ($alert) {
                $alert->update(['comment' => trim(($alert->comment ? $alert->comment . "\n" : '') . translate(key: 'Rider answered I am OK at {time}, after the alert.', replace: ['time' => now()->format('H:i')]))]);
            }
            $trip = TripRequest::with('customer')->find($checkin->trip_request_id);
            if ($trip) {
                $this->tellContacts($trip, TrustedContact::where('user_id', $trip->customer_id)->get(), 'trip_checkin_contact',
                    translate(key: '{name} is OK', replace: ['name' => $trip->customer?->first_name]),
                    translate(key: '{name} has now confirmed they are OK on their E-Ride trip.', replace: ['name' => $trip->customer?->first_name]));
            }
            return $checkin;
        }
        if ($checkin->status !== TripCheckin::ASKED) {
            return $checkin;
        }
        if ($answer === 'ok') {
            $checkin->update(['status' => TripCheckin::OK, 'answered_at' => now()]);
            TripWatch::where('trip_request_id', $checkin->trip_request_id)->update(['quiet_until' => now()->addMinutes(CheckinSettings::all()['quiet_minutes'])]);
            return $checkin;
        }
        $checkin->update(['status' => TripCheckin::HELP, 'answered_at' => now()]);
        $this->escalate($checkin, 'help', now());
        return $checkin->fresh();
    }

    /** A safety alert for E-Ride (the SOS list) and the trusted contacts told. */
    protected function escalate(TripCheckin $checkin, string $why, Carbon $now): void
    {
        $trip = TripRequest::with(['customer', 'driver'])->find($checkin->trip_request_id);
        if (!$trip) {
            return;
        }
        $contacts = TrustedContact::where('user_id', $trip->customer_id)->orderBy('created_at')->get();
        $reason = $why === 'help' ? 'Rider asked for help at a trip check-in' : 'Rider did not answer a trip check-in';
        $comment = translate(key: 'Check-in ({reason}). Trusted contacts: {contacts}', replace: [
            'reason' => TripCheckin::reasonLabel($checkin->reason),
            'contacts' => $contacts->isEmpty() ? translate('none') : $contacts->map(fn($c) => $c->name . ' ' . $c->phone)->implode(', '),
        ]);
        // One alert per trip and rider, as SOS does: a second problem adds to it.
        $alert = SafetyAlert::where('trip_request_id', $trip->id)->where('sent_by', $trip->customer_id)->first();
        if ($alert) {
            $alert->update(['number_of_alert' => (int)$alert->number_of_alert + 1, 'status' => 'pending',
                'comment' => trim(($alert->comment ? $alert->comment . "\n" : '') . $comment)]);
        } else {
            $alert = SafetyAlert::create([
                'trip_request_id' => $trip->id, 'sent_by' => $trip->customer_id,
                'alert_location' => $this->address($checkin->latitude, $checkin->longitude),
                'trip_status_when_make_alert' => $trip->current_status, 'reason' => [$reason], 'comment' => $comment,
            ]);
        }
        $checkin->update(['escalated_at' => $now, 'safety_alert_id' => $alert->id]);
        $this->safely(fn() => sendTopicNotification(topic: 'admin_safety_alert_notification', title: translate('new_safety_alert'),
            description: translate($reason), type: 'customer', sentBy: $trip->customer_id, tripReferenceId: $trip->ref_id,
            route: route('admin.safety-alert.index', ['type' => 'customer'])));
        $this->tellContacts($trip, $contacts, 'trip_checkin_contact',
            translate(key: '{name} may need help', replace: ['name' => $trip->customer?->first_name]),
            translate(key: '{name} did not confirm they are OK on an E-Ride trip. E-Ride is checking. Follow the trip live: {link}', replace: [
                'name' => $trip->customer?->first_name, 'link' => app(TripShareService::class)->url($trip) ?? '',
            ]));
    }

    /** True if the night contact was told (only possible automatically when they use E-Ride). */
    protected function nightShare(TripRequest $trip, Carbon $now): bool
    {
        $watch = TripWatch::firstOrCreate(['trip_request_id' => $trip->id]);
        if ($watch->night_shared_at) {
            return false;
        }
        $contact = TrustedContact::where('user_id', $trip->customer_id)->where('night_share', 1)->first();
        $link = app(TripShareService::class)->url($trip); // null while trip sharing is switched off
        if (!$contact || !$link) {
            return false;
        }
        $watch->update(['night_shared_at' => $now]);
        app(TripShareService::class)->markShared($trip);
        return $this->tellContacts($trip, collect([$contact]), 'night_ride_shared',
            translate(key: '{name} is on an E-Ride night ride', replace: ['name' => $trip->customer?->first_name]),
            translate(key: 'Follow the ride of {name} live until they arrive: {link}', replace: ['name' => $trip->customer?->first_name, 'link' => $link])) > 0;
    }

    /** Pushes to the contacts who use E-Ride (found by their number); the others are on the safety alert for staff to call. */
    protected function tellContacts(TripRequest $trip, $contacts, string $action, string $title, string $body): int
    {
        $told = 0;
        foreach ($contacts as $contact) {
            $digits = substr(preg_replace('/\D/', '', (string)$contact->phone), -9);
            if (strlen($digits) < 9) {
                continue;
            }
            $user = User::where('user_type', CUSTOMER)->where('phone', 'like', '%' . $digits)->where('id', '!=', $trip->customer_id)->first();
            if ($user) {
                $this->push($user, $trip, $action, $title, $body);
                $told++;
            }
        }
        return $told;
    }

    public function active(string $tripId, string $customerId): ?TripCheckin
    {
        return TripCheckin::where('trip_request_id', $tripId)->where('customer_id', $customerId)->where('status', TripCheckin::ASKED)->latest('asked_at')->first();
    }

    protected function push(?User $user, TripRequest $trip, string $action, string $title, string $body): void
    {
        if (!$user?->fcm_token) {
            return;
        }
        $this->safely(fn() => sendDeviceNotification(fcm_token: $user->fcm_token, title: $title, description: $body, status: 1,
            ride_request_id: $trip->id, type: $trip->type, notification_type: 'trip', action: $action, user_id: $user->id));
    }

    protected function address(?float $lat, ?float $lng): string
    {
        if ($lat === null || $lng === null) {
            return 'N/A';
        }
        try {
            $key = businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? null;
            $r = Http::timeout(8)->get(MAP_API_BASE_URI . '/geocode/json?latlng=' . $lat . ',' . $lng . '&key=' . $key);
            return json_decode($r->body())->results[0]->formatted_address ?? "{$lat}, {$lng}";
        } catch (Throwable) {
            return "{$lat}, {$lng}";
        }
    }

    protected function safely(callable $step): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            Log::warning('Trip check-in step failed', ['error' => $e->getMessage()]);
        }
    }
}
