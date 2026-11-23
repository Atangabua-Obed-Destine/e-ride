<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Service\BookingService;
use Modules\BusManagement\Service\PaperService;
use Modules\BusManagement\Service\ScheduleService;

/**
 * Paper sheets: the pencil seat sheet of each departure kept in step with E-Ride (pencil, erase, check,
 * print), and pausing sales in the app. The feed is polled on every portal page and rings loudly.
 */
class PaperController extends Controller
{
    public function __construct(protected PaperService $paper)
    {
    }

    public function index(Request $request): View
    {
        $agency = $request->attributes->get('busAgency');
        $departures = $this->paper->departures($agency);
        $ids = $departures->pluck('id')->map(fn($id) => (string)$id)->all();
        $system = $this->paper->systemSeats($ids);
        $todo = collect($this->paper->todo($agency, $departures))->groupBy('departure_id');
        $rows = $departures->map(fn(BusDeparture $d) => [
            'd' => $d,
            'label' => PaperService::label($d),
            'class' => ScheduleService::classLabel($d->class),
            'taken' => count($system[(string)$d->id] ?? []),
            'todo' => count($todo[(string)$d->id] ?? []),
        ]);
        $on = PaperService::on($agency);
        return view('busmanagement::partner.paper', compact('rows', 'on'));
    }

    /** Polled by every portal page: what to pencil or erase, sheets to check, and whether app sales are paused. */
    public function feed(Request $request): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        return response()->json(self::state($this->paper, $agency));
    }

    public static function state(PaperService $paper, BusAgency $agency): array
    {
        $on = PaperService::on($agency);
        $departures = $on ? $paper->departures($agency) : collect();
        return [
            'on' => $on,
            'paused' => $agency->online_paused_at ? ['at' => $agency->online_paused_at->format('H:i'), 'auto' => (bool)$agency->online_pause_auto] : null,
            'todo' => $on ? $paper->todo($agency, $departures) : [],
            'checks' => $on ? $paper->checksDue($agency, $departures) : [],
        ];
    }

    /** One departure: its seats as the sheet must show them, the to-dos, "Sheet matches". */
    public function show(Request $request, string $departure): View
    {
        $agency = $request->attributes->get('busAgency');
        $d = $this->departure($agency, $departure);
        return view('busmanagement::partner.paper_check', [
            'd' => $d,
            'label' => PaperService::label($d),
            'class' => ScheduleService::classLabel($d->class),
            'counterKept' => BookingService::counterSeats($d, $agency->rules),
        ]);
    }

    /** The seat map of one departure with each seat's paper state, polled by the check screen. */
    public function seats(Request $request, string $departure): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $d = $this->departure($agency, $departure);
        $id = (string)$d->id;
        $sys = $this->paper->systemSeats([$id])[$id] ?? [];
        $onPaper = $this->paper->paperSeats([$id])[$id] ?? [];
        $seats = [];
        foreach ($sys as $seat => $s) {
            $seats[$seat] = ['state' => isset($onPaper[$seat]) ? 'ok' : 'pencil', 'kind' => $s['state'], 'name' => $s['name']];
        }
        foreach (array_keys($onPaper) as $seat) {
            $seats[$seat] ??= ['state' => 'erase', 'kind' => null, 'name' => null];
        }
        return response()->json([
            'plan' => $d->seat_plan,
            'seats' => $seats,
            'todo' => array_values(array_filter($this->paper->todo($agency, collect([$d])), fn($i) => $i['departure_id'] === $id)),
            'checked' => $d->paper_checked_at?->format('H:i'),
            'checked_today' => $d->paper_checked_at?->isToday() ?? false,
        ]);
    }

    /** The agent pencilled or erased one seat. */
    public function tick(Request $request, string $departure): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $d = $this->departure($agency, $departure);
        $data = $request->validate(['seat' => 'required|string|max:8', 'action' => 'required|in:pencil,erase']);
        try {
            $this->paper->mark($d, $data['seat'], $data['action'], auth()->id());
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'message' => $data['action'] === 'pencil'
            ? translate(key: 'Seat {seat} is on the sheet.', replace: ['seat' => $data['seat']])
            : translate(key: 'Seat {seat} is erased.', replace: ['seat' => $data['seat']])]);
    }

    public function matches(Request $request, string $departure): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $d = $this->departure($agency, $departure);
        $this->paper->matches($d, auth()->id());
        return response()->json(['ok' => true, 'message' => translate('Sheet checked. It matches E-Ride.')]);
    }

    /** A new sheet: the bus layout with the seats sold already marked. Printing starts the sheet afresh. */
    public function print(Request $request, string $departure): View
    {
        $agency = $request->attributes->get('busAgency');
        $d = $this->departure($agency, $departure);
        $sys = $this->paper->printed($d, auth()->id());
        return view('busmanagement::partner.paper_print', [
            'd' => $d,
            'agency' => $agency,
            'sold' => array_filter($sys, fn($s) => $s['state'] !== 'held'),
            'held' => array_keys(array_filter($sys, fn($s) => $s['state'] === 'held')),
            'counterKept' => BookingService::counterSeats($d, $agency->rules),
            'class' => ScheduleService::classLabel($d->class),
        ]);
    }

    public function pause(Request $request): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $n = $this->paper->pause($agency, auth()->id());
        return response()->json(['ok' => true, 'message' => $n > 0
            ? translate(key: 'Sales in the app are paused. {n} waiting request(s) were declined.', replace: ['n' => $n])
            : translate('Sales in the app are paused. Your counter keeps working.')]);
    }

    public function resume(Request $request): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $this->paper->resume($agency);
        return response()->json(['ok' => true, 'message' => translate('Sales in the app are back on.')]);
    }

    protected function departure(BusAgency $agency, string $id): BusDeparture
    {
        return BusDeparture::with(['destination', 'vehicle'])->where('agency_id', $agency->id)->whereNotNull('vehicle_id')->findOrFail($id);
    }
}
