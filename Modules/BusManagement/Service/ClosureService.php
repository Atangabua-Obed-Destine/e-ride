<?php

namespace Modules\BusManagement\Service;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusClosure;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusSeatClaim;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Lib\BusPush;
use Modules\ZoneManagement\Entities\SafetyArea;
use Modules\ZoneManagement\Service\SafetyService;
use Throwable;

/**
 * Lockdowns and ghost towns. An agency declares a "no travel" period (or E-Ride suggests one from an area pause
 * covering its office) and chooses what happens to its passengers:
 *   move     each paid booking moves to the first departure after the period (same destination and class, the
 *            same seat when free); the passenger keeps the new time or cancels for everything back;
 *   cancel   every paid booking is refunded in full;
 *   at_risk  not sure yet: the departures stop selling and passengers are warned (free cancellation) until the
 *            agency confirms or lifts it; undecided at its deadline, passengers are moved.
 * Requests still waiting are declined with the reason. Lifting reopens the departures that have not left.
 */
class ClosureService
{
    /** How far ahead a replacement departure may be. */
    public const MOVE_WITHIN_DAYS = 14;

    public function __construct(protected BookingService $bookings)
    {
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'lockdown' => translate('Lockdown'),
            'ghost_town' => translate('Ghost town'),
            'road_closed' => translate('Road closed'),
            'strike' => translate('Strike'),
            default => translate('No travel'),
        };
    }

    /**
     * input: kind, note, repeat (0|1),
     *   period: starts_at, ends_at (Y-m-d H:i)
     *   repeat: days[] (weekday bits), from_date, until_date (Y-m-d, optional), day_from, day_to (H:i)
     *   destination_ids[] (empty: all), outcome (move|cancel|at_risk), decide_by (Y-m-d H:i, at_risk), safety_area_id
     */
    public function make(BusAgency $agency, array $input): BusClosure
    {
        $repeat = !empty($input['repeat']);
        $data = Validator::make($input, [
            'kind' => ['required', Rule::in(BusClosure::KINDS)],
            'note' => 'nullable|string|max:300',
            'starts_at' => $repeat ? 'nullable' : 'required|date_format:Y-m-d H:i',
            'ends_at' => $repeat ? 'nullable' : 'required|date_format:Y-m-d H:i|after:starts_at',
            'days' => $repeat ? 'required|array|min:1' : 'nullable',
            'days.*' => 'integer|in:1,2,4,8,16,32,64',
            'from_date' => $repeat ? 'required|date_format:Y-m-d' : 'nullable',
            'until_date' => 'nullable|date_format:Y-m-d',
            'day_from' => $repeat ? 'required|date_format:H:i' : 'nullable',
            'day_to' => $repeat ? 'required|date_format:H:i|after:day_from' : 'nullable',
            'destination_ids' => 'nullable|array',
            'destination_ids.*' => [Rule::exists('bus_destinations', 'id')->where('agency_id', $agency->id)],
            'outcome' => ['required', Rule::in([BusClosure::MOVE, BusClosure::CANCEL, BusClosure::AT_RISK])],
            'decide_by' => 'required_if:outcome,at_risk|nullable|date_format:Y-m-d H:i',
            'safety_area_id' => 'nullable|string|max:36',
        ], [
            'ends_at.after' => translate('The end must come after the start.'),
            'day_to.after' => translate('The end must come after the start.'),
            'days.required' => translate('Choose the day that repeats.'),
            'decide_by.required_if' => translate('Say by when you will decide.'),
        ])->validate();
        if (!$repeat && Carbon::parse($data['ends_at'])->lte(now())) {
            throw ValidationException::withMessages(['ends_at' => translate('This period is already over.')]);
        }
        if (($data['outcome'] ?? null) === BusClosure::AT_RISK && Carbon::parse($data['decide_by'])->lte(now())) {
            throw ValidationException::withMessages(['decide_by' => translate('The decision time must be later than now.')]);
        }
        return new BusClosure([
            'agency_id' => $agency->id,
            'kind' => $data['kind'],
            'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
            'starts_at' => $repeat ? Carbon::parse($data['from_date'])->startOfDay() : Carbon::parse($data['starts_at']),
            'ends_at' => $repeat ? (filled($data['until_date'] ?? null) ? Carbon::parse($data['until_date'])->endOfDay() : null) : Carbon::parse($data['ends_at']),
            'days' => $repeat ? array_sum(array_map('intval', $data['days'])) : null,
            'day_from' => $repeat ? $data['day_from'] : null,
            'day_to' => $repeat ? $data['day_to'] : null,
            'destination_ids' => $data['destination_ids'] ?? null ?: null,
            'outcome' => $data['outcome'],
            'decide_by' => $data['outcome'] === BusClosure::AT_RISK ? Carbon::parse($data['decide_by']) : null,
            'status' => BusClosure::ACTIVE,
            'safety_area_id' => $data['safety_area_id'] ?? null,
        ]);
    }

    /** Departures a closure would stop: open, not left yet, not already under another closure. */
    public function affected(BusClosure $closure): Collection
    {
        return BusDeparture::with(['destination'])->where('agency_id', $closure->agency_id)->whereIn('status', BusDeparture::OPEN)
            ->whereNull('closure_id')->where('date', '>=', today()->toDateString())
            ->when(!$closure->repeats() && $closure->ends_at, fn($q) => $q->where('date', '<=', $closure->ends_at->toDateString()))
            ->when($closure->starts_at, fn($q) => $q->where('date', '>=', $closure->starts_at->toDateString()))
            ->when($closure->repeats() && $closure->ends_at, fn($q) => $q->where('date', '<=', $closure->ends_at->toDateString()))
            ->orderBy('date')->orderBy('time')->get()
            ->filter(fn(BusDeparture $d) => $d->leavesAt()->isFuture() && $closure->covers($d->leavesAt(), $d->destination_id))->values();
    }

    /** What declaring it would touch, before the agency confirms. */
    public function preview(BusAgency $agency, array $input): array
    {
        $closure = $this->make($agency, $input);
        $deps = $this->affected($closure);
        $bookings = BusBooking::whereIn('departure_id', $deps->pluck('id'))->whereIn('status', [...BusBooking::HOLDING, BusBooking::PAID])->withCount(['tickets as seats' => fn($q) => $q->whereIn('status', [BusTicket::HELD, BusTicket::VALID])])->get();
        $paid = $bookings->where('status', BusBooking::PAID);
        return [
            'departures' => $deps->count(),
            'passengers' => (int)$paid->sum('seats'),
            'app' => (int)$paid->where('source', BusBooking::APP)->sum('seats'),
            'counter' => (int)$paid->where('source', BusBooking::COUNTER)->sum('seats'),
            'waiting' => $bookings->whereIn('status', BusBooking::HOLDING)->count(),
            'list' => $deps->take(30)->map(fn($d) => $d->leavesAt()->translatedFormat('D j M, H:i') . ' · ' . $d->destination?->city)->values(),
        ];
    }

    public function declare(BusAgency $agency, array $input, ?string $byUserId): BusClosure
    {
        $closure = $this->make($agency, $input);
        $closure->created_by = $byUserId;
        $closure->save();
        foreach ($this->affected($closure) as $d) {
            $this->applyTo($closure, $d);
        }
        return $closure->fresh();
    }

    /** A departure falls under a closure (when declared, or when the timetable opens a new one inside a repeating one). */
    public function applyTo(BusClosure $closure, BusDeparture $d): void
    {
        $reason = $this->reasonText($closure);
        // Sales stop first, under the departure's lock: a request or a counter sale
        // in flight either finished before (and is handled below) or is refused.
        DB::transaction(function () use ($d, $closure) {
            BusDeparture::whereKey($d->id)->lockForUpdate()->first()?->update(['closure_id' => $closure->id]);
        });
        $d->closure_id = $closure->id;
        // Requests not paid yet end now, with the reason.
        BusBooking::with('customer')->where('departure_id', $d->id)->whereIn('status', BusBooking::HOLDING)->get()->each(function (BusBooking $b) use ($reason, $closure) {
            DB::transaction(function () use ($b, $closure) {
                $x = BusBooking::whereKey($b->id)->lockForUpdate()->first();
                if (in_array($x->status, BusBooking::HOLDING, true)) {
                    $x->update(['status' => BusBooking::DECLINED, 'decline_reason' => Str::limit($this->reasonText($closure), 200, ''), 'closure_id' => $closure->id]);
                    $this->bookings->release($x);
                }
            });
            BusPush::send($b->customer, 'bus_closure_declined', '{agency} cannot take your request', '{reason} Nothing was paid.',
                ['agency' => $closure->agency->displayName(), 'reason' => $reason], ['bus_booking_id' => $b->id]);
        });

        if ($closure->outcome === BusClosure::AT_RISK) {
            $this->eachPaid($d, fn(BusBooking $b) => BusPush::send($b->customer, 'bus_closure_at_risk', 'Your trip to {city} may not go ahead',
                '{reason} {agency} decides by {time}. You can cancel now for a full refund.',
                ['city' => $d->destination?->city, 'reason' => $reason, 'agency' => $closure->agency->displayName(), 'time' => $closure->decide_by->format('d/m H:i')],
                ['bus_booking_id' => $b->id]));
            return;
        }
        $this->closeDeparture($closure, $d);
    }

    /** The departure does not run: its paid bookings move (or are refunded), then it is cancelled. */
    protected function closeDeparture(BusClosure $closure, BusDeparture $d): void
    {
        $reason = $this->reasonText($closure);
        // The departure stops running first, under its lock: a payment landing now
        // is sent back instead of selling a seat on a bus that does not run.
        // Passengers already on board: the bus is effectively leaving, left as is.
        $stopped = DB::transaction(function () use ($d, $closure, $reason) {
            $locked = BusDeparture::whereKey($d->id)->lockForUpdate()->first();
            if (!$locked || !$locked->isOpen()) {
                return false;
            }
            if (BusTicket::where('departure_id', $d->id)->where('status', BusTicket::BOARDED)->exists()) {
                Log::warning('Bus closure skipped a departure already boarding', ['departure' => $d->id, 'closure' => $closure->id]);
                return false;
            }
            $locked->update(['status' => BusDeparture::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => Str::limit($reason, 300, ''), 'closure_id' => $closure->id]);
            return true;
        });
        if (!$stopped) {
            return;
        }
        // A locking read: a payment being settled right now finishes first and is seen here.
        $paidIds = DB::transaction(fn() => BusBooking::where('departure_id', $d->id)->where('status', BusBooking::PAID)->lockForUpdate()->pluck('id'));
        foreach (BusBooking::with(['tickets', 'customer'])->whereIn('id', $paidIds)->get() as $b) {
            $to = $closure->outcome === BusClosure::MOVE ? $this->moveBooking($closure, $b, $d) : null;
            if ($to) {
                BusPush::send($b->customer, 'bus_closure_moved', 'Your trip to {city} moved to {when}',
                    '{reason} Keep the new time, or cancel for a full refund.',
                    ['city' => $d->destination?->city, 'when' => $to->leavesAt()->format('d/m H:i'), 'reason' => $reason], ['bus_booking_id' => $b->id]);
                continue;
            }
            DB::transaction(function () use ($b, $closure) {
                $x = BusBooking::whereKey($b->id)->lockForUpdate()->first();
                if ($x->status === BusBooking::PAID) {
                    $this->bookings->endBooking($x, 'closure', (float)$x->amount);
                    $x->update(['closure_id' => $closure->id]);
                }
            });
            BusPush::send($b->customer, 'bus_closure_refund', 'Your trip to {city} is cancelled',
                '{reason} {agency} refunds you in full to your Mobile Money.',
                ['city' => $d->destination?->city, 'reason' => $reason, 'agency' => $closure->agency->displayName()], ['bus_booking_id' => $b->id]);
        }
        $d->refresh();
    }

    /** The first departure after the closure with room for the whole booking. Same seats when free. */
    public function moveBooking(BusClosure $closure, BusBooking $b, BusDeparture $from): ?BusDeparture
    {
        $live = $b->tickets->whereIn('status', [BusTicket::VALID])->values();
        if ($live->isEmpty()) {
            return null;
        }
        $after = $closure->endAfter($from->leavesAt());
        $candidates = BusDeparture::where('agency_id', $from->agency_id)->where('destination_id', $from->destination_id)->where('class', $from->class)
            ->whereIn('status', BusDeparture::OPEN)->whereNull('closure_id')->whereNotNull('vehicle_id')
            ->whereBetween('date', [$after->toDateString(), $after->copy()->addDays(self::MOVE_WITHIN_DAYS)->toDateString()])
            ->orderBy('date')->orderBy('time')->get()
            ->filter(fn(BusDeparture $d) => $d->leavesAt()->gte($after) && $d->leavesAt()->isFuture() && !$this->closedBy($d));
        foreach ($candidates as $to) {
            try {
                $moved = DB::transaction(function () use ($b, $to, $from, $live, $closure) {
                    $x = BusBooking::whereKey($b->id)->lockForUpdate()->first();
                    $toLocked = BusDeparture::whereKey($to->id)->lockForUpdate()->first();
                    if ($x->status !== BusBooking::PAID || !$toLocked->isOpen() || $toLocked->closure_id) {
                        return false;
                    }
                    $taken = array_flip(BookingService::takenSeats($toLocked));
                    $plan = array_map('strval', $toLocked->seat_plan['seats'] ?? []);
                    $free = array_values(array_filter($plan, fn($s) => !isset($taken[$s])));
                    if (count($free) < $live->count()) {
                        return false;
                    }
                    BusSeatClaim::whereIn('ticket_id', $live->pluck('id'))->delete();
                    foreach ($live as $t) {
                        $seat = in_array((string)$t->seat, $free, true) ? (string)$t->seat : $free[0];
                        $free = array_values(array_diff($free, [$seat]));
                        BusSeatClaim::create(['departure_id' => $toLocked->id, 'seat' => $seat, 'ticket_id' => $t->id]);
                        $t->update(['departure_id' => $toLocked->id, 'seat' => $seat]);
                    }
                    $x->update(['departure_id' => $toLocked->id, 'moved_from_departure_id' => $from->id, 'moved_at' => now(), 'closure_id' => $closure->id,
                        'move_kept_at' => null, 'reminded_at' => null, 'eve_reminded_at' => null, 'collect_reminded_at' => null]);
                    return true;
                });
            } catch (UniqueConstraintViolationException) {
                $moved = false;
            }
            if ($moved) {
                return $to->fresh();
            }
        }
        return null;
    }

    /** Is a departure inside any active closure of its agency (repeating ones included)? */
    public function closedBy(BusDeparture $d): ?BusClosure
    {
        return BusClosure::where('agency_id', $d->agency_id)->where('status', BusClosure::ACTIVE)->get()
            ->first(fn(BusClosure $c) => $c->covers($d->leavesAt(), $d->destination_id));
    }

    /** At risk, decided: move or refund now. */
    public function confirm(BusClosure $closure, string $outcome): BusClosure
    {
        if (!$closure->isAtRisk()) {
            throw ValidationException::withMessages(['closure' => translate('This is already decided.')]);
        }
        if (!in_array($outcome, [BusClosure::MOVE, BusClosure::CANCEL], true)) {
            throw ValidationException::withMessages(['outcome' => translate('Choose what happens to the passengers.')]);
        }
        $closure->update(['outcome' => $outcome, 'decided_at' => now()]);
        foreach (BusDeparture::with(['destination'])->where('closure_id', $closure->id)->whereIn('status', BusDeparture::OPEN)->get() as $d) {
            $this->closeDeparture($closure, $d);
        }
        return $closure->fresh();
    }

    /** Over early (or never happened): departures that have not left run again. */
    public function lift(BusClosure $closure): BusClosure
    {
        if ($closure->status !== BusClosure::ACTIVE) {
            throw ValidationException::withMessages(['closure' => translate('This is already lifted.')]);
        }
        $closure->update(['status' => BusClosure::LIFTED, 'lifted_at' => now()]);
        foreach (BusDeparture::with(['destination', 'agency'])->where('closure_id', $closure->id)->get() as $d) {
            if (!$d->leavesAt()->isFuture()) {
                continue;
            }
            $wasCancelled = $d->status === BusDeparture::CANCELLED;
            $d->update(['closure_id' => null] + ($wasCancelled ? ['status' => BusDeparture::SCHEDULED, 'cancelled_at' => null, 'cancel_reason' => null] : []));
            if (!$wasCancelled) {
                $this->eachPaid($d, fn(BusBooking $b) => BusPush::send($b->customer, 'bus_closure_lifted', 'Your trip to {city} goes ahead',
                    '{agency} lifted the warning: the bus leaves at {time} as planned.',
                    ['city' => $d->destination?->city, 'agency' => $closure->agency->displayName(), 'time' => $d->leavesAt()->format('d/m H:i')], ['bus_booking_id' => $b->id]));
            }
        }
        // Passengers already moved keep their new time; they hear the original runs again.
        BusBooking::with(['customer', 'departure'])->where('closure_id', $closure->id)->where('status', BusBooking::PAID)->whereNotNull('moved_at')->get()
            ->each(fn(BusBooking $b) => BusPush::send($b->customer, 'bus_closure_lifted', '{agency} lifted the {kind}',
                'Your ticket stays on {when}. To travel on the original departure instead, speak to the agency.',
                ['agency' => $closure->agency->displayName(), 'kind' => mb_strtolower(self::kindLabel($closure->kind)), 'when' => $b->departure?->leavesAt()->format('d/m H:i')],
                ['bus_booking_id' => $b->id]));
        return $closure->fresh();
    }

    /** The passenger keeps the new time (the banner goes away; free cancellation stays until the trip). */
    public function keep(BusBooking $b): void
    {
        if ($b->moved_at && !$b->move_kept_at) {
            $b->update(['move_kept_at' => now()]);
        }
    }

    /** Every minute: at-risk closures past their deadline move their passengers. */
    public function tick(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $n = 0;
        BusClosure::where('status', BusClosure::ACTIVE)->where('outcome', BusClosure::AT_RISK)->where('decide_by', '<=', $now)->get()
            ->each(function (BusClosure $c) use (&$n) {
                $this->confirm($c, BusClosure::MOVE);
                $n++;
            });
        return $n;
    }

    /** E-Ride area pauses (planned or on) covering the agency's office, not handled yet: offered as ready-made closures. */
    public function suggestions(BusAgency $agency): Collection
    {
        if ($agency->lat === null || $agency->lng === null || !class_exists(SafetyArea::class)) {
            return collect();
        }
        $handled = BusClosure::where('agency_id', $agency->id)->whereNotNull('safety_area_id')->pluck('safety_area_id')->all();
        try {
            $safety = app(SafetyService::class);
            return SafetyArea::where('kind', SafetyArea::PAUSE)->whereNull('lifted_at')->where('ends_at', '>', now())
                ->where('starts_at', '<', now()->addDays(7))->whereNotIn('id', $handled)->get()
                ->filter(fn(SafetyArea $a) => $safety->covers($a, $agency->lat, $agency->lng))->values();
        } catch (Throwable) {
            return collect();
        }
    }

    /** Does a closure touch this day (any hour)? */
    public static function onDay(BusClosure $c, CarbonInterface $day): bool
    {
        $start = Carbon::instance($day)->startOfDay();
        $end = $start->copy()->endOfDay();
        if (!$c->repeats()) {
            return $c->starts_at->lt($end) && $c->ends_at->gt($start);
        }
        return (!$c->starts_at || $start->gte($c->starts_at->copy()->startOfDay()))
            && (!$c->ends_at || $start->lte($c->ends_at))
            && ($c->days & (1 << ($start->dayOfWeekIso - 1)));
    }

    /** For passengers: "Ghost town every Monday, 06:00-18:00" or "Lockdown, Mon 17 Nov 06:00 to Tue 18 Nov 18:00". */
    public function notice(BusClosure $c): array
    {
        $when = $c->repeats()
            ? translate(key: 'every {days}, {from} to {to}', replace: ['days' => self::dayNames($c->days), 'from' => substr($c->day_from, 0, 5), 'to' => substr($c->day_to, 0, 5)])
            : $c->starts_at->translatedFormat('D j M H:i') . ' - ' . $c->ends_at->translatedFormat('D j M H:i');
        return [
            'id' => $c->id,
            'agency' => $c->agency?->displayName(),
            'kind' => self::kindLabel($c->kind),
            'note' => $c->note,
            'when' => $when,
            'at_risk' => $c->isAtRisk(),
            'decide_by' => $c->decide_by?->toIso8601String(),
        ];
    }

    public static function dayNames(int $bits): string
    {
        $names = [translate('Monday'), translate('Tuesday'), translate('Wednesday'), translate('Thursday'), translate('Friday'), translate('Saturday'), translate('Sunday')];
        return implode(', ', array_values(array_filter($names, fn($n, $i) => $bits & (1 << $i), ARRAY_FILTER_USE_BOTH)));
    }

    public function reasonText(BusClosure $c): string
    {
        return trim(self::kindLabel($c->kind) . ($c->note ? ': ' . $c->note : '') . '.');
    }

    protected function eachPaid(BusDeparture $d, callable $fn): void
    {
        BusBooking::with('customer')->where('departure_id', $d->id)->where('status', BusBooking::PAID)->where('source', BusBooking::APP)->get()->each($fn);
    }
}
