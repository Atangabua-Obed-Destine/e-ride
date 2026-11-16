<?php

namespace Modules\BusManagement\Transformers;

use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Lib\BusSettings;
use Modules\BusManagement\Service\BookingService;
use Modules\BusManagement\Service\ScheduleService;

/** What the customer app gets for agencies, departures, seat maps and bookings. Times are ISO 8601 with the offset. */
class BusPresenter
{
    public static function agency(BusAgency $a, bool $full = false): array
    {
        $out = [
            'id' => $a->id,
            'name' => $a->name,
            'origin_city' => $a->origin_city,
            'logo' => $a->logo ? dynamicStorage('storage/app/public/bus/logo/' . $a->logo) : null,
            'rating' => $a->rating,
            'rating_count' => (int)$a->rating_count,
            // "Usually answers in 3 min".
            'answer_minutes' => $a->answer_seconds_avg ? max(1, (int)ceil($a->answer_seconds_avg / 60)) : null,
            'google_place_id' => $a->google_place_id,
        ];
        if ($full) {
            $rules = BusSettings::rules($a->rules);
            $out += [
                'address' => $a->address,
                'lat' => $a->lat,
                'lng' => $a->lng,
                'phone' => $a->phone,
                'about' => $a->about,
                'rules' => [
                    'free_bags' => $rules['free_bags'],
                    'bag_kg' => $rules['bag_kg'],
                    'extra_bag_fee' => $rules['extra_bag_fee'],
                    'require_id' => $rules['require_id'],
                    'online_cutoff_minutes' => $rules['online_cutoff_minutes'],
                    // [hours before departure, % back], most generous first.
                    'cancel_tiers' => $rules['cancel_tiers'],
                ],
            ];
        }
        return $out;
    }

    /** One departure in a list. $taken: its taken seats; $block: why the app cannot book it, or null. */
    public static function departure(BusDeparture $d, array $taken, ?string $block): array
    {
        $agency = $d->agency;
        $points = $d->destination?->dropPoints?->where('active', true)
            ->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'fare' => $p->fareFor($d->class), 'minutes_from_start' => $p->minutes_from_start])
            ->filter(fn($p) => $p['fare'] !== null)->values()->all() ?? [];
        $fares = array_column($points, 'fare');
        $free = $d->seat_plan ? BookingService::freeOnline($d, $taken, $agency?->rules) : [];
        $leaves = $d->leavesAt();
        return [
            'id' => $d->id,
            'agency' => $agency ? self::agency($agency) : null,
            'from' => $agency?->origin_city,
            'to' => $d->destination?->city,
            'duration_minutes' => $d->destination?->duration_minutes,
            'class' => $d->class,
            'class_label' => ScheduleService::classLabel($d->class),
            // fixed: leaves at its time | when_full: from its time, leaves when full
            'kind' => $d->kind,
            'date' => $d->date->toDateString(),
            'leaves_at' => $leaves->toIso8601String(),
            'arrives_at' => $d->destination?->duration_minutes ? $leaves->copy()->addMinutes($d->destination->duration_minutes)->toIso8601String() : null,
            'delayed' => $d->delayed_to !== null,
            'status' => $d->status,
            // The agency answers within minutes, or the seats are held at once (instant).
            'instant' => (bool)$d->auto_accept,
            'seats_total' => (int)$d->capacity,
            'seats_left' => count($free),
            'from_fare' => $fares ? min($fares) : null,
            'drop_points' => $points,
            'bus' => $d->vehicle ? ['name' => $d->vehicle->name, 'amenities' => $d->vehicle->amenities ?? []] : null,
            'bookable' => $block === null && count($free) > 0,
            'closed_reason' => $block,
            'online_closes_at' => $agency ? BookingService::onlineClosesAt($d, $agency->rules)->toIso8601String() : null,
        ];
    }

    /**
     * The seat map: the plan's decks and rows, each seat with its state for this passenger:
     * free | taken | counter (sold at the agency only) | mine (held or bought by them).
     */
    public static function seatMap(BusDeparture $d, array $taken, array $mine): array
    {
        $taken = array_flip($taken);
        $mine = array_flip($mine);
        $counter = array_flip(BookingService::counterSeats($d));
        $decks = [];
        foreach ($d->seat_plan['decks'] ?? [] as $deck) {
            $rows = [];
            foreach ($deck['rows'] as $row) {
                $rows[] = array_map(function ($cell) use ($taken, $mine, $counter) {
                    if ($cell['t'] === 'seat') {
                        $n = (string)$cell['n'];
                        $cell['s'] = isset($mine[$n]) ? 'mine' : (isset($taken[$n]) ? 'taken' : (isset($counter[$n]) ? 'counter' : 'free'));
                    }
                    return $cell;
                }, $row);
            }
            $decks[] = ['deck' => $deck['deck'] ?? null, 'cabin' => $deck['cabin'] ?? false, 'rows' => $rows];
        }
        return ['total' => (int)($d->seat_plan['total'] ?? 0), 'decks' => $decks];
    }

    public static function booking(BusBooking $b, ?array $cancelQuote = null): array
    {
        $d = $b->departure;
        $leaves = $d?->leavesAt();
        return [
            'id' => $b->id,
            'code' => $b->code,
            // requested | accepted | paid | declined | expired | cancelled
            'status' => $b->status,
            'agency' => $b->agency ? self::agency($b->agency) + ['phone' => $b->agency->phone, 'address' => $b->agency->address,
                    'lat' => $b->agency->lat, 'lng' => $b->agency->lng] : null,
            'departure' => $d ? [
                'id' => $d->id,
                'from' => $b->agency?->origin_city,
                'to' => $d->destination?->city,
                'class' => $d->class,
                'class_label' => ScheduleService::classLabel($d->class),
                'kind' => $d->kind,
                'leaves_at' => $leaves->toIso8601String(),
                'delayed' => $d->delayed_to !== null,
                'status' => $d->status,
                'cancel_reason' => $d->cancel_reason,
                'bus' => $d->vehicle ? ['name' => $d->vehicle->name, 'registration' => $d->vehicle->registration, 'amenities' => $d->vehicle->amenities ?? []] : null,
            ] : null,
            'drop_point' => $b->dropPoint ? ['id' => $b->dropPoint->id, 'name' => $b->dropPoint->name] : null,
            'amount' => (float)$b->amount,
            'respond_by' => $b->respond_by?->toIso8601String(),
            'pay_by' => $b->pay_by?->toIso8601String(),
            'paid_at' => $b->paid_at?->toIso8601String(),
            'payment_method' => $b->payment_method,
            'decline_reason' => $b->decline_reason,
            'cancelled_by' => $b->cancelled_by,
            'refund' => $b->refund_status !== 'none' ? [
                'amount' => (float)$b->refund_amount,
                // due | sent
                'status' => $b->refund_status,
                'due_by' => $b->refund_due_by?->toIso8601String(),
                'reference' => $b->refund_ref,
            ] : null,
            'tickets' => $b->tickets->sortBy(fn($t) => (int)$t->seat)->values()->map(fn(BusTicket $t) => [
                'code' => $t->code,
                'seat' => $t->seat,
                'passenger_name' => $t->passenger_name,
                'id_number' => $t->id_number,
                'bags' => $t->bags,
                'fare' => (float)$t->fare,
                // held | valid | boarded | no_show | cancelled
                'status' => $t->status,
                // What the boarding agent scans; only shown once paid.
                'qr' => in_array($t->status, [BusTicket::VALID, BusTicket::BOARDED], true) ? 'ERIDE-BUS:' . $t->code : null,
            ])->all(),
            'can_pay' => $b->status === BusBooking::ACCEPTED && $b->pay_by?->isFuture(),
            'cancel' => $cancelQuote,
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }
}
