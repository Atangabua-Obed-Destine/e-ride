@extends('storemanagement::partner.layout')

@section('title', translate('Orders'))

@push('head')
<style>
    .alertbar { display: flex; align-items: center; gap: 10px; background: #fff7e6; border: 1px solid #f3d9a4; color: #6b4a00; border-radius: 14px; padding: 10px 12px; margin-bottom: 14px; font-weight: 600; font-size: 14px; }
    .alertbar button { margin-left: auto; }
    .lane { margin-bottom: 22px; }
    .lane h2 { font-size: 13px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); display: flex; align-items: center; gap: 8px; margin: 0 0 10px; }
    .lane h2 .count { background: #e4e4ea; color: var(--ink); border-radius: 999px; padding: 1px 8px; font-size: 12px; letter-spacing: 0; }
    .lane.is-new h2 { color: var(--red); }
    .lane.is-new h2 .count { background: var(--red); color: #fff; }
    .grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
    @media (min-width: 700px) { .grid { grid-template-columns: 1fr 1fr; } }
    @media (min-width: 1050px) { .grid { grid-template-columns: 1fr 1fr 1fr; } }
    .o { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
    .o.is-new { border: 2px solid var(--red); box-shadow: 0 10px 30px -14px rgba(224, 32, 32, .6); }
    .o.is-arrived { animation: pop .4s ease; }
    @keyframes pop { from { transform: scale(.97); opacity: .4 } to { transform: none; opacity: 1 } }
    .o__head { display: flex; align-items: baseline; gap: 8px; }
    .o__ref { font-weight: 900; font-size: 20px; letter-spacing: -.01em; }
    .o__who { color: var(--muted); font-size: 13px; }
    .o__timer { margin-left: auto; font-weight: 800; font-size: 13px; font-variant-numeric: tabular-nums; padding: 3px 9px; border-radius: 999px; background: #f0f0f3; }
    .o__timer.is-hot { background: var(--red); color: #fff; }
    .o__timer.is-ok { background: rgba(18, 161, 80, .12); color: var(--ok); }
    .o__items { list-style: none; margin: 0; padding: 0; border-top: 1px dashed var(--line); border-bottom: 1px dashed var(--line); }
    .o__items li { display: flex; gap: 10px; padding: 7px 0; font-size: 15px; }
    .o__items li + li { border-top: 1px solid #f2f2f5; }
    .o__qty { font-weight: 900; min-width: 30px; }
    .o__total { display: flex; justify-content: space-between; font-weight: 800; }
    .o__note { background: #fff7e6; border-radius: 10px; padding: 8px 10px; font-size: 14px; }
    .o__rider { display: flex; align-items: center; gap: 10px; background: #f4f4f6; border-radius: 12px; padding: 10px; font-size: 14px; }
    .o__rider i { font-size: 22px; color: var(--muted); }
    .o__code { margin-left: auto; text-align: center; }
    .o__code b { display: block; font-size: 22px; letter-spacing: .15em; font-variant-numeric: tabular-nums; }
    .o__code small { font-size: 11px; color: var(--muted); }
    .prep { display: grid; grid-template-columns: repeat(auto-fit, minmax(42px, 1fr)); gap: 6px; }
    .prep button { min-height: 44px; border-radius: 12px; border: 1px solid var(--line); background: #fff; font-weight: 800; }
    .prep button.is-on { border-color: var(--red); background: var(--red-soft); color: var(--red); }
    .o__actions { display: flex; gap: 8px; }
    .o__actions .btn { min-height: 48px; flex: 1; }
    .o__state { font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 6px; }
    .o__state.searching::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--warn); animation: blink 1.2s infinite; }
    @keyframes blink { 50% { opacity: .2 } }
    .done-row { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; font-size: 14px; }
    .done-row .tag { margin-left: auto; font-size: 12px; font-weight: 800; border-radius: 999px; padding: 2px 9px; }
    .tag.ok { background: rgba(18, 161, 80, .12); color: var(--ok); }
    .tag.no { background: #f0f0f3; color: var(--muted); }
    .empty { text-align: center; color: var(--muted); padding: 40px 16px; }
    .empty i { font-size: 44px; display: block; margin-bottom: 8px; color: #c8c8d0; }
    .live { font-size: 12px; color: var(--muted); display: flex; align-items: center; gap: 6px; }
    .live b { width: 7px; height: 7px; border-radius: 50%; background: var(--ok); display: inline-block; }
    .live.is-off b { background: var(--red); }
</style>
@endpush

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h1 class="fs-4 fw-bold m-0">{{ translate('Orders') }}</h1>
        <span class="live" data-live><b></b><span data-live-label>{{ translate('Live') }}</span></span>
    </div>

    <div class="alertbar" data-sound-bar>
        <i class="bi bi-volume-up-fill fs-5"></i>
        <span>{{ translate('Turn on sound so you never miss an order.') }}</span>
        <button type="button" class="btn btn-sm btn-dark" data-sound-on>{{ translate('Turn_on') }}</button>
    </div>

    <div data-board>
        <div class="empty"><div class="spinner-border text-secondary" role="status"></div></div>
    </div>

    <p class="text-center mt-3"><a href="{{ route('partner.orders.history') }}" class="text-muted">{{ translate('All past orders') }}</a></p>

    <div class="modal fade" id="reject-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 18px">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">{{ translate('Decline this order') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-14">{{ translate('The customer is refunded straight away. Tell them why:') }}</p>
                    <div class="d-grid gap-2" data-reasons>
                        @foreach([translate('Too busy right now'), translate('An item is sold out'), translate('We are closing soon')] as $reason)
                            <button type="button" class="btn btn-outline-secondary text-start" data-reason="{{ $reason }}">{{ $reason }}</button>
                        @endforeach
                        <input type="text" class="form-control" maxlength="200" placeholder="{{ translate('Other reason') }}" data-reason-other>
                        <button type="button" class="btn btn-primary" data-reason-send>{{ translate('Decline order') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
<script>
(function () {
    var T = {
        newOrders: @json(translate('New')),
        preparing: @json(translate('Preparing')),
        rider: @json(translate('Rider')),
        onTheWay: @json(translate('On the way')),
        done: @json(translate('Last 3 hours')),
        empty: @json(translate('No orders right now. New orders appear here with a sound.')),
        accept: @json(translate('Accept')),
        decline: @json(translate('Decline')),
        readyIn: @json(translate('Ready in (min)')),
        ready: @json(translate('Food is ready, call a rider')),
        respondIn: @json(translate('Answer in')),
        readyAt: @json(translate('Ready at')),
        late: @json(translate('Late')),
        searching: @json(translate('Looking for a rider')),
        riderComing: @json(translate('Rider on the way to you')),
        pickedUp: @json(translate('Picked up, on the way to the customer')),
        pickupCode: @json(translate('Pickup code')),
        delivered: @json(translate('Delivered')),
        rejected: @json(translate('Declined')),
        cancelled: @json(translate('Cancelled')),
        expired: @json(translate('Expired')),
        failed: @json(translate('Not delivered')),
        offline: @json(translate('Offline, retrying')),
        live: @json(translate('Live')),
        total: @json(translate('Food total')),
        note: @json(translate('Note')),
        newOrderTitle: @json(translate('New order')),
        accepted: @json(translate('Order accepted')),
        declined: @json(translate('Order declined, the customer is refunded')),
        riderCalled: @json(translate('Looking for a rider now')),
        failedSave: @json(translate('Could not save. Check your connection and try again.')),
        items: @json(translate('items')).toLowerCase(),
        item: @json(translate('item')).toLowerCase(),
    };
    var urls = {
        feed: @json(route('partner.orders.feed')),
        accept: @json(route('partner.orders.accept', ['order' => '__ID__'])),
        reject: @json(route('partner.orders.reject', ['order' => '__ID__'])),
        ready: @json(route('partner.orders.ready', ['order' => '__ID__'])),
    };
    var defaultPrep = {{ (int)$store->default_prep_minutes }};
    var board = document.querySelector('[data-board]');
    var badge = document.querySelector('[data-new-badge]');
    var live = document.querySelector('[data-live]');
    var seen = null, orders = [], serverOffset = 0, prepChoice = {}, rejecting = null, rendered = {};

    /* ---------- sound: a loud two-tone alarm that repeats until someone acts */
    var ctx = null, ringing = null;
    function unlock() {
        try { ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); ctx.resume(); } catch (e) {}
        document.querySelector('[data-sound-bar]').style.display = 'none';
        try { localStorage.setItem('erp_sound', '1'); } catch (e) {}
        if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
        keepAwake();
    }
    document.querySelector('[data-sound-on]').addEventListener('click', unlock);
    document.addEventListener('pointerdown', function once() { if (ctx) return; unlock(); }, {once: true});
    try { if (localStorage.getItem('erp_sound') === '1') document.querySelector('[data-sound-bar]').style.display = 'none'; } catch (e) {}
    function beep() {
        if (!ctx) return;
        [0, .22].forEach(function (at, i) {
            var o = ctx.createOscillator(), g = ctx.createGain();
            o.type = 'square'; o.frequency.value = i ? 1320 : 880;
            g.gain.setValueAtTime(.0001, ctx.currentTime + at);
            g.gain.exponentialRampToValueAtTime(.35, ctx.currentTime + at + .02);
            g.gain.exponentialRampToValueAtTime(.0001, ctx.currentTime + at + .2);
            o.connect(g).connect(ctx.destination); o.start(ctx.currentTime + at); o.stop(ctx.currentTime + at + .22);
        });
        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    }
    function ring(on) {
        if (on && !ringing) { beep(); ringing = setInterval(beep, 1600); }
        if (!on && ringing) { clearInterval(ringing); ringing = null; }
    }
    var wakeLock = null;
    function keepAwake() {
        if (!('wakeLock' in navigator) || wakeLock) return;
        navigator.wakeLock.request('screen').then(function (l) { wakeLock = l; l.addEventListener('release', function () { wakeLock = null; }); }).catch(function () {});
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { keepAwake(); poll(); } });

    /* ---------- helpers */
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
    function now() { return Date.now() + serverOffset; }
    function mmss(ms) { var s = Math.max(0, Math.round(ms / 1000)); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); }
    function clock(iso) { var d = new Date(iso); return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }
    function count(n) { return n + ' ' + (n === 1 ? T.item : T.items); }
    function url(name, id) { return urls[name].replace('__ID__', id); }

    function itemsHtml(o) {
        return '<ul class="o__items">' + o.items.map(function (i) {
            return '<li><span class="o__qty">' + i.quantity + 'x</span><span>' + esc(i.name) + '</span></li>';
        }).join('') + '</ul>' +
            '<div class="o__total"><span>' + T.total + '</span><span>' + esc(o.items_subtotal_text) + '</span></div>' +
            (o.note ? '<div class="o__note"><b>' + T.note + ':</b> ' + esc(o.note) + '</div>' : '');
    }
    function riderHtml(o) {
        if (o.status === 'picked_up') return '<div class="o__state" style="color:var(--ok)"><i class="bi bi-bicycle"></i>' + T.pickedUp + '</div>';
        if (!o.rider) return '<div class="o__state searching">' + T.searching + '</div>';
        return '<div class="o__rider"><i class="bi bi-person-circle"></i><div><b>' + esc(o.rider.name) + '</b><br>' +
            '<a href="tel:' + esc(o.rider.phone) + '">' + esc(o.rider.phone) + '</a>' + (o.rider.vehicle ? ' · ' + esc(o.rider.vehicle) : '') + '</div>' +
            (o.rider.pickup_code ? '<div class="o__code"><b>' + esc(o.rider.pickup_code) + '</b><small>' + T.pickupCode + '</small></div>' : '') + '</div>' +
            '<div class="o__state"><i class="bi bi-geo-alt"></i>' + T.riderComing + '</div>';
    }
    function card(o) {
        var head = '<div class="o__head"><span class="o__ref">#' + o.ref + '</span><span class="o__who">' + esc(o.customer_name) + ' · ' + count(o.items_count) + '</span>';
        if (o.status === 'placed' && o.respond_by) {
            var left = new Date(o.respond_by).getTime() - now();
            head += '<span class="o__timer ' + (left < 90000 ? 'is-hot' : '') + '" data-countdown="' + o.respond_by + '">' + T.respondIn + ' ' + mmss(left) + '</span>';
        } else if (o.status === 'accepted' && o.ready_at) {
            var late = new Date(o.ready_at).getTime() < now();
            head += '<span class="o__timer ' + (late ? 'is-hot' : 'is-ok') + '">' + (late ? T.late : T.readyAt + ' ' + clock(o.ready_at)) + '</span>';
        }
        head += '</div>';
        var body = itemsHtml(o), actions = '';
        if (o.status === 'placed') {
            var p = prepChoice[o.id] || defaultPrep;
            var opts = [10, 15, 20, 30, 45, 60];
            if (opts.indexOf(defaultPrep) < 0) opts.splice(2, 0, defaultPrep);
            actions = '<div class="fw-bold" style="font-size:13px">' + T.readyIn + '</div><div class="prep">' +
                opts.map(function (m) { return '<button type="button" class="' + (m === p ? 'is-on' : '') + '" data-prep="' + m + '" data-id="' + o.id + '">' + m + '</button>'; }).join('') +
                '</div><div class="o__actions"><button type="button" class="btn btn-outline-secondary" data-reject="' + o.id + '">' + T.decline + '</button>' +
                '<button type="button" class="btn btn-primary" style="flex:2" data-accept="' + o.id + '">' + T.accept + ' · ' + p + ' min</button></div>';
        } else if (o.status === 'accepted') {
            actions = '<button type="button" class="btn btn-primary w-100" style="min-height:52px" data-ready="' + o.id + '"><i class="bi bi-bell"></i> ' + T.ready + '</button>';
        } else if (o.status === 'rider_requested' || o.status === 'picked_up') {
            actions = riderHtml(o);
        }
        var arrived = !rendered[o.id + o.status];
        return '<article class="o ' + (o.status === 'placed' ? 'is-new' : '') + (arrived ? ' is-arrived' : '') + '" data-order="' + o.id + '">' + head + body + actions + '</article>';
    }
    function doneRow(o) {
        var map = {delivered: ['ok', T.delivered], rejected: ['no', T.rejected], cancelled: ['no', T.cancelled], expired: ['no', T.expired], delivery_failed: ['no', T.failed]};
        var m = map[o.status] || ['no', o.status];
        return '<div class="done-row"><b>#' + o.ref + '</b><span class="text-muted">' + count(o.items_count) + ' · ' + esc(o.items_subtotal_text) + '</span><span class="tag ' + m[0] + '">' + m[1] + '</span></div>';
    }
    function lane(cls, title, list, render) {
        if (!list.length) return '';
        return '<section class="lane ' + cls + '"><h2>' + title + ' <span class="count">' + list.length + '</span></h2><div class="' + (render === doneRow ? 'd-grid gap-2' : 'grid') + '">' + list.map(render).join('') + '</div></section>';
    }
    function render() {
        var by = function (s) { return orders.filter(function (o) { return s.indexOf(o.status) >= 0; }); };
        var fresh = by(['placed']);
        var html = lane('is-new', T.newOrders, fresh, card) +
            lane('', T.preparing, by(['accepted']), card) +
            lane('', T.rider, by(['rider_requested']), card) +
            lane('', T.onTheWay, by(['picked_up']), card) +
            lane('', T.done, by(['delivered', 'rejected', 'cancelled', 'expired', 'delivery_failed']).reverse(), doneRow);
        board.innerHTML = html || '<div class="empty"><i class="bi bi-receipt"></i>' + T.empty + '</div>';
        orders.forEach(function (o) { rendered[o.id + o.status] = true; });
        badge.textContent = fresh.length; badge.style.display = fresh.length ? 'inline-flex' : 'none';
        document.title = (fresh.length ? '(' + fresh.length + ') ' : '') + @json(translate('Orders')) + ' · E-Ride';
        // Keeps ringing until someone acts; tapping an order to read it
        // pauses the alarm for a minute.
        ring(fresh.length > 0 && Date.now() > silencedUntil);
    }
    var silencedUntil = 0;

    /* ---------- polling */
    var failures = 0;
    function poll() {
        fetch(urls.feed, {headers: {'Accept': 'application/json'}, cache: 'no-store'})
            .then(function (r) { if (r.status === 401) { location.reload(); throw 0; } return r.json(); })
            .then(function (d) {
                failures = 0; live.classList.remove('is-off'); live.querySelector('[data-live-label]').textContent = T.live;
                serverOffset = new Date(d.server_time).getTime() - Date.now();
                var ids = d.orders.filter(function (o) { return o.status === 'placed'; }).map(function (o) { return o.id; });
                if (seen !== null) {
                    var arrived = ids.filter(function (id) { return seen.indexOf(id) < 0; });
                    if (arrived.length && 'Notification' in window && Notification.permission === 'granted' && document.hidden) {
                        new Notification(T.newOrderTitle, {body: '#' + d.orders.find(function (o) { return o.id === arrived[0]; }).ref, tag: 'erp-order'});
                    }
                }
                seen = ids;
                orders = d.orders;
                if (!document.querySelector('.modal.show')) render();
            })
            .catch(function () {
                failures++;
                if (failures > 1) { live.classList.add('is-off'); live.querySelector('[data-live-label]').textContent = T.offline; }
            });
    }
    poll();
    setInterval(poll, 8000);
    setInterval(function () {
        document.querySelectorAll('[data-countdown]').forEach(function (el) {
            var left = new Date(el.dataset.countdown).getTime() - now();
            el.textContent = T.respondIn + ' ' + mmss(left);
            el.classList.toggle('is-hot', left < 90000);
        });
    }, 1000);

    /* ---------- actions */
    function act(name, id, body, okText) {
        ring(false);
        return partnerPost(url(name, id), body).then(function (d) {
            if (d.order) {
                orders = orders.map(function (o) { return o.id === d.order.id ? d.order : o; });
                render();
            }
            partnerToast(d.ok ? okText : (d.message || T.failedSave));
            poll();
        }).catch(function () { partnerToast(T.failedSave); render(); });
    }
    board.addEventListener('click', function (e) {
        if (e.target.closest('.o.is-new')) { silencedUntil = Date.now() + 60000; ring(false); }
        var t = e.target.closest('button');
        if (!t) return;
        if (t.dataset.prep) { prepChoice[t.dataset.id] = parseInt(t.dataset.prep, 10); render(); return; }
        if (t.dataset.accept) { t.disabled = true; act('accept', t.dataset.accept, {prep_minutes: prepChoice[t.dataset.accept] || defaultPrep}, T.accepted); return; }
        if (t.dataset.ready) { t.disabled = true; act('ready', t.dataset.ready, {}, T.riderCalled); return; }
        if (t.dataset.reject) { rejecting = t.dataset.reject; ring(false); bootstrap.Modal.getOrCreateInstance(document.getElementById('reject-modal')).show(); }
    });
    var modalEl = document.getElementById('reject-modal');
    function sendReject(reason) {
        if (!rejecting || !reason) return;
        bootstrap.Modal.getInstance(modalEl).hide();
        act('reject', rejecting, {reason: reason}, T.declined);
        rejecting = null;
    }
    modalEl.querySelectorAll('[data-reason]').forEach(function (b) { b.addEventListener('click', function () { sendReject(b.dataset.reason); }); });
    modalEl.querySelector('[data-reason-send]').addEventListener('click', function () { sendReject(modalEl.querySelector('[data-reason-other]').value.trim()); });
    modalEl.addEventListener('hidden.bs.modal', function () { modalEl.querySelector('[data-reason-other]').value = ''; render(); });
})();
</script>
@endpush
