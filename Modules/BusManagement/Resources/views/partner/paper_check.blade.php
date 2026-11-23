@extends('busmanagement::partner.layout')

@section('title', translate('Check the sheet'))

@push('head')
<style>
    .pc-grid { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 16px; align-items: start; }
    @media (max-width: 860px) { .pc-grid { grid-template-columns: minmax(0, 1fr); } }
    .sp-seat.pp-ok { background: #1d2d2b; }
    .sp-seat.pp-held { background: #f59e0b; }
    .sp-seat.pp-pencil { background: #f59e0b; outline: 3px dashed #9a4a06; outline-offset: 1px; animation: ppBlink 1s steps(2) infinite; }
    .sp-seat.pp-erase { background: #fff; color: #b91c1c; box-shadow: inset 0 0 0 3px #b91c1c; text-decoration: line-through; animation: ppBlink 1s steps(2) infinite; }
    @keyframes ppBlink { 50% { opacity: .45; } }
    @media (prefers-reduced-motion: reduce) { .sp-seat.pp-pencil, .sp-seat.pp-erase { animation: none; } }
    .pc-todo { display: flex; gap: 10px; align-items: center; padding: 8px 0; border-top: 1px solid var(--line); }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="{{ route('bus-partner.paper') }}" class="btn btn-soft btn-sm" aria-label="{{ translate('Paper sheets') }}"><i class="bi bi-arrow-left"></i></a>
    <div class="flex-grow-1">
        <h1 class="fs-5 fw-bold mb-0">{{ translate('Check the sheet') }} · {{ $label }}</h1>
        <div class="b-hint">{{ $class }} · {{ $d->vehicle?->registration }} · <span id="checkedAt"></span></div>
    </div>
    <a class="btn btn-soft btn-sm" href="{{ route('bus-partner.paper.print', $d->id) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ translate('Print a new sheet') }}</a>
    <a class="btn btn-soft btn-sm" href="{{ route('bus-partner.sell', ['departure' => $d->id]) }}"><i class="bi bi-ticket-perforated"></i> {{ translate('Type in paper sales') }}</a>
</div>

<div class="pc-grid">
    <section class="b-card p-3" aria-labelledby="mapTitle">
        <h2 class="b-h mb-1" id="mapTitle">{{ translate('E-Ride now') }}</h2>
        <div class="b-hint mb-2">{{ translate('Compare with your sheet, seat by seat. Every dark or orange seat must be pencilled, and no other seat.') }}</div>
        <div class="sp-legend mb-2">
            <span><i style="background:#1d2d2b"></i>{{ translate('Sold, on the sheet') }}</span>
            <span><i style="background:#f59e0b"></i>{{ translate('Accepted, waiting for payment') }}</span>
            <span><i style="background:#f59e0b;outline:2px dashed #9a4a06"></i>{{ translate('Pencil it') }}</span>
            <span><i style="background:#fff;box-shadow:inset 0 0 0 2px #b91c1c"></i>{{ translate('Erase it') }}</span>
            <span><i style="background:#22c55e"></i>{{ translate('Free') }}</span>
            <span><i style="background:#22c55e;box-shadow:inset 0 0 0 3px #1d2d2b"></i>{{ translate('Kept for the counter') }}</span>
        </div>
        <div id="map" class="d-flex flex-column align-items-center gap-2"></div>
    </section>
    <section class="b-card p-3" aria-labelledby="todoTitle">
        <h2 class="b-h mb-2" id="todoTitle">{{ translate('To do on this sheet') }}</h2>
        <div id="todo"></div>
        <hr>
        <p class="small mb-2">{{ translate('When every seat taken in E-Ride is pencilled on your sheet, and no other seat is, confirm it.') }}</p>
        <button type="button" class="btn btn-primary w-100" id="matchBtn"><i class="bi bi-check2-square"></i> {{ translate('The sheet matches E-Ride') }}</button>
    </section>
</div>
@endsection

@push('script')
<script>
(function () {
    var T = {
        none: @json(translate('Nothing to pencil or erase.')), pencil: @json(translate('Pencil')), erase: @json(translate('Erase')),
        donePencil: @json(translate('Done, it is pencilled')), doneErase: @json(translate('Done, it is erased')),
        checked: @json(translate(key: 'Last checked {time}', replace: ['time' => '{time}'])), never: @json(translate('Never checked')),
        sure: @json(translate('Every seat taken in E-Ride is pencilled on the sheet, and no other seat is.')),
        seat: @json(translate('Seat')),
    };
    var urls = {seats: @json(route('bus-partner.paper.seats', $d->id)), tick: @json(route('bus-partner.paper.tick', $d->id)), match: @json(route('bus-partner.paper.matches', $d->id))};
    var kept = {}; @json($counterKept).forEach(function (s) { kept[s] = true; });
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var data = null, busy = false;

    function draw() {
        busSeatPlanRender(document.getElementById('map'), data.plan, {seat: function (c, el) {
            var s = data.seats[c.n];
            el.disabled = true;
            if (!s) { if (kept[c.n]) el.classList.add('st-kept'); el.setAttribute('aria-label', T.seat + ' ' + c.n); return; }
            el.classList.add(s.state === 'ok' ? (s.kind === 'held' ? 'pp-held' : 'pp-ok') : 'pp-' + s.state);
            el.title = (s.name || '') + (s.state === 'pencil' ? ' · ' + T.pencil : s.state === 'erase' ? ' · ' + T.erase : '');
            el.setAttribute('aria-label', T.seat + ' ' + c.n + ', ' + el.title);
        }});
        var box = document.getElementById('todo');
        box.innerHTML = data.todo.length ? data.todo.map(function (i) {
            var er = i.action === 'erase';
            return '<div class="pc-todo"><b class="' + (er ? 'text-danger' : '') + '">' + (er ? T.erase : T.pencil) + ' ' + esc(i.seat) + '</b>' +
                '<div class="flex-grow-1 b-hint">' + esc(i.why) + (i.name ? ' · ' + esc(i.name) : '') + '</div>' +
                '<button class="btn btn-sm ' + (er ? 'btn-primary' : 'btn-dark') + '" data-seat="' + esc(i.seat) + '" data-action="' + i.action + '">' + (er ? T.doneErase : T.donePencil) + '</button></div>';
        }).join('') : '<div class="b-hint">' + T.none + '</div>';
        document.getElementById('checkedAt').textContent = data.checked ? T.checked.replace('{time}', data.checked) : T.never;
    }
    function load() {
        return fetch(urls.seats, {headers: {'Accept': 'application/json'}}).then(function (r) { return r.json(); })
            .then(function (d) { data = d; if (!busy) draw(); });
    }
    (function poll() { load().catch(function () {}).finally(function () { setTimeout(poll, 6000); }); })();

    document.getElementById('todo').addEventListener('click', function (e) {
        var b = e.target.closest('[data-seat]');
        if (!b) return;
        b.disabled = true; busy = true;
        busPost(urls.tick, {seat: b.dataset.seat, action: b.dataset.action}).then(function (d) { busToast(d.message); })
            .finally(function () { busy = false; load(); if (window.busPaperRefresh) busPaperRefresh(); });
    });
    document.getElementById('matchBtn').addEventListener('click', function () {
        if (!confirm(T.sure)) return;
        var b = this; b.disabled = true;
        busPost(urls.match).then(function (d) { busToast(d.message); })
            .finally(function () { b.disabled = false; load(); if (window.busPaperRefresh) busPaperRefresh(); });
    });
})();
</script>
@endpush
