@extends('busmanagement::partner.layout')

@section('title', translate('Boarding'))

@php
    use Modules\BusManagement\Entities\BusStaff;
    use Modules\BusManagement\Service\ScheduleService;
@endphp

@push('head')
<style>
    .chips { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: thin; }
    .chip { flex: none; border: 1px solid #dde3e2; background: #fff; border-radius: 12px; padding: 8px 12px; text-align: left; text-decoration: none; color: var(--ink); line-height: 1.2; }
    .chip.active { background: #1d2d2b; color: #fff; border-color: #1d2d2b; }
    .chip small { display: block; font-size: 11.5px; opacity: .75; }
    .scan-box { position: relative; background: #111; border-radius: 14px; overflow: hidden; aspect-ratio: 4 / 3; max-height: 320px; }
    .scan-box video { width: 100%; height: 100%; object-fit: cover; }
    .scan-box::after { content: ""; position: absolute; inset: 18% 22%; border: 3px solid rgba(255,255,255,.85); border-radius: 14px; }
    .flash { border-radius: 14px; padding: 14px; font-weight: 800; font-size: 17px; display: flex; gap: 10px; align-items: center; }
    .flash.ok { background: #e9f7ef; color: var(--ok); }
    .flash.bad { background: #fdecec; color: #b91c1c; }
    .flash.warn { background: #fff4e5; color: var(--warn); }
    .flash .bi { font-size: 28px; }
    #code::placeholder { text-transform: none; }
    .count { font-size: 30px; font-weight: 800; font-variant-numeric: tabular-nums; }
    .m-row { display: grid; grid-template-columns: 44px minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 8px 0; border-top: 1px solid var(--line); }
    .m-seat { width: 40px; height: 34px; border-radius: 8px; background: #22c55e; color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; }
    .m-row.is-boarded .m-seat { background: #9ca3af; }
    .m-row.is-no_show .m-seat { background: #e5e7eb; color: #6b7280; }
</style>
@endpush

@section('content')
@if($departures->isEmpty())
    <div class="b-card p-4 text-center">
        <h1 class="fs-5 fw-bold">{{ translate('Boarding') }}</h1>
        <p class="b-hint mb-0">{{ translate('No bus leaves today.') }}</p>
    </div>
@else
<h1 class="visually-hidden">{{ translate('Boarding') }}</h1>
<nav class="chips mb-3" aria-label="{{ translate('Buses today') }}">
    @foreach($departures as $d)
        <a class="chip {{ $current && $current->id === $d->id ? 'active' : '' }}" href="{{ route('bus-partner.boarding', ['departure' => $d->id]) }}" @if($current && $current->id === $d->id) aria-current="page" @endif>
            <b>{{ $d->leavesAt()->format($d->date->isToday() ? 'H:i' : 'D H:i') }}</b> {{ $d->destination?->city }}
            <small>{{ ScheduleService::classLabel($d->class) }} · {{ $d->vehicle?->registration }} · {{ translate(ucfirst($d->status)) }}</small>
        </a>
    @endforeach
</nav>

<div class="row g-3">
    <div class="col-lg-5">
        <section class="b-card p-3 mb-3" aria-labelledby="scanTitle">
            <div class="d-flex align-items-start gap-2 mb-2">
                <div class="flex-grow-1"><h2 class="b-h" id="scanTitle">{{ translate('Scan tickets') }}</h2><div class="b-hint" id="depLabel"></div></div>
                <div class="text-end"><div class="count"><span id="nBoarded">0</span><span class="text-muted fs-6">/<span id="nSold">0</span></span></div><div class="b-hint">{{ translate('on board') }}</div></div>
            </div>
            <div class="scan-box mb-2" id="scanBox" hidden><video id="video" playsinline muted aria-label="{{ translate('Camera') }}"></video></div>
            <div class="d-flex gap-2 mb-2">
                <button class="btn btn-dark flex-grow-1" id="camBtn"><i class="bi bi-qr-code-scan"></i> <span>{{ translate('Scan with the camera') }}</span></button>
            </div>
            <form class="d-flex gap-2" id="typeForm">
                <label class="visually-hidden" for="code">{{ translate('Ticket or booking code') }}</label>
                <input id="code" class="form-control text-uppercase" maxlength="40" placeholder="{{ translate('Or type the code') }}" autocomplete="off" autocapitalize="characters">
                <button class="btn btn-primary">{{ translate('Board') }}</button>
            </form>
            <div id="flash" class="mt-2" aria-live="assertive"></div>
        </section>

        <section class="b-card p-3" aria-labelledby="actTitle">
            <h2 class="b-h mb-2" id="actTitle">{{ translate('This bus') }}</h2>
            <div class="d-flex flex-wrap gap-2" id="actions">
                <button class="btn btn-soft" id="startBtn">{{ translate('Open boarding') }}</button>
                <a class="btn btn-soft" id="printBtn" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ translate('Print the passenger list') }}</a>
                @if($role !== BusStaff::BOARDING)
                    <button class="btn btn-soft" id="delayBtn">{{ translate('Delay') }}</button>
                @endif
                <button class="btn btn-primary" id="departBtn"><i class="bi bi-bus-front"></i> {{ translate('Departed') }}</button>
                @if($role === BusStaff::MANAGER)
                    <button class="btn btn-outline-danger" id="cancelBtn">{{ translate('Cancel this departure') }}</button>
                @endif
            </div>
            <div class="b-hint mt-2">{{ translate('Departed is the end: tickets not scanned become no-shows and the app passengers concerned are told.') }}</div>
        </section>
    </div>

    <section class="col-lg-7" aria-labelledby="listTitle">
        <div class="b-card p-3">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <h2 class="b-h flex-grow-1" id="listTitle">{{ translate('Passengers') }}</h2>
                <div class="seg" role="radiogroup" aria-label="{{ translate('Show') }}">
                    <input type="radio" name="f" id="f_all" value="all" checked><label for="f_all">{{ translate('All') }}</label>
                    <input type="radio" name="f" id="f_wait" value="valid"><label for="f_wait">{{ translate('Not yet') }}</label>
                    <input type="radio" name="f" id="f_on" value="boarded"><label for="f_on">{{ translate('On board') }}</label>
                </div>
            </div>
            <div class="b-hint mb-2" id="missingIds"></div>
            <input class="form-control form-control-sm mb-2" id="search" placeholder="{{ translate('Find a name or seat') }}" aria-label="{{ translate('Find a name or seat') }}">
            <div id="manifest"></div>
        </div>
    </section>
</div>
@endif
@endsection

@push('script')
@if($current)
<script>
(function () {
    var dep = @json($current->id);
    var collectMode = @json(\Modules\BusManagement\Lib\BusSettings::rules($busAgency->rules)['tickets'] === 'collect');
    var base = @json(url('partner/bus-agency/boarding')) + '/' + dep;
    var T = {
        boarded: @json(translate('On board')), waiting: @json(translate('Not yet')), noShow: @json(translate('Not on board')),
        app: @json(translate('App')), counter: @json(translate('Counter')), undo: @json(translate('Undo')),
        empty: @json(translate('No passenger yet.')), noCam: @json(translate('This phone cannot scan here. Type the code under the QR instead.')),
        stop: @json(translate('Stop the camera')), cam: @json(translate('Scan with the camera')),
        delayAsk: @json(translate('New departure time (HH:MM)')), departAsk: @json(translate(key: 'Mark the bus as departed. {n} passenger(s) not scanned will be marked not on board.', replace: ['n' => '{n}'])),
        cancelAsk: @json(translate('Say why this departure is cancelled. Passengers see it.')), missing: @json(translate(key: '{n} booking(s) without an ID number on the list.', replace: ['n' => '{n}'])),
        status: {scheduled: @json(translate('Scheduled')), boarding: @json(translate('Boarding')), departed: @json(translate('Departed')), cancelled: @json(translate('Cancelled'))},
        delayed: @json(translate('delayed to')), bags: @json(translate('bags')), printNow: @json(translate('Print now')),
        bookerId: @json(translate('Booker ID')), notCollected: @json(translate('Not collected')), print: @json(translate('Print')),
    };
    var data = null, filter = 'all', lastCode = '', lastAt = 0;
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

    function render() {
        if (!data) return;
        document.getElementById('depLabel').textContent = data.label + ' · ' + (data.bus || '') + ' · ' + (T.status[data.status] || data.status) + (data.delayed_to ? ' · ' + T.delayed + ' ' + data.delayed_to : '');
        document.getElementById('nBoarded').textContent = data.boarded;
        document.getElementById('nSold').textContent = data.sold;
        document.getElementById('missingIds').textContent = data.missing_ids ? T.missing.replace('{n}', data.missing_ids) : '';
        var open = data.status === 'scheduled' || data.status === 'boarding';
        document.querySelectorAll('#actions button, #camBtn, #typeForm button').forEach(function (b) { b.disabled = !open; });
        document.getElementById('startBtn').hidden = data.status !== 'scheduled';
        var q = document.getElementById('search').value.trim().toLowerCase();
        var rows = data.tickets.filter(function (t) {
            return (filter === 'all' || t.status === filter) && (!q || (t.name || '').toLowerCase().indexOf(q) >= 0 || String(t.seat) === q || (t.code || '').toLowerCase() === q);
        });
        document.getElementById('manifest').innerHTML = rows.length ? rows.map(function (t) {
            var st = t.status === 'boarded' ? '<span class="b-tag ok">' + T.boarded + ' ' + esc(t.boarded_at || '') + '</span>' : t.status === 'no_show' ? '<span class="b-tag bad">' + T.noShow + '</span>' : '<span class="b-tag mute">' + T.waiting + '</span>';
            return '<div class="m-row is-' + t.status + '"><div class="m-seat">' + esc(t.seat) + '</div><div style="min-width:0"><div class="fw-bold text-truncate">' + esc(t.name) + '</div>' +
                '<div class="b-hint">' + (t.booker_id ? T.bookerId + ' ' + esc(t.booker_id) + ' · ' : '') + esc(t.drop_point || '') + ' · ' + (t.source === 'app' ? T.app : T.counter) + (t.bags > 1 ? ' · ' + t.bags + ' ' + T.bags : '') +
                (!t.collected && t.status === 'valid' && collectMode ? ' · <span class="text-warning fw-bold">' + T.notCollected + '</span>' : '') + '</div></div>' +
                '<div class="d-flex flex-column align-items-end gap-1">' + st + (t.status === 'boarded' && open ? '<button class="btn btn-link btn-sm p-0" data-undo="' + esc(t.code) + '">' + T.undo + '</button>' : '') +
                (open && t.status !== 'no_show' ? '<a class="btn btn-link btn-sm p-0" target="_blank" rel="noopener" href="' + t.print + '">' + T.print + '</a>' : '') + '</div></div>';
        }).join('') : '<div class="b-hint py-2">' + T.empty + '</div>';
    }
    function load() {
        return fetch(base + '/manifest', {headers: {'Accept': 'application/json'}}).then(function (r) { return r.json(); }).then(function (d) { data = d; render(); }).catch(function () {});
    }
    (function poll() { load().finally(function () { setTimeout(poll, 10000); }); })();
    document.getElementById('printBtn').href = base + '/print';

    // Feedback the agent can hear and feel with a queue in front of them.
    function beep(ok) {
        if (navigator.vibrate) navigator.vibrate(ok ? 80 : [200, 80, 200]);
        try {
            var a = window._busAudio = window._busAudio || new (window.AudioContext || window.webkitAudioContext)();
            var o = a.createOscillator(), g = a.createGain();
            o.frequency.value = ok ? 1046 : 220; o.connect(g); g.connect(a.destination);
            g.gain.setValueAtTime(0.25, a.currentTime); g.gain.exponentialRampToValueAtTime(0.0001, a.currentTime + (ok ? .15 : .45));
            o.start(); o.stop(a.currentTime + (ok ? .16 : .46));
        } catch (e) {}
    }
    function flash(d) {
        var cls = d.ok ? 'ok' : (d.result === 'already' ? 'warn' : 'bad');
        var icon = d.ok ? 'bi-check-circle-fill' : (d.result === 'already' ? 'bi-exclamation-circle-fill' : 'bi-x-circle-fill');
        document.getElementById('flash').innerHTML = '<div class="flash ' + cls + '"><i class="bi ' + icon + '" aria-hidden="true"></i><span class="flex-grow-1">' + esc(d.message) + '</span>' +
            (d.print ? '<a class="btn btn-dark btn-sm" target="_blank" rel="noopener" href="' + d.print + '"><i class="bi bi-printer"></i> ' + T.printNow + '</a>' : '') + '</div>';
        beep(d.ok);
    }
    function scan(code) {
        code = (code || '').trim();
        if (!code) return;
        // The camera sees the same code many times a second.
        if (code === lastCode && Date.now() - lastAt < 4000) return;
        lastCode = code; lastAt = Date.now();
        busPost(base + '/scan', {code: code}).then(function (d) { flash(d); load(); });
    }
    document.getElementById('typeForm').addEventListener('submit', function (e) {
        e.preventDefault(); var i = document.getElementById('code'); lastCode = ''; scan(i.value); i.value = ''; i.focus();
    });

    // Camera scanning with the browser's own barcode reader (Chrome on Android).
    var stream = null, detector = null, looping = false;
    var camBtn = document.getElementById('camBtn');
    function stopCam() {
        looping = false;
        if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
        stream = null; document.getElementById('scanBox').hidden = true; camBtn.querySelector('span').textContent = T.cam;
    }
    camBtn.addEventListener('click', function () {
        if (stream) { stopCam(); return; }
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices) { busToast(T.noCam); document.getElementById('code').focus(); return; }
        detector = detector || new BarcodeDetector({formats: ['qr_code']});
        navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}}).then(function (s) {
            stream = s; var v = document.getElementById('video'); v.srcObject = s; v.play();
            document.getElementById('scanBox').hidden = false; camBtn.querySelector('span').textContent = T.stop; looping = true;
            (function tick() {
                if (!looping) return;
                detector.detect(v).then(function (codes) { if (codes.length) scan(codes[0].rawValue); }).catch(function () {})
                    .finally(function () { setTimeout(tick, 250); });
            })();
        }).catch(function () { busToast(T.noCam); });
    });
    window.addEventListener('pagehide', stopCam);

    document.querySelectorAll('input[name=f]').forEach(function (r) { r.addEventListener('change', function () { filter = this.value; render(); }); });
    document.getElementById('search').addEventListener('input', render);
    document.getElementById('manifest').addEventListener('click', function (e) {
        var u = e.target.closest('[data-undo]'); if (!u) return;
        busPost(base + '/undo/' + encodeURIComponent(u.dataset.undo)).then(function (d) { busToast(d.message); load(); });
    });
    var done = function (d) { busToast(d.message); load(); };
    document.getElementById('startBtn').addEventListener('click', function () { busPost(base + '/start').then(done); });
    var delayBtn = document.getElementById('delayBtn');
    if (delayBtn) delayBtn.addEventListener('click', function () {
        var t = prompt(T.delayAsk); if (!t) return;
        busPost(base + '/delay', {time: t.trim()}).then(done);
    });
    document.getElementById('departBtn').addEventListener('click', function () {
        var waiting = data ? data.tickets.filter(function (t) { return t.status === 'valid'; }).length : 0;
        if (!confirm(T.departAsk.replace('{n}', waiting))) return;
        stopCam();
        busPost(base + '/departed').then(done);
    });
    var cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn) cancelBtn.addEventListener('click', function () {
        var why = prompt(T.cancelAsk); if (!why) return;
        busPost(base + '/cancel', {reason: why}).then(done);
    });
})();
</script>
@endif
@endpush
