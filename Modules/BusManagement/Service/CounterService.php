<?php

namespace Modules\BusManagement\Service;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDropPoint;
use Modules\BusManagement\Entities\BusSeatClaim;
use Modules\BusManagement\Entities\BusStaff;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Lib\BusSettings;

/**
 * Quick sell: an agent sells seats to someone standing at the counter, paid on the spot (cash, or Mobile
 * Money to the agency's own number). It shares the live seat map with the app: the same seat claims, so a
 * seat sold here disappears from the app at once, and the other way round. Counter sales keep working when
 * online booking is paused or closed, until the bus has left. E-Ride's counter commission applies.
 */
class CounterService
{
    public const METHODS = ['cash', 'momo', 'orange'];

    /** Ways to take money at the counter: cash only while the admin's cash switch is on. */
    public static function methods(): array
    {
        return \Modules\TripManagement\Lib\PaymentWays::cash() ? self::METHODS : array_values(array_diff(self::METHODS, ['cash']));
    }

    public function __construct(protected BookingService $bookings)
    {
    }

    /** input: seats[], drop_point_id, passengers[] {name, phone?, id_number?, bags?}, method */
    public function sell(BusAgency $agency, BusStaff $staff, BusDeparture $departure, array $input): BusBooking
    {
        if ((string)$departure->agency_id !== (string)$agency->id) {
            abort(404);
        }
        if (!$departure->isOpen()) {
            throw ValidationException::withMessages(['departure' => translate('This departure has left or was cancelled.')]);
        }
        if ($departure->closure_id) {
            throw ValidationException::withMessages(['departure' => translate('A lockdown stops or puts this departure at risk: no sales until it is decided.')]);
        }
        if (!$departure->vehicle_id || empty($departure->seat_plan['seats'])) {
            throw ValidationException::withMessages(['departure' => translate('Put a bus on this departure first (Departures).')]);
        }
        $rules = BusSettings::rules($agency->rules);
        $data = Validator::make($input, [
            'seats' => 'required|array|min:1|max:20',
            'seats.*' => 'required|distinct',
            'drop_point_id' => ['required', Rule::exists('bus_drop_points', 'id')->where('destination_id', $departure->destination_id)->where('active', 1)],
            'passengers' => 'required|array|size:' . max(1, count((array)($input['seats'] ?? []))),
            'passengers.*.name' => 'required|string|min:2|max:120',
            'passengers.*.phone' => 'nullable|string|max:20',
            'passengers.*.id_number' => 'nullable|string|max:40',
            'passengers.*.bags' => 'nullable|integer|min:0|max:9',
            'method' => ['required', Rule::in(self::methods())],
            'booker_id_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9 \-\/]+$/'],
        ], [
            'passengers.*.name.required' => translate('Add the name of every passenger.'),
            'passengers.*.name.min' => translate('Add the name of every passenger.'),
            'seats.required' => translate('Choose the seats on the plan.'),
        ])->validate();

        $planSeats = array_map('strval', $departure->seat_plan['seats']);
        $seats = array_map('strval', $data['seats']);
        if ($bad = array_diff($seats, $planSeats)) {
            throw ValidationException::withMessages(['seats' => translate(key: 'Seat {seat} is not on this bus.', replace: ['seat' => reset($bad)])]);
        }
        $fare = BusDropPoint::find($data['drop_point_id'])->fareFor($departure->class);
        if ($fare === null) {
            throw ValidationException::withMessages(['drop_point_id' => translate('This bus does not stop there.')]);
        }
        $passengers = array_values($data['passengers']);

        return DB::transaction(function () use ($agency, $staff, $departure, $data, $seats, $passengers, $fare, $rules) {
            $locked = BusDeparture::whereKey($departure->id)->lockForUpdate()->first();
            if (!$locked->isOpen() || (string)$locked->vehicle_id !== (string)$departure->vehicle_id) {
                throw ValidationException::withMessages(['departure' => translate('This departure just changed. Look at it again.')]);
            }
            if ($locked->closure_id) {
                throw ValidationException::withMessages(['departure' => translate('A lockdown stops or puts this departure at risk: no sales until it is decided.')]);
            }
            $lines = [];
            foreach ($seats as $i => $seat) {
                $p = $passengers[$i];
                $bags = isset($p['bags']) ? (int)$p['bags'] : $rules['free_bags'];
                $lines[] = ['seat' => $seat, 'name' => trim($p['name']), 'phone' => filled($p['phone'] ?? null) ? trim($p['phone']) : null,
                    'id_number' => filled($p['id_number'] ?? null) ? Str::upper(trim($p['id_number'])) : null, 'bags' => $bags,
                    'fare' => $fare + max(0, $bags - $rules['free_bags']) * $rules['extra_bag_fee']];
            }
            $amount = array_sum(array_column($lines, 'fare'));
            $booking = BusBooking::create([
                'code' => $this->bookings->newCode(BusBooking::class),
                'agency_id' => $agency->id,
                'departure_id' => $departure->id,
                'source' => BusBooking::COUNTER,
                'staff_user_id' => $staff->user_id,
                'drop_point_id' => $data['drop_point_id'],
                'status' => BusBooking::PAID,
                'amount' => $amount,
                'commission_pct' => $agency->commission_counter_pct,
                'commission' => round($amount * (float)$agency->commission_counter_pct / 100, 2),
                'payment_method' => $data['method'],
                'paid_at' => now(),
                'phone' => $lines[0]['phone'],
                'booker_id_type' => filled($data['booker_id_number'] ?? null) ? 'id_card' : null,
                'booker_id_number' => filled($data['booker_id_number'] ?? null) ? Str::upper(preg_replace('/\s+/', '', $data['booker_id_number'])) : null,
            ]);
            foreach ($lines as $line) {
                $ticket = BusTicket::create([
                    'code' => $this->bookings->newCode(BusTicket::class, 10),
                    'booking_id' => $booking->id,
                    'departure_id' => $departure->id,
                    'seat' => $line['seat'],
                    'passenger_name' => $line['name'],
                    'phone' => $line['phone'],
                    'id_number' => $line['id_number'],
                    'bags' => $line['bags'],
                    'fare' => $line['fare'],
                    'status' => BusTicket::VALID,
                ]);
                try {
                    BusSeatClaim::create(['departure_id' => $departure->id, 'seat' => $line['seat'], 'ticket_id' => $ticket->id]);
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages(['seats' => translate(key: 'Seat {seat} was just taken. Choose another one.', replace: ['seat' => $line['seat']])]);
                }
            }
            return $booking->fresh(['tickets', 'dropPoint']);
        });
    }
}
