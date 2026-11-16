<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Service\BookingService;
use Modules\BusManagement\Service\CounterService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Quick sell at the counter, on the live seat map shared with the app; printable tickets. */
class SellController extends Controller
{
    public function __construct(protected CounterService $counter)
    {
    }

    public function index(Request $request): View
    {
        $agency = $request->attributes->get('busAgency');
        $departures = $this->openDepartures($agency);
        $current = $departures->firstWhere('id', $request->query('departure')) ?? $departures->first();
        return view('busmanagement::partner.sell', compact('departures', 'current'));
    }

    /** The live seat map: taken seats with who has them, polled every few seconds. */
    public function seats(Request $request, string $departure): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $d = BusDeparture::with('destination.dropPoints')->where('agency_id', $agency->id)->findOrFail($departure);
        return response()->json([
            'plan' => $d->seat_plan,
            'taken' => BookingService::seatStates($d),
            'counter_kept' => BookingService::counterSeats($d, $agency->rules),
            'open' => $d->isOpen() && $d->vehicle_id !== null,
            'online_closed' => now()->gte(BookingService::onlineClosesAt($d, $agency->rules)),
            'drop_points' => $d->destination?->dropPoints->where('active', true)->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'fare' => $p->fareFor($d->class)])
                ->filter(fn($p) => $p['fare'] !== null)->values(),
        ]);
    }

    public function sell(Request $request, string $departure): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $d = BusDeparture::where('agency_id', $agency->id)->findOrFail($departure);
        try {
            $booking = $this->counter->sell($agency, $request->attributes->get('busStaff'), $d, $request->only(['seats', 'drop_point_id', 'passengers', 'method']));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json([
            'ok' => true,
            'code' => $booking->code,
            'amount' => getCurrencyFormat($booking->amount),
            'print' => route('bus-partner.tickets.print', $booking->code),
            'message' => translate(key: 'Sold: {n} ticket(s), {amount}.', replace: ['n' => $booking->tickets->count(), 'amount' => getCurrencyFormat($booking->amount)]),
        ]);
    }

    /** Tickets to print (receipt printer or A4), one per seat, with the QR the boarding agent scans. */
    public function print(Request $request, string $code): View
    {
        $agency = $request->attributes->get('busAgency');
        $booking = BusBooking::with(['tickets', 'dropPoint', 'departure.destination', 'departure.vehicle'])->where('agency_id', $agency->id)->where('code', $code)->firstOrFail();
        $qr = fn(string $ticket) => (string)QrCode::format('svg')->size(150)->margin(0)->errorCorrection('M')->generate('ERIDE-BUS:' . $ticket);
        return view('busmanagement::partner.print_tickets', compact('booking', 'qr'));
    }

    protected function openDepartures($agency)
    {
        return BusDeparture::with(['destination', 'vehicle'])->where('agency_id', $agency->id)->whereIn('status', BusDeparture::OPEN)
            ->whereNotNull('vehicle_id')->whereBetween('date', [today()->toDateString(), today()->addDays(7)->toDateString()])
            ->orderBy('date')->orderBy('time')->get();
    }
}
