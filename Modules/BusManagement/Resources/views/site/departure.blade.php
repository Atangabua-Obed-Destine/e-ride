@extends('busmanagement::site._layout')

@php
    $at = \Illuminate\Support\Carbon::parse($info['leaves_at']);
    $canPick = $info['bookable'] && $webBooking && !$myBooking;
    $full = $info['seats_left'] === 0 && $info['closed_reason'] === null;
@endphp
@section('title', ($info['from'] ?? '') . ' → ' . ($info['to'] ?? '') . ' · ' . $t->when($at))

@push('head')
<style>
.bus{border:2px solid #E3E8E7;border-radius:36px 36px 18px 18px;padding:16px 14px 14px;display:inline-flex;flex-direction:column;gap:8px;background:#FBFCFC}
.srow{display:flex;gap:6px}
.cell{width:44px;height:40px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.seat input{position:absolute;opacity:0;width:1px;height:1px}
.seat label{width:44px;height:40px;border-radius:9px 9px 6px 6px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;cursor:pointer;background:#fff;border:1.5px solid #B8C1C0;color:#1D2D2B;user-select:none}
.seat input:checked + label{background:#E02020;border-color:#E02020;color:#fff}
.seat input:focus-visible + label{outline:2px solid #1D2D2B;outline-offset:2px}
.seat input:disabled + label{cursor:not-allowed}
.seat.taken label{background:#5F6B6A;border-color:#5F6B6A;color:#fff}
.seat.counter label{background:#F4F6F6;border:1.5px dashed #B8C1C0;color:#5F6B6A}
.seat.mine label{background:#E02020;border-color:#E02020;color:#fff}
.door{border:1.5px dashed #C9D1D0;border-radius:8px;font-size:11px;font-weight:700;color:#5F6B6A}
.lg{width:18px;height:16px;border-radius:5px 5px 3px 3px;flex-shrink:0;display:inline-block}
.pulse{width:8px;height:8px;border-radius:50%;background:#15803D;box-shadow:0 0 0 3px rgba(21,128,61,.18);display:inline-block}
.dock{position:sticky;bottom:0;background:#fff;border-top:1px solid #EEF1F0;padding:12px 16px 16px;z-index:4}
.layout{display:grid;gap:0}
@media (min-width:900px){.layout{grid-template-columns:minmax(0,1fr) 360px;gap:20px;padding:20px 16px 0;align-items:start}.dock{position:static;border:1px solid #E3E8E7;border-radius:14px}}
</style>
@endpush

@section('body')
<div class="sec" style="background:#fff;border-bottom:1px solid #E8ECEB;padding:12px 16px">
    <div class="row" style="gap:12px">
        <a class="chip" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('bus.web.search', ['from' => $info['from'], 'to' => $info['to'], 'date' => $info['date']]) }}" aria-label="{{ $t('back') }}">‹</a>
        <div class="col" style="gap:0;min-width:0">
            <h1 style="font-size:18px">{{ $t->when($at) }} · {{ $info['to'] }}</h1>
            <span class="xs muted">{{ $departure->agency->displayName() }} · {{ $t->classLabel($info['class']) }}@if($info['bus']) · {{ $info['bus']['name'] }}@endif</span>
        </div>
    </div>
</div>

@if($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif

<div class="layout">
<div>
    @if($myBooking)
        <div style="padding:12px 16px 0"><a class="ok row" style="text-decoration:none;justify-content:space-between" href="{{ route('bus.web.booking', ['code' => $myBooking->code]) }}"><span>{{ $t('booking', ['code' => $myBooking->code]) }}</span><b>›</b></a></div>
    @endif
    @if(!$info['bookable'] || !$webBooking)
        <div style="padding:12px 16px 0">
            <div class="warn col" style="gap:8px">
                <b>{{ $full ? $t('full') : $t('not_bookable') }}</b>
                @if($info['closed_reason'])<span>{{ $info['closed_reason'] }}</span>@endif
                @if($full || !$webBooking)<a class="btn dark" href="{{ route('bus.web.app', ['to' => 'bh']) }}">{{ $full ? $t('waitlist_in_app') : $t('book_in_app') }}</a>@endif
            </div>
        </div>
    @endif

    <div class="row" style="padding:12px 16px 4px;gap:8px"><span class="pulse"></span><span class="xs b" style="color:#15803D">{{ $t('live_seats') }}</span></div>
    <div class="row xs" style="padding:4px 16px 12px;gap:12px;flex-wrap:wrap">
        <span class="row" style="gap:5px"><span class="lg" style="background:#fff;border:1.5px solid #B8C1C0"></span>{{ $t('free') }}</span>
        <span class="row" style="gap:5px"><span class="lg" style="background:#E02020"></span>{{ $t('yours') }}</span>
        <span class="row" style="gap:5px"><span class="lg" style="background:#5F6B6A"></span>{{ $t('taken') }}</span>
        <span class="row" style="gap:5px"><span class="lg" style="background:#F4F6F6;border:1.5px dashed #B8C1C0"></span>{{ $t('counter_only') }}</span>
    </div>

    <form id="pick" method="POST" action="{{ route('bus.web.pick', ['id' => $departure->id]) }}">
        @csrf
        <div style="overflow-x:auto;padding:0 16px 16px;display:flex;flex-direction:column;align-items:center;gap:14px">
            @foreach($map['decks'] as $deck)
                @if(count($map['decks']) > 1)<div class="xs b muted">{{ ($deck['deck'] ?? '') === 'upper' ? $t('deck_upper') : $t('deck_lower') }}</div>@endif
                <div class="bus" role="group" aria-label="{{ $t('pick_seats') }}">
                    @foreach($deck['rows'] as $row)
                        <div class="srow">
                            @foreach($row as $cell)
                                @if($cell['t'] === 'seat')
                                    @php($n = (string)$cell['n'])
                                    @php($state = $cell['s'] ?? 'free')
                                    @php($checked = $state === 'free' && in_array($n, $picked, true))
                                    <div class="seat {{ $state }}" data-seat="{{ $n }}">
                                        <input type="checkbox" name="seats[]" value="{{ $n }}" id="s{{ $n }}" @checked($checked || $state === 'mine') @disabled($state !== 'free' || !$canPick)>
                                        <label for="s{{ $n }}" aria-label="{{ $t('seat_n', ['seat' => $n]) }}, {{ $state === 'free' ? $t('free') : ($state === 'mine' ? $t('yours') : ($state === 'counter' ? $t('counter_only') : $t('taken'))) }}">{{ $n }}</label>
                                    </div>
                                @elseif($cell['t'] === 'driver')
                                    <div class="cell" title="{{ $t('driver') }}"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8A9594" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/><path d="M12 3v6.5M4.5 15l5.3-2M19.5 15l-5.3-2"/></svg><span class="sr">{{ $t('driver') }}</span></div>
                                @elseif($cell['t'] === 'door')
                                    <div class="cell door" style="width:{{ 44 * max(1, (int)($cell['span'] ?? 1)) + 6 * (max(1, (int)($cell['span'] ?? 1)) - 1) }}px">{{ $t('door') }}</div>
                                @else
                                    <div class="cell" aria-hidden="true"></div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </form>
</div>

<div class="dock">
    <div class="row" style="gap:12px">
        <div class="col" style="gap:1px;flex:1;min-width:0">
            <span class="sm b" id="sum">{{ $t('pick_up_to', ['n' => $maxSeats]) }}</span>
            <span class="xs muted" id="sub">{{ $t('n_free', ['n' => $info['seats_left']]) }}</span>
        </div>
        <button class="pill" style="height:50px;border-radius:12px;padding:0 20px" type="submit" form="pick" id="go" @disabled(!$canPick)>{{ $t('continue') }}</button>
    </div>
    @if($canPick && !$me)
        <div class="xs muted row" style="gap:6px;margin-top:10px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg>{{ $t('next_sign_in') }}</div>
    @endif
</div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var max = {{ (int)$maxSeats }}, fare = {{ (int)($info['from_fare'] ?? 0) }}, canPick = {{ $canPick ? 'true' : 'false' }};
    var words = {pick: @json($t('pick_up_to', ['n' => $maxSeats])), one: @json($t('seat_n', ['seat' => '#'])), many: @json($t('seats_n', ['seats' => '#'])),
        held: @json($t('held_once_sent')), free: @json($t('n_free', ['n' => '#'])), taken: @json($t('seat_taken', ['seat' => '#']))};
    var form = document.getElementById('pick'), go = document.getElementById('go');
    function boxes() { return Array.prototype.slice.call(form.querySelectorAll('input[name="seats[]"]')); }
    function money(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA'; }
    function update() {
        if (!canPick) return;
        var on = boxes().filter(function (b) { return b.checked; }).map(function (b) { return b.value; });
        boxes().forEach(function (b) { if (!b.checked && !b.closest('.seat').classList.contains('taken') && !b.closest('.seat').classList.contains('counter')) b.disabled = on.length >= max; });
        go.disabled = on.length === 0;
        document.getElementById('sum').textContent = on.length ? (on.length === 1 ? words.one.replace('#', on[0]) : words.many.replace('#', on.join(', '))) + (fare ? ' · ' + money(fare * on.length) : '') : words.pick;
        document.getElementById('sub').textContent = on.length ? words.held : words.free.replace('#', boxes().filter(function (b) { return !b.disabled || b.checked; }).length);
    }
    form.addEventListener('change', update);
    update();
    // Live: seats taken by others (app, counter, website) while this page is open.
    if (canPick) setInterval(function () {
        fetch(@json(route('bus.web.live', ['id' => $departure->id])), {headers: {'Accept': 'application/json'}}).then(function (r) { return r.json(); }).then(function (d) {
            (d.taken || []).forEach(function (s) {
                var cell = form.querySelector('.seat[data-seat="' + s + '"]');
                if (!cell || cell.classList.contains('taken')) return;
                var box = cell.querySelector('input');
                if (box.checked) { box.checked = false; alert(words.taken.replace('#', s)); }
                cell.classList.remove('free'); cell.classList.add('taken'); box.disabled = true;
            });
            update();
        }).catch(function () {});
    }, 15000);
})();
</script>
@endpush
