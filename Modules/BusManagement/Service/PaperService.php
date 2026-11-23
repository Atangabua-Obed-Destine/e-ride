<?php

namespace Modules\BusManagement\Service;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusPaperSeat;
use Modules\BusManagement\Entities\BusSeatClaim;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Lib\BusPush;
use Modules\BusManagement\Lib\BusSettings;

/**
 * The paper copy. An agency keeping one has a pencil seat sheet for each departure, so when the light or the
 * internet goes it carries on selling on paper without clashing with the app.
 *
 * Every seat taken in E-Ride (except requests nobody accepted yet) must be pencilled on the sheet. Agents
 * confirm what they pencil or erase (bus_paper_seats), and the portal compares that with E-Ride: a seat
 * taken in E-Ride and not on the sheet is "pencil it", a seat on the sheet and free in E-Ride is "erase it"
 * (a payment time that ran out, a cancellation). Accepting a request and selling at the counter need the
 * tick, so those land on the sheet at once; everything else shows up as a loud to-do.
 *
 * Sales in the app can be paused (the agent steps out, a cut). Requests nobody answers twice in a row pause
 * them by themselves. Paper sales are typed in afterwards, even once the bus has left (CounterService).
 */
class PaperService
{
    /** The sheet is checked against E-Ride this long before each departure. */
    public const CHECK_BEFORE_MINUTES = 30;
    /** Requests in a row nobody answered before app sales pause by themselves. */
    public const AUTO_PAUSE_AFTER = 2;
    /** Paper sales can still be typed in this long after the bus left. */
    public const LATE_ENTRY_DAYS = 3;
    /** Departures whose sheets the portal follows. */
    public const DAYS_AHEAD = 7;

    public function __construct(protected BookingService $bookings)
    {
    }

    public static function on(?BusAgency $agency): bool
    {
        return $agency !== null && BusSettings::rules($agency->rules)['paper_copy'];
    }

    /** Departures with a sheet to keep: open, with a bus, from today for the next days. */
    public function departures(BusAgency $agency): Collection
    {
        return BusDeparture::with(['destination', 'vehicle'])->where('agency_id', $agency->id)->whereIn('status', BusDeparture::OPEN)
            ->whereNotNull('vehicle_id')->whereBetween('date', [today()->toDateString(), today()->addDays(self::DAYS_AHEAD)->toDateString()])
            ->orderBy('date')->orderBy('time')->get();
    }

    /**
     * Seats that must be on the sheets: taken in E-Ride, except requests not accepted yet.
     * @return array<string, array<string, array{state: string, name: ?string}>> departure id => seat => state
     */
    public function systemSeats(array $departureIds): array
    {
        $out = [];
        if (!$departureIds) {
            return $out;
        }
        BusSeatClaim::whereIn('bus_seat_claims.departure_id', $departureIds)
            ->join('bus_tickets as t', 't.id', '=', 'bus_seat_claims.ticket_id')
            ->join('bus_bookings as b', 'b.id', '=', 't.booking_id')
            ->where('b.status', '!=', BusBooking::REQUESTED)
            ->get(['bus_seat_claims.departure_id', 'bus_seat_claims.seat', 't.status as ticket_status', 't.passenger_name', 'b.source'])
            ->each(function ($row) use (&$out) {
                $out[(string)$row->departure_id][(string)$row->seat] = [
                    'state' => match (true) {
                        $row->ticket_status === BusTicket::BOARDED => 'boarded',
                        $row->ticket_status === BusTicket::HELD => 'held',
                        $row->source === BusBooking::COUNTER => 'counter',
                        default => 'app',
                    },
                    'name' => $row->passenger_name,
                ];
            });
        return $out;
    }

    /** @return array<string, array<string, true>> departure id => seat => on the sheet */
    public function paperSeats(array $departureIds): array
    {
        $out = [];
        if (!$departureIds) {
            return $out;
        }
        BusPaperSeat::whereIn('departure_id', $departureIds)->get(['departure_id', 'seat'])
            ->each(function ($row) use (&$out) {
                $out[(string)$row->departure_id][(string)$row->seat] = true;
            });
        return $out;
    }

    /**
     * What the agents must do on the sheets, oldest departure first.
     * @return array<int, array{departure_id: string, departure: string, seat: string, action: string, why: string}>
     */
    public function todo(BusAgency $agency, ?Collection $departures = null): array
    {
        $departures ??= $this->departures($agency);
        $ids = $departures->pluck('id')->map(fn($id) => (string)$id)->all();
        $system = $this->systemSeats($ids);
        $paper = $this->paperSeats($ids);
        $items = [];
        $erase = [];
        foreach ($departures as $d) {
            $sys = $system[(string)$d->id] ?? [];
            $onPaper = $paper[(string)$d->id] ?? [];
            foreach ($sys as $seat => $s) {
                if (!isset($onPaper[$seat])) {
                    $items[] = ['departure_id' => (string)$d->id, 'departure' => self::label($d), 'seat' => (string)$seat, 'action' => 'pencil',
                        'why' => self::stateLabel($s['state']), 'name' => $s['name'], 'leaves' => $d->leavesAt()->getTimestamp()];
                }
            }
            foreach (array_keys($onPaper) as $seat) {
                if (!isset($sys[$seat])) {
                    $erase[(string)$d->id][] = (string)$seat;
                    $items[] = ['departure_id' => (string)$d->id, 'departure' => self::label($d), 'seat' => (string)$seat, 'action' => 'erase',
                        'why' => translate('Free again in E-Ride'), 'name' => null, 'leaves' => $d->leavesAt()->getTimestamp()];
                }
            }
        }
        // Why a seat is free again: the last booking that had it.
        if ($erase) {
            $why = [];
            BusTicket::whereIn('bus_tickets.departure_id', array_keys($erase))->where('bus_tickets.status', BusTicket::CANCELLED)
                ->join('bus_bookings as b', 'b.id', '=', 'bus_tickets.booking_id')
                ->orderBy('bus_tickets.updated_at')
                ->get(['bus_tickets.departure_id', 'bus_tickets.seat', 'b.status as booking_status'])
                ->each(function ($row) use (&$why) {
                    $why[(string)$row->departure_id][(string)$row->seat] = match ($row->booking_status) {
                        BusBooking::EXPIRED => translate('The payment time ran out'),
                        BusBooking::CANCELLED => translate('Cancelled'),
                        BusBooking::DECLINED => translate('Declined'),
                        default => translate('Free again in E-Ride'),
                    };
                });
            foreach ($items as &$item) {
                if ($item['action'] === 'erase' && isset($why[$item['departure_id']][$item['seat']])) {
                    $item['why'] = $why[$item['departure_id']][$item['seat']];
                }
            }
            unset($item);
        }
        usort($items, fn($a, $b) => [$a['leaves'], $a['action'] === 'erase' ? 0 : 1, (int)$a['seat']] <=> [$b['leaves'], $b['action'] === 'erase' ? 0 : 1, (int)$b['seat']]);
        return array_map(fn($i) => array_diff_key($i, ['leaves' => 1]), $items);
    }

    /** Departures leaving soon whose sheet nobody checked against E-Ride in the last half hour before it. */
    public function checksDue(BusAgency $agency, ?Collection $departures = null): array
    {
        $departures ??= $this->departures($agency);
        $now = now();
        return $departures->filter(function (BusDeparture $d) use ($now) {
            $leaves = $d->leavesAt();
            $from = $leaves->copy()->subMinutes(self::CHECK_BEFORE_MINUTES);
            return $now->gte($from) && $now->lte($leaves->copy()->addHour())
                && (!$d->paper_checked_at || $d->paper_checked_at->lt($from));
        })->map(fn(BusDeparture $d) => ['departure_id' => (string)$d->id, 'departure' => self::label($d)])->values()->all();
    }

    /** One seat pencilled or erased on the sheet (the agent ticked it). */
    public function mark(BusDeparture $d, string $seat, string $action, ?string $userId): void
    {
        if (!in_array($seat, array_map('strval', $d->seat_plan['seats'] ?? []), true)) {
            throw ValidationException::withMessages(['seat' => translate(key: 'Seat {seat} is not on this bus.', replace: ['seat' => $seat])]);
        }
        if ($action === 'pencil') {
            $this->pencil($d, [$seat], $userId);
        } else {
            BusPaperSeat::where('departure_id', $d->id)->where('seat', $seat)->delete();
        }
    }

    /** Seats the agent pencilled (when accepting, selling, or ticking a to-do). */
    public function pencil(BusDeparture $d, array $seats, ?string $userId): void
    {
        $now = now();
        BusPaperSeat::insertOrIgnore(array_map(fn($s) => ['departure_id' => $d->id, 'seat' => (string)$s, 'by_user_id' => $userId, 'created_at' => $now], array_values($seats)));
    }

    /** "Sheet matches": the agent compared the sheet with E-Ride seat by seat. */
    public function matches(BusDeparture $d, ?string $userId): void
    {
        $this->replace($d, array_keys($this->systemSeats([(string)$d->id])[(string)$d->id] ?? []), $userId);
    }

    /**
     * A new sheet printed: it shows the seats sold (paid or on board). Seats accepted and not paid yet are
     * left for the agent to pencil, so they can be erased if the payment time runs out.
     */
    public function printed(BusDeparture $d, ?string $userId): array
    {
        $sys = $this->systemSeats([(string)$d->id])[(string)$d->id] ?? [];
        $sold = array_keys(array_filter($sys, fn($s) => $s['state'] !== 'held'));
        $this->replace($d, $sold, $userId);
        return $sys;
    }

    protected function replace(BusDeparture $d, array $seats, ?string $userId): void
    {
        DB::transaction(function () use ($d, $seats, $userId) {
            BusPaperSeat::where('departure_id', $d->id)->delete();
            if ($seats) {
                $this->pencil($d, $seats, $userId);
            }
            $d->forceFill(['paper_checked_at' => now(), 'paper_checked_by' => $userId])->save();
        });
    }

    // ------------------------------------------------------------ pausing

    /** Sales in the app stop; requests waiting for an answer are declined. Returns how many were declined. */
    public function pause(BusAgency $agency, ?string $userId, bool $auto = false): int
    {
        $paused = DB::transaction(function () use ($agency, $userId, $auto) {
            $a = BusAgency::whereKey($agency->id)->lockForUpdate()->first();
            if ($a->online_paused_at) {
                return false;
            }
            $a->forceFill(['online_paused_at' => now(), 'online_paused_by' => $userId, 'online_pause_auto' => $auto])->save();
            return true;
        });
        if (!$paused) {
            return 0;
        }
        $agency->refresh();
        $reason = translate('The agency is away from the counter for a moment');
        $declined = 0;
        BusBooking::with('customer')->where('agency_id', $agency->id)->where('status', BusBooking::REQUESTED)->get()
            ->each(function (BusBooking $booking) use ($agency, $reason, &$declined) {
                $done = DB::transaction(function () use ($booking, $reason) {
                    $b = BusBooking::whereKey($booking->id)->lockForUpdate()->first();
                    if ($b->status !== BusBooking::REQUESTED) {
                        return false;
                    }
                    $b->update(['status' => BusBooking::DECLINED, 'decline_reason' => Str::limit($reason, 200, '')]);
                    $this->bookings->release($b);
                    return true;
                });
                if ($done) {
                    $declined++;
                    BusPush::send($booking->customer, 'bus_declined', '{agency} could not take your request',
                        '{reason}. Your seats are free again: try a bit later or another departure.',
                        ['agency' => $agency->name, 'reason' => $reason], ['bus_booking_id' => $booking->id]);
                }
            });
        return $declined;
    }

    public function resume(BusAgency $agency): void
    {
        $agency->forceFill(['online_paused_at' => null, 'online_paused_by' => null, 'online_pause_auto' => false, 'unanswered_streak' => 0])->save();
    }

    /** A request ran out with nobody answering. Twice in a row: nobody is at the counter, app sales pause. */
    public function unanswered(?BusAgency $agency): void
    {
        if (!self::on($agency)) {
            return;
        }
        BusAgency::whereKey($agency->id)->increment('unanswered_streak');
        $agency->refresh();
        if ($agency->unanswered_streak >= self::AUTO_PAUSE_AFTER && !$agency->online_paused_at) {
            $this->pause($agency, null, true);
        }
    }

    /** Someone answered a request: the counter is staffed. */
    public static function answered(BusAgency $agency): void
    {
        if ($agency->unanswered_streak > 0) {
            BusAgency::whereKey($agency->id)->update(['unanswered_streak' => 0]);
            $agency->unanswered_streak = 0;
        }
    }

    // ------------------------------------------------------------- labels

    public static function label(BusDeparture $d): string
    {
        $leaves = $d->leavesAt();
        return ($leaves->isToday() ? $leaves->format('H:i') : $leaves->translatedFormat('D j M, H:i')) . ' · ' . $d->destination?->city;
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            'held' => translate('Accepted, waiting for payment'),
            'counter' => translate('Sold at the counter'),
            'boarded' => translate('On board'),
            default => translate('Sold in the app'),
        };
    }
}
