@extends('busmanagement::site._layout')

@php
    $d = $booking->departure;
    $at = $d->leavesAt();
    $agency = $booking->agency;
    $status = $booking->status;
    $live = in_array($status, ['requested', 'accepted'], true);
    $seatList = $booking->tickets->sortBy(fn($x) => (int)$x->seat)->pluck('seat')->implode(', ');
    // Where the news really goes: the chosen channel if it works, else the other one, else nowhere (follow the page).
    $via = in_array($booking->notify_channel, $channels, true) ? $booking->notify_channel : ($channels[0] ?? null);
    $phone = $booking->phone ?: $me->phone;
    $answerMin = $agency?->answer_seconds_avg ? max(1, (int)ceil($agency->answer_seconds_avg / 60)) : null;
@endphp
@section('title', $t('booking', ['code' => $booking->code]))

@push('head')
<style>
.st{display:flex;align-items:flex-start;gap:12px}
.dot{width:26px;height:26px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.line{width:2px;height:20px;background:#E3E8E7;margin-left:12px}
.ticket{background:#fff;border-radius:18px;border:1px solid #E3E8E7;overflow:hidden}
.th{padding:14px 16px;background:#1D2D2B;color:#fff;display:flex;justify-content:space-between;align-items:center;gap:10px}
.tg{padding:16px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.k{font-size:11px;color:#5F6B6A;font-weight:600}.v{font-size:14px;font-weight:700}
.qr{border-top:2px dashed #E3E8E7;padding:16px;display:flex;align-items:center;gap:16px}
.qr svg{width:132px;height:132px;flex-shrink:0}
.count{font-size:26px;font-weight:900;font-variant-numeric:tabular-nums}
</style>
@endpush

@section('body')
<div class="page">
    @if($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif

    {{-- Waiting for the agency --}}
    @if($status === 'requested')
        <div class="sec col" style="gap:6px;padding-top:22px">
            <div class="xs b" style="color:#B45309;letter-spacing:.04em">{{ mb_strtoupper($t('booking', ['code' => $booking->code])) }} · {{ mb_strtoupper($t('seats_held')) }}</div>
            <h1>{{ $t('answering', ['agency' => $agency->displayName()]) }}</h1>
            <p class="sm muted" style="margin:0">{{ $answerMin ? $t('usually', ['n' => $answerMin]) . ' ' : '' }}{{ $t('answering_sub') }}</p>
        </div>
        <div class="card" style="margin:6px 16px 0">
            <div class="st"><span class="dot" style="background:#15803D">@include('busmanagement::site._check')</span><div class="col" style="gap:0"><span class="sm b">{{ $t('request_sent') }}</span><span class="xs muted">{{ $t->when($at) }} · {{ $seatList }} · {{ $t->money($booking->amount) }}</span></div></div>
            <div class="line"></div>
            <div class="st"><span class="dot" style="background:#FEF3C7;border:2px solid #F59E0B"><span style="width:8px;height:8px;border-radius:50%;background:#B45309"></span></span><div class="col" style="gap:0"><span class="sm b">{{ $t('agency_answering') }}</span>@if($booking->respond_by)<span class="xs b" style="color:#7A3E0B">{{ $t('answer_due', ['time' => '']) }}<span data-until="{{ $booking->respond_by->toIso8601String() }}"></span></span>@endif</div></div>
            <div class="line"></div>
            <div class="st"><span class="dot" style="background:#F4F6F6;border:2px solid #D5DCDB"></span><div class="col" style="gap:0"><span class="sm b muted">{{ $t('pay_within', ['n' => $settings['pay_minutes']]) }}</span><span class="xs muted">{{ $t('momo_om') }}</span></div></div>
            <div class="line"></div>
            <div class="st"><span class="dot" style="background:#F4F6F6;border:2px solid #D5DCDB"></span><span class="sm b muted">{{ $t('your_ticket') }}</span></div>
        </div>
        <div class="ok row" style="margin:14px 16px 0;align-items:flex-start">{{ $via === 'whatsapp' ? $t('close_page_wa', ['phone' => $phone]) : ($via === 'sms' ? $t('close_page_sms', ['phone' => $phone]) : $t('follow_here')) }}</div>
        <form method="POST" action="{{ route('bus.web.booking.cancel', ['code' => $booking->code]) }}" class="sec" onsubmit="return confirm(@json($t('cancel_confirm')))">@csrf<button class="btn ghost" type="submit">{{ $t('cancel_request') }}</button></form>

    {{-- Accepted: pay --}}
    @elseif($status === 'accepted')
        <div class="sec col" style="gap:6px;padding-top:22px">
            <div class="row" style="gap:8px"><span class="dot" style="width:22px;height:22px;background:#15803D">@include('busmanagement::site._check')</span><span class="sm b" style="color:#15803D">{{ $t('accepted', ['agency' => $agency->displayName()]) }}</span></div>
            <h1>{{ $t('pay_to_confirm') }} · {{ count(explode(', ', $seatList)) === 1 ? $t('seat_n', ['seat' => $seatList]) : $t('seats_n', ['seats' => $seatList]) }}</h1>
        </div>
        @if($booking->pay_by)
            <div class="warn row" style="margin:6px 16px 0;justify-content:space-between">
                <div class="col" style="gap:0"><span class="xs b">{{ $t('time_left') }}</span><span class="xs">{{ $t('time_left_sub') }}</span></div>
                <span class="count" data-until="{{ $booking->pay_by->toIso8601String() }}"></span>
            </div>
        @endif
        @if($payState === 'done' || $paying)
            <div class="ok" style="margin:12px 16px 0" role="status">{{ $t('pay_waiting') }}</div>
        @elseif($payState === 'failed')
            <div class="err" role="alert">{{ $t('pay_failed') }}</div>
        @endif
        <div class="sec col" style="gap:10px">
            <div class="row" style="justify-content:space-between"><span class="sm muted">{{ $t->when($at) }} · {{ $t->classLabel($d->class) }}</span><span class="b" style="font-size:20px">{{ $t->money($booking->amount) }}</span></div>
            <div class="row" style="gap:8px"><span class="tag" style="background:#FFCC00;color:#1D2D2B">MTN MoMo</span><span class="tag" style="background:#1D2D2B;color:#FF7900">Orange Money</span></div>
            <p class="xs muted" style="margin:0">{{ $t('pay_direct', ['agency' => $agency->displayName()]) }}</p>
            <form method="POST" action="{{ route('bus.web.booking.pay', ['code' => $booking->code]) }}" onsubmit="this.querySelector('button').disabled=true">@csrf<button class="btn" type="submit">{{ $t('pay_amount', ['amount' => $t->money($booking->amount)]) }}</button></form>
            <form method="POST" action="{{ route('bus.web.booking.cancel', ['code' => $booking->code]) }}" onsubmit="return confirm(@json($t('cancel_confirm')))">@csrf<button class="btn ghost" type="submit">{{ $t('cancel_request') }}</button></form>
        </div>

    {{-- Paid: the ticket --}}
    @elseif($status === 'paid')
        <div class="row" style="padding:16px 16px 0;gap:8px"><span class="dot" style="width:22px;height:22px;background:#15803D">@include('busmanagement::site._check')</span><span class="sm b" style="color:#15803D">{{ $ticketOnWhatsApp ? $t('paid_banner_wa') : $t('paid_banner') }}</span></div>
        @if($d->status === 'cancelled')<div class="err">{{ $t('departure_cancelled') }} @if($d->cancel_reason){{ $t('reason', ['reason' => $d->cancel_reason]) }}@endif</div>@endif
        @if($collect)
            <div class="ticket" style="margin:12px 16px 0">
                <div class="th"><div class="col" style="gap:0"><span class="b">{{ $agency->displayName() }}</span><span class="xs" style="color:#C9D1D0">{{ $agency->origin_city }} → {{ $d->destination?->city }} · {{ $t->classLabel($d->class) }}</span></div><span class="b" style="letter-spacing:.06em">{{ $booking->code }}</span></div>
                <div class="tg"><div><div class="k">{{ $t('date') }}</div><div class="v">{{ $t->day($at) }}</div></div><div><div class="k">{{ $t('leaves') }}</div><div class="v">{{ $at->format('H:i') }}</div></div><div><div class="k">{{ $t('seat') }}</div><div class="v">{{ $seatList }}</div></div></div>
                <div class="qr">{!! $qr('ERIDE-BUS:' . $booking->code) !!}<div class="col" style="gap:4px"><span class="sm b">{{ $t('collect_title') }}</span><span class="xs muted">{{ $t('collect_body', ['agency' => $agency->displayName(), 'city' => $agency->origin_city]) }}</span>@if($b['collect']['collected'] ?? false)<span class="tag t-green">{{ $t('collected') }}</span>@endif</div></div>
            </div>
        @else
            @foreach($booking->tickets->sortBy(fn($x) => (int)$x->seat) as $ticket)
                @continue($ticket->status === 'cancelled')
                <div class="ticket" style="margin:12px 16px 0">
                    <div class="th"><div class="col" style="gap:0"><span class="b">{{ $agency->displayName() }}</span><span class="xs" style="color:#C9D1D0">{{ $agency->origin_city }} → {{ $d->destination?->city }} · {{ $t->classLabel($d->class) }}</span></div><span class="b" style="letter-spacing:.06em">{{ $booking->code }}</span></div>
                    <div class="tg">
                        <div><div class="k">{{ $t('date') }}</div><div class="v">{{ $t->day($at) }}</div></div>
                        <div><div class="k">{{ $t('leaves') }}</div><div class="v">{{ $at->format('H:i') }}</div></div>
                        <div><div class="k">{{ $t('seat') }}</div><div class="v">{{ $ticket->seat }}</div></div>
                        <div style="grid-column:span 2"><div class="k">{{ $t('traveller') }}</div><div class="v">{{ $ticket->passenger_name }}</div></div>
                        <div><div class="k">{{ $t('gets_off') }}</div><div class="v">{{ $booking->dropPoint?->name }}</div></div>
                    </div>
                    <div class="qr">@if(in_array($ticket->status, ['valid', 'boarded'], true)){!! $qr('ERIDE-BUS:' . $ticket->code) !!}@endif<div class="col" style="gap:4px"><span class="sm b">{{ $t('show_boarding') }}</span><span class="xs muted">{{ $t('be_there', ['city' => $agency->origin_city]) }}</span></div></div>
                </div>
            @endforeach
        @endif
        <div class="noprint" style="padding:12px 16px 0">
            <a class="btn line" href="{{ route('bus.web.booking.print', ['code' => $booking->code]) }}" target="_blank" rel="noopener">{{ $t('print') }}</a>
        </div>
        @if(!empty($b['refund']))
            <div class="warn" style="margin:12px 16px 0">{{ $b['refund']['status'] === 'sent' ? $t('refund_sent', ['agency' => $agency->displayName(), 'amount' => $t->money($b['refund']['amount'])]) : $t('refund_due', ['agency' => $agency->displayName(), 'amount' => $t->money($b['refund']['amount'])]) }}</div>
        @endif

    {{-- Ended without a ticket --}}
    @else
        @php
            $title = match (true) {
                $status === 'declined' => $t('declined_title', ['agency' => $agency->displayName()]),
                $status === 'expired' && $booking->accepted_at => $t('pay_expired_title'),
                $status === 'expired' => $t('expired_title'),
                default => $t('cancelled_title'),
            };
        @endphp
        <div class="sec col" style="gap:8px;padding-top:24px">
            <div class="xs b muted">{{ mb_strtoupper($t('booking', ['code' => $booking->code])) }}</div>
            <h1>{{ $title }}</h1>
            <p class="sm muted" style="margin:0">{{ $t->when($at) }} · {{ $agency->origin_city }} → {{ $d->destination?->city }} · {{ $seatList }}</p>
            @if($booking->decline_reason)<p class="sm" style="margin:0">{{ $t('reason', ['reason' => $booking->decline_reason]) }}</p>@endif
            @if(!empty($b['refund']))
                <div class="warn">{{ $b['refund']['status'] === 'sent' ? $t('refund_sent', ['agency' => $agency->displayName(), 'amount' => $t->money($b['refund']['amount'])]) : $t('refund_due', ['agency' => $agency->displayName(), 'amount' => $t->money($b['refund']['amount'])]) }}</div>
            @elseif(!$booking->paid_at)
                <div class="ok">{{ $t('nothing_charged') }}</div>
            @endif
            <a class="btn" style="margin-top:8px" href="{{ route('bus.web.search', ['from' => $agency->origin_city, 'to' => $d->destination?->city]) }}">{{ $t('other_departures') }}</a>
        </div>
    @endif

    @if($status === 'paid' || $live)
        <div class="noprint" style="padding:14px 16px 0">
            <div class="appband" style="flex-direction:column;align-items:stretch;gap:10px">
                <div class="col" style="gap:2px"><span class="b sm">{{ $t('easier_app') }}</span><span class="xs sub">{{ $t('easier_app_sub') }}</span></div>
                <a class="pill light" style="height:42px;border-radius:10px" href="{{ $appLink }}" data-fallback="{{ $appPage }}">{{ $t('open_ticket_app') }}</a>
            </div>
            @if($status === 'paid')<a class="xs b" href="{{ $appLink }}" data-fallback="{{ $appPage }}" style="display:block;padding:12px 0 0;color:#5F6B6A">{{ $t('cancel_in_app') }}</a>@endif
        </div>
    @endif
</div>
@endsection

@push('scripts')
@include('busmanagement::site._open_app_js')
<script>
(function () {
    // Countdowns.
    var els = document.querySelectorAll('[data-until]');
    function tick() {
        els.forEach(function (el) {
            var s = Math.max(0, Math.round((new Date(el.dataset.until) - new Date()) / 1000));
            el.textContent = Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
        });
    }
    if (els.length) { tick(); setInterval(tick, 1000); }
    @if($live || ($status === 'paid' && $collect))
    // The page follows the booking: it reloads when the agency answers, the money arrives or time runs out.
    var state = null;
    function poll() {
        fetch(@json(route('bus.web.booking.status', ['code' => $booking->code])), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (state === null) { state = d.state; }
                else if (d.state && d.state !== state) { window.location.replace(@json(route('bus.web.booking', ['code' => $booking->code]))); return; }
                setTimeout(poll, {{ $live ? 5000 : 30000 }});
            })
            .catch(function () { setTimeout(poll, 10000); });
    }
    poll();
    @endif
})();
</script>
@endpush
