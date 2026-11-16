<?php

namespace Modules\BusManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusSeatClaim;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Entities\BusWaitlist;
use Modules\BusManagement\Lib\BusSettings;
use Modules\BusManagement\Service\BookingService;
use Modules\BusManagement\Transformers\BusPresenter;

/** Customer app, E-Ride Bus Travel: where you can go, departures, an agency, a departure's live seat map, the waitlist. */
class BusController extends Controller
{
    public function __construct(protected BookingService $bookings)
    {
    }

    /**
     * Whether bus booking is on, its numbers, and every "from" city with its destinations:
     * the lowest fare to each, and how many departures it has in the coming week.
     */
    public function meta(): JsonResponse
    {
        $settings = BusSettings::all();
        $routes = [];
        if ($settings['enabled']) {
            $live = $this->live()->pluck('origin_city', 'id');
            $departures = BusDeparture::whereIn('agency_id', $live->keys())->whereIn('status', BusDeparture::OPEN)->whereNotNull('vehicle_id')
                ->whereBetween('date', [today()->toDateString(), today()->addDays(6)->toDateString()])
                ->selectRaw('destination_id, count(*) as n')->groupBy('destination_id')->pluck('n', 'destination_id');
            BusDestination::with(['dropPoints' => fn($q) => $q->where('active', true)])->where('active', true)->whereIn('agency_id', $live->keys())->get()
                ->each(function (BusDestination $d) use (&$routes, $live, $departures) {
                    $from = $live[$d->agency_id];
                    $fares = $d->dropPoints->flatMap(fn($p) => array_filter([$p->fareFor('classic'), $p->fareFor('vip'), $p->fareFor('executive')]));
                    $row = $routes[$from][$d->city] ?? ['city' => $d->city, 'from_fare' => null, 'departures' => 0];
                    if ($fares->isNotEmpty()) {
                        $row['from_fare'] = $row['from_fare'] === null ? $fares->min() : min($row['from_fare'], $fares->min());
                    }
                    $row['departures'] += (int)($departures[$d->id] ?? 0);
                    $routes[$from][$d->city] = $row;
                });
        }
        return $this->ok([
            'enabled' => $settings['enabled'],
            'max_seats' => $settings['max_seats'],
            'answer_minutes' => $settings['answer_minutes'],
            'pay_minutes' => $settings['pay_minutes'],
            'open_days' => $settings['open_days'],
            'routes' => collect($routes)->map(fn($to, $from) => ['from' => $from, 'to' => collect($to)->sortByDesc('departures')->values()])->sortKeys()->values(),
        ]);
    }

    /** ?from=Bamenda&to=Yaoundé&date=Y-m-d&class=: the day's departures, and how many there are on the next days. */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|string|max:80',
            'to' => 'nullable|string|max:80',
            'date' => 'nullable|date_format:Y-m-d',
            'class' => 'nullable|in:classic,vip,executive',
        ]);
        if (!BusSettings::enabled()) {
            return $this->fail('bus', translate('Bus booking is not available in the app yet.'));
        }
        $date = Carbon::parse($request->input('date', today()->toDateString()))->startOfDay();
        if ($date->lt(today())) {
            $date = today();
        }
        $base = fn() => BusDeparture::whereIn('agency_id', $this->live()->where('origin_city', $request->input('from'))->select('id'))
            ->whereIn('status', BusDeparture::OPEN)->whereNotNull('vehicle_id')
            ->when($request->filled('to'), fn($q) => $q->whereIn('destination_id', BusDestination::where('city', $request->input('to'))->select('id')))
            ->when($request->filled('class'), fn($q) => $q->where('class', $request->input('class')));

        $departures = $base()->where('date', $date->toDateString())
            ->with(['agency', 'vehicle', 'destination.dropPoints'])->orderBy('time')->get()
            ->filter(fn(BusDeparture $d) => $d->leavesAt()->isFuture())->values();
        $taken = $this->takenBy($departures->pluck('id')->all());
        $list = $departures->map(fn(BusDeparture $d) => BusPresenter::departure($d, $taken[$d->id] ?? [], $this->bookings->onlineBlock($d)))
            // Bookable first, then by time.
            ->sortBy(fn($d) => [$d['bookable'] ? 0 : 1, $d['leaves_at']])->values();

        $days = $base()->whereBetween('date', [today()->toDateString(), today()->addDays(BusSettings::all()['open_days'] - 1)->toDateString()])
            ->selectRaw('date, count(*) as n')->groupBy('date')->pluck('n', 'date');
        return $this->ok([
            'date' => $date->toDateString(),
            'departures' => $list,
            'days' => collect($days)->map(fn($n, $d) => ['date' => Carbon::parse($d)->toDateString(), 'departures' => (int)$n])->values(),
        ]);
    }

    /** The agencies taking bookings (to mark them on Explore > Bus agencies), with where they go. */
    public function agencies(): JsonResponse
    {
        if (!BusSettings::enabled()) {
            return $this->ok([]);
        }
        $agencies = $this->live()->with(['destinations' => fn($q) => $q->where('active', true)->orderBy('sort')])->orderBy('name')->get();
        $next = BusDeparture::whereIn('agency_id', $agencies->pluck('id'))->whereIn('status', BusDeparture::OPEN)->whereNotNull('vehicle_id')
            ->whereBetween('date', [today()->toDateString(), today()->addDays(2)->toDateString()])->orderBy('date')->orderBy('time')->get()
            ->filter(fn($d) => $d->leavesAt()->isFuture())->groupBy('agency_id')->map(fn($list) => $list->first()->leavesAt()->toIso8601String());
        return $this->ok($agencies->map(fn(BusAgency $a) => BusPresenter::agency($a) + [
            'destinations' => $a->destinations->pluck('city')->values(),
            'address' => $a->address, 'lat' => $a->lat, 'lng' => $a->lng, 'phone' => $a->phone,
            'next_departure' => $next[$a->id] ?? null,
        ])->values());
    }

    /** An agency: its details, rules, destinations with fares, and the next departures. */
    public function agency(string $id): JsonResponse
    {
        $agency = BusAgency::find($id);
        if (!$agency || !in_array($agency->status, [BusAgency::LIVE, BusAgency::PAUSED], true)) {
            return $this->fail('agency', translate('This agency is not on E-Ride.'), 404);
        }
        $destinations = BusDestination::with(['dropPoints' => fn($q) => $q->where('active', true)])->where('agency_id', $agency->id)->where('active', true)->orderBy('sort')->get();
        $departures = BusDeparture::with(['agency', 'vehicle', 'destination.dropPoints'])->where('agency_id', $agency->id)->whereIn('status', BusDeparture::OPEN)
            ->whereNotNull('vehicle_id')->whereBetween('date', [today()->toDateString(), today()->addDays(2)->toDateString()])
            ->orderBy('date')->orderBy('time')->get()->filter(fn($d) => $d->leavesAt()->isFuture())->take(30)->values();
        $taken = $this->takenBy($departures->pluck('id')->all());
        return $this->ok(BusPresenter::agency($agency, true) + [
            'bookable' => $agency->isBookable() && BusSettings::enabled(),
            'destinations' => $destinations->map(fn(BusDestination $d) => [
                'city' => $d->city,
                'duration_minutes' => $d->duration_minutes,
                'drop_points' => $d->dropPoints->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'minutes_from_start' => $p->minutes_from_start,
                    'fares' => array_filter(['classic' => $p->fareFor('classic'), 'vip' => $p->fareFor('vip'), 'executive' => $p->fareFor('executive')])])->values(),
            ])->values(),
            'departures' => $departures->map(fn($d) => BusPresenter::departure($d, $taken[$d->id] ?? [], $this->bookings->onlineBlock($d)))->values(),
        ]);
    }

    /** A departure with its live seat map, as this passenger sees it. */
    public function departure(Request $request, string $id): JsonResponse
    {
        $departure = BusDeparture::with(['agency', 'vehicle', 'destination.dropPoints'])->find($id);
        if (!$departure || !$departure->agency || $departure->agency->status === BusAgency::CHECKING) {
            return $this->fail('departure', translate('This departure does not exist.'), 404);
        }
        $user = $request->user();
        $taken = BookingService::takenSeats($departure);
        $mineBooking = BusBooking::where('departure_id', $departure->id)->where('customer_id', $user->id)
            ->whereIn('status', [...BusBooking::HOLDING, BusBooking::PAID])->latest()->first();
        $mine = $mineBooking ? BusTicket::where('booking_id', $mineBooking->id)->whereIn('status', [BusTicket::HELD, BusTicket::VALID, BusTicket::BOARDED])->pluck('seat')->map(fn($s) => (string)$s)->all() : [];
        return $this->ok(BusPresenter::departure($departure, $taken, $this->bookings->onlineBlock($departure)) + [
            'agency_full' => BusPresenter::agency($departure->agency, true),
            'seat_map' => BusPresenter::seatMap($departure, $taken, $mine),
            'my_booking_id' => $mineBooking?->id,
            'on_waitlist' => BusWaitlist::where('departure_id', $departure->id)->where('user_id', $user->id)->exists(),
        ]);
    }

    public function waitlist(Request $request, string $id): JsonResponse
    {
        $departure = BusDeparture::find($id);
        if (!$departure) {
            return $this->fail('departure', translate('This departure does not exist.'), 404);
        }
        try {
            $on = $this->bookings->toggleWaitlist($request->user(), $departure);
        } catch (ValidationException $e) {
            return $this->failWith($e);
        }
        return $this->ok(['on_waitlist' => $on]);
    }

    protected function live()
    {
        return BusAgency::where('status', BusAgency::LIVE)->whereNotNull('fapshi_ok_at');
    }

    /** @return array<string, string[]> taken seats by departure */
    protected function takenBy(array $ids): array
    {
        $out = [];
        if ($ids) {
            BusSeatClaim::whereIn('departure_id', $ids)->get(['departure_id', 'seat'])->each(function ($c) use (&$out) {
                $out[$c->departure_id][] = (string)$c->seat;
            });
        }
        return $out;
    }

    protected function ok($data): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, $data));
    }

    protected function fail(string $code, string $message, int $status = 403): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), $status);
    }

    protected function failWith(ValidationException $e): JsonResponse
    {
        $errors = [];
        foreach ($e->errors() as $field => $messages) {
            $errors[] = ['error_code' => $field, 'message' => $messages[0]];
        }
        return response()->json(responseFormatter(DEFAULT_400, errors: $errors), 403);
    }
}
