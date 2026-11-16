@extends('busmanagement::partner.layout')

@section('title', translate('Today'))

@push('head')
<script>window.busTodayPage = true;</script>
<style>
    .t-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 16px; align-items: start; }
    @media (max-width: 991px) { .t-grid { grid-template-columns: minmax(0, 1fr); } }
    .req { border: 2px solid #fde2e2; border-radius: 16px; background: #fff; padding: 14px; display: flex; gap: 14px; animation: pop .35s ease; }
    .req.is-urgent { border-color: var(--red); }
    @keyframes pop { from { transform: translateY(-6px); opacity: 0; } to { transform: none; opacity: 1; } }
    .ring { width: 64px; height: 64px; flex: none; position: relative; }
    .ring svg { transform: rotate(-90deg); }
    .ring b { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 14px; font-variant-numeric: tabular-nums; }
    .seat-chip { display: inline-flex; gap: 6px; align-items: center; background: #f4f6f6; border-radius: 10px; padding: 4px 8px; font-size: 12.5px; margin: 0 6px 6px 0; }
    .seat-chip b { background: #1d2d2b; color: #fff; border-radius: 6px; padding: 0 6px; }
    .bar { display: flex; height: 8px; border-radius: 6px; overflow: hidden; background: #e9eded; }
    .bar span { display: block; height: 100%; }
    .dep-row { display: grid; grid-template-columns: 58px minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 10px 0; border-top: 1px solid var(--line); }
    .dep-row:first-child { border-top: 0; }
    .reasons button { margin: 0 6px 6px 0; }
    .empty { text-align: center; padding: 28px 12px; color: var(--muted); }
    .idbox { border-radius: 12px; padding: 10px; margin: 4px 0 8px; background: #f4f6f6; }
    .idbox.is-check { background: #fff4e5; }
    .idbox img { height: 110px; max-width: 100%; border-radius: 8px; border: 1px solid var(--line); object-fit: cover; cursor: zoom-in; }
    .empty i { font-size: 34px; display: block; margin-bottom: 6px; color: #c3cbca; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="fs-5 fw-bold mb-0">{{ translate('Today') }}</h1>
        <div class="b-hint" id="clock" aria-live="off"></div>
    </div>
    <button type="button" class="btn btn-soft btn-sm" id="soundBtn" aria-pressed="false"><i class="bi bi-volume-mute"></i> <span>{{ translate('Turn the ring on') }}</span></button>
    <a href="{{ route('bus-partner.sell') }}" class="btn btn-primary btn-sm"><i class="bi bi-ticket-perforated"></i> {{ translate('Quick sell') }}</a>
</div>

@foreach($suggestions as $s)
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
        <div class="flex-grow-1"><b>{{ translate('E-Ride reports') }}: {{ $s->title }}</b> · {{ $s->starts_at->translatedFormat('D j M H:i') }} - {{ $s->ends_at->translatedFormat('D j M H:i') }}
            <div class="small">{{ translate('Your departures inside may not be able to leave. Handle them in one step.') }}</div></div>
        @if($busStaff->role === 'manager')<a class="btn btn-dark btn-sm" href="{{ route('bus-partner.closures', ['area' => $s->id]) }}#declare">{{ translate('Handle it') }}</a>@endif
    </div>
@endforeach
@foreach($atRisk as $c)
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
        <div class="flex-grow-1"><b>{{ \Modules\BusManagement\Service\ClosureService::kindLabel($c->kind) }}{{ $c->note ? ': ' . $c->note : '' }}</b>
            <div class="small">{{ translate(key: 'Departures at risk. Decide by {time}, or passengers are moved.', replace: ['time' => $c->decide_by->translatedFormat('D j M H:i')]) }}</div></div>
        @if($busStaff->role === 'manager')<a class="btn btn-dark btn-sm" href="{{ route('bus-partner.closures.show', $c->id) }}">{{ translate('Decide') }}</a>@endif
    </div>
@endforeach

<div class="t-grid">
    <div>
        <section class="mb-3" aria-labelledby="reqTitle">
            <div class="d-flex align-items-center gap-2 mb-2"><h2 class="b-h" id="reqTitle">{{ translate('Seat requests to answer') }}</h2><span class="b-tag bad" id="reqCount" style="display:none"></span></div>
            <div id="requests" class="d-flex flex-column gap-2" aria-live="polite"></div>
        </section>
        <section class="b-card p-3 mb-3" aria-labelledby="payTitle">
            <h2 class="b-h mb-1" id="payTitle">{{ translate('Accepted, waiting for payment') }}</h2>
            <div class="b-hint mb-2">{{ translate('Seats stay held until the passenger pays or the time runs out.') }}</div>
            <div id="awaiting"></div>
        </section>
        @if($busStaff->role === \Modules\BusManagement\Entities\BusStaff::MANAGER)
        <section class="b-card p-3 mb-3" aria-labelledby="refTitle">
            <h2 class="b-h mb-1" id="refTitle">{{ translate('Refunds to send') }}</h2>
            <div class="b-hint mb-2">{{ translate('Send the money to the passenger by Mobile Money from your own account, then write the reference here. The passenger is told.') }}</div>
            <div id="refunds"></div>
        </section>
        @endif
    </div>
    <section class="b-card p-3" aria-labelledby="depTitle">
        <h2 class="b-h mb-2" id="depTitle">{{ translate('Buses today') }}</h2>
        <div class="sp-legend mb-2"><span><i style="background:#9ca3af"></i>{{ translate('On board') }}</span><span><i style="background:#2563eb"></i>{{ translate('App') }}</span><span><i style="background:#1d2d2b"></i>{{ translate('Counter') }}</span><span><i style="background:#f59e0b"></i>{{ translate('Held') }}</span><span><i style="background:#e9eded"></i>{{ translate('Free') }}</span></div>
        <div id="departures"></div>
    </section>
</div>
@endsection

@push('script')
<script>
(function () {
    var T = {
        accept: @json(translate('Accept')), decline: @json(translate('Decline')), acceptChecked: @json(translate('ID checked, accept')),
        bookerId: @json(translate('ID of the person booking')), verified: @json(translate('E-Ride ID Verified')), lookId: @json(translate('Look at the ID before accepting.')), why: @json(translate('Give a reason. The passenger sees it.')),
        none: @json(translate('No request waiting. New ones ring here and show at the top.')),
        noPay: @json(translate('Nobody is paying right now.')), noRefund: @json(translate('No refund to send.')),
        noBus: @json(translate('No bus today.')), seats: @json(translate('seats')), to: @json(translate('to')),
        free: @json(translate(key: '{n} seats free', replace: ['n' => '{n}'])), sell: @json(translate('Sell')), board: @json(translate('Boarding')),
        left: @json(translate('left to answer')), payBy: @json(translate(key: '{t} left to pay', replace: ['t' => '{t}'])), ref: @json(translate('Mobile Money reference')),
        sent: @json(translate('Mark sent')), dueBy: @json(translate('Send by')), cancel: @json(translate('Cancel')),
        soundOn: @json(translate('Ring on')), soundOff: @json(translate('Turn the ring on')), other: @json(translate('Other reason')),
        newReq: @json(translate('New seat request')), confirmCancel: @json(translate('This cancels the booking and frees its seats.')),
        status: {scheduled: @json(translate('Scheduled')), boarding: @json(translate('Boarding')), departed: @json(translate('Departed'))},
        delayed: @json(translate('Delayed')),
    };
    var REASONS = [@json(translate('The bus is full')), @json(translate('This departure changed')), @json(translate('We could not reach you'))];
    var urls = {
        feed: @json(route('bus-partner.today.feed')),
        accept: @json(url('partner/bus-agency/bookings/__/accept')), decline: @json(url('partner/bus-agency/bookings/__/decline')),
        cancel: @json(url('partner/bus-agency/bookings/__/cancel')), refund: @json(url('partner/bus-agency/bookings/__/refund-sent')),
        sell: @json(route('bus-partner.sell')), board: @json(route('bus-partner.boarding')),
    };
    var isManager = @json($busStaff->role === \Modules\BusManagement\Entities\BusStaff::MANAGER);
    var answerSeconds = @json(\Modules\BusManagement\Lib\BusSettings::all()['answer_minutes'] * 60);
    var offset = 0, known = null, data = null, busy = {};
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var now = function () { return Date.now() + offset; };
    var left = function (iso) { return Math.max(0, Math.round((new Date(iso).getTime() - now()) / 1000)); };
    var mmss = function (s) { var m = Math.floor(s / 60), r = s % 60; return m + ':' + (r < 10 ? '0' : '') + r; };

    // The ring: a short two-tone chime (browsers only allow sound after a tap, hence the button).
    var audio = null, soundOn = localStorage.getItem('busRing') === '1';
    var soundBtn = document.getElementById('soundBtn');
    function paintSound() {
        soundBtn.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
        soundBtn.querySelector('i').className = 'bi ' + (soundOn ? 'bi-volume-up' : 'bi-volume-mute');
        soundBtn.querySelector('span').textContent = soundOn ? T.soundOn : T.soundOff;
    }
    function chime() {
        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
        if (!soundOn) return;
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            [880, 1320].forEach(function (f, i) {
                var o = audio.createOscillator(), g = audio.createGain(), t = audio.currentTime + i * 0.18;
                o.frequency.value = f; o.connect(g); g.connect(audio.destination);
                g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.3, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.35);
                o.start(t); o.stop(t + 0.4);
            });
        } catch (e) {}
    }
    soundBtn.addEventListener('click', function () { soundOn = !soundOn; localStorage.setItem('busRing', soundOn ? '1' : '0'); paintSound(); if (soundOn) chime(); });
    paintSound();

    function seatChips(b) {
        return b.seats.map(function (s) {
            return '<span class="seat-chip"><b>' + esc(s.seat) + '</b>' + esc(s.name) + (s.bags > 1 ? ' · ' + s.bags + ' 🧳' : '') + '</span>';
        }).join('');
    }
    function idBox(b) {
        var k = b.booker;
        if (!k) return '';
        return '<div class="idbox' + (k.check ? ' is-check' : '') + '"><div class="small fw-bold">' + T.bookerId + '</div>' +
            '<div>' + esc(k.type) + ' · <b>' + esc(k.number) + '</b> ' + (k.verified ? '<span class="b-tag ok"><i class="bi bi-patch-check-fill"></i>' + T.verified + '</span>' : '') + '</div>' +
            (k.check && k.photos.length ? '<div class="d-flex gap-2 flex-wrap mt-2">' + k.photos.map(function (u) { return '<a href="' + u + '" target="_blank" rel="noopener"><img src="' + u + '" alt=""></a>'; }).join('') + '</div>' +
                '<div class="b-hint mt-1">' + T.lookId + '</div>' : '') + '</div>';
    }
    function ring(b) {
        var s = left(b.deadline), frac = Math.min(1, s / answerSeconds), c = 2 * Math.PI * 27;
        var color = s < 120 ? '#e02020' : '#1d2d2b';
        return '<div class="ring" aria-label="' + mmss(s) + ' ' + T.left + '"><svg width="64" height="64"><circle cx="32" cy="32" r="27" stroke="#eef1f0" stroke-width="6" fill="none"/>' +
            '<circle cx="32" cy="32" r="27" stroke="' + color + '" stroke-width="6" fill="none" stroke-linecap="round" stroke-dasharray="' + c + '" stroke-dashoffset="' + (c * (1 - frac)) + '" data-ring/></svg><b data-left>' + mmss(s) + '</b></div>';
    }
    function renderRequests() {
        var box = document.getElementById('requests'), n = data.requests.length, cnt = document.getElementById('reqCount');
        cnt.textContent = n; cnt.style.display = n ? 'inline-flex' : 'none';
        if (!n) { box.innerHTML = '<div class="b-card empty"><i class="bi bi-bell"></i>' + T.none + '</div>'; return; }
        box.innerHTML = data.requests.map(function (b) {
            return '<article class="req' + (left(b.deadline) < 120 ? ' is-urgent' : '') + '" data-id="' + b.id + '" data-deadline="' + b.deadline + '">' + ring(b) +
                '<div class="flex-grow-1" style="min-width:0">' +
                '<div class="d-flex flex-wrap gap-2 align-items-baseline"><b class="fs-6">' + esc(b.name) + '</b>' + (b.phone ? '<a class="small" href="tel:' + esc(b.phone) + '">' + esc(b.phone) + '</a>' : '') + '<span class="ms-auto fw-bold">' + esc(b.amount) + '</span></div>' +
                '<div class="small mb-2">' + esc(b.departure) + ' · ' + T.to + ' ' + esc(b.drop_point) + '</div>' + seatChips(b) + idBox(b) +
                '<div class="d-flex flex-wrap gap-2 mt-1" data-actions><button class="btn btn-primary" data-accept>' + (b.booker && b.booker.check ? T.acceptChecked : T.accept) + '</button><button class="btn btn-soft" data-decline>' + T.decline + '</button></div>' +
                '<div class="reasons mt-2" data-reasons hidden><div class="small fw-semibold mb-1">' + T.why + '</div>' +
                REASONS.map(function (r) { return '<button class="btn btn-soft btn-sm" data-reason="' + esc(r) + '">' + esc(r) + '</button>'; }).join('') +
                '<div class="d-flex gap-2"><input class="form-control form-control-sm" maxlength="200" placeholder="' + T.other + '" aria-label="' + T.other + '" data-other><button class="btn btn-dark btn-sm" data-reason-other>' + T.decline + '</button></div></div>' +
                '</div></article>';
        }).join('');
    }
    function renderList(id, list, empty, row) {
        var el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = list.length ? list.map(row).join('') : '<div class="b-hint">' + empty + '</div>';
    }
    function renderAll() {
        renderRequests();
        renderList('awaiting', data.awaiting, T.noPay, function (b) {
            return '<div class="d-flex flex-wrap gap-2 align-items-center py-2 border-top"><div class="flex-grow-1" style="min-width:0"><b>' + esc(b.name) + '</b> · ' + esc(b.amount) +
                '<div class="b-hint">' + esc(b.departure) + ' · ' + T.seats + ' ' + b.seats.map(function (s) { return esc(s.seat); }).join(', ') + '</div></div>' +
                '<span class="b-tag warn" data-countdown="' + b.deadline + '">' + T.payBy.replace('{t}', mmss(left(b.deadline))) + '</span>' +
                (isManager ? '<button class="btn btn-soft btn-sm" data-cancel="' + b.id + '">' + T.cancel + '</button>' : '') + '</div>';
        });
        renderList('refunds', data.refunds, T.noRefund, function (b) {
            return '<form class="py-2 border-top" data-refund="' + b.id + '"><div class="d-flex flex-wrap gap-2"><b>' + esc(b.refund) + '</b> → ' + esc(b.name) +
                (b.phone ? ' · <a href="tel:' + esc(b.phone) + '">' + esc(b.phone) + '</a>' : '') + '<span class="b-tag ' + (left(b.deadline) < 6 * 3600 ? 'bad' : 'mute') + ' ms-auto">' + T.dueBy + ' ' + new Date(b.deadline).toLocaleString([], {weekday: 'short', hour: '2-digit', minute: '2-digit'}) + '</span></div>' +
                '<div class="b-hint">' + esc(b.departure) + ' · ' + esc(b.code) + '</div>' +
                '<div class="d-flex gap-2 mt-1"><input class="form-control form-control-sm" name="reference" required maxlength="100" placeholder="' + T.ref + '" aria-label="' + T.ref + '"><button class="btn btn-dark btn-sm">' + T.sent + '</button></div></form>';
        });
        renderList('departures', data.departures, T.noBus, function (d) {
            var cap = Math.max(1, d.capacity), pct = function (n) { return (n / cap * 100) + '%'; };
            var tag = d.status === 'departed' ? 'blue' : d.status === 'boarding' ? 'ok' : 'mute';
            return '<div class="dep-row"><div><b class="fs-6">' + esc(d.time) + '</b>' + (d.delayed ? '<div class="b-tag warn">' + T.delayed + '</div>' : '') + '</div>' +
                '<div style="min-width:0"><div class="small fw-bold text-truncate">' + esc(d.to) + ' · ' + esc(d.class) + ' · ' + esc(d.bus || '') + '</div>' +
                '<div class="bar my-1" aria-hidden="true"><span style="width:' + pct(d.boarded) + ';background:#9ca3af"></span><span style="width:' + pct(d.app) + ';background:#2563eb"></span><span style="width:' + pct(d.counter) + ';background:#1d2d2b"></span><span style="width:' + pct(d.held) + ';background:#f59e0b"></span></div>' +
                '<div class="b-hint">' + (d.app + d.counter + d.boarded) + '/' + d.capacity + ' · ' + T.free.replace('{n}', d.free) + ' · <span class="b-tag ' + tag + '">' + (T.status[d.status] || d.status) + '</span></div></div>' +
                '<div class="d-flex flex-column gap-1">' + (d.status !== 'departed' ? '<a class="btn btn-soft btn-sm" href="' + urls.sell + '?departure=' + d.id + '">' + T.sell + '</a>' : '') +
                '<a class="btn btn-soft btn-sm" href="' + urls.board + '?departure=' + d.id + '">' + T.board + '</a></div></div>';
        });
    }

    function load() {
        return fetch(urls.feed, {headers: {'Accept': 'application/json'}}).then(function (r) {
            if (r.status === 401) { location.reload(); throw new Error('session'); }
            return r.json();
        }).then(function (d) {
            offset = new Date(d.now).getTime() - Date.now();
            var ids = d.requests.map(function (b) { return b.id; });
            if (known && ids.some(function (id) { return known.indexOf(id) < 0; })) { chime(); busToast(T.newReq); }
            known = ids; data = d;
            if (window.busSetBadge) busSetBadge(d.count);
            if (!Object.keys(busy).length) renderAll();
        }).catch(function () {});
    }
    (function poll() { load().finally(function () { setTimeout(poll, 8000); }); })();

    // Countdowns, every second, without rebuilding the cards.
    setInterval(function () {
        document.getElementById('clock').textContent = new Date(now()).toLocaleString([], {weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit'});
        document.querySelectorAll('.req').forEach(function (el) {
            var s = left(el.dataset.deadline), c = 2 * Math.PI * 27;
            el.querySelector('[data-left]').textContent = mmss(s);
            el.querySelector('[data-ring]').setAttribute('stroke-dashoffset', c * (1 - Math.min(1, s / answerSeconds)));
            el.classList.toggle('is-urgent', s < 120);
            if (s === 0) el.style.opacity = .5;
        });
        document.querySelectorAll('[data-countdown]').forEach(function (el) { el.textContent = T.payBy.replace('{t}', mmss(left(el.dataset.countdown))); });
    }, 1000);

    function act(url, id, body, el) {
        busy[id] = true;
        if (el) el.querySelectorAll('button,input').forEach(function (b) { b.disabled = true; });
        return busPost(url.replace('__', id), body).then(function (d) { busToast(d.message); })
            .catch(function () {}).finally(function () { delete busy[id]; load(); });
    }
    document.addEventListener('click', function (e) {
        var card = e.target.closest('.req');
        if (card) {
            var id = card.dataset.id;
            if (e.target.closest('[data-accept]')) act(urls.accept, id, {}, card);
            if (e.target.closest('[data-decline]')) { busy[id] = true; card.querySelector('[data-reasons]').hidden = false; card.querySelector('[data-actions]').hidden = true; card.querySelector('[data-reason]').focus(); }
            var r = e.target.closest('[data-reason]');
            if (r) act(urls.decline, id, {reason: r.dataset.reason}, card);
            if (e.target.closest('[data-reason-other]')) act(urls.decline, id, {reason: card.querySelector('[data-other]').value}, card);
        }
        var c = e.target.closest('[data-cancel]');
        if (c && confirm(T.confirmCancel)) act(urls.cancel, c.dataset.cancel, {reason: ''}, c.parentNode);
    });
    document.addEventListener('submit', function (e) {
        var f = e.target.closest('[data-refund]');
        if (!f) return;
        e.preventDefault();
        act(urls.refund, f.dataset.refund, {reference: f.reference.value}, f);
    });
})();
</script>
@endpush
