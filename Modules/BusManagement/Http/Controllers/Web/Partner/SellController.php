<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusBooking;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Service\BookingService;
use Modules\BusManagement\Service\CounterService;
use Modules\BusManagement\Service\PaperService;
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
        $left = $this->leftDepartures($agency);
        $current = $departures->firstWhere('id', $request->query('departure')) ?? $left->firstWhere('id', $request->query('departure')) ?? $departures->first();
        $paper = PaperService::on($agency);
        return view('busmanagement::partner.sell', compact('departures', 'left', 'current', 'paper'));
    }

    /** The live seat map: taken seats with who has them, polled every few seconds. */
    public function seats(Request $request, string $departure): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $d = BusDeparture::with(['destination.dropPoints', 'vehicle'])->where('agency_id', $agency->id)->findOrFail($departure);
        return response()->json([
            'plan' => $d->seat_plan,
            'taken' => BookingService::seatStates($d),
            'counter_kept' => BookingService::counterSeats($d, $agency->rules),
            'open' => ($d->isOpen() && $d->vehicle_id !== null) || CounterService::lateEntryOk($d),
            // The bus left: only sales made on paper during a cut are typed in.
            'late' => !$d->isOpen() && CounterService::lateEntryOk($d),
            'online_closed' => now()->gte(BookingService::onlineClosesAt($d, $agency->rules)),
            'drop_points' => $d->destination?->dropPoints->where('active', true)->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'fare' => $p->fareFor($d->class)])
                ->filter(fn($p) => $p['fare'] !== null)->values(),
            // The paper sheet of this bus, shown under the sale and printed from here.
            'sheet' => [
                'agency' => $agency->name,
                'route' => $agency->origin_city . ' → ' . $d->destination?->city,
                'when' => $d->leavesAt()->translatedFormat('l j F Y, H:i'),
                'class' => \Modules\BusManagement\Service\ScheduleService::classLabel($d->class),
                'bus' => $d->vehicle?->registration,
                'checked' => $d->paper_checked_at?->format('d/m H:i'),
            ],
        ]);
    }

    public function sell(Request $request, string $departure): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $d = BusDeparture::where('agency_id', $agency->id)->findOrFail($departure);
        // Keeping a paper copy: the seats sold go on the sheet too (the agent ticks it).
        // The agent checked the tickets and took the money before the sale goes through.
        if (!$request->boolean('collected')) {
            return response()->json(['ok' => false, 'message' => translate('Collect the money and tick it first.')], 422);
        }
        $paper = PaperService::on($agency);
        if ($paper && !$request->boolean('paper')) {
            return response()->json(['ok' => false, 'message' => translate('Pencil the seats on the paper sheet and tick it first.')], 422);
        }
        try {
            $booking = $this->counter->sell($agency, $request->attributes->get('busStaff'), $d, $request->only(['seats', 'drop_point_id', 'passengers', 'method', 'booker_id_number', 'paper_sale', 'payer_only']));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        if ($paper && $d->fresh()->isOpen()) {
            app(PaperService::class)->pencil($d, $booking->tickets->pluck('seat')->all(), auth()->id());
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
        abort_unless($booking->status === BusBooking::PAID, 404);
        // Printing hands the tickets over: they are collected. A second print is stamped REPRINT and counted.
        $reprint = [];
        DB::transaction(function () use ($booking, &$reprint) {
            foreach ($booking->tickets()->whereIn('status', [BusTicket::VALID, BusTicket::BOARDED])->lockForUpdate()->get() as $t) {
                $reprint[$t->id] = $t->print_count > 0;
                $t->forceFill([
                    'print_count' => $t->print_count + 1,
                    'last_printed_at' => now(),
                    'collected_at' => $t->collected_at ?? now(),
                    'collected_by' => $t->collected_by ?? auth()->id(),
                ])->save();
            }
        });
        $booking->load('tickets');
        $qr = fn(string $ticket) => (string)QrCode::format('svg')->size(150)->margin(0)->errorCorrection('M')->generate('ERIDE-BUS:' . $ticket);
        return view('busmanagement::partner.print_tickets', compact('booking', 'qr', 'reprint'));
    }

    protected function openDepartures($agency)
    {
        return BusDeparture::with(['destination', 'vehicle'])->where('agency_id', $agency->id)->whereIn('status', BusDeparture::OPEN)
            ->whereNotNull('vehicle_id')->whereBetween('date', [today()->toDateString(), today()->addDays(7)->toDateString()])
            ->orderBy('date')->orderBy('time')->get();
    }

    /** Buses that left in the last days, for typing in the paper sales made during a cut. */
    protected function leftDepartures($agency)
    {
        return BusDeparture::with(['destination', 'vehicle'])->where('agency_id', $agency->id)->where('status', BusDeparture::DEPARTED)
            ->whereNotNull('vehicle_id')->whereBetween('date', [today()->subDays(PaperService::LATE_ENTRY_DAYS)->toDateString(), today()->toDateString()])
            ->orderByDesc('date')->orderByDesc('time')->get()->filter(fn($d) => CounterService::lateEntryOk($d))->values();
    }
}
