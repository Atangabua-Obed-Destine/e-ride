<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Service\BoardingService;
use Modules\BusManagement\Service\BookingService;
use Modules\BusManagement\Service\ScheduleService;

/** Boarding: scan tickets, the manifest for the checkpoints, Departed. */
class BoardingController extends Controller
{
    public function __construct(protected BoardingService $boarding, protected BookingService $bookings)
    {
    }

    public function index(Request $request): View
    {
        $agency = $request->attributes->get('busAgency');
        // Today's buses (and those that left today, to look back), then the early ones of tomorrow.
        $departures = BusDeparture::with(['destination', 'vehicle'])->where('agency_id', $agency->id)
            ->whereBetween('date', [today()->toDateString(), today()->addDay()->toDateString()])
            ->where('status', '!=', BusDeparture::CANCELLED)->whereNotNull('vehicle_id')
            ->orderBy('date')->orderBy('time')->get()
            ->filter(fn($d) => $d->date->isToday() || $d->leavesAt()->lt(now()->addHours(12)))->values();
        $current = $departures->firstWhere('id', $request->query('departure'))
            ?? $departures->first(fn($d) => $d->isOpen()) ?? $departures->first();
        $role = $request->attributes->get('busStaff')->role;
        return view('busmanagement::partner.boarding', compact('departures', 'current', 'role'));
    }

    public function manifest(Request $request, string $departure): JsonResponse
    {
        $d = $this->find($request, $departure);
        return response()->json($this->manifestData($d));
    }

    public function scan(Request $request, string $departure): JsonResponse
    {
        $d = $this->find($request, $departure);
        [$result, $message, $ticket] = $this->boarding->scan($request->attributes->get('busAgency'), $d, (string)$request->input('code', ''), auth()->id());
        return response()->json(['result' => $result, 'ok' => $result === 'boarded', 'message' => $message, 'seat' => $ticket?->seat]);
    }

    public function undo(Request $request, string $departure, string $ticket): JsonResponse
    {
        return $this->run($request, $departure, fn($agency, $d) => $this->boarding->undo($agency, $d, $ticket), translate('Scan undone.'));
    }

    public function start(Request $request, string $departure): JsonResponse
    {
        return $this->run($request, $departure, fn($agency, $d) => $this->boarding->start($agency, $d), translate('Boarding open.'));
    }

    public function depart(Request $request, string $departure): JsonResponse
    {
        $result = null;
        $response = $this->run($request, $departure, function ($agency, $d) use (&$result) {
            $result = $this->boarding->depart($agency, $d);
        }, '');
        if ($result) {
            return response()->json(['ok' => true, 'message' => translate(key: 'Departed with {boarded} on board. {absent} not on board.', replace: ['boarded' => $result[0], 'absent' => $result[1]])]);
        }
        return $response;
    }

    public function delay(Request $request, string $departure): JsonResponse
    {
        $time = (string)$request->input('time', '');
        return $this->run($request, $departure, fn($agency, $d) => $this->boarding->delay($agency, $d, $time),
            translate(key: 'Now leaving at {time}. App passengers were told.', replace: ['time' => $time]));
    }

    public function cancel(Request $request, string $departure): JsonResponse
    {
        $reason = (string)$request->input('reason', '');
        if (trim($reason) === '') {
            return response()->json(['ok' => false, 'message' => translate('Say why: passengers see it.')], 422);
        }
        return $this->run($request, $departure, fn($agency, $d) => $this->bookings->cancelDeparture($agency, $d, $reason),
            translate('Departure cancelled. App passengers who paid are owed a full refund (Today, Refunds).'));
    }

    /** The passenger list for the police checkpoints and the driver. */
    public function print(Request $request, string $departure): View
    {
        $d = $this->find($request, $departure);
        $data = $this->manifestData($d);
        return view('busmanagement::partner.print_manifest', ['departure' => $d, 'data' => $data]);
    }

    protected function manifestData(BusDeparture $d): array
    {
        $d->loadMissing(['destination', 'vehicle']);
        $tickets = BusTicket::with(['booking.dropPoint'])->where('departure_id', $d->id)
            ->whereIn('status', [BusTicket::VALID, BusTicket::BOARDED, BusTicket::NO_SHOW])->get()->sortBy(fn($t) => (int)$t->seat)->values();
        return [
            'id' => $d->id,
            'label' => $d->leavesAt()->translatedFormat('D j M, H:i') . ' · ' . $d->destination?->city . ' · ' . ScheduleService::classLabel($d->class),
            'bus' => $d->vehicle?->registration,
            'status' => $d->status,
            'delayed_to' => $d->delayed_to ? substr($d->delayed_to, 0, 5) : null,
            'capacity' => (int)$d->capacity,
            'boarded' => $tickets->where('status', BusTicket::BOARDED)->count(),
            'sold' => $tickets->count(),
            'missing_ids' => $tickets->whereNull('id_number')->count(),
            'tickets' => $tickets->map(fn(BusTicket $t) => [
                'code' => $t->code,
                'seat' => $t->seat,
                'name' => $t->passenger_name,
                'id_number' => $t->id_number,
                'phone' => $t->phone,
                'bags' => $t->bags,
                'drop_point' => $t->booking?->dropPoint?->name,
                'source' => $t->booking?->source,
                'status' => $t->status,
                'boarded_at' => $t->boarded_at?->format('H:i'),
            ])->all(),
        ];
    }

    protected function find(Request $request, string $id): BusDeparture
    {
        return BusDeparture::where('agency_id', $request->attributes->get('busAgency')->id)->findOrFail($id);
    }

    protected function run(Request $request, string $departure, callable $fn, string $done): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $d = $this->find($request, $departure);
        try {
            $fn($agency, $d);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'message' => $done]);
    }
}
