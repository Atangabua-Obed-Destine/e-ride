<?php

namespace Modules\ZoneManagement\Service;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\AdminModule\Entities\AdminNotification;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserLastLocation;
use Modules\ZoneManagement\Entities\DangerReport;
use Modules\ZoneManagement\Entities\SafetyArea;
use Modules\ZoneManagement\Entities\Zone;
use Modules\ZoneManagement\Lib\SafetySettings;
use Throwable;

/**
 * Area pause, no-go areas and danger reports. A report always warns (E-Ride and
 * nearby drivers); only repeated driver reports or an admin block an area. A
 * blocked area refuses new trips with a pickup or a destination inside it.
 */
class SafetyService
{
    /** Live positions older than this are not "near" anymore. */
    public const FRESH_DRIVER_MIN = 10;
    public const FRESH_CUSTOMER_MIN = 60;
    /** Planned pauses are announced this long before they start. */
    public const PRE_NOTIFY_HOURS = 12;

    // ------------------------------------------------------------- reports

    public function report(User $user, string $type, float $lat, float $lng, ?string $place, ?string $note, ?string $tripId = null): DangerReport
    {
        $settings = SafetySettings::all();
        if (!$settings['enabled']) {
            $this->fail(translate('Danger reports are not available now.'));
        }
        $recent = DangerReport::where('user_id', $user->id)->where('created_at', '>=', now()->subHour())->count();
        if ($recent >= $settings['reports_per_hour']) {
            $this->fail(translate('You sent several reports in the last hour. E-Ride is looking into them.'));
        }
        $report = DangerReport::create([
            'user_id' => $user->id,
            'role' => $user->user_type === DRIVER ? DRIVER : CUSTOMER,
            'type' => $type,
            'place' => $place ? mb_substr(trim($place), 0, 120) : null,
            'note' => $note ? trim($note) : null,
            'latitude' => $lat,
            'longitude' => $lng,
            'zone_id' => $this->zoneAt($lat, $lng)?->id,
            'trip_request_id' => $tripId,
            'status' => DangerReport::OPEN,
        ]);

        $covering = $this->blockingArea($lat, $lng);
        if ($covering) {
            $report->update(['safety_area_id' => $covering->id]);
        }
        $this->alertAdmins($report);
        $this->cautionDrivers($report, $settings);
        if ($report->role === DRIVER && !$covering) {
            $this->maybeAutoNoGo($report, $settings);
        }
        return $report->fresh();
    }

    /** Two drivers or more near the same spot in a short time: block it for a while, until an admin decides. */
    protected function maybeAutoNoGo(DangerReport $report, array $settings): ?SafetyArea
    {
        $near = DangerReport::where('role', DRIVER)->where('status', DangerReport::OPEN)->whereNull('safety_area_id')
            ->where('created_at', '>=', now()->subMinutes($settings['auto_window_min']))->get()
            ->filter(fn(DangerReport $r) => haversineDistance($r->latitude, $r->longitude, $report->latitude, $report->longitude) <= $settings['auto_radius_m']);
        if ($near->pluck('user_id')->unique()->count() < $settings['auto_reports']) {
            return null;
        }
        $area = SafetyArea::create([
            'kind' => SafetyArea::NO_GO,
            'source' => 'auto',
            'latitude' => round($near->avg('latitude'), 7),
            'longitude' => round($near->avg('longitude'), 7),
            'radius_m' => $settings['auto_radius_m'],
            'title' => $near->pluck('place')->filter()->first() ?? ($this->zoneAt($report->latitude, $report->longitude)?->name ?? translate('Reported area')),
            'reason' => DangerReport::label($near->groupBy('type')->sortByDesc(fn($g) => $g->count())->keys()->first()),
            'starts_at' => now(),
            'ends_at' => now()->addMinutes($settings['auto_minutes']),
            'confirmed' => false,
        ]);
        DangerReport::whereIn('id', $near->pluck('id'))->update(['safety_area_id' => $area->id]);
        $this->safely(fn() => AdminNotification::create([
            'model' => 'safety_area', 'model_id' => $area->id,
            // A fixed text: the bell translates it, so no place names in it (each would become a language key).
            'message' => 'Automatic no-go area: confirm or lift it',
        ]));
        $this->notifyArea($area);
        return $area;
    }

    // ------------------------------------------------------------- blocking

    /** The area in force that covers this point, if any. */
    public function blockingArea(float $lat, float $lng, ?Carbon $at = null): ?SafetyArea
    {
        if (!SafetySettings::all()['enabled']) {
            return null;
        }
        foreach (SafetyArea::active($at)->orderBy('ends_at', 'desc')->get() as $area) {
            if ($this->covers($area, $lat, $lng)) {
                return $area;
            }
        }
        return null;
    }

    /** Why a trip from $pickup to $destination cannot be booked now, or null. */
    public function tripProblem(Point $pickup, Point $destination): ?string
    {
        foreach ([[$pickup, 'pickup'], [$destination, 'destination']] as [$point, $which]) {
            $area = $this->blockingArea($point->latitude, $point->longitude);
            if ($area) {
                $vars = ['place' => $area->title, 'time' => $this->when($area->ends_at), 'reason' => $area->reason];
                return $which === 'pickup'
                    ? translate(key: 'E-Ride is paused around {place} until {time}: {reason}. Choose a pickup point outside this area.', replace: $vars)
                    : translate(key: 'E-Ride is paused around {place} until {time}: {reason}. Choose a destination outside this area.', replace: $vars);
            }
        }
        return null;
    }

    public function covers(SafetyArea $area, float $lat, float $lng): bool
    {
        if ($area->zone_id) {
            return Zone::whereKey($area->zone_id)->whereContains('coordinates', new Point($lat, $lng))->exists();
        }
        return $area->radius_m !== null
            && haversineDistance($area->latitude, $area->longitude, $lat, $lng) <= $area->radius_m;
    }

    /** Banners for the apps: areas in force and fresh reports near this point. */
    public function nearby(float $lat, float $lng, bool $forDriver): array
    {
        $settings = SafetySettings::all();
        if (!$settings['enabled']) {
            return ['areas' => [], 'reports' => []];
        }
        $reach = $settings['banner_radius_km'] * 1000;
        $areas = SafetyArea::active()->get()->filter(fn(SafetyArea $a) => $a->zone_id
            ? $this->covers($a, $lat, $lng)
            : haversineDistance($a->latitude, $a->longitude, $lat, $lng) - $a->radius_m <= $reach)
            ->values()->map(fn(SafetyArea $a) => $this->presentArea($a, $lat, $lng))->all();
        // Upcoming planned pauses (the next 12 hours) so people can plan.
        $soon = SafetyArea::whereNull('lifted_at')->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHours(self::PRE_NOTIFY_HOURS))->get()
            ->filter(fn(SafetyArea $a) => $a->zone_id ? $this->covers($a, $lat, $lng) : haversineDistance($a->latitude, $a->longitude, $lat, $lng) - $a->radius_m <= $reach)
            ->values()->map(fn(SafetyArea $a) => $this->presentArea($a, $lat, $lng))->all();
        $reports = DangerReport::whereIn('status', [DangerReport::OPEN, DangerReport::CONFIRMED])->whereNull('safety_area_id')
            ->where('created_at', '>=', now()->subMinutes($settings['report_fade_min']))->get()
            ->filter(fn(DangerReport $r) => haversineDistance($r->latitude, $r->longitude, $lat, $lng) <= $settings['caution_radius_km'] * 1000)
            // Riders see reports only once someone at E-Ride confirmed them; drivers see every fresh one.
            ->filter(fn(DangerReport $r) => $forDriver || $r->status === DangerReport::CONFIRMED)
            ->values()->map(fn(DangerReport $r) => [
                'id' => $r->id, 'type' => $r->type, 'place' => $r->place,
                'distance_m' => (int)round(haversineDistance($r->latitude, $r->longitude, $lat, $lng)),
                'minutes_ago' => (int)$r->created_at->diffInMinutes(now()),
            ])->all();
        return ['areas' => $areas, 'upcoming' => $soon, 'reports' => $reports];
    }

    public function presentArea(SafetyArea $a, ?float $lat = null, ?float $lng = null): array
    {
        return [
            'id' => $a->id,
            'kind' => $a->kind,
            'title' => $a->title,
            'reason' => $a->reason,
            'whole_zone' => (bool)$a->zone_id,
            'latitude' => $a->latitude,
            'longitude' => $a->longitude,
            'radius_m' => $a->radius_m,
            'starts_at' => $a->starts_at->toIso8601String(),
            'ends_at' => $a->ends_at->toIso8601String(),
            'confirmed' => $a->confirmed,
            'inside' => $lat !== null && $this->covers($a, $lat, $lng),
        ];
    }

    // ------------------------------------------------------------- admin

    /** A pause for a zone or a circle, now or planned. */
    public function pause(User $admin, array $data): SafetyArea
    {
        $zoneId = $data['zone_id'] ?? null; // a whole zone, else a circle
        $area = SafetyArea::create([
            'kind' => $data['kind'] ?? SafetyArea::PAUSE,
            'source' => 'admin',
            'zone_id' => $zoneId,
            'latitude' => $zoneId ? null : $data['latitude'],
            'longitude' => $zoneId ? null : $data['longitude'],
            'radius_m' => $zoneId ? null : (int)$data['radius_m'],
            'title' => $data['title'],
            'reason' => $data['reason'],
            'starts_at' => Carbon::parse($data['starts_at'] ?? now()),
            'ends_at' => Carbon::parse($data['ends_at']),
            'confirmed' => true,
            'created_by' => $admin->id,
        ]);
        if ($area->starts_at->lte(now())) {
            $this->notifyArea($area);
        }
        return $area;
    }

    /** Confirm an automatic no-go area, optionally for longer and wider. */
    public function confirm(SafetyArea $area, User $admin, ?int $minutes = null, ?int $radius = null, ?string $reason = null): SafetyArea
    {
        $area->update(array_filter([
            'confirmed' => true,
            'ends_at' => $minutes ? now()->addMinutes($minutes) : null,
            'radius_m' => $radius && !$area->zone_id ? $radius : null,
            'reason' => $reason ?: null,
            'created_by' => $area->created_by ?? $admin->id,
        ], fn($v) => $v !== null));
        $area->reports()->where('status', DangerReport::OPEN)->update(['status' => DangerReport::CONFIRMED, 'handled_by' => $admin->id, 'handled_at' => now()]);
        return $area;
    }

    public function lift(SafetyArea $area, User $admin): SafetyArea
    {
        $wasActive = $area->isActive();
        $area->update(['lifted_at' => now(), 'created_by' => $area->created_by ?? $admin->id]);
        if ($wasActive && $area->notified_at) {
            $this->broadcast($area, 'area_lifted', translate('Service is back'),
                translate(key: 'E-Ride trips around {place} are running again.', replace: ['place' => $area->title]));
        }
        return $area;
    }

    public function decideReport(DangerReport $report, User $admin, string $status): DangerReport
    {
        $report->update(['status' => $status, 'handled_by' => $admin->id, 'handled_at' => now()]);
        return $report;
    }

    // ------------------------------------------------------------- the clock

    /** Every minute: start planned pauses with a push, announce upcoming ones, let old reports fade. */
    public function tick(?Carbon $now = null): array
    {
        $now ??= now();
        $c = ['started' => 0, 'announced' => 0, 'faded' => 0];
        if (!SafetySettings::all()['enabled']) {
            return $c;
        }
        foreach (SafetyArea::active($now)->whereNull('notified_at')->get() as $area) {
            $this->notifyArea($area);
            $c['started']++;
        }
        $soon = SafetyArea::whereNull('lifted_at')->whereNull('pre_notified_at')->whereNull('notified_at')
            ->where('starts_at', '>', $now->copy()->addMinutes(30))->where('starts_at', '<=', $now->copy()->addHours(self::PRE_NOTIFY_HOURS))->get();
        foreach ($soon as $area) {
            $area->update(['pre_notified_at' => $now]);
            $this->broadcast($area, 'area_pause_planned', translate('Service pause planned'),
                translate(key: 'E-Ride will pause trips around {place} from {from} to {to}: {reason}.', replace: [
                    'place' => $area->title, 'from' => $this->when($area->starts_at), 'to' => $this->when($area->ends_at), 'reason' => $area->reason,
                ]));
            $c['announced']++;
        }
        $c['faded'] = DangerReport::where('status', DangerReport::OPEN)
            ->where('created_at', '<', $now->copy()->subMinutes(SafetySettings::all()['report_fade_min']))
            ->update(['status' => DangerReport::EXPIRED]);
        return $c;
    }

    // ------------------------------------------------------------- telling people

    protected function alertAdmins(DangerReport $report): void
    {
        $what = DangerReport::label($report->type);
        $where = $report->place ?: ($report->zone?->name ?? '');
        // A fixed text (the bell translates it); what and where are on the Safety map.
        $this->safely(fn() => AdminNotification::create([
            'model' => 'danger_report', 'model_id' => $report->id, 'message' => 'New danger report',
        ]));
        // The same channel as SOS alerts, so the admin panel rings at once.
        $this->safely(fn() => sendTopicNotification(topic: 'admin_safety_alert_notification', title: translate('Danger reported'),
            description: $what . ($where ? ' · ' . $where : ''), type: 'danger_report', action: 'danger_report'));
    }

    protected function cautionDrivers(DangerReport $report, array $settings): void
    {
        $title = translate('Caution near you');
        $body = translate(key: '{what} reported {where}, just now. Avoid the area if you can.', replace: [
            'what' => DangerReport::label($report->type),
            'where' => $report->place ? translate(key: 'near {place}', replace: ['place' => $report->place]) : translate('close to you'),
        ]);
        foreach ($this->usersNear($report->latitude, $report->longitude, $settings['caution_radius_km'] * 1000, DRIVER, self::FRESH_DRIVER_MIN) as $user) {
            if ($user->id !== $report->user_id) {
                $this->push($user, 'danger_caution', $title, $body);
            }
        }
    }

    /** A blocked area starts: everyone in or near it, and trips already going there. */
    public function notifyArea(SafetyArea $area): void
    {
        $area->update(['notified_at' => now()]);
        $this->broadcast($area, $area->kind === SafetyArea::PAUSE ? 'area_paused' : 'area_no_go',
            $area->kind === SafetyArea::PAUSE ? translate('Service paused') : translate('Area closed for safety'),
            translate(key: 'No E-Ride trips to or from {place} until {time}: {reason}.', replace: [
                'place' => $area->title, 'time' => $this->when($area->ends_at), 'reason' => $area->reason,
            ]));
        // Trips accepted or under way that start or end inside: both sides decide what to do.
        TripRequest::with(['coordinate', 'customer', 'driver'])->whereIn('current_status', [ACCEPTED, OUT_FOR_PICKUP, ONGOING])->get()
            ->filter(function (TripRequest $trip) use ($area) {
                $c = $trip->coordinate;
                return $c && ($this->covers($area, $c->pickup_coordinates->latitude, $c->pickup_coordinates->longitude)
                        || $this->covers($area, $c->destination_coordinates->latitude, $c->destination_coordinates->longitude));
            })
            ->each(function (TripRequest $trip) use ($area) {
                $body = translate(key: 'Your trip goes to or from {place}, which E-Ride closed for safety: {reason}. Agree on a safe place, or cancel without a fee.', replace: [
                    'place' => $area->title, 'reason' => $area->reason,
                ]);
                $this->push($trip->customer, 'area_trip_affected', translate('Your trip is affected'), $body);
                $this->push($trip->driver, 'area_trip_affected', translate('Your trip is affected'), $body);
            });
    }

    /** A whole zone: every rider and driver (topics). A circle: those whose live position is in or near it. */
    protected function broadcast(SafetyArea $area, string $action, string $title, string $body): void
    {
        if ($area->zone_id) {
            foreach (['customers_send_notification', 'drivers_send_notification'] as $topic) {
                $this->safely(fn() => sendTopicNotification(topic: $topic, title: $title, description: $body, type: 'safety', action: $action));
            }
            return;
        }
        $reach = $area->radius_m + SafetySettings::all()['banner_radius_km'] * 1000;
        foreach ([[DRIVER, self::FRESH_DRIVER_MIN], [CUSTOMER, self::FRESH_CUSTOMER_MIN]] as [$type, $fresh]) {
            foreach ($this->usersNear($area->latitude, $area->longitude, $reach, $type, $fresh) as $user) {
                $this->push($user, $action, $title, $body);
            }
        }
    }

    /** @return Collection<User> users of a type whose live position is fresh and within $metres. */
    public function usersNear(float $lat, float $lng, float $metres, string $type, int $freshMinutes): Collection
    {
        $ids = UserLastLocation::where('type', $type)->where('updated_at', '>=', now()->subMinutes($freshMinutes))->get(['user_id', 'latitude', 'longitude'])
            ->filter(fn($l) => is_numeric($l->latitude) && is_numeric($l->longitude)
                && haversineDistance((float)$l->latitude, (float)$l->longitude, $lat, $lng) <= $metres)
            ->pluck('user_id')->unique();
        return $ids->isEmpty() ? collect() : User::whereIn('id', $ids)->where('is_active', 1)->get();
    }

    protected function push(?User $user, string $action, string $title, string $body): void
    {
        if (!$user?->fcm_token) {
            return;
        }
        $this->safely(fn() => sendDeviceNotification(fcm_token: $user->fcm_token, title: $title, description: $body, status: 1,
            type: 'safety', notification_type: 'safety', action: $action, user_id: $user->id));
    }

    // ------------------------------------------------------------- helpers

    public function zoneAt(float $lat, float $lng): ?Zone
    {
        return Zone::where('is_active', 1)->whereContains('coordinates', new Point($lat, $lng))->first();
    }

    /** "16:00" today, else "Mon 16:00". */
    protected function when(Carbon $at): string
    {
        return $at->isToday() ? $at->format('H:i') : $at->format('D d M, H:i');
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['safety' => $message]);
    }

    protected function safely(callable $step): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            Log::warning('Safety step failed', ['error' => $e->getMessage()]);
        }
    }
}
