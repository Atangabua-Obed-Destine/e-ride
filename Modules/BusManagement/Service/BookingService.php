<?php

namespace Modules\BusManagement\Service;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDropPoint;
use Modules\BusManagement\Entities\BusPayment;
use Modules\BusManagement\Entities\BusSeatClaim;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Entities\BusWaitlist;
use Modules\BusManagement\Lib\BusPush;
use Modules\BusManagement\Lib\BusSettings;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Service\IdVerificationService;

/**
 * Seats from the app: the passenger asks, the agency accepts or declines, the passenger pays.
 *
 * A seat is taken by a row in bus_seat_claims (unique per departure and seat) from the moment it is
 * asked for until the booking ends without a sale, so the app and the counter can never sell it twice.
 * Requests and unpaid acceptances run out by themselves (bus:tick). A paid booking cancelled by the
 * passenger gets the agency's refund tiers; one cancelled by the agency gets everything back. The agency
 * sends refunds from its own money (refund_status due, then sent). Refunded money carries no commission.
 */
class BookingService
{
    public const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    /** Requests (asked or accepted, not paid) one passenger can have at once, all departures together. */
    public const MAX_OPEN_PER_USER = 2;
    /** A payment started before the deadline is waited for this long after it. */
    public const PAY_GRACE_MINUTES = 10;
    /** A departure nobody marked departed is closed this long after its time. */
    public const AUTO_DEPART_HOURS = 6;

    // ------------------------------------------------------------------ seats

    /** Seat numbers only the counter sells: the last ones of the plan (the back of the bus). */
    public static function counterSeats(BusDeparture $departure, ?array $rules = null): array
    {
        $seats = array_map('strval', $departure->seat_plan['seats'] ?? []);
        $n = min(count($seats), BusSettings::rules($rules ?? $departure->agency?->rules)['counter_seats']);
        return $n > 0 ? array_slice($seats, -$n) : [];
    }

    /** @return string[] seats taken on a departure (held, sold or boarded) */
    public static function takenSeats(BusDeparture $departure): array
    {
        return BusSeatClaim::where('departure_id', $departure->id)->pluck('seat')->map(fn($s) => (string)$s)->all();
    }

    /** Seats the app can still book: free and not kept for the counter. */
    public static function freeOnline(BusDeparture $departure, ?array $taken = null, ?array $rules = null): array
    {
        $taken ??= self::takenSeats($departure);
        $blocked = array_flip(array_merge($taken, self::counterSeats($departure, $rules)));
        return array_values(array_filter(array_map('strval', $departure->seat_plan['seats'] ?? []), fn($s) => !isset($blocked[$s])));
    }

    /** When the app stops selling this departure. */
    public static function onlineClosesAt(BusDeparture $departure, ?array $rules = null): Carbon
    {
        return $departure->leavesAt()->subMinutes(BusSettings::rules($rules ?? $departure->agency?->rules)['online_cutoff_minutes']);
    }

    /** Why the app cannot book this departure right now, or null when it can. */
    public function onlineBlock(BusDeparture $departure): ?string
    {
        $agency = $departure->agency;
        if (!BusSettings::enabled()) {
            return translate('Bus booking is not available in the app yet.');
        }
        if (!$agency || !$agency->isBookable()) {
            return translate('This agency is not taking bookings in the app right now.');
        }
        if (!$departure->isOpen() || !$departure->vehicle_id || empty($departure->seat_plan['seats'])) {
            return translate('This departure is not open for booking.');
        }
        if ($departure->closure_id) {
            return translate('Sales are stopped: a lockdown may affect this departure. The agency decides soon.');
        }
        if (now()->gte(self::onlineClosesAt($departure, $agency->rules))) {
            return translate('Booking in the app has closed for this departure. The agency counter may still have seats.');
        }
        return null;
    }

    // ---------------------------------------------------------------- request

    /**
     * The passenger asks for seats. Holds them at once; auto-accept lines skip the agency's answer.
     * input: seats[], drop_point_id, passengers[] {name, phone?, bags?}, phone (to pay with, optional),
     * booker_id_number (only when the agency asks for the number alone and the person has no ID on E-Ride).
     * Only the person booking gives an ID (see bookerId()), even when the seats are for others.
     */
    public function request(User $user, BusDeparture $departure, array $input): BusBooking
    {
        $departure->loadMissing(['agency', 'destination']);
        if ($why = $this->onlineBlock($departure)) {
            throw ValidationException::withMessages(['departure' => $why]);
        }
        $agency = $departure->agency;
        $rules = BusSettings::rules($agency->rules);
        $settings = BusSettings::all();
        $seatCount = count((array)($input['seats'] ?? []));

        $data = Validator::make($input, [
            'seats' => 'required|array|min:1|max:' . $settings['max_seats'],
            'seats.*' => 'required|distinct',
            'drop_point_id' => ['required', Rule::exists('bus_drop_points', 'id')->where('destination_id', $departure->destination_id)->where('active', 1)],
            'passengers' => 'required|array|size:' . max(1, $seatCount),
            'passengers.*.name' => 'required|string|min:2|max:120',
            'passengers.*.phone' => 'nullable|string|max:20',
            'booker_id_number' => ['nullable', 'string', 'min:5', 'max:40', 'regex:/^[A-Za-z0-9 \-\/]+$/'],
            'passengers.*.bags' => 'nullable|integer|min:0|max:5',
            'phone' => 'nullable|string|max:20',
        ], [
            'seats.max' => translate(key: 'At most {n} seats in one request.', replace: ['n' => $settings['max_seats']]),
            'seats.*.distinct' => translate('The same seat is chosen twice.'),
            'passengers.size' => translate('Add one passenger for each seat.'),
            'passengers.*.name.required' => translate('Add the name of every passenger.'),
            'passengers.*.name.min' => translate('Add the name of every passenger.'),
        ], ['seats' => translate('Seats'), 'drop_point_id' => translate('Where you get off')])->validate();

        $booker = $this->bookerId($user, $rules['id_check'], $data['booker_id_number'] ?? null);
        $seats = array_map(fn($s) => (string)$s, $data['seats']);
        $planSeats = array_map('strval', $departure->seat_plan['seats'] ?? []);
        if ($bad = array_diff($seats, $planSeats)) {
            throw ValidationException::withMessages(['seats' => translate(key: 'Seat {seat} is not on this bus.', replace: ['seat' => reset($bad)])]);
        }
        if ($counter = array_intersect($seats, self::counterSeats($departure, $agency->rules))) {
            throw ValidationException::withMessages(['seats' => translate(key: 'Seat {seat} is sold at the agency counter only.', replace: ['seat' => reset($counter)])]);
        }

        $drop = BusDropPoint::find($data['drop_point_id']);
        $fare = $drop->fareFor($departure->class);
        if ($fare === null) {
            throw ValidationException::withMessages(['drop_point_id' => translate('This bus does not stop there.')]);
        }

        if (BusBooking::where('customer_id', $user->id)->where('departure_id', $departure->id)->whereIn('status', BusBooking::HOLDING)->exists()) {
            throw ValidationException::withMessages(['departure' => translate('You already asked for seats on this departure. Wait for the answer, or cancel it first.')]);
        }
        if (BusBooking::where('customer_id', $user->id)->whereIn('status', BusBooking::HOLDING)->count() >= self::MAX_OPEN_PER_USER) {
            throw ValidationException::withMessages(['departure' => translate('You have seat requests waiting already. Pay or cancel one of them first.')]);
        }

        $passengers = array_values($data['passengers']);
        $lines = [];
        foreach ($seats as $i => $seat) {
            $p = $passengers[$i];
            $bags = isset($p['bags']) ? (int)$p['bags'] : $rules['free_bags'];
            $lines[] = [
                'seat' => $seat,
                'passenger_name' => trim($p['name']),
                'phone' => filled($p['phone'] ?? null) ? trim($p['phone']) : null,
                'id_number' => null,
                'bags' => $bags,
                'fare' => $fare + max(0, $bags - $rules['free_bags']) * $rules['extra_bag_fee'],
            ];
        }

        $now = now();
        $auto = (bool)$departure->auto_accept;
        $booking = DB::transaction(function () use ($user, $departure, $agency, $drop, $lines, $data, $now, $auto, $settings, $booker) {
            // The departure may have been cancelled or changed bus since it was read: check again under its lock.
            $locked = BusDeparture::whereKey($departure->id)->lockForUpdate()->first();
            if (!$locked || !$locked->isOpen() || (string)$locked->vehicle_id !== (string)$departure->vehicle_id) {
                throw ValidationException::withMessages(['departure' => translate('This departure just changed. Look at it again.')]);
            }
            // A lockdown declared a moment ago (it sets this under the same lock).
            if ($locked->closure_id) {
                throw ValidationException::withMessages(['departure' => translate('Sales are stopped: a lockdown may affect this departure. The agency decides soon.')]);
            }
            $booking = BusBooking::create([
                'code' => $this->newCode(BusBooking::class),
                'agency_id' => $agency->id,
                'departure_id' => $departure->id,
                'customer_id' => $user->id,
                'source' => BusBooking::APP,
                'drop_point_id' => $drop->id,
                'status' => $auto ? BusBooking::ACCEPTED : BusBooking::REQUESTED,
                'respond_by' => $auto ? null : $this->earliest($now->copy()->addMinutes($settings['answer_minutes']), $departure->leavesAt()),
                'accepted_at' => $auto ? $now : null,
                'pay_by' => $auto ? $this->earliest($now->copy()->addMinutes($settings['pay_minutes']), $departure->leavesAt()) : null,
                'amount' => array_sum(array_column($lines, 'fare')),
                'commission_pct' => $agency->commission_app_pct,
                'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : $user->phone,
            ] + $booker);
            foreach ($lines as $line) {
                $ticket = BusTicket::create($line + [
                    'code' => $this->newCode(BusTicket::class, 10),
                    'booking_id' => $booking->id,
                    'departure_id' => $departure->id,
                    'status' => BusTicket::HELD,
                ]);
                $this->claim($departure, $ticket);
            }
            return $booking;
        });

        return $booking->fresh(['tickets', 'departure', 'agency']);
    }

    /**
     * The booker's ID for a booking, as the agency checks it:
     *   eride / agents  an ID sent to E-Ride (verified, or pending: the agency's agents look at the photo);
     *   number          the number of an ID sent to E-Ride, or one typed for this booking.
     */
    protected function bookerId(User $user, string $check, ?string $typed): array
    {
        $id = app(IdVerificationService::class)->usable($user);
        if (!$id && $check !== 'number') {
            throw ValidationException::withMessages(['booker_id' => translate('This agency needs your ID: add your ID card or passport (once, from your profile) and book again.')]);
        }
        if (!$id && !filled($typed)) {
            throw ValidationException::withMessages(['booker_id_number' => translate('Add your ID card number: the agency needs it for its passenger list.')]);
        }
        return [
            'booker_id_type' => $id?->doc_type ?? 'id_card',
            'booker_id_number' => $id?->doc_number ?? Str::upper(preg_replace('/\s+/', '', (string)$typed)),
            'booker_verification_id' => $id?->id,
            'booker_verified' => (bool)$id?->isVerified(),
        ];
    }

    /** Whether an agent must look at the booker's ID photo before accepting. */
    public static function needsIdCheck(BusBooking $booking, ?array $rules = null): bool
    {
        $check = BusSettings::rules($rules ?? $booking->agency?->rules)['id_check'];
        return $booking->booker_verification_id !== null && ($check === 'agents' || ($check === 'eride' && !$booking->booker_verified));
    }

    /** Takes the seat of a ticket, or fails when someone has it. */
    protected function claim(BusDeparture $departure, BusTicket $ticket): void
    {
        try {
            BusSeatClaim::create(['departure_id' => $departure->id, 'seat' => $ticket->seat, 'ticket_id' => $ticket->id]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['seats' => translate(key: 'Seat {seat} was just taken. Choose another one.', replace: ['seat' => $ticket->seat])]);
        }
    }

    // --------------------------------------------------------- agency answers

    public function accept(BusAgency $agency, BusBooking $booking, ?string $byUserId = null): BusBooking
    {
        $this->own($agency, $booking);
        $booking = DB::transaction(function () use ($booking) {
            $b = BusBooking::whereKey($booking->id)->lockForUpdate()->first();
            if ($b->status !== BusBooking::REQUESTED) {
                throw ValidationException::withMessages(['booking' => translate('This request was already answered or cancelled.')]);
            }
            if ($b->respond_by && $b->respond_by->lte(now())) {
                throw ValidationException::withMessages(['booking' => translate('Too late: this request has run out.')]);
            }
            if (self::needsIdCheck($b)) {
                $b->id_checked_by = auth()->id();
                $b->id_checked_at = now();
            }
            $b->update([
                'status' => BusBooking::ACCEPTED,
                'accepted_at' => now(),
                'pay_by' => $this->earliest(now()->addMinutes(BusSettings::all()['pay_minutes']), $b->departure->leavesAt()),
            ]);
            return $b;
        });
        $this->recordAnswer($agency, $booking);
        BusPush::send($booking->customer, 'bus_accepted', '{agency} accepted your seats',
            'Pay {amount} before {time} and your tickets are yours.',
            ['agency' => $agency->name, 'amount' => getCurrencyFormat($booking->amount), 'time' => $booking->pay_by->format('H:i')],
            ['bus_booking_id' => $booking->id]);
        return $booking->fresh();
    }

    public function decline(BusAgency $agency, BusBooking $booking, string $reason): BusBooking
    {
        $this->own($agency, $booking);
        $reason = Str::limit(trim($reason), 200, '');
        $booking = DB::transaction(function () use ($booking, $reason) {
            $b = BusBooking::whereKey($booking->id)->lockForUpdate()->first();
            if ($b->status !== BusBooking::REQUESTED) {
                throw ValidationException::withMessages(['booking' => translate('This request was already answered or cancelled.')]);
            }
            $b->update(['status' => BusBooking::DECLINED, 'decline_reason' => $reason ?: null]);
            $this->release($b);
            return $b;
        });
        $this->recordAnswer($agency, $booking);
        BusPush::send($booking->customer, 'bus_declined', '{agency} could not take your request',
            $reason !== '' ? '{reason}. Your seats are free again: try another departure.' : 'Your seats are free again: try another departure.',
            ['agency' => $agency->name, 'reason' => $reason], ['bus_booking_id' => $booking->id]);
        $this->notifyWaitlist($booking->departure);
        return $booking->fresh();
    }

    /** Average time the agency takes to answer, shown to passengers ("usually answers in 3 min"). */
    protected function recordAnswer(BusAgency $agency, BusBooking $booking): void
    {
        $seconds = max(1, (int)$booking->created_at->diffInSeconds(now(), true));
        $avg = $agency->answer_seconds_avg;
        $agency->forceFill(['answer_seconds_avg' => $avg ? (int)round($avg * 0.8 + $seconds * 0.2) : $seconds])->save();
    }

    // ----------------------------------------------------------- cancelling

    /** What the passenger gets back if they cancel now: [cancellable, percent, refund, hours_before]. */
    public function cancelQuote(BusBooking $booking): array
    {
        $booking->loadMissing(['departure', 'agency', 'tickets']);
        if (in_array($booking->status, BusBooking::HOLDING, true)) {
            return ['cancellable' => true, 'percent' => 0, 'refund' => 0, 'hours_before' => null, 'paid' => false];
        }
        if ($booking->status !== BusBooking::PAID || !$booking->departure->isOpen()
            || $booking->departure->leavesAt()->lte(now())
            || $booking->tickets->contains(fn(BusTicket $t) => $t->status !== BusTicket::VALID)) {
            return ['cancellable' => false, 'percent' => 0, 'refund' => 0, 'hours_before' => null, 'paid' => true];
        }
        $hours = now()->diffInMinutes($booking->departure->leavesAt(), true) / 60;
        // Moved because of a lockdown, or on a departure at risk: everything back, whatever the agency's tiers.
        $percent = $booking->closure_id || $booking->departure->closure_id ? 100 : BusSettings::refundPercent($booking->agency->rules ?? [], $hours);
        return [
            'cancellable' => true,
            'percent' => $percent,
            'refund' => (float)floor($booking->amount * $percent / 100),
            'hours_before' => round($hours, 1),
            'paid' => true,
        ];
    }

    public function cancelByPassenger(User $user, BusBooking $booking): BusBooking
    {
        if ((string)$booking->customer_id !== (string)$user->id) {
            abort(404);
        }
        $quote = $this->cancelQuote($booking);
        if (!$quote['cancellable']) {
            throw ValidationException::withMessages(['booking' => translate('This booking can no longer be cancelled in the app. Speak to the agency.')]);
        }
        $wasPaid = $quote['paid'];
        $booking = DB::transaction(function () use ($booking, $quote) {
            $b = BusBooking::whereKey($booking->id)->lockForUpdate()->first();
            $expected = $quote['paid'] ? [BusBooking::PAID] : BusBooking::HOLDING;
            if (!in_array($b->status, $expected, true)) {
                throw ValidationException::withMessages(['booking' => translate('This booking changed meanwhile. Look again.')]);
            }
            $this->endBooking($b, 'passenger', $quote['refund']);
            return $b;
        });
        if ($wasPaid && $quote['refund'] > 0) {
            BusPush::send($booking->customer, 'bus_refund_due', 'Booking cancelled: {amount} to come back',
                '{agency} sends your refund to your Mobile Money within {hours} hours.',
                ['amount' => getCurrencyFormat($quote['refund']), 'agency' => $booking->agency->name, 'hours' => BusSettings::all()['refund_hours']],
                ['bus_booking_id' => $booking->id]);
        }
        $this->notifyWaitlist($booking->departure);
        return $booking->fresh();
    }

    /** The agency cancels one booking (the passenger asked at the counter, a problem with the bus): all the money back. */
    public function cancelByAgency(BusAgency $agency, BusBooking $booking, string $reason): BusBooking
    {
        $this->own($agency, $booking);
        $booking = DB::transaction(function () use ($booking) {
            $b = BusBooking::whereKey($booking->id)->lockForUpdate()->first();
            if (!in_array($b->status, [...BusBooking::HOLDING, BusBooking::PAID], true)) {
                throw ValidationException::withMessages(['booking' => translate('This booking is already closed.')]);
            }
            // A passenger already on the bus keeps their seat: it must never be sold again.
            if (BusTicket::where('booking_id', $b->id)->where('status', BusTicket::BOARDED)->exists()) {
                throw ValidationException::withMessages(['booking' => translate('A passenger of this booking is already on board, so it cannot be cancelled.')]);
            }
            $this->endBooking($b, 'agency', $b->status === BusBooking::PAID ? $b->amount : 0);
            return $b;
        });
        $this->tellCancelled($booking, $reason);
        $this->notifyWaitlist($booking->departure);
        return $booking->fresh();
    }

    /** The agency cancels a whole departure: every booking ends, every app passenger who paid gets it all back. */
    public function cancelDeparture(BusAgency $agency, BusDeparture $departure, string $reason): int
    {
        if ((string)$departure->agency_id !== (string)$agency->id) {
            abort(404);
        }
        $reason = Str::limit(trim($reason), 300, '');
        $ended = DB::transaction(function () use ($departure, $reason) {
            $d = BusDeparture::whereKey($departure->id)->lockForUpdate()->first();
            if (!$d->isOpen()) {
                throw ValidationException::withMessages(['departure' => translate('This departure is already closed.')]);
            }
            if (BusTicket::where('departure_id', $d->id)->where('status', BusTicket::BOARDED)->exists()) {
                throw ValidationException::withMessages(['departure' => translate('Passengers are already on board: this departure can no longer be cancelled.')]);
            }
            $d->update(['status' => BusDeparture::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason ?: null]);
            $bookings = BusBooking::where('departure_id', $d->id)->whereIn('status', [...BusBooking::HOLDING, BusBooking::PAID])->lockForUpdate()->get();
            foreach ($bookings as $b) {
                $this->endBooking($b, 'departure', $b->status === BusBooking::PAID ? $b->amount : 0);
            }
            return $bookings;
        });
        foreach ($ended as $b) {
            $this->tellCancelled($b, $reason);
        }
        BusWaitlist::where('departure_id', $departure->id)->delete();
        return $ended->count();
    }

    protected function tellCancelled(BusBooking $booking, string $reason): void
    {
        if ($booking->source !== BusBooking::APP) {
            return;
        }
        $booking->loadMissing(['agency', 'departure.destination']);
        $replace = [
            'agency' => $booking->agency->name,
            'city' => $booking->departure->destination?->city,
            'time' => $booking->departure->leavesAt()->format('d/m H:i'),
            'reason' => Str::limit(trim($reason), 120, ''),
            'amount' => getCurrencyFormat($booking->refund_amount),
        ];
        $body = $booking->refund_amount > 0
            ? 'Your {time} trip to {city} is cancelled. {agency} sends back {amount} to your Mobile Money.'
            : 'Your {time} trip to {city} is cancelled. Nothing was paid.';
        BusPush::send($booking->customer, 'bus_cancelled', '{agency} cancelled your trip', $body . ($replace['reason'] !== '' ? ' {reason}' : ''),
            $replace, ['bus_booking_id' => $booking->id]);
    }

    /** Ends a held or paid booking: its tickets are cancelled, its seats free, any refund owed recorded. Call inside a transaction. */
    public function endBooking(BusBooking $b, string $by, float $refund): void
    {
        $wasPaid = $b->status === BusBooking::PAID;
        // A counter ticket the agency cancels is paid back in cash, there and then.
        // (A lockdown is different: the passenger is not at the counter, so the refund waits for them.)
        $cash = $wasPaid && $b->source === BusBooking::COUNTER && !in_array($by, ['passenger', 'closure'], true);
        $refund = $cash ? (float)$b->amount : ($wasPaid ? max(0, min((float)$b->amount, $refund)) : 0);
        // Added to anything already owed on it (a second payment waiting to go back).
        $alreadyOwed = $b->refund_status === 'due' ? (float)$b->refund_owed : 0;
        $owed = $alreadyOwed + ($refund > 0 && !$cash ? $refund : 0);
        $b->fill([
            'status' => BusBooking::CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $by,
            'refund_amount' => $refund,
            'refund_owed' => $owed,
            'refund_status' => $owed > 0 ? 'due' : ($refund > 0 ? 'sent' : $b->refund_status),
            'refund_due_by' => $owed > 0 ? ($alreadyOwed > 0 && $b->refund_due_by ? $b->refund_due_by : now()->addHours(BusSettings::all()['refund_hours'])) : null,
            'refund_sent_at' => $cash ? now() : $b->refund_sent_at,
        ]);
        if ($wasPaid) {
            // E-Ride takes its commission on what the agency keeps only.
            $b->commission = round(((float)$b->amount - $refund) * (float)$b->commission_pct / 100, 2);
        }
        $b->save();
        $this->release($b);
    }

    /** Frees the seats of a booking that ended without travelling. A boarded seat is never freed. */
    public function release(BusBooking $b): void
    {
        $ids = BusTicket::where('booking_id', $b->id)->where('status', '!=', BusTicket::BOARDED)->pluck('id');
        BusSeatClaim::whereIn('ticket_id', $ids)->delete();
        BusTicket::whereIn('id', $ids)->whereIn('status', [BusTicket::HELD, BusTicket::VALID])->update(['status' => BusTicket::CANCELLED]);
    }

    // --------------------------------------------------------------- paying

    /**
     * Money arrived for a booking (called by BusPaymentService inside its transaction, the booking locked).
     * Accepted: paid. Ran out meanwhile: paid too if its seats are still free, otherwise the agency owes it back.
     *
     * @return string paid | refund_due
     */
    public function settlePaid(BusBooking $b, BusPayment $payment): string
    {
        if ($b->status === BusBooking::PAID) {
            // Paid twice: the second payment goes back.
            $this->oweBack($b, (float)$payment->amount);
            return 'refund_due';
        }
        // The departure stopped running meanwhile (cancelled, a lockdown, left):
        // the seats are not sold, the money goes back. A locking read on the
        // bookings by whoever cancelled it sees this outcome either way.
        $departure = BusDeparture::find($b->departure_id);
        if ($b->status === BusBooking::ACCEPTED && (!$departure || !$departure->isOpen())) {
            $b->update(['status' => BusBooking::CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => 'departure']);
            $this->release($b);
            $this->oweBack($b, (float)$payment->amount);
            return 'refund_due';
        }
        if ($b->status !== BusBooking::ACCEPTED && !$this->reclaim($b)) {
            $this->oweBack($b, (float)$payment->amount);
            return 'refund_due';
        }
        $b->update([
            'status' => BusBooking::PAID,
            'paid_at' => now(),
            'payment_method' => str_contains(Str::lower((string)$payment->medium), 'orange') ? 'orange' : 'momo',
            'commission' => round((float)$b->amount * (float)$b->commission_pct / 100, 2),
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);
        BusTicket::where('booking_id', $b->id)->whereIn('status', [BusTicket::HELD, BusTicket::CANCELLED])->update(['status' => BusTicket::VALID]);
        return 'paid';
    }

    /**
     * Money paid beyond the fare (late, a second time, or for a departure that
     * no longer runs) that the agency sends back. Kept apart from the fare
     * refund, so statements and commission never move; added to what is still
     * owed, or a new refund when the last one was already sent.
     */
    protected function oweBack(BusBooking $b, float $amount): void
    {
        $due = $b->refund_status === 'due';
        $b->update([
            'extra_refund' => round((float)$b->extra_refund + $amount, 2),
            'refund_owed' => round(($due ? (float)$b->refund_owed : 0) + $amount, 2),
            'refund_status' => 'due',
            'refund_due_by' => $due && $b->refund_due_by ? $b->refund_due_by : now()->addHours(BusSettings::all()['refund_hours']),
        ]);
    }

    /** Takes back the seats of a booking that ran out, when nobody took them since. */
    protected function reclaim(BusBooking $b): bool
    {
        if ($b->status !== BusBooking::EXPIRED) {
            return false;
        }
        $departure = BusDeparture::find($b->departure_id);
        if (!$departure || !$departure->isOpen()) {
            return false;
        }
        try {
            DB::transaction(function () use ($b, $departure) {
                foreach (BusTicket::where('booking_id', $b->id)->get() as $ticket) {
                    $this->claim($departure, $ticket);
                }
            });
            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /** The agency sent a refund it owed (Mobile Money reference). */
    public function markRefundSent(BusAgency $agency, BusBooking $booking, string $reference): BusBooking
    {
        $this->own($agency, $booking);
        $reference = Str::limit(trim($reference), 100, '');
        if ($reference === '') {
            throw ValidationException::withMessages(['reference' => translate('Add the Mobile Money reference of the refund.')]);
        }
        $sent = 0.0;
        $booking = DB::transaction(function () use ($booking, $reference, &$sent) {
            $b = BusBooking::whereKey($booking->id)->lockForUpdate()->first();
            if ($b->refund_status !== 'due') {
                throw ValidationException::withMessages(['booking' => translate('No refund is owed on this booking.')]);
            }
            $sent = (float)$b->refund_owed ?: (float)$b->refund_amount;
            // An earlier refund's reference is kept alongside.
            $ref = $b->refund_ref && $b->refund_ref !== $reference ? Str::limit($b->refund_ref . ', ' . $reference, 100, '') : $reference;
            $b->update(['refund_status' => 'sent', 'refund_ref' => $ref, 'refund_sent_at' => now(), 'refund_owed' => 0]);
            return $b;
        });
        BusPush::send($booking->customer, 'bus_refund_sent', '{agency} sent your refund',
            '{amount} sent to your Mobile Money. Reference {ref}.',
            ['agency' => $agency->name, 'amount' => getCurrencyFormat($sent), 'ref' => $reference], ['bus_booking_id' => $booking->id]);
        return $booking->fresh();
    }

    /**
     * Every taken seat of a departure, for the agency's screens:
     * seat => {state: held | app | counter | boarded, name, code, booking}
     */
    public static function seatStates(BusDeparture $departure): array
    {
        $out = [];
        BusSeatClaim::where('bus_seat_claims.departure_id', $departure->id)
            ->join('bus_tickets as t', 't.id', '=', 'bus_seat_claims.ticket_id')
            ->join('bus_bookings as b', 'b.id', '=', 't.booking_id')
            ->get(['bus_seat_claims.seat', 't.status as ticket_status', 't.passenger_name', 't.code', 'b.source', 'b.code as booking_code', 'b.status as booking_status'])
            ->each(function ($row) use (&$out) {
                $state = match (true) {
                    $row->ticket_status === BusTicket::BOARDED => 'boarded',
                    $row->ticket_status === BusTicket::HELD => 'held',
                    $row->source === BusBooking::COUNTER => 'counter',
                    default => 'app',
                };
                $out[(string)$row->seat] = ['state' => $state, 'name' => $row->passenger_name, 'ticket' => $row->code, 'booking' => $row->booking_code];
            });
        return $out;
    }

    // ----------------------------------------------------------------- tick

    /**
     * Requests the agency did not answer in time, and acceptances not paid in time, end; their seats free.
     * A payment started before the deadline gets a few more minutes (the passenger is approving it on their phone).
     * @return array{requests: int, unpaid: int}
     */
    public function expireDue(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $done = ['requests' => 0, 'unpaid' => 0];

        BusBooking::where('status', BusBooking::REQUESTED)->where('respond_by', '<', $now)->limit(200)->get()
            ->each(function (BusBooking $booking) use (&$done) {
                if ($this->expireOne($booking, BusBooking::REQUESTED)) {
                    $done['requests']++;
                    BusPush::send($booking->customer, 'bus_request_expired', '{agency} did not answer in time',
                        'Your seats are free again. Try another departure or another agency.',
                        ['agency' => $booking->agency?->name], ['bus_booking_id' => $booking->id]);
                    $this->notifyWaitlist($booking->departure);
                }
            });

        BusBooking::where('status', BusBooking::ACCEPTED)->where('pay_by', '<', $now)->limit(200)->get()
            ->each(function (BusBooking $booking) use (&$done, $now) {
                $paying = BusPayment::where('booking_id', $booking->id)->whereIn('status', BusPayment::OPEN)->whereNotNull('initiated_at')
                    ->where('initiated_at', '<=', $booking->pay_by)->exists();
                if ($paying && $now->lt($booking->pay_by->copy()->addMinutes(self::PAY_GRACE_MINUTES))) {
                    return;
                }
                if ($this->expireOne($booking, BusBooking::ACCEPTED)) {
                    $done['unpaid']++;
                    BusPush::send($booking->customer, 'bus_pay_expired', 'Your seats were released',
                        'The time to pay for your seats with {agency} ran out. You can ask again if seats are left.',
                        ['agency' => $booking->agency?->name], ['bus_booking_id' => $booking->id]);
                    $this->notifyWaitlist($booking->departure);
                }
            });

        return $done;
    }

    protected function expireOne(BusBooking $booking, string $from): bool
    {
        return DB::transaction(function () use ($booking, $from) {
            $b = BusBooking::whereKey($booking->id)->lockForUpdate()->first();
            if ($b->status !== $from) {
                return false;
            }
            $b->update(['status' => BusBooking::EXPIRED]);
            $this->release($b);
            return true;
        });
    }

    /** Departures nobody marked departed close some hours after their time. */
    public function closePast(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $closed = 0;
        BusDeparture::whereIn('status', BusDeparture::OPEN)->where('date', '<=', $now->toDateString())->limit(500)->get()
            ->each(function (BusDeparture $d) use ($now, &$closed) {
                if ($d->leavesAt()->addHours(self::AUTO_DEPART_HOURS)->lt($now)) {
                    $d->update(['status' => BusDeparture::DEPARTED, 'departed_at' => $d->leavesAt()]);
                    BusWaitlist::where('departure_id', $d->id)->delete();
                    $closed++;
                }
            });
        return $closed;
    }

    /** Tickets printed at the counter: app passengers who have not collected theirs get a reminder 2 hours before. */
    public function collectReminders(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $sent = 0;
        BusBooking::with(['agency', 'departure.destination', 'customer', 'tickets'])->where('source', BusBooking::APP)->where('status', BusBooking::PAID)
            ->whereNull('collect_reminded_at')
            ->whereIn('departure_id', BusDeparture::whereIn('status', BusDeparture::OPEN)->whereBetween('date', [$now->copy()->subDay()->toDateString(), $now->copy()->addDay()->toDateString()])->select('id'))
            ->limit(300)->get()
            ->each(function (BusBooking $b) use ($now, &$sent) {
                if (BusSettings::rules($b->agency?->rules)['tickets'] !== 'collect' || !$b->departure) {
                    return;
                }
                $leaves = $b->departure->leavesAt();
                $uncollected = $b->tickets->contains(fn($t) => $t->status === BusTicket::VALID && $t->collected_at === null);
                if (!$uncollected || $leaves->lte($now) || $leaves->gt($now->copy()->addHours(2))) {
                    return;
                }
                $b->forceFill(['collect_reminded_at' => $now])->save();
                BusPush::send($b->customer, 'bus_collect_reminder', 'Collect your ticket at {agency}',
                    'Show code {code} at the counter before {time}. Your bus to {city} leaves at {leaves}.',
                    ['agency' => $b->agency->name, 'code' => $b->code, 'time' => $leaves->copy()->subMinutes(30)->format('H:i'),
                        'city' => $b->departure->destination?->city, 'leaves' => $leaves->format('H:i')], ['bus_booking_id' => $b->id]);
                $sent++;
            });
        return $sent;
    }

    // ------------------------------------------------------------- waitlist

    /** Toggles "tell me when a seat frees up". Returns whether the person is now on the list. */
    public function toggleWaitlist(User $user, BusDeparture $departure): bool
    {
        $row = BusWaitlist::where('departure_id', $departure->id)->where('user_id', $user->id)->first();
        if ($row) {
            $row->delete();
            return false;
        }
        if (!$departure->isOpen()) {
            throw ValidationException::withMessages(['departure' => translate('This departure is not open for booking.')]);
        }
        BusWaitlist::create(['departure_id' => $departure->id, 'user_id' => $user->id]);
        return true;
    }

    /** Seats freed up: tell the people waiting (once each). */
    public function notifyWaitlist(?BusDeparture $departure): int
    {
        if (!$departure) {
            return 0;
        }
        $departure = $departure->fresh(['agency', 'destination']);
        if ($this->onlineBlock($departure) !== null || !self::freeOnline($departure)) {
            return 0;
        }
        $rows = BusWaitlist::where('departure_id', $departure->id)->whereNull('notified_at')->limit(50)->get();
        foreach ($rows as $row) {
            $row->update(['notified_at' => now()]);
            BusPush::send(User::find($row->user_id), 'bus_seat_free', 'A seat is free to {city}',
                '{agency}, {time}: a seat just freed up. Be quick.',
                ['city' => $departure->destination?->city, 'agency' => $departure->agency->name, 'time' => $departure->leavesAt()->format('d/m H:i')],
                ['bus_departure_id' => $departure->id]);
        }
        return $rows->count();
    }

    // ---------------------------------------------------------------- helpers

    public function newCode(string $model, int $length = 8): string
    {
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= self::CODE_CHARS[random_int(0, strlen(self::CODE_CHARS) - 1)];
            }
        } while ($model::where('code', $code)->exists());
        return $code;
    }

    protected function earliest(CarbonInterface $a, CarbonInterface $b): Carbon
    {
        return Carbon::instance($a->lt($b) ? $a : $b);
    }

    protected function own(BusAgency $agency, BusBooking $booking): void
    {
        if ((string)$booking->agency_id !== (string)$agency->id) {
            abort(404);
        }
    }
}
