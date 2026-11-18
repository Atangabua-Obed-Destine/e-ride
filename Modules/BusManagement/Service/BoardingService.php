<?php

namespace Modules\BusManagement\Service;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Entities\BusWaitlist;
use Modules\BusManagement\Lib\BusPush;

/**
 * At the bus: boarding opens, tickets are scanned (or typed), the manifest is printed for the checkpoints,
 * the bus is marked Departed. That is the end: nothing is tracked on the road. Tickets not scanned by then
 * are no-shows. A delay moves the time and tells the app passengers.
 */
class BoardingService
{
    public function __construct(protected BookingService $bookings)
    {
    }

    public function start(BusAgency $agency, BusDeparture $departure): BusDeparture
    {
        $this->own($agency, $departure);
        if ($departure->status === BusDeparture::SCHEDULED) {
            $departure->update(['status' => BusDeparture::BOARDING, 'boarding_at' => now()]);
        }
        return $departure;
    }

    /**
     * A scanned or typed code. Returns [result, message, ticket]:
     * boarded (now on board) | already | wrong_departure | not_paid | cancelled | unknown
     */
    public function scan(BusAgency $agency, BusDeparture $departure, string $code, ?string $byUserId): array
    {
        $this->own($agency, $departure);
        $code = strtoupper(trim(preg_replace('/^ERIDE-BUS:/i', '', trim($code))));
        $ticket = $code === '' ? null : BusTicket::with(['booking.dropPoint', 'departure.destination'])
            ->whereIn('departure_id', BusDeparture::where('agency_id', $agency->id)->select('id'))
            ->where(fn($q) => $q->where('code', $code)->orWhereIn('booking_id', BusBooking::where('agency_id', $agency->id)->where('code', $code)->select('id')))
            ->first();
        if (!$ticket) {
            return ['unknown', translate('No ticket of yours has this code.'), null];
        }
        if ((string)$ticket->departure_id !== (string)$departure->id) {
            $other = $ticket->departure;
            return ['wrong_departure', translate(key: 'This ticket is for {time} to {city}, not this bus.',
                replace: ['time' => $other->leavesAt()->format('d/m H:i'), 'city' => $other->destination?->city]), $ticket];
        }
        if (!$departure->isOpen()) {
            return ['closed', translate('This bus has already left.'), $ticket];
        }
        $collectMode = \Modules\BusManagement\Lib\BusSettings::rules($agency->rules)['tickets'] === 'collect';
        return DB::transaction(function () use ($ticket, $byUserId, $departure, $collectMode) {
            $t = BusTicket::whereKey($ticket->id)->lockForUpdate()->first();
            return match ($t->status) {
                BusTicket::BOARDED => ['already', translate(key: 'Already on board since {time}. Seat {seat}.', replace: ['time' => $t->boarded_at?->format('H:i'), 'seat' => $t->seat]), $t],
                BusTicket::HELD => ['not_paid', translate('Not paid yet: this seat is only held.'), $t],
                BusTicket::CANCELLED, BusTicket::NO_SHOW => ['cancelled', translate('This ticket was cancelled.'), $t],
                default => $collectMode && $t->collected_at === null
                    ? ['not_collected', translate(key: 'Seat {seat}: not collected yet. Print the ticket now, then scan it.', replace: ['seat' => $t->seat]), $t]
                    : (function () use ($t, $byUserId, $departure) {
                    $t->update(['status' => BusTicket::BOARDED, 'boarded_at' => now(), 'boarded_by' => $byUserId]);
                    if ($departure->status === BusDeparture::SCHEDULED) {
                        $departure->update(['status' => BusDeparture::BOARDING, 'boarding_at' => now()]);
                    }
                    return ['boarded', translate(key: 'Seat {seat}: {name}. Welcome on board.', replace: ['seat' => $t->seat, 'name' => $t->passenger_name]), $t];
                })(),
            };
        });
    }

    /** A mistaken scan. */
    public function undo(BusAgency $agency, BusDeparture $departure, string $ticketCode): void
    {
        $this->own($agency, $departure);
        if (!$departure->isOpen()) {
            throw ValidationException::withMessages(['departure' => translate('This bus has already left.')]);
        }
        BusTicket::where('departure_id', $departure->id)->where('code', $ticketCode)->where('status', BusTicket::BOARDED)
            ->update(['status' => BusTicket::VALID, 'boarded_at' => null, 'boarded_by' => null]);
    }

    /** The bus leaves. Requests not paid end, tickets not scanned are no-shows. Returns [boarded, no_shows]. */
    public function depart(BusAgency $agency, BusDeparture $departure): array
    {
        $this->own($agency, $departure);
        [$departure, $noShows, $boarded] = DB::transaction(function () use ($departure) {
            $d = BusDeparture::whereKey($departure->id)->lockForUpdate()->first();
            if (!$d->isOpen()) {
                throw ValidationException::withMessages(['departure' => translate('This bus has already left or was cancelled.')]);
            }
            // Locked, so a payment settling at this very moment either lands first
            // (the booking is PAID and keeps its seats) or waits and finds it ended.
            BusBooking::where('departure_id', $d->id)->whereIn('status', BusBooking::HOLDING)->lockForUpdate()->get()
                ->each(function (BusBooking $b) {
                    $b->update(['status' => BusBooking::EXPIRED]);
                    BusTicket::where('booking_id', $b->id)->update(['status' => BusTicket::CANCELLED]);
                    \Modules\BusManagement\Entities\BusSeatClaim::whereIn('ticket_id', BusTicket::where('booking_id', $b->id)->select('id'))->delete();
                });
            $noShows = BusTicket::with('booking')->where('departure_id', $d->id)->where('status', BusTicket::VALID)->get();
            BusTicket::whereIn('id', $noShows->pluck('id'))->update(['status' => BusTicket::NO_SHOW]);
            $d->update(['status' => BusDeparture::DEPARTED, 'departed_at' => now()]);
            return [$d, $noShows, BusTicket::where('departure_id', $d->id)->where('status', BusTicket::BOARDED)->count()];
        });
        BusWaitlist::where('departure_id', $departure->id)->delete();
        $departure->loadMissing('destination');
        foreach ($noShows->filter(fn($t) => $t->booking?->source === BusBooking::APP)->groupBy('booking_id') as $tickets) {
            $b = $tickets->first()->booking;
            BusPush::send($b->customer, 'bus_no_show', 'Your bus to {city} has left',
                '{agency} marked seat {seats} as not on board when it left at {time}. Speak to the agency if this is wrong.',
                ['city' => $departure->destination?->city, 'agency' => $agency->name, 'seats' => $tickets->pluck('seat')->implode(', '), 'time' => now()->format('H:i')],
                ['bus_booking_id' => $b->id]);
        }
        return [$boarded, $noShows->count()];
    }

    /** A later time for the departure; app passengers with seats are told. */
    public function delay(BusAgency $agency, BusDeparture $departure, string $time): BusDeparture
    {
        $this->own($agency, $departure);
        if (!$departure->isOpen()) {
            throw ValidationException::withMessages(['departure' => translate('This bus has already left or was cancelled.')]);
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw ValidationException::withMessages(['time' => translate('Give the new time.')]);
        }
        $new = Carbon::parse($departure->date->toDateString() . ' ' . $time);
        if ($new->lte(Carbon::parse($departure->date->toDateString() . ' ' . $departure->time))) {
            throw ValidationException::withMessages(['time' => translate('The new time must be later than the planned one.')]);
        }
        $departure->update(['delayed_to' => $time]);
        $departure->loadMissing('destination');
        BusBooking::with('customer')->where('departure_id', $departure->id)->where('source', BusBooking::APP)
            ->whereIn('status', [...BusBooking::HOLDING, BusBooking::PAID])->get()
            ->each(fn(BusBooking $b) => BusPush::send($b->customer, 'bus_delayed', 'Your bus to {city} leaves later',
                '{agency}: the bus now leaves at {time}.', ['city' => $departure->destination?->city, 'agency' => $agency->name, 'time' => $time],
                ['bus_booking_id' => $b->id]));
        return $departure->fresh();
    }

    protected function own(BusAgency $agency, BusDeparture $departure): void
    {
        if ((string)$departure->agency_id !== (string)$agency->id) {
            abort(404);
        }
    }
}
