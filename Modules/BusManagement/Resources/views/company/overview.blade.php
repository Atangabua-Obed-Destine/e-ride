@extends('busmanagement::company.layout')

@section('title', translate('Overview'))

@push('head')
<style>
    .br-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; }
    .br { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 14px; display: flex; flex-direction: column; gap: 8px; }
    .br.is-paused { border-color: #fca5a5; }
    .br-top { display: flex; gap: 8px; align-items: baseline; flex-wrap: wrap; }
    .br-top b { font-size: 16px; }
    .bar { height: 10px; background: #eef1f0; border-radius: 6px; overflow: hidden; display: flex; }
    .bar span { display: block; height: 100%; }
    .br-row { display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: 13px; }
    .br-row b { font-weight: 800; }
    .money { display: flex; gap: 10px; flex-wrap: wrap; font-size: 12.5px; color: var(--muted); }
    .money b { color: var(--ink); }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="fs-5 fw-bold mb-0 me-auto">{{ translate('Today across your branches') }}</h1>
    <span class="b-hint">{{ translate('Updated') }} <span id="upd">…</span></span>
</div>
@include('busmanagement::company._filters')

<div class="kpis mb-3" id="kpis" aria-live="polite"></div>
<div class="br-grid" id="branches"></div>
@endsection

@push('script')
<script>
(function () {
    var T = {
        deps: @json(translate(key: '{n} departures · {left} left', replace: ['n' => '{n}', 'left' => '{left}'])), next: @json(translate(key: 'next at {t}', replace: ['t' => '{t}'])),
        seats: @json(translate(key: '{sold} of {cap} seats sold', replace: ['sold' => '{sold}', 'cap' => '{cap}'])),
        held: @json(translate(key: '{n} held', replace: ['n' => '{n}'])), requests: @json(translate(key: '{n} requests waiting', replace: ['n' => '{n}'])),
        paper: @json(translate(key: '{n} paper sheet to-dos', replace: ['n' => '{n}'])),
        paused: @json(translate('App sales paused')), autoPaused: @json(translate('paused by itself: requests not answered')), live: @json(translate('Taking bookings')),
        setup: @json(translate('Setting up')), stopped: @json(translate('Paused by E-Ride')), refunds: @json(translate(key: '{n} refunds to send', replace: ['n' => '{n}'])),
        today: @json(translate('Taken today')), cash: @json(translate('Cash')), pause: @json(translate('Pause app sales')), resume: @json(translate('Resume app sales')),
        open: @json(translate('Open')), kDeps: @json(translate('Departures today')), kSold: @json(translate('Seats sold')), kReq: @json(translate('Requests waiting')),
        kPaused: @json(translate('Branches paused')), kPaper: @json(translate('Paper to-dos')), kMoney: @json(translate('Taken today')), kCash: @json(translate(key: '{amount} in cash', replace: ['amount' => '{amount}'])),
        none: @json(translate('No branch to show.')), confirmPause: @json(translate('Pause app sales at this branch? Requests waiting are declined. Its counter keeps selling.')),
    };
    var canAct = @json($busCompanyStaff->canAct());
    var feedUrl = @json(route('bus-company.overview.feed') . (request()->getQueryString() ? '?' . request()->getQueryString() : ''));
    var pauseUrl = @json(url('partner/bus-company/branches/__/pause')), openUrl = @json(url('partner/bus-company/branches/__'));
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var busy = false;

    function kpi(label, value, extra) { return '<div class="kpi"><b>' + value + '</b><span>' + label + (extra ? ' · ' + extra : '') + '</span></div>'; }
    function render(d) {
        var t = d.totals;
        document.getElementById('upd').textContent = d.now;
        document.getElementById('kpis').innerHTML = kpi(T.kDeps, t.departures) + kpi(T.kSold, t.sold + ' / ' + t.capacity) + kpi(T.kReq, t.requests) +
            kpi(T.kPaused, t.paused) + kpi(T.kPaper, t.paper_todo) + kpi(T.kMoney, hoMoney(t.total), T.kCash.replace('{amount}', hoMoney(t.cash)));
        document.getElementById('branches').innerHTML = d.branches.length ? d.branches.map(function (b) {
            var cap = Math.max(1, b.capacity), pct = function (n) { return (n / cap * 100) + '%'; }, sold = b.app + b.counter + b.boarded;
            var state = b.paused ? '<span class="b-tag bad">' + T.paused + ' ' + esc(b.paused.at) + '</span>' + (b.paused.auto ? '<span class="b-hint">' + T.autoPaused + '</span>' : '')
                : b.status === 'live' ? '<span class="b-tag ok">' + T.live + '</span>' : b.status === 'paused' ? '<span class="b-tag bad">' + T.stopped + '</span>' : '<span class="b-tag warn">' + T.setup + '</span>';
            return '<article class="br' + (b.paused ? ' is-paused' : '') + '"><div class="br-top"><b>' + esc(b.name) + '</b><span class="b-hint">' + esc(b.region_label) + '</span><span class="ms-auto">' + state + '</span></div>' +
                '<div class="br-row"><span>' + T.deps.replace('{n}', b.departures).replace('{left}', b.left) + (b.next ? ' · <b>' + T.next.replace('{t}', esc(b.next)) + '</b>' : '') + '</span></div>' +
                '<div class="bar" aria-hidden="true"><span style="width:' + pct(b.boarded) + ';background:#9ca3af"></span><span style="width:' + pct(b.app) + ';background:#2563eb"></span><span style="width:' + pct(b.counter) + ';background:#1d2d2b"></span><span style="width:' + pct(b.held) + ';background:#f59e0b"></span></div>' +
                '<div class="br-row"><span><b>' + T.seats.replace('{sold}', sold).replace('{cap}', b.capacity) + '</b></span>' + (b.held ? '<span>' + T.held.replace('{n}', b.held) + '</span>' : '') +
                '<span class="' + (b.requests ? 'text-danger fw-bold' : '') + '">' + T.requests.replace('{n}', b.requests) + '</span>' +
                (b.paper_todo !== null ? '<span class="' + (b.paper_todo ? 'text-danger fw-bold' : '') + '">' + T.paper.replace('{n}', b.paper_todo) + '</span>' : '') +
                (b.refunds ? '<span class="text-warning fw-bold">' + T.refunds.replace('{n}', b.refunds) + '</span>' : '') + '</div>' +
                '<div class="money">' + T.today + ' <b>' + hoMoney(b.total) + '</b> · ' + T.cash + ' <b>' + hoMoney(b.cash) + '</b> · MTN <b>' + hoMoney(b.momo) + '</b> · Orange <b>' + hoMoney(b.orange) + '</b></div>' +
                '<div class="d-flex gap-2 mt-1"><a class="btn btn-soft btn-sm" href="' + openUrl.replace('__', b.id) + '">' + T.open + '</a>' +
                (canAct && b.status === 'live' ? '<button class="btn btn-sm ' + (b.paused ? 'btn-primary' : 'btn-dark') + '" data-pause="' + b.id + '" data-on="' + (b.paused ? 0 : 1) + '">' + (b.paused ? T.resume : T.pause) + '</button>' : '') +
                '</div></article>';
        }).join('') : '<div class="b-card p-4 text-center b-hint">' + T.none + '</div>';
    }
    function load() {
        return fetch(feedUrl, {headers: {'Accept': 'application/json'}}).then(function (r) {
            if (r.status === 401) { location.reload(); throw new Error('session'); }
            return r.json();
        }).then(function (d) { if (!busy) render(d); }).catch(function () {});
    }
    (function poll() { load().finally(function () { setTimeout(poll, 20000); }); })();
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-pause]');
        if (!b) return;
        var on = b.dataset.on === '1';
        if (on && !confirm(T.confirmPause)) return;
        busy = true; b.disabled = true;
        busPost(pauseUrl.replace('__', b.dataset.pause), {pause: on}).then(function (d) { busToast(d.message); })
            .catch(function () {}).finally(function () { busy = false; load(); });
    });
})();
</script>
@endpush
