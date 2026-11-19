<?php

namespace Modules\BusManagement\Http\Controllers\Web\Site;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusSeatClaim;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Lib\BusSettings;
use Modules\BusManagement\Lib\BusWebLinks;
use Modules\BusManagement\Service\BookingService;
use Modules\BusManagement\Transformers\BusPresenter;

/**
 * The public bus website, no account needed: search, departures, agencies, a departure's seat map.
 * Picking seats keeps them in the session; signing in comes when the traveller presses Continue.
 */
class SiteController extends SiteBase
{
    public function __construct(protected BookingService $bookings)
    {
    }

    public function home(Request $request): Response
    {
        $t = $this->words($request);
        $data = BusSettings::enabled() ? Cache::remember('bus-web:home', 60, fn() => $this->homeData()) : ['origins' => [], 'routes' => [], 'agencies' => []];
        return $this->page($request, 'home', $data + ['t' => $t, 'from' => $request->query('from', $data['origins'][0] ?? 'Bamenda')], index: true);
    }

    /** Where people can go from each city this week, the lowest fares, and the agencies. */
    protected function homeData(): array
    {
        $live = $this->live()->orderBy('name')->get();
        $counts = BusDeparture::whereIn('agency_id', $live->pluck('id'))->whereIn('status', BusDeparture::OPEN)->whereNotNull('vehicle_id')
            ->whereBetween('date', [today()->toDateString(), today()->addDays(6)->toDateString()])
            ->selectRaw('destination_id, count(*) as n')->groupBy('destination_id')->pluck('n', 'destination_id');
        $routes = [];
        BusDestination::with(['dropPoints' => fn($q) => $q->where('active', true)])->where('active', true)->whereIn('agency_id', $live->pluck('id'))->get()
            ->each(function (BusDestination $d) use (&$routes, $live, $counts) {
                $from = $live->firstWhere('id', $d->agency_id)?->origin_city;
                $fares = $d->dropPoints->flatMap(fn($p) => array_filter([$p->fareFor('classic'), $p->fareFor('vip'), $p->fareFor('executive')]));
                $row = $routes[$from][$d->city] ?? ['to' => $d->city, 'fare' => null, 'departures' => 0, 'agencies' => []];
                if ($fares->isNotEmpty()) {
                    $row['fare'] = $row['fare'] === null ? $fares->min() : min($row['fare'], $fares->min());
                }
                $row['departures'] += (int)($counts[$d->id] ?? 0);
                $row['agencies'][$d->agency_id] = true;
                $routes[$from][$d->city] = $row;
            });
        foreach ($routes as $from => $list) {
            $routes[$from] = collect($list)->map(fn($r) => ['to' => $r['to'], 'fare' => $r['fare'], 'departures' => $r['departures'], 'agencies' => count($r['agencies'])])
                ->sortByDesc('departures')->values()->all();
        }
        ksort($routes);
        return [
            'origins' => array_keys($routes) ?: $live->pluck('origin_city')->unique()->values()->all(),
            'routes' => $routes,
            'agencies' => $live->map(fn(BusAgency $a) => ['name' => $a->name, 'slug' => $a->slug, 'city' => $a->origin_city])->values()->all(),
        ];
    }

    /** ?from=&to=&date=Y-m-d&class= */
    public function search(Request $request): Response|RedirectResponse
    {
        $t = $this->words($request);
        if (!BusSettings::enabled()) {
            return redirect()->route('bus.web.home');
        }
        $data = $request->validate([
            'from' => 'nullable|string|max:80',
            'to' => 'nullable|string|max:80',
            'date' => 'nullable|date_format:Y-m-d',
            'class' => 'nullable|in:classic,vip,executive',
        ]);
        $from = trim((string)($data['from'] ?? '')) ?: ($this->live()->value('origin_city') ?? 'Bamenda');
        $to = trim((string)($data['to'] ?? ''));
        $date = isset($data['date']) ? Carbon::parse($data['date'])->startOfDay() : today();
        if ($date->lt(today())) {
            $date = today();
        }
        $last = today()->addDays(BusSettings::all()['open_days'] - 1);
        if ($date->gt($last)) {
            $date = $last;
        }
        $class = $data['class'] ?? null;
        $key = 'bus-web:search:' . md5(json_encode([$from, $to, $date->toDateString(), $class]));
        $result = Cache::remember($key, 20, fn() => $this->searchData($from, $to, $date, $class));
        return $this->page($request, 'results', $result + [
            't' => $t, 'from' => $from, 'to' => $to, 'date' => $date, 'class' => $class,
            'cities' => $this->cities($from),
        ], index: true);
    }

    protected function searchData(string $from, string $to, Carbon $date, ?string $class): array
    {
        $base = fn() => BusDeparture::whereIn('agency_id', $this->live()->where('origin_city', $from)->select('id'))
            ->whereIn('status', BusDeparture::OPEN)->whereNotNull('vehicle_id')
            ->when($to !== '', fn($q) => $q->whereIn('destination_id', BusDestination::where('city', $to)->select('id')))
            ->when($class, fn($q) => $q->where('class', $class));
        $departures = $base()->where('date', $date->toDateString())->with(['agency', 'vehicle', 'destination.dropPoints'])->orderBy('time')->get()
            ->filter(fn(BusDeparture $d) => $d->leavesAt()->isFuture())->values();
        $taken = $this->takenBy($departures->pluck('id')->all());
        $list = $departures->map(fn(BusDeparture $d) => BusPresenter::departure($d, $taken[$d->id] ?? [], $this->bookings->onlineBlock($d))
            + ['id_check' => BusSettings::rules($d->agency?->rules)['id_check'] !== 'number', 'agency_slug' => $d->agency?->slug])
            ->sortBy(fn($d) => [$d['bookable'] ? 0 : 1, $d['leaves_at']])->values()->all();
        $days = $base()->whereBetween('date', [today()->toDateString(), today()->addDays(BusSettings::all()['open_days'] - 1)->toDateString()])
            ->selectRaw('date, count(*) as n')->groupBy('date')->pluck('n', 'date')
            ->mapWithKeys(fn($n, $d) => [Carbon::parse($d)->toDateString() => (int)$n])->all();
        return ['departures' => $list, 'days' => $days];
    }

    /** Cities people can go to from $from, for the search box. */
    protected function cities(string $from): array
    {
        return Cache::remember('bus-web:cities:' . md5($from), 300, fn() => BusDestination::where('active', true)
            ->whereIn('agency_id', $this->live()->where('origin_city', $from)->select('id'))->distinct()->orderBy('city')->pluck('city')->all());
    }

    public function agency(Request $request, string $slug): Response
    {
        $t = $this->words($request);
        $agency = BusAgency::where('slug', $slug)->whereIn('status', [BusAgency::LIVE, BusAgency::PAUSED])->first();
        if (!$agency) {
            return $this->missing($request, 'agency_not_found');
        }
        $destinations = BusDestination::with(['dropPoints' => fn($q) => $q->where('active', true)])->where('agency_id', $agency->id)
            ->where('active', true)->orderBy('sort')->get();
        $departures = BusDeparture::with(['agency', 'vehicle', 'destination.dropPoints'])->where('agency_id', $agency->id)
            ->whereIn('status', BusDeparture::OPEN)->whereNotNull('vehicle_id')
            ->whereBetween('date', [today()->toDateString(), today()->addDays(2)->toDateString()])
            ->orderBy('date')->orderBy('time')->get()->filter(fn($d) => $d->leavesAt()->isFuture())->take(12)->values();
        $taken = $this->takenBy($departures->pluck('id')->all());
        return $this->page($request, 'agency', [
            't' => $t,
            'agency' => $agency,
            'info' => BusPresenter::agency($agency, true),
            'bookable' => $agency->isBookable() && BusSettings::enabled(),
            'destinations' => $destinations,
            'departures' => $departures->map(fn($d) => BusPresenter::departure($d, $taken[$d->id] ?? [], $this->bookings->onlineBlock($d))
                + ['id_check' => BusSettings::rules($agency->rules)['id_check'] !== 'number'])->all(),
        ], index: true);
    }

    /** A departure's live seat map. Seats picked before are shown picked. */
    public function departure(Request $request, string $id): Response
    {
        $t = $this->words($request);
        $departure = BusDeparture::with(['agency', 'vehicle', 'destination.dropPoints'])->find($id);
        if (!$departure || !$departure->agency || !in_array($departure->agency->status, [BusAgency::LIVE, BusAgency::PAUSED], true)) {
            return $this->missing($request, 'departure_not_found');
        }
        $taken = BookingService::takenSeats($departure);
        $mine = [];
        $myBooking = null;
        if ($me = $this->user()) {
            $myBooking = BusBooking::where('departure_id', $departure->id)->where('customer_id', $me->id)
                ->whereIn('status', [...BusBooking::HOLDING, BusBooking::PAID])->latest()->first();
            if ($myBooking) {
                $mine = BusTicket::where('booking_id', $myBooking->id)->whereIn('status', [BusTicket::HELD, BusTicket::VALID, BusTicket::BOARDED])
                    ->pluck('seat')->map(fn($s) => (string)$s)->all();
            }
        }
        $info = BusPresenter::departure($departure, $taken, $this->bookings->onlineBlock($departure));
        $web = BusSettings::web();
        return $this->page($request, 'departure', [
            't' => $t,
            'departure' => $departure,
            'info' => $info,
            'map' => BusPresenter::seatMap($departure, $taken, $mine),
            'picked' => $myBooking ? [] : array_values(array_diff((array)$request->session()->get('bus_pick.' . $departure->id, []), $taken)),
            'myBooking' => $myBooking,
            'maxSeats' => BusSettings::all()['max_seats'],
            'webBooking' => $web['booking'],
            'appLink' => BusWebLinks::openInApp('bh'),
        ]);
    }

    /** Taken seats, for the seat map to update while it is open. */
    public function liveSeats(string $id): JsonResponse
    {
        $departure = BusDeparture::find($id);
        if (!$departure) {
            return response()->json(['taken' => []], 404);
        }
        return response()->json(['taken' => BookingService::takenSeats($departure), 'open' => $this->bookings->onlineBlock($departure) === null]);
    }

    /** Continue: the picked seats wait in the session while the traveller signs in. */
    public function pick(Request $request, string $id): RedirectResponse
    {
        $t = $this->words($request);
        $departure = BusDeparture::with('agency')->find($id);
        if (!$departure) {
            return redirect()->route('bus.web.home');
        }
        $max = BusSettings::all()['max_seats'];
        $data = $request->validate(['seats' => 'required|array|min:1|max:' . $max, 'seats.*' => 'required|string|max:6|distinct'], [
            'seats.required' => $t('pick_up_to', ['n' => $max]),
            'seats.max' => $t('pick_up_to', ['n' => $max]),
        ]);
        if (!BusSettings::web()['booking']) {
            return back()->withErrors(['seats' => $t('not_bookable')]);
        }
        if ($why = $this->bookings->onlineBlock($departure)) {
            return back()->withErrors(['seats' => $why]);
        }
        $free = BookingService::freeOnline($departure);
        $seats = array_values(array_unique(array_map('strval', $data['seats'])));
        if ($gone = array_diff($seats, $free)) {
            return back()->withErrors(['seats' => $t('seat_taken', ['seat' => reset($gone)])]);
        }
        $request->session()->put('bus_pick.' . $departure->id, $seats);
        return redirect()->route('bus.web.travellers', ['id' => $departure->id]);
    }

    /** "Continue in the app": opens the app on a booking (bt:CODE), ID verification (bv) or bus home (bh). */
    public function app(Request $request): Response
    {
        $t = $this->words($request);
        $to = (string)$request->query('to', 'bh');
        if (!BusWebLinks::valid($to)) {
            $to = 'bh';
        }
        $back = filter_var($request->query('back'), FILTER_VALIDATE_URL) && str_starts_with((string)$request->query('back'), url('/'))
            ? $request->query('back') : route('bus.web.home');
        return $this->page($request, 'app', [
            't' => $t,
            'openApp' => BusWebLinks::openInApp($to),
            'playUrl' => BusWebLinks::playStore($to),
            'appStoreUrl' => \App\Http\Controllers\LandingPageController::appStoreUrl(),
            'back' => $back,
        ]);
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
}
