<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Service\ScheduleService;

/**
 * Collect: a passenger who booked in the app comes to the counter with their booking code (or its QR). The agent
 * finds the booking, checks the booker's ID, and prints the tickets (printing marks them collected).
 */
class CollectController extends Controller
{
    public function index(): View
    {
        return view('busmanagement::partner.collect');
    }

    /** ?code= a booking code, a ticket code, or what the QR says (ERIDE-BUS:...). */
    public function lookup(Request $request): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        $code = strtoupper(trim(preg_replace('/^ERIDE-BUS:/i', '', trim((string)$request->query('code', '')))));
        if ($code === '') {
            return response()->json(['found' => false, 'message' => translate('Scan or type the code the passenger shows.')]);
        }
        $booking = BusBooking::with(['tickets', 'departure.destination', 'dropPoint', 'customer', 'agency'])->where('agency_id', $agency->id)
            ->where(fn($q) => $q->where('code', $code)->orWhereIn('id', BusTicket::where('code', $code)->select('booking_id')))->first();
        if (!$booking) {
            return response()->json(['found' => false, 'message' => translate('No booking of yours has this code.')]);
        }
        $d = $booking->departure;
        $problem = match (true) {
            $booking->status === BusBooking::PAID => null,
            in_array($booking->status, BusBooking::HOLDING, true) => translate('Not paid yet: there is no ticket to print.'),
            default => translate('This booking was cancelled or ran out: there is no ticket to print.'),
        };
        if (!$problem && $d && !$d->isOpen()) {
            $problem = translate('This bus has already left or was cancelled.');
        }
        $live = $booking->tickets->whereIn('status', [BusTicket::VALID, BusTicket::BOARDED])->sortBy(fn($t) => (int)$t->seat)->values();
        return response()->json([
            'found' => true,
            'problem' => $problem,
            'code' => $booking->code,
            'source' => $booking->source,
            'name' => trim(($booking->customer?->first_name ?? '') . ' ' . ($booking->customer?->last_name ?? '')) ?: $live->first()?->passenger_name,
            'phone' => $booking->phone ?: $booking->customer?->phone,
            'departure' => $d ? $d->leavesAt()->translatedFormat('D j M, H:i') . ' · ' . $d->destination?->city . ' · ' . ScheduleService::classLabel($d->class) : null,
            'drop_point' => $booking->dropPoint?->name,
            'amount' => getCurrencyFormat($booking->amount),
            'booker' => TodayController::booker($booking),
            'tickets' => $live->map(fn(BusTicket $t) => ['seat' => $t->seat, 'name' => $t->passenger_name, 'collected' => $t->collected_at !== null,
                'printed' => $t->print_count])->all(),
            'print' => $problem ? null : route('bus-partner.tickets.print', $booking->code),
        ]);
    }
}
