@extends('busmanagement::partner.layout')

@section('title', translate('Collect'))

@push('head')
<style>
    .scan-box { position: relative; background: #111; border-radius: 14px; overflow: hidden; aspect-ratio: 4 / 3; max-height: 300px; }
    .scan-box video { width: 100%; height: 100%; object-fit: cover; }
    .scan-box::after { content: ""; position: absolute; inset: 18% 22%; border: 3px solid rgba(255,255,255,.85); border-radius: 14px; }
    #code::placeholder { text-transform: none; }
    .id-photos img { max-height: 220px; max-width: 100%; border-radius: 10px; border: 1px solid var(--line); cursor: zoom-in; }
</style>
@endpush

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <section class="b-card p-3" aria-labelledby="cTitle">
            <h1 class="b-h" id="cTitle">{{ translate('Collect tickets') }}</h1>
            <div class="b-hint mb-2">{{ translate('The passenger shows the booking code in the E-Ride app. Check the ID, then print their tickets.') }}</div>
            <div class="scan-box mb-2" id="scanBox" hidden><video id="video" playsinline muted aria-label="{{ translate('Camera') }}"></video></div>
            <button class="btn btn-dark w-100 mb-2" id="camBtn"><i class="bi bi-qr-code-scan"></i> <span>{{ translate('Scan with the camera') }}</span></button>
            <form class="d-flex gap-2" id="typeForm">
                <label class="visually-hidden" for="code">{{ translate('Booking code') }}</label>
                <input id="code" class="form-control text-uppercase" maxlength="40" placeholder="{{ translate('Or type the booking code') }}" autocomplete="off" autocapitalize="characters">
                <button class="btn btn-primary">{{ translate('Find') }}</button>
            </form>
        </section>
    </div>
    <section class="col-lg-7" aria-live="polite" id="result"></section>
</div>
@endsection

@push('script')
<script>
(function () {
    var T = {
        booker: @json(translate('ID of the person who booked')), verified: @json(translate('E-Ride ID Verified')), check: @json(translate('Check this ID against the card in hand')),
        print: @json(translate('Print the tickets')), reprint: @json(translate('Print again (marked REPRINT)')), collected: @json(translate('Collected')),
        notYet: @json(translate('Not collected yet')), seat: @json(translate('Seat')), noCam: @json(translate('This phone cannot scan here. Type the code instead.')),
        stop: @json(translate('Stop the camera')), cam: @json(translate('Scan with the camera')), app: @json(translate('Booked in the app')), counter: @json(translate('Sold at the counter')),
    };
    var lookup = @json(route('bus-partner.collect.lookup'));
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var box = document.getElementById('result'), last = '';
    function show(d) {
        if (!d.found) { box.innerHTML = '<div class="b-card p-3"><div class="alert alert-warning mb-0">' + esc(d.message) + '</div></div>'; return; }
        var b = d.booker, allDone = d.tickets.length && d.tickets.every(function (t) { return t.collected; });
        var id = b ? '<div class="rounded-3 p-2 my-2" style="background:' + (b.check ? '#fff4e5' : '#f4f6f6') + '"><div class="small fw-bold">' + T.booker + '</div>' +
            '<div>' + esc(b.type) + ' · <b>' + esc(b.number) + '</b> ' + (b.verified ? '<span class="b-tag ok"><i class="bi bi-patch-check-fill"></i>' + T.verified + '</span>' : '') + '</div>' +
            (b.photos.length ? '<div class="id-photos d-flex gap-2 flex-wrap mt-2">' + b.photos.map(function (u) { return '<a href="' + u + '" target="_blank" rel="noopener"><img src="' + u + '" alt=""></a>'; }).join('') + '</div>' : '') +
            '<div class="b-hint mt-1">' + T.check + '</div></div>' : '';
        box.innerHTML = '<div class="b-card p-3">' +
            '<div class="d-flex flex-wrap gap-2 align-items-baseline"><h2 class="b-h flex-grow-1">' + esc(d.name) + '</h2><code>' + esc(d.code) + '</code></div>' +
            '<div class="small">' + esc(d.departure) + ' · ' + esc(d.drop_point || '') + ' · ' + esc(d.amount) + ' · ' + (d.source === 'app' ? T.app : T.counter) + '</div>' +
            (d.problem ? '<div class="alert alert-danger mt-2 mb-0">' + esc(d.problem) + '</div>' : '') + id +
            d.tickets.map(function (t) { return '<div class="d-flex justify-content-between border-top py-2"><span><b>' + T.seat + ' ' + esc(t.seat) + '</b> · ' + esc(t.name) + '</span>' +
                '<span class="b-tag ' + (t.collected ? 'ok' : 'warn') + '">' + (t.collected ? T.collected : T.notYet) + (t.printed > 1 ? ' · ×' + t.printed : '') + '</span></div>'; }).join('') +
            (d.print ? '<a class="btn btn-primary btn-lg w-100 mt-2" target="_blank" rel="noopener" href="' + d.print + '" id="printNow"><i class="bi bi-printer"></i> ' + (allDone ? T.reprint : T.print) + '</a>' : '') +
            '</div>';
        var p = document.getElementById('printNow');
        if (p) p.addEventListener('click', function () { setTimeout(function () { find(d.code, true); }, 1500); });
    }
    function find(code, again) {
        code = (code || '').trim();
        if (!code || (!again && code === last)) return;
        last = code;
        fetch(lookup + '?code=' + encodeURIComponent(code), {headers: {'Accept': 'application/json'}}).then(function (r) { return r.json(); }).then(show);
    }
    document.getElementById('typeForm').addEventListener('submit', function (e) { e.preventDefault(); last = ''; find(document.getElementById('code').value); });

    var stream = null, detector = null, looping = false, camBtn = document.getElementById('camBtn');
    function stopCam() { looping = false; if (stream) stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; document.getElementById('scanBox').hidden = true; camBtn.querySelector('span').textContent = T.cam; }
    camBtn.addEventListener('click', function () {
        if (stream) { stopCam(); return; }
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices) { busToast(T.noCam); document.getElementById('code').focus(); return; }
        detector = detector || new BarcodeDetector({formats: ['qr_code']});
        navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}}).then(function (s) {
            stream = s; var v = document.getElementById('video'); v.srcObject = s; v.play();
            document.getElementById('scanBox').hidden = false; camBtn.querySelector('span').textContent = T.stop; looping = true;
            (function tick() {
                if (!looping) return;
                detector.detect(v).then(function (codes) { if (codes.length) { find(codes[0].rawValue); } }).catch(function () {}).finally(function () { setTimeout(tick, 300); });
            })();
        }).catch(function () { busToast(T.noCam); });
    });
    window.addEventListener('pagehide', stopCam);
})();
</script>
@endpush
