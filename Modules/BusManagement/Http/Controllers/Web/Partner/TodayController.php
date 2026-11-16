<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Service\BookingService;
use Modules\BusManagement\Service\ScheduleService;

/** Today: seat requests to answer (with a countdown and a ring), seats waiting for payment, refunds owed, today's buses. */
class TodayController extends Controller
{
    public function __construct(protected BookingService $bookings)
    {
    }

    public function index(): View
    {
        return view('busmanagement::partner.today');
    }

    /** Polled every few seconds by Today, and by the menu badge (?count=1). */
    public function feed(Request $request): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $requests = BusBooking::with(['tickets', 'dropPoint', 'departure.destination', 'customer'])->where('agency_id', $agency->id)
            ->where('status', BusBooking::REQUESTED)->where('respond_by', '>', now())->orderBy('respond_by')->get();
        if ($request->boolean('count')) {
            return response()->json(['count' => $requests->count()]);
        }
        $awaiting = BusBooking::with(['tickets', 'dropPoint', 'departure.destination', 'customer'])->where('agency_id', $agency->id)
            ->where('status', BusBooking::ACCEPTED)->orderBy('pay_by')->get();
        $refunds = BusBooking::with(['tickets', 'dropPoint', 'departure.destination', 'customer'])->where('agency_id', $agency->id)
            ->where('refund_status', 'due')->orderBy('refund_due_by')->get();
        $departures = BusDeparture::with(['destination', 'vehicle'])->where('agency_id', $agency->id)->where('date', today()->toDateString())
            ->where('status', '!=', BusDeparture::CANCELLED)->orderBy('time')->get();
        $counts = BusTicket::whereIn('bus_tickets.departure_id', $departures->pluck('id'))->whereIn('bus_tickets.status', [BusTicket::HELD, BusTicket::VALID, BusTicket::BOARDED])
            ->join('bus_bookings as b', 'b.id', '=', 'bus_tickets.booking_id')
            ->selectRaw('bus_tickets.departure_id, bus_tickets.status, b.source, count(*) as n')->groupBy('bus_tickets.departure_id', 'bus_tickets.status', 'b.source')->get();

        $card = fn(BusBooking $b) => [
            'id' => $b->id,
            'code' => $b->code,
            'name' => trim(($b->customer?->first_name ?? '') . ' ' . ($b->customer?->last_name ?? '')) ?: $b->tickets->first()?->passenger_name,
            'phone' => $b->phone ?: $b->customer?->phone,
            'departure' => $this->label($b->departure),
            'departure_id' => $b->departure_id,
            'drop_point' => $b->dropPoint?->name,
            'amount' => getCurrencyFormat($b->amount),
            'refund' => $b->refund_amount > 0 ? getCurrencyFormat($b->refund_amount) : null,
            'seats' => $b->tickets->whereIn('status', [BusTicket::HELD, BusTicket::VALID, BusTicket::CANCELLED])->sortBy(fn($t) => (int)$t->seat)
                ->map(fn($t) => ['seat' => $t->seat, 'name' => $t->passenger_name, 'id_number' => $t->id_number, 'bags' => $t->bags])->values(),
            'asked_at' => $b->created_at?->toIso8601String(),
            'deadline' => ($b->status === BusBooking::REQUESTED ? $b->respond_by : ($b->status === BusBooking::ACCEPTED ? $b->pay_by : $b->refund_due_by))?->toIso8601String(),
            'source' => $b->source,
        ];

        return response()->json([
            'now' => now()->toIso8601String(),
            'count' => $requests->count(),
            'requests' => $requests->map($card)->values(),
            'awaiting' => $awaiting->map($card)->values(),
            'refunds' => $refunds->map($card)->values(),
            'departures' => $departures->map(function (BusDeparture $d) use ($counts) {
                $c = $counts->where('departure_id', $d->id);
                $sum = fn($filter) => (int)$c->filter($filter)->sum('n');
                $boarded = $sum(fn($r) => $r->status === BusTicket::BOARDED);
                $held = $sum(fn($r) => $r->status === BusTicket::HELD);
                // Sold and not on board yet, by where it was sold.
                $app = $sum(fn($r) => $r->status === BusTicket::VALID && $r->source === BusBooking::APP);
                $counter = $sum(fn($r) => $r->status === BusTicket::VALID && $r->source === BusBooking::COUNTER);
                return [
                    'id' => $d->id,
                    'time' => $d->leavesAt()->format('H:i'),
                    'delayed' => $d->delayed_to !== null,
                    'to' => $d->destination?->city,
                    'class' => ScheduleService::classLabel($d->class),
                    'bus' => $d->vehicle?->registration,
                    'status' => $d->status,
                    'capacity' => (int)$d->capacity,
                    'held' => $held,
                    'app' => $app,
                    'counter' => $counter,
                    'boarded' => $boarded,
                    'free' => max(0, (int)$d->capacity - $held - $app - $counter - $boarded),
                ];
            })->values(),
        ]);
    }

    public function accept(Request $request, string $booking): JsonResponse
    {
        return $this->act($request, $booking, fn($agency, $b) => $this->bookings->accept($agency, $b, auth()->id()), translate('Accepted. The passenger has been asked to pay.'));
    }

    public function decline(Request $request, string $booking): JsonResponse
    {
        $reason = (string)$request->input('reason', '');
        return $this->act($request, $booking, fn($agency, $b) => $this->bookings->decline($agency, $b, $reason), translate('Declined. The passenger was told and the seats are free.'));
    }

    public function cancel(Request $request, string $booking): JsonResponse
    {
        $reason = (string)$request->input('reason', '');
        return $this->act($request, $booking, fn($agency, $b) => $this->bookings->cancelByAgency($agency, $b, $reason), translate('Booking cancelled. Seats are free again.'));
    }

    public function refundSent(Request $request, string $booking): JsonResponse
    {
        $ref = (string)$request->input('reference', '');
        return $this->act($request, $booking, fn($agency, $b) => $this->bookings->markRefundSent($agency, $b, $ref), translate('Refund marked as sent. The passenger was told.'));
    }

    protected function act(Request $request, string $id, callable $fn, string $done): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $b = BusBooking::where('agency_id', $agency->id)->find($id);
        if (!$b) {
            return response()->json(['ok' => false, 'message' => translate('This booking does not exist.')], 404);
        }
        try {
            $fn($agency, $b);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'message' => $done]);
    }

    protected function label(?BusDeparture $d): ?string
    {
        if (!$d) {
            return null;
        }
        return $d->leavesAt()->translatedFormat('D j M, H:i') . ' · ' . $d->destination?->city . ' · ' . ScheduleService::classLabel($d->class);
    }
}
