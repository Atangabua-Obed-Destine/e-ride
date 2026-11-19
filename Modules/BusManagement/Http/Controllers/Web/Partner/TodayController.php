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
use Illuminate\Support\Facades\Storage;
use Modules\BusManagement\Lib\BusSettings;
use Modules\BusManagement\Service\BookingService;
use Modules\UserManagement\Entities\IdVerification;
use Modules\UserManagement\Service\IdVerificationService;
use Modules\BusManagement\Service\ScheduleService;

/** Today: seat requests to answer (with a countdown and a ring), seats waiting for payment, refunds owed, today's buses. */
class TodayController extends Controller
{
    public function __construct(protected BookingService $bookings)
    {
    }

    public function index(Request $request): View
    {
        $agency = $request->attributes->get('busAgency');
        $closures = app(\Modules\BusManagement\Service\ClosureService::class);
        $suggestions = $closures->suggestions($agency);
        $atRisk = \Modules\BusManagement\Entities\BusClosure::where('agency_id', $agency->id)->where('status', 'active')->where('outcome', 'at_risk')->orderBy('decide_by')->get();
        return view('busmanagement::partner.today', compact('suggestions', 'atRisk'));
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
            // What to send now (a fare refund, a late or second payment, or both).
            'refund' => ($owed = $b->refund_status === 'due' ? ((float)$b->refund_owed ?: (float)$b->refund_amount) : (float)$b->refund_amount + (float)$b->extra_refund) > 0
                ? getCurrencyFormat($owed) : null,
            'seats' => $b->tickets->whereIn('status', [BusTicket::HELD, BusTicket::VALID, BusTicket::CANCELLED])->sortBy(fn($t) => (int)$t->seat)
                ->map(fn($t) => ['seat' => $t->seat, 'name' => $t->passenger_name, 'id_number' => $t->id_number, 'bags' => $t->bags])->values(),
            'asked_at' => $b->created_at?->toIso8601String(),
            'deadline' => ($b->status === BusBooking::REQUESTED ? $b->respond_by : ($b->status === BusBooking::ACCEPTED ? $b->pay_by : $b->refund_due_by))?->toIso8601String(),
            'source' => $b->source,
            'booker' => self::booker($b),
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

    /**
     * The booker's ID as the agency sees it: the number, E-Ride ID Verified or not, and (when an agent must look
     * at it) the photos, served through idPhoto().
     */
    public static function booker(BusBooking $b): ?array
    {
        if (!$b->booker_id_number) {
            return null;
        }
        $check = BookingService::needsIdCheck($b);
        $hasPhotos = $b->booker_verification_id !== null;
        return [
            'type' => $b->booker_id_type === 'passport' ? translate('Passport') : translate('ID card'),
            'number' => $b->booker_id_number,
            'verified' => (bool)$b->booker_verified,
            'check' => $check,
            'checked' => $b->id_checked_at !== null,
            // Photos only when an agent has to look: an agency trusting E-Ride sees the badge, the name and the number.
            'photos' => $hasPhotos && $check ? array_values(array_filter([
                route('bus-partner.bookings.id', [$b->id, 'front']),
                IdVerification::whereKey($b->booker_verification_id)->whereNotNull('back_path')->exists() ? route('bus-partner.bookings.id', [$b->id, 'back']) : null,
            ])) : [],
        ];
    }

    /** The booker's ID photo, for this agency's staff, until the day after the trip. */
    public function idPhoto(Request $request, string $booking, string $side)
    {
        $agency = $request->attributes->get('busAgency');
        $b = BusBooking::with('departure')->where('agency_id', $agency->id)->findOrFail($booking);
        abort_unless($b->booker_verification_id && in_array($side, ['front', 'back'], true) && BookingService::needsIdCheck($b), 404);
        abort_if($b->departure && $b->departure->leavesAt()->addDay()->isPast(), 404);
        $record = IdVerification::find($b->booker_verification_id);
        $path = $record ? app(IdVerificationService::class)->file($record, $side) : null;
        abort_unless($path, 404);
        return Storage::disk(IdVerificationService::DISK)->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    protected function label(?BusDeparture $d): ?string
    {
        if (!$d) {
            return null;
        }
        return $d->leavesAt()->translatedFormat('D j M, H:i') . ' · ' . $d->destination?->city . ' · ' . ScheduleService::classLabel($d->class);
    }
}
