<?php

namespace Modules\TripManagement\Service;

use App\Events\CustomerTripCancelledEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\TripManagement\Entities\TempTripNotification;
use Modules\TripManagement\Entities\TripDeposit;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Lib\DepositSettings;
use Modules\UserManagement\Entities\User;
use Modules\ZoneManagement\Entities\DangerReport;
use Modules\ZoneManagement\Entities\SafetyArea;
use Modules\ZoneManagement\Lib\SafetySettings;
use Modules\ZoneManagement\Service\SafetyService;
use Throwable;

/**
 * Scheduled rides with the E-Ride features, run every minute by scheduled-rides:tick:
 *  - the booking deposit, when the admin asks it a set time before the pickup;
 *  - safety: an area pause never blocks a scheduled ride. Rider and driver get a
 *    heads-up before the pickup (and the rider at booking when a pause covers that time);
 *  - "still looking for a driver" shortly before the pickup;
 *  - a ride no driver took is cancelled some minutes after its time, deposit refunded.
 * What was sent is kept in trip_requests.schedule_marks so each goes out once.
 */
class ScheduledRideService
{
    /** "Still looking for a driver" goes out this many minutes before the pickup. */
    public const LOOKING_MINUTES = 15;

    public function __construct(protected TripDepositService $deposits, protected SafetyService $safety)
    {
    }

    public function tick(?Carbon $now = null): array
    {
        $now ??= now();
        $c = ['deposits_asked' => 0, 'alerts' => 0, 'still_looking' => 0, 'no_driver' => 0];
        $c['deposits_asked'] = $this->askDeposits($now);
        $c['still_looking'] = $this->stillLooking($now);
        $c['no_driver'] = $this->cancelUntaken($now);
        foreach ($this->upcoming($now, SafetySettings::all()['scheduled_alert_minutes']) as $trip) {
            $c['alerts'] += $this->safetyCheck($trip, $now);
        }
        return $c;
    }

    // --------------------------------------------------------------- deposit

    /** 'before_pickup' timing: ask the deposit of accepted scheduled rides whose time came. */
    protected function askDeposits(Carbon $now): int
    {
        $s = DepositSettings::all();
        if (!$s['status'] || $s['scheduled_timing'] !== 'before_pickup') {
            return 0;
        }
        $n = 0;
        $trips = TripRequest::with('customer')->where('ride_request_type', SCHEDULED)->whereIn('current_status', [ACCEPTED, OUT_FOR_PICKUP])
            ->whereNotNull('driver_id')->where('scheduled_at', '<=', $this->stamp($now->copy()->addMinutes($s['scheduled_before_minutes'])))
            ->whereDoesntHave('deposit')->limit(100)->get();
        foreach ($trips as $trip) {
            $this->safely(function () use ($trip, &$n) {
                $this->deposits->openScheduledDue($trip) && $n++;
            });
        }
        return $n;
    }

    // ---------------------------------------------------------------- driver

    /** Shortly before the pickup and still no driver: tell the rider we are on it. */
    protected function stillLooking(Carbon $now): int
    {
        $n = 0;
        foreach ($this->untaken()->where('scheduled_at', '<=', $this->stamp($now->copy()->addMinutes(self::LOOKING_MINUTES)))
                     ->where('scheduled_at', '>', $this->stamp($now))->with('customer')->limit(100)->get() as $trip) {
            if ($this->marked($trip, 'still_looking') || $this->deposits->awaitingAtBooking($trip)) {
                continue;
            }
            $this->mark($trip, 'still_looking', true);
            $this->push($trip->customer, $trip, 'scheduled_still_looking', 'Still looking for a driver',
                'We are still looking for a driver for your ride at {time}. We will tell you as soon as one accepts.',
                ['time' => $this->time($trip)]);
            $n++;
        }
        return $n;
    }

    /** No driver took it, some minutes past its time: cancelled, and a paid deposit refunded. */
    protected function cancelUntaken(Carbon $now): int
    {
        $grace = DepositSettings::all()['scheduled_no_driver_minutes'];
        $n = 0;
        foreach ($this->untaken()->where('scheduled_at', '<=', $this->stamp($now->copy()->subMinutes($grace)))->limit(100)->get() as $trip) {
            $this->safely(function () use ($trip, &$n) {
                $cancelled = DB::transaction(function () use ($trip) {
                    $locked = TripRequest::whereKey($trip->id)->lockForUpdate()->first();
                    if (!$locked || $locked->current_status !== PENDING || $locked->driver_id) {
                        return false;
                    }
                    $locked->current_status = CANCELLED;
                    $locked->trip_cancellation_reason = 'No driver accepted the scheduled ride';
                    $locked->save();
                    $locked->tripStatus()->update([CANCELLED => now()]);
                    $locked->fee()->update(['cancelled_by' => 'admin']);
                    return true;
                });
                if (!$cancelled) {
                    return;
                }
                $n++;
                $deposit = $this->deposits->onTripCancelled($trip->id, 'no_driver', PENDING);
                $trip = TripRequest::with(['customer', 'tempNotifications.user'])->find($trip->id);
                // A refunded deposit has its own push (with the amount); otherwise this one.
                if (!$deposit || $deposit->status !== TripDeposit::REFUNDED) {
                    $this->push($trip->customer, $trip, 'scheduled_no_driver', 'No driver for your scheduled ride',
                        'No driver could take your ride at {time}, so it was cancelled. You can book a ride now.', ['time' => $this->time($trip)]);
                }
                // Drivers who were shown the request: it is gone.
                foreach ($trip->tempNotifications as $temp) {
                    try {
                        $temp->user && checkReverbConnection() && CustomerTripCancelledEvent::broadcast($temp->user, $trip);
                    } catch (Throwable) {
                    }
                }
                TempTripNotification::where('trip_request_id', $trip->id)->delete();
            });
        }
        return $n;
    }

    // ------------------------------------------------------------ the time

    /**
     * Why a scheduled ride cannot be booked (or moved) to this pickup time, or null: the
     * admin's limits in Business setup > Trips > Scheduled trips (shortest notice, how far
     * ahead). The app's date picker keeps to them; this keeps any app to them.
     */
    public function timeProblem(?string $scheduledAt): ?string
    {
        if (!businessConfig('schedule_trip_status')?->value) {
            return translate('Scheduled rides are not available right now.');
        }
        try {
            $at = Carbon::parse((string)$scheduledAt);
        } catch (Throwable) {
            return translate('Choose a valid pickup time.');
        }
        $get = fn(string $key) => businessConfig($key, SCHEDULE_TRIP_SETTINGS)?->value;
        // A little slack: the rider picked the time a moment before sending it.
        $slack = 120;
        $min = (int)$get('minimum_schedule_book_time');
        $minType = (string)($get('minimum_schedule_book_time_type') ?: 'minute');
        if ($min > 0 && $at->lt(now()->addSeconds((int)convertTimeToSecond($min, $minType) - $slack))) {
            return translate(key: 'Book at least {time} ahead.', replace: ['time' => $this->span($min, $minType)]);
        }
        if ($at->lt(now()->subSeconds($slack))) {
            return translate('Choose a pickup time in the future.');
        }
        $max = (int)$get('advance_schedule_book_time');
        $maxType = (string)($get('advance_schedule_book_time_type') ?: 'day');
        if ($max > 0 && $at->gt(now()->addSeconds((int)convertTimeToSecond($max, $maxType) + $slack))) {
            return translate(key: 'You can book up to {time} ahead.', replace: ['time' => $this->span($max, $maxType)]);
        }
        return null;
    }

    /** "30 minutes", "1 hour", "7 days", in the rider's language. */
    protected function span(int $n, string $type): string
    {
        $unit = in_array($type, ['minute', 'hour', 'day'], true) ? $type : 'minute';
        return translate(key: '{n} ' . $unit . ($n === 1 ? '' : 's'), replace: ['n' => $n]);
    }

    // ---------------------------------------------------------------- safety

    /**
     * Heads-up about area pauses, no-go areas and fresh danger reports at the pickup or
     * the destination of a trip: to the rider, and to the driver once one has it.
     * A scheduled ride: at booking ($trip given as an id, nothing near yet) only pauses that
     * cover the pickup time are told, so the rider can plan; the same pause is told again
     * near the time. A ride for now: when a driver accepts ($only = ['driver']; the rider
     * was warned before booking).
     *
     * @param array|null $only 'customer' and/or 'driver': tell only them
     * @return int pushes sent
     */
    public function safetyCheck(TripRequest|string $trip, ?Carbon $now = null, ?array $only = null): int
    {
        $now ??= now();
        if (!SafetySettings::all()['enabled']) {
            return 0;
        }
        $trip = $trip instanceof TripRequest ? $trip : TripRequest::find($trip);
        $trip?->loadMissing(['coordinate', 'customer', 'driver']);
        if (!$trip || !$trip->coordinate || $trip->type != RIDE_REQUEST
            || !in_array($trip->current_status, [PENDING, ACCEPTED, OUT_FOR_PICKUP])) {
            return 0;
        }
        $scheduled = $trip->ride_request_type == SCHEDULED && $trip->scheduled_at;
        $at = $scheduled ? Carbon::parse($trip->scheduled_at) : $now->copy();
        $near = !$scheduled || $at->lte($now->copy()->addMinutes(SafetySettings::all()['scheduled_alert_minutes']));
        $points = [$trip->coordinate->pickup_coordinates, $trip->coordinate->destination_coordinates];

        // Pauses and no-go areas in force at the pickup time (or now, when it is close).
        $areas = SafetyArea::whereNull('lifted_at')
            ->where(fn($q) => $q->where(fn($w) => $w->where('starts_at', '<=', $at)->where('ends_at', '>', $at))
                ->when($near, fn($q) => $q->orWhere(fn($w) => $w->where('starts_at', '<=', $now)->where('ends_at', '>', $now))))
            ->get()
            ->filter(fn(SafetyArea $a) => collect($points)->contains(fn($p) => $p && $this->safety->covers($a, $p->latitude, $p->longitude)));

        $sent = 0;
        $who = $near ? ['customer' => $trip->customer, 'driver' => $trip->driver] : ['booking' => $trip->customer];
        foreach ($who as $mark => $user) {
            if (!$user || ($only !== null && !in_array($mark === 'booking' ? 'customer' : $mark, $only))) {
                continue;
            }
            foreach ($areas as $area) {
                if (in_array($area->id, (array)$this->marked($trip, 'safety_' . $mark))) {
                    continue;
                }
                $this->mark($trip, 'safety_' . $mark, array_merge((array)$this->marked($trip, 'safety_' . $mark), [$area->id]));
                $sent += $this->pushArea($user, $trip, $area, $mark === 'driver');
            }
        }

        // Fresh danger reports near the pickup or destination, close to the time.
        if ($near) {
            $settings = SafetySettings::all();
            $reports = DangerReport::whereIn('status', [DangerReport::OPEN, DangerReport::CONFIRMED])->whereNull('safety_area_id')
                ->where('created_at', '>=', $now->copy()->subMinutes($settings['report_fade_min']))->get()
                ->filter(fn(DangerReport $r) => collect($points)->contains(fn($p) => $p
                    && haversineDistance($r->latitude, $r->longitude, $p->latitude, $p->longitude) <= $settings['caution_radius_km'] * 1000));
            if ($reports->isNotEmpty()) {
                $what = $reports->map(fn(DangerReport $r) => DangerReport::label($r->type))->unique()->implode(', ');
                foreach (['customer' => $trip->customer, 'driver' => $trip->driver] as $mark => $user) {
                    $new = $reports->pluck('id')->diff((array)$this->marked($trip, 'reports_' . $mark));
                    if (!$user || $new->isEmpty() || ($only !== null && !in_array($mark, $only))) {
                        continue;
                    }
                    $this->mark($trip, 'reports_' . $mark, array_values(array_merge((array)$this->marked($trip, 'reports_' . $mark), $new->all())));
                    $this->push($user, $trip, 'scheduled_safety_alert', $scheduled ? 'Heads-up for your scheduled ride' : 'Heads-up for your ride',
                        match (true) {
                            $mark === 'driver' && $scheduled => 'Reported near the pickup or destination of your ride at {time}: {what}. Stay alert and agree a safe meeting point with the customer.',
                            $mark === 'driver' => 'Reported near the pickup or destination of this ride: {what}. Stay alert and agree a safe meeting point with the customer.',
                            $scheduled => 'Reported near your pickup or destination: {what}. Your ride at {time} still goes ahead. Stay alert and agree a safe meeting point with your driver.',
                            default => 'Reported near your pickup or destination: {what}. Stay alert and agree a safe meeting point with your driver.',
                        },
                        ['time' => $scheduled ? $this->time($trip) : '', 'what' => $what]);
                    $sent++;
                }
            }
        }
        return $sent;
    }

    protected function pushArea(User $user, TripRequest $trip, SafetyArea $area, bool $driver): int
    {
        $vars = ['place' => $area->title, 'reason' => $area->reason, 'until' => $this->when($area->ends_at)];
        if ($trip->ride_request_type != SCHEDULED || !$trip->scheduled_at) {
            // A ride for now (only areas in force now reach here).
            $this->push($user, $trip, 'scheduled_safety_alert', 'Heads-up for your ride', $driver
                ? 'This ride goes to or from {place}, where E-Ride has paused trips until {until}: {reason}. Stay alert and agree a safe meeting point with the customer.'
                : 'Your ride goes to or from {place}, where E-Ride has paused trips until {until}: {reason}. Stay alert and agree a safe meeting point with your driver. You can cancel for free.', $vars);
            return 1;
        }
        $planned = $area->starts_at->isFuture();
        $vars += ['time' => $this->time($trip), 'from' => $this->when($area->starts_at)];
        $body = match (true) {
            $driver && $planned => 'Your ride at {time} goes to or from {place}, where E-Ride pauses trips from {from} to {until}: {reason}. It still goes ahead. Stay alert and agree a safe meeting point with the customer.',
            $driver => 'Your ride at {time} goes to or from {place}, where E-Ride has paused trips until {until}: {reason}. It still goes ahead. Stay alert and agree a safe meeting point with the customer.',
            $planned => 'E-Ride pauses trips around {place} from {from} to {until}: {reason}. Your ride at {time} still goes ahead. Stay alert and agree a safe meeting point with your driver, or change the time.',
            default => 'E-Ride has paused trips around {place} until {until}: {reason}. Your ride at {time} still goes ahead. Stay alert and agree a safe meeting point with your driver.',
        };
        $this->push($user, $trip, 'scheduled_safety_alert', 'Heads-up for your scheduled ride', $body, $vars);
        return 1;
    }

    // --------------------------------------------------------------- helpers

    /** Scheduled rides due within $minutes (and not past), pending or taken. */
    protected function upcoming(Carbon $now, int $minutes): Collection
    {
        return TripRequest::with(['coordinate', 'customer', 'driver'])->where('ride_request_type', SCHEDULED)
            ->whereIn('current_status', [PENDING, ACCEPTED, OUT_FOR_PICKUP])
            ->where('scheduled_at', '>', $this->stamp($now->copy()->subMinutes(5)))
            ->where('scheduled_at', '<=', $this->stamp($now->copy()->addMinutes($minutes)))
            ->limit(500)->get();
    }

    protected function untaken()
    {
        return TripRequest::where('ride_request_type', SCHEDULED)->where('current_status', PENDING)->whereNull('driver_id')->whereNotNull('scheduled_at');
    }

    /** scheduled_at is a text column ("2026-11-20 14:30:00.000"): compared as text in that shape. */
    protected function stamp(Carbon $at): string
    {
        return $at->format('Y-m-d H:i:s');
    }

    protected function marked(TripRequest $trip, string $key): mixed
    {
        return ($trip->schedule_marks ?? [])[$key] ?? null;
    }

    protected function mark(TripRequest $trip, string $key, mixed $value): void
    {
        $marks = $trip->schedule_marks ?? [];
        $marks[$key] = $value;
        $trip->schedule_marks = $marks;
        TripRequest::whereKey($trip->id)->update(['schedule_marks' => json_encode($marks)]);
    }

    protected function time(TripRequest $trip): string
    {
        return $this->when(Carbon::parse($trip->scheduled_at));
    }

    /** "16:00" today, else "Mon 20 Nov, 16:00". */
    protected function when(Carbon $at): string
    {
        return $at->isToday() ? $at->format('H:i') : $at->format('D d M, H:i');
    }

    protected function push(?User $user, TripRequest $trip, string $action, string $title, string $body, array $replace = []): void
    {
        if (!$user?->fcm_token) {
            return;
        }
        $this->safely(function () use ($user, $trip, $action, $title, $body, $replace) {
            $locale = $user->current_language_key;
            sendDeviceNotification(
                fcm_token: $user->fcm_token,
                title: translate(key: $title, replace: $replace, locale: $locale),
                description: translate(key: $body, replace: $replace, locale: $locale),
                status: 1,
                ride_request_id: $trip->id,
                type: $trip->type,
                notification_type: 'trip',
                action: $action,
                user_id: $user->id,
            );
        });
    }

    protected function safely(callable $step): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            Log::warning('Scheduled ride step failed', ['error' => $e->getMessage()]);
        }
    }
}
