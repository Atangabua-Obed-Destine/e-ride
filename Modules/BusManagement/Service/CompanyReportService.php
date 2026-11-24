<?php

namespace Modules\BusManagement\Service;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Lib\CameroonRegions;
use Modules\UserManagement\Entities\User;

/**
 * What a bus company's head office sees across its branches: today live, and reports for a period by
 * branch, route, agent and day. Money counts when it was taken (paid_at), less what went back.
 */
class CompanyReportService
{
    // ------------------------------------------------------------- today, live

    /** One card per branch for today, plus the totals. */
    public function today(Collection $branches): array
    {
        $ids = $branches->pluck('id')->map(fn($id) => (string)$id)->all();
        $deps = BusDeparture::with('destination')->whereIn('agency_id', $ids)->where('date', today()->toDateString())
            ->where('status', '!=', BusDeparture::CANCELLED)->orderBy('time')->get();
        $seats = BusTicket::whereIn('bus_tickets.departure_id', $deps->pluck('id'))
            ->whereIn('bus_tickets.status', [BusTicket::HELD, BusTicket::VALID, BusTicket::BOARDED])
            ->join('bus_bookings as b', 'b.id', '=', 'bus_tickets.booking_id')
            ->selectRaw('b.agency_id, bus_tickets.status, b.source, count(*) as n')->groupBy('b.agency_id', 'bus_tickets.status', 'b.source')->get();
        $money = BusBooking::whereIn('agency_id', $ids)->where('paid_at', '>=', today())->whereNotNull('paid_at')
            ->selectRaw('agency_id, source, payment_method, count(*) as bookings, sum(amount - coalesce(refund_amount, 0)) as total')
            ->groupBy('agency_id', 'source', 'payment_method')->get();
        $requests = BusBooking::whereIn('agency_id', $ids)->where('status', BusBooking::REQUESTED)->where('respond_by', '>', now())
            ->selectRaw('agency_id, count(*) as n')->groupBy('agency_id')->pluck('n', 'agency_id');
        $awaiting = BusBooking::whereIn('agency_id', $ids)->where('status', BusBooking::ACCEPTED)
            ->selectRaw('agency_id, count(*) as n')->groupBy('agency_id')->pluck('n', 'agency_id');
        $refunds = BusBooking::whereIn('agency_id', $ids)->where('refund_status', 'due')
            ->selectRaw('agency_id, count(*) as n')->groupBy('agency_id')->pluck('n', 'agency_id');
        $paper = app(PaperService::class);

        $cards = $branches->map(function (BusAgency $a) use ($deps, $seats, $money, $requests, $awaiting, $refunds, $paper) {
            $id = (string)$a->id;
            $mine = $deps->where('agency_id', $id);
            $s = $seats->where('agency_id', $id);
            $sum = fn($f) => (int)$s->filter($f)->sum('n');
            $m = $money->where('agency_id', $id);
            $paperOn = PaperService::on($a);
            return [
                'id' => $id,
                'name' => $a->branchLabel(),
                'city' => $a->origin_city,
                'region' => $a->region,
                'region_label' => CameroonRegions::label($a->region),
                'status' => $a->status,
                'paused' => $a->online_paused_at ? ['at' => $a->online_paused_at->format('H:i'), 'auto' => (bool)$a->online_pause_auto] : null,
                'departures' => $mine->count(),
                'left' => $mine->where('status', BusDeparture::DEPARTED)->count(),
                'next' => optional($mine->first(fn($d) => $d->isOpen() && $d->leavesAt()->gte(now()->subMinutes(30))))->leavesAt()?->format('H:i'),
                'capacity' => (int)$mine->sum('capacity'),
                'held' => $sum(fn($r) => $r->status === BusTicket::HELD),
                'app' => $sum(fn($r) => $r->status === BusTicket::VALID && $r->source === BusBooking::APP),
                'counter' => $sum(fn($r) => $r->status === BusTicket::VALID && $r->source === BusBooking::COUNTER),
                'boarded' => $sum(fn($r) => $r->status === BusTicket::BOARDED),
                'requests' => (int)($requests[$id] ?? 0),
                'awaiting' => (int)($awaiting[$id] ?? 0),
                'refunds' => (int)($refunds[$id] ?? 0),
                'paper_todo' => $paperOn ? count($paper->todo($a)) : null,
                'cash' => (float)$m->where('payment_method', 'cash')->sum('total'),
                'momo' => (float)$m->where('payment_method', 'momo')->sum('total'),
                'orange' => (float)$m->where('payment_method', 'orange')->sum('total'),
                'total' => (float)$m->sum('total'),
            ];
        })->values();

        $t = fn($k) => $cards->sum($k);
        return [
            'branches' => $cards->all(),
            'totals' => [
                'departures' => $t('departures'), 'capacity' => $t('capacity'), 'sold' => $t('app') + $t('counter') + $t('boarded'), 'held' => $t('held'),
                'requests' => $t('requests'), 'paused' => $cards->whereNotNull('paused')->count(), 'paper_todo' => $cards->sum(fn($c) => (int)$c['paper_todo']),
                'cash' => $t('cash'), 'momo' => $t('momo'), 'orange' => $t('orange'), 'total' => $t('total'),
            ],
        ];
    }

    // ------------------------------------------------------------- a period

    /** Bookings that took money in the period (counted when paid), with what went back taken off. */
    protected function paid(array $ids, CarbonInterface $from, CarbonInterface $to)
    {
        return BusBooking::whereIn('bus_bookings.agency_id', $ids)->whereNotNull('bus_bookings.paid_at')
            ->whereBetween('bus_bookings.paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    public function report(Collection $branches, CarbonInterface $from, CarbonInterface $to): array
    {
        $ids = $branches->pluck('id')->map(fn($id) => (string)$id)->all();
        $net = 'bus_bookings.amount - coalesce(bus_bookings.refund_amount, 0)';
        $tickets = '(select count(*) from bus_tickets t where t.booking_id = bus_bookings.id)';

        // By branch: money by way of paying, tickets by where they were sold.
        $money = $this->paid($ids, $from, $to)
            ->selectRaw("agency_id, source, payment_method, count(*) as bookings, sum($tickets) as tickets, sum($net) as total, sum(coalesce(commission, 0)) as commission")
            ->groupBy('agency_id', 'source', 'payment_method')->get();
        // Seats filled on the buses that left in the period.
        $left = BusDeparture::whereIn('agency_id', $ids)->where('status', BusDeparture::DEPARTED)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get(['id', 'agency_id', 'destination_id', 'capacity']);
        $onBoard = BusTicket::whereIn('departure_id', $left->pluck('id'))->whereIn('status', [BusTicket::BOARDED, BusTicket::NO_SHOW, BusTicket::VALID])
            ->selectRaw('departure_id, status, count(*) as n')->groupBy('departure_id', 'status')->get();
        $answers = BusBooking::whereIn('agency_id', $ids)->where('source', BusBooking::APP)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw("agency_id, count(*) as asked,
                sum(case when accepted_at is not null then 1 else 0 end) as accepted,
                sum(case when status = 'declined' then 1 else 0 end) as declined,
                sum(case when status = 'expired' and accepted_at is null then 1 else 0 end) as unanswered,
                avg(case when accepted_at is not null then timestampdiff(second, created_at, accepted_at) end) as answer_seconds")
            ->groupBy('agency_id')->get()->keyBy('agency_id');
        $cancelled = BusBooking::whereIn('agency_id', $ids)->whereNotNull('cancelled_at')
            ->whereBetween('cancelled_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('agency_id, count(*) as n, sum(coalesce(refund_amount, 0)) as refunded')->groupBy('agency_id')->get()->keyBy('agency_id');
        $refundsDue = BusBooking::whereIn('agency_id', $ids)->where('refund_status', 'due')
            ->selectRaw('agency_id, count(*) as n')->groupBy('agency_id')->pluck('n', 'agency_id');

        $byBranch = $branches->map(function (BusAgency $a) use ($money, $left, $onBoard, $answers, $cancelled, $refundsDue) {
            $id = (string)$a->id;
            $m = $money->where('agency_id', $id);
            $deps = $left->where('agency_id', $id);
            $ob = $onBoard->whereIn('departure_id', $deps->pluck('id'));
            $cap = (int)$deps->sum('capacity');
            $boarded = (int)$ob->where('status', BusTicket::BOARDED)->sum('n');
            $ans = $answers->get($id);
            return [
                'id' => $id, 'name' => $a->branchLabel(), 'city' => $a->origin_city, 'region' => CameroonRegions::label($a->region),
                'app_tickets' => (int)$m->where('source', BusBooking::APP)->sum('tickets'),
                'counter_tickets' => (int)$m->where('source', BusBooking::COUNTER)->sum('tickets'),
                'app_total' => (float)$m->where('source', BusBooking::APP)->sum('total'),
                'counter_total' => (float)$m->where('source', BusBooking::COUNTER)->sum('total'),
                'cash' => (float)$m->where('payment_method', 'cash')->sum('total'),
                'momo' => (float)$m->where('payment_method', 'momo')->sum('total'),
                'orange' => (float)$m->where('payment_method', 'orange')->sum('total'),
                'total' => (float)$m->sum('total'),
                'commission' => (float)$m->sum('commission'),
                'departures' => $deps->count(),
                'fill' => $cap > 0 ? round($ob->whereIn('status', [BusTicket::BOARDED, BusTicket::NO_SHOW, BusTicket::VALID])->sum('n') / $cap * 100) : null,
                'no_shows' => (int)$ob->where('status', BusTicket::NO_SHOW)->sum('n'),
                'boarded' => $boarded,
                'asked' => (int)($ans->asked ?? 0),
                'declined' => (int)($ans->declined ?? 0),
                'unanswered' => (int)($ans->unanswered ?? 0),
                'answer_minutes' => $ans && $ans->answer_seconds !== null ? round($ans->answer_seconds / 60, 1) : null,
                'cancelled' => (int)($cancelled->get($id)->n ?? 0),
                'refunded' => (float)($cancelled->get($id)->refunded ?? 0),
                'refunds_due' => (int)($refundsDue[$id] ?? 0),
            ];
        })->values();

        // By route: each branch's destinations, on the buses that left.
        $routes = BusDeparture::whereIn('bus_departures.agency_id', $ids)->where('bus_departures.status', BusDeparture::DEPARTED)
            ->whereBetween('bus_departures.date', [$from->toDateString(), $to->toDateString()])
            ->join('bus_destinations as dst', 'dst.id', '=', 'bus_departures.destination_id')
            ->selectRaw("bus_departures.agency_id, dst.city, count(*) as departures, sum(bus_departures.capacity) as capacity,
                sum((select count(*) from bus_tickets t where t.departure_id = bus_departures.id and t.status in ('boarded', 'no_show', 'valid'))) as sold,
                sum((select coalesce(sum(t.fare), 0) from bus_tickets t where t.departure_id = bus_departures.id and t.status in ('boarded', 'no_show', 'valid'))) as fares")
            ->groupBy('bus_departures.agency_id', 'dst.city')->get();
        $names = $branches->mapWithKeys(fn($a) => [(string)$a->id => $a->branchLabel()]);
        $byRoute = $routes->map(fn($r) => [
            'branch' => $names[(string)$r->agency_id] ?? '', 'from' => optional($branches->firstWhere('id', $r->agency_id))->origin_city, 'to' => $r->city,
            'departures' => (int)$r->departures, 'capacity' => (int)$r->capacity, 'sold' => (int)$r->sold,
            'fill' => $r->capacity > 0 ? round($r->sold / $r->capacity * 100) : null, 'fares' => (float)$r->fares,
        ])->sortByDesc('fares')->values();

        // By agent: counter sales of each person, with how they were paid and the signs to watch.
        $agents = $this->paid($ids, $from, $to)->where('source', BusBooking::COUNTER)
            ->selectRaw("agency_id, staff_user_id, count(*) as sales, sum($tickets) as tickets, sum($net) as total,
                sum(case when payment_method = 'cash' then $net else 0 end) as cash,
                sum(case when payment_method = 'momo' then $net else 0 end) as momo,
                sum(case when payment_method = 'orange' then $net else 0 end) as orange,
                sum(case when paper_sale = 1 then 1 else 0 end) as paper_sales,
                sum(case when payer_only = 1 then 1 else 0 end) as payer_only")
            ->groupBy('agency_id', 'staff_user_id')->get();
        $people = User::whereIn('id', $agents->pluck('staff_user_id')->filter())->get(['id', 'first_name', 'last_name'])->keyBy('id');
        $byAgent = $agents->map(fn($r) => [
            'branch' => $names[(string)$r->agency_id] ?? '',
            'name' => trim(($people[$r->staff_user_id]->first_name ?? translate('Unknown')) . ' ' . ($people[$r->staff_user_id]->last_name ?? '')),
            'sales' => (int)$r->sales, 'tickets' => (int)$r->tickets, 'total' => (float)$r->total,
            'cash' => (float)$r->cash, 'momo' => (float)$r->momo, 'orange' => (float)$r->orange,
            'paper_sales' => (int)$r->paper_sales, 'payer_only' => (int)$r->payer_only,
        ])->sortByDesc('total')->values();

        // By day.
        $byDay = $this->paid($ids, $from, $to)
            ->selectRaw("date(paid_at) as day, sum($tickets) as tickets, sum($net) as total,
                sum(case when source = 'app' then $net else 0 end) as app, sum(case when source = 'counter' then $net else 0 end) as counter")
            ->groupBy(DB::raw('date(paid_at)'))->orderBy('day')->get()
            ->map(fn($r) => ['day' => $r->day, 'tickets' => (int)$r->tickets, 'total' => (float)$r->total, 'app' => (float)$r->app, 'counter' => (float)$r->counter])->values();

        $sum = fn($k) => $byBranch->sum($k);
        return [
            'totals' => [
                'tickets' => $sum('app_tickets') + $sum('counter_tickets'), 'app_tickets' => $sum('app_tickets'), 'counter_tickets' => $sum('counter_tickets'),
                'total' => $sum('total'), 'cash' => $sum('cash'), 'momo' => $sum('momo'), 'orange' => $sum('orange'),
                'commission' => $sum('commission'), 'departures' => $sum('departures'), 'no_shows' => $sum('no_shows'),
                'cancelled' => $sum('cancelled'), 'refunds_due' => $sum('refunds_due'),
            ],
            'branches' => $byBranch->all(),
            'routes' => $byRoute->all(),
            'agents' => $byAgent->all(),
            'days' => $byDay->all(),
        ];
    }
}
