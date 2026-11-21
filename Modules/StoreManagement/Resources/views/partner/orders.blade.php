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
        .o__branch { font-size: 12px; font-weight: 700; background: #111114; color: #fff; border-radius: 999px; padding: 2px 9px; white-space: nowrap; }
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
    .o.is-waiting { border-style: dashed; }
    .o__contact { display: flex; gap: 8px; }
    .o__contact a { flex: 1; min-height: 42px; display: flex; align-items: center; justify-content: center; gap: 6px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; border: 1px solid var(--line); color: var(--ink); background: #fff; }
    .o__contact a.wa { color: #128c4a; border-color: #b8e6cb; background: #f2fbf6; }
    .o__cancel { background: none; border: 0; color: var(--muted); font-size: 13px; text-decoration: underline; padding: 0; align-self: center; }
    .o__wait { background: #f4f4f6; border-radius: 12px; padding: 10px 12px; font-size: 14px; font-weight: 600; }
    .o__paid { font-size: 12px; font-weight: 800; color: var(--ok); background: rgba(18, 161, 80, .12); border-radius: 999px; padding: 2px 9px; }
    .chg li s { color: var(--muted); }
    .chg .tag-chg { font-size: 11px; font-weight: 800; border-radius: 999px; padding: 1px 7px; margin-left: auto; white-space: nowrap; }
    .tag-chg.removed { background: #fde8e8; color: var(--red); }
    .tag-chg.reduced, .tag-chg.increased { background: #fff3d6; color: #8a5a00; }
    .tag-chg.swapped { background: #e7f0ff; color: #1d4ed8; }
    .cm-row { border: 1px solid var(--line); border-radius: 12px; padding: 10px; display: grid; gap: 8px; }
    .cm-row.is-removed { opacity: .55; }
    .cm-top { display: flex; align-items: center; gap: 8px; font-weight: 700; }
    .cm-qty { margin-left: auto; display: flex; align-items: center; gap: 6px; }
    .cm-qty button { width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--line); background: #fff; font-weight: 900; font-size: 18px; line-height: 1; }
    .cm-qty b { min-width: 22px; text-align: center; }
    .who { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .who button { min-height: 52px; border-radius: 12px; border: 1px solid var(--line); background: #fff; font-weight: 700; font-size: 13px; line-height: 1.2; padding: 6px; }
    .who button small { display: block; font-weight: 600; color: var(--muted); }
    .who button.is-on { border-color: var(--red); background: var(--red-soft); color: var(--red); }
    .who button.is-on small { color: var(--red); }
    .o__own { margin: 6px 0; padding: 8px 10px; border-radius: 6px; background: #E0F2FE; color: #075985; font-size: 13px; }
    .o__own b { display: block; font-size: 13px; letter-spacing: .02em; }
    .o__own a { color: inherit; font-weight: 700; }
    .o__link { background: none; border: 0; color: var(--ink); font-size: 13px; font-weight: 700; text-decoration: underline; padding: 0; align-self: center; }
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
                    <h5 class="modal-title fw-bold" data-reject-title>{{ translate('Decline this order') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-14" data-reject-hint>{{ translate('Nothing was paid yet. Tell the customer why:') }}</p>
                    <div class="d-grid gap-2" data-reasons>
                        <button type="button" class="btn btn-outline-secondary text-start" data-reason="{{ translate('No rider came to collect the order') }}" data-reason-norider style="display:none">{{ translate('No rider came to collect the order') }}</button>
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

    <div class="modal fade" id="changes-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 18px">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">{{ translate('Suggest changes') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-14">{{ translate('Lower a quantity, remove a dish, or offer another one instead. The customer sees the new total and accepts or cancels. Nothing is paid until they accept.') }}</p>
                    <div class="d-grid gap-2" data-cm-rows></div>
                    <label class="form-label fw-bold mt-3 mb-1" style="font-size:14px">{{ translate('Message to the customer (optional)') }}</label>
                    <textarea class="form-control" rows="2" maxlength="300" data-cm-note placeholder="{{ translate('Ex: No plantains today, we can do rice instead.') }}"></textarea>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <div class="w-100 d-flex justify-content-between fw-bold mb-1" style="font-size:15px"><span>{{ translate('New food total') }}</span><span data-cm-total></span></div>
                    <button type="button" class="btn btn-primary w-100" style="min-height:50px" data-cm-send>{{ translate('Send to the customer') }}</button>
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
        readyHere: @json(translate('It is ready: tell the customer')),
        toldReady: @json(translate('Customer told it is ready')),
        eatHere: @json(translate('EAT HERE')),
        collect: @json(translate('COLLECT')),
        people: @json(translate('people')),
        codeHint: @json(translate('Customer code')),
        handOver: @json(translate('Hand over')),
        handedOver: @json(translate('Order handed over')),
        customerTold: @json(translate('The customer has been told')),
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
        comingBack: @json(translate('Not delivered: the rider is bringing it back. When you have it, give this code')),
        offline: @json(translate('Offline, retrying')),
        live: @json(translate('Live')),
        total: @json(translate('Food total')),
        note: @json(translate('Note')),
        newOrderTitle: @json(translate('New order')),
        accepted: @json(translate('Order accepted')),
        declined: @json(translate('Order declined')),
        riderCalled: @json(translate('Looking for a rider now')),
        failedSave: @json(translate('Could not save. Check your connection and try again.')),
        confirm: @json(translate('Confirm')),
        suggest: @json(translate('Change')),
        confirmed: @json(translate('Order confirmed. The customer pays, then you start cooking.')),
        waiting: @json(translate('Waiting for the customer')),
        payIn: @json(translate('Pays within')),
        answerIn: @json(translate('Answers within')),
        waitPay: @json(translate('Confirmed. Waiting for the customer to pay. Do not start cooking yet.')),
        waitAnswer: @json(translate('Changes sent. Waiting for the customer to accept them.')),
        paidStart: @json(translate('is paid: start cooking')),
        paid: @json(translate('Paid')),
        call: @json(translate('Call')),
        whatsapp: @json(translate('WhatsApp')),
        cancelOrder: @json(translate('Cancel this order')),
        cancelTitle: @json(translate('Cancel this order')),
        declineTitle: @json(translate('Decline this order')),
        hintUnpaid: @json(translate('Nothing was paid yet. Tell the customer why:')),
        hintPaid: @json(translate('The customer is refunded to their E-Ride wallet straight away. Tell them why:')),
        hintPaidNoRefund: @json(translate('The customer has paid. E-Ride will contact them about their money. Tell them why:')),
        hintNoRider: @json(translate('No rider has taken this order yet, and E-Ride keeps calling riders. Cancel only if you cannot keep the food.')),
        declineSend: @json(translate('Decline order')),
        cancelSend: @json(translate('Cancel order')),
        tries: @json(translate('try')),
        cancelled2: @json(translate('Order cancelled')),
        changesSent: @json(translate('Changes sent to the customer')),
        keep: @json(translate('Keep')),
        offer: @json(translate('Offer instead')),
        removed: @json(translate('Removed')),
        reduced: @json(translate('Fewer')),
        increased: @json(translate('More')),
        swapped: @json(translate('Instead')),
        loading: @json(translate('Loading')),
        items: @json(translate('items')).toLowerCase(),
        item: @json(translate('item')).toLowerCase(),
        whoBrings: @json(translate('This customer is close by. Who brings it')),
        byRider: @json(translate('E-Ride rider')),
        byStaff: @json(translate('Our staff')),
        customerPays: @json(translate('customer pays')),
        staffBring: @json(translate('OUR STAFF BRING IT')),
        map: @json(translate('Map')),
        staffLeave: @json(translate('Our staff are leaving with it')),
        staffOut: @json(translate('Your staff are on the way. Ask the customer for their code at the door.')),
        staffHint: @json(translate('When it is ready, send your staff. They ask the customer for the code at the door.')),
        deliveredBtn: @json(translate('Delivered')),
        staffGone: @json(translate('The customer has been told it is on the way')),
        staffDelivered: @json(translate('Order delivered')),
        markReady: @json(translate('It is ready')),
        useRider: @json(translate('Nobody can go: send a rider instead')),
        useRiderAsk: @json(translate('Send an E-Ride rider instead of your staff? The customer pays nothing more. This amount comes out of what you earn on this order:')),
        useRiderFree: @json(translate('Send an E-Ride rider instead of your staff? It costs you nothing more.')),
        riderInstead: @json(translate('A rider is being called')),
        youPay: @json(translate('You pay towards the rider')),
        heldFirst: @json(translate('Calling your riders first')),
        heldOnly: @json(translate('Calling only your riders')),
        openRiders: @json(translate('Call every rider nearby')),
        ridersOpened: @json(translate('Every rider nearby is being called')),
    };
    var urls = {
        feed: @json(route('partner.orders.feed')),
        accept: @json(route('partner.orders.accept', ['order' => '__ID__'])),
        reject: @json(route('partner.orders.reject', ['order' => '__ID__'])),
        ready: @json(route('partner.orders.ready', ['order' => '__ID__'])),
        handover: @json(route('partner.orders.hand-over', ['order' => '__ID__'])),
        swaps: @json(route('partner.orders.swaps', ['order' => '__ID__'])),
        propose: @json(route('partner.orders.propose', ['order' => '__ID__'])),
        out: @json(route('partner.orders.out', ['order' => '__ID__'])),
        userider: @json(route('partner.orders.use-rider', ['order' => '__ID__'])),
        openriders: @json(route('partner.orders.open-riders', ['order' => '__ID__'])),
    };
    var defaultPrep = {{ (int)$store->default_prep_minutes }};
    var board = document.querySelector('[data-board]');
    var badge = document.querySelector('[data-new-badge]');
    var live = document.querySelector('[data-live]');
    var seen = null, orders = [], serverOffset = 0, prepChoice = {}, rejecting = null, rendered = {}, lastStatus = {};
    // "Our staff will bring it", chosen on a new order from a customer close by.
    var staffChoice = {};

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
        if (!o.rider) {
            var looking = o.riders_held ? (o.riders_held === 'only' ? T.heldOnly : T.heldFirst) : T.searching;
            return '<div class="o__state searching">' + looking + (o.rider_attempts > 1 ? ' · ' + T.tries + ' ' + o.rider_attempts : '') + '</div>' +
                (o.store_covers_text ? '<div class="o__who">' + T.youPay + ': ' + esc(o.store_covers_text) + '</div>' : '') +
                (o.riders_held ? '<button type="button" class="btn btn-outline-dark w-100" style="min-height:44px" data-openriders="' + o.id + '"><i class="bi bi-broadcast"></i> ' + T.openRiders + '</button>' : '');
        }
        return '<div class="o__rider"><i class="bi bi-person-circle"></i><div><b>' + esc(o.rider.name) + '</b><br>' +
            '<a href="tel:' + esc(o.rider.phone) + '">' + esc(o.rider.phone) + '</a>' + (o.rider.vehicle ? ' · ' + esc(o.rider.vehicle) : '') + '</div>' +
            (o.rider.pickup_code ? '<div class="o__code"><b>' + esc(o.rider.pickup_code) + '</b><small>' + T.pickupCode + '</small></div>' : '') + '</div>' +
            '<div class="o__state"><i class="bi bi-geo-alt"></i>' + T.riderComing + '</div>';
    }
    function contactHtml(o) {
        if (!o.customer_phone) return '';
        var wa = o.whatsapp ? '<a class="wa" target="_blank" rel="noopener" href="https://wa.me/' + esc(o.whatsapp) + '?text=' + encodeURIComponent('E-Ride #' + o.ref + ': ') + '"><i class="bi bi-whatsapp"></i>' + T.whatsapp + '</a>' : '';
        return '<div class="o__contact"><a href="tel:' + esc(o.customer_phone) + '"><i class="bi bi-telephone"></i>' + T.call + '</a>' + wa + '</div>';
    }
    function proposalHtml(o) {
        var p = o.proposal;
        if (!p) return '';
        return '<ul class="o__items chg">' + p.lines.map(function (l) {
            var tag = l.change === 'same' ? '' : '<span class="tag-chg ' + l.change + '">' + (T[l.change] || l.change) + '</span>';
            var text = l.change === 'swapped'
                ? '<s>' + l.was_quantity + 'x ' + esc(l.was_name) + '</s> ' + l.quantity + 'x ' + esc(l.name)
                : (l.change === 'removed' ? '<s>' + l.was_quantity + 'x ' + esc(l.name) + '</s>'
                    : (l.change === 'same' ? l.quantity + 'x ' + esc(l.name) : '<s>' + l.was_quantity + 'x</s> ' + l.quantity + 'x ' + esc(l.name)));
            return '<li><span>' + text + '</span>' + tag + '</li>';
        }).join('') + '</ul>' + (p.note ? '<div class="o__note">' + esc(p.note) + '</div>' : '');
    }
    function card(o) {
        var head = '<div class="o__head"><span class="o__ref">#' + o.ref + '</span>' + (o.branch ? '<span class="o__branch"><i class="bi bi-shop"></i> ' + esc(o.branch) + '</span>' : '') + '<span class="o__who">' + esc(o.customer_name) + ' · ' + count(o.items_count) + '</span>';
        var due = o.respond_by || o.answer_by || o.pay_by;
        var dueLabel = o.respond_by ? T.respondIn : (o.answer_by ? T.answerIn : T.payIn);
        if (due) {
            var left = new Date(due).getTime() - now();
            head += '<span class="o__timer ' + (o.respond_by && left < 90000 ? 'is-hot' : '') + '" data-countdown="' + due + '" data-label="' + esc(dueLabel) + '" data-hot="' + (o.respond_by ? 1 : 0) + '">' + dueLabel + ' ' + mmss(left) + '</span>';
        } else if (o.status === 'accepted' && o.ready_at) {
            var late = new Date(o.ready_at).getTime() < now();
            head += '<span class="o__timer ' + (late ? 'is-hot' : 'is-ok') + '">' + (late ? T.late : T.readyAt + ' ' + clock(o.ready_at)) + '</span>';
        }
        head += '</div>';
        // Not a delivery: say so loudly, with the time they come.
        var here = o.fulfilment === 'dine_in' || o.fulfilment === 'pickup';
        if (here) {
            head += '<div style="margin:6px 0;padding:6px 10px;border-radius:6px;background:#FEF3C7;color:#92400E;font-weight:700;font-size:13px">' +
                '<i class="bi ' + (o.fulfilment === 'dine_in' ? 'bi-cup-hot' : 'bi-bag-check') + '"></i> ' +
                (o.fulfilment === 'dine_in' ? T.eatHere : T.collect) + (o.arrive_at ? ' · ' + clock(o.arrive_at) : '') +
                (o.fulfilment === 'dine_in' && o.guests ? ' · ' + o.guests + ' ' + T.people : '') + '</div>';
        }
        // The store's own staff bring it: say so, with where they go.
        var byStaff = o.delivered_by === 'store';
        if (byStaff) {
            head += '<div class="o__own"><b><i class="bi bi-person-walking"></i> ' + T.staffBring + ' · ' + esc(o.delivery_fee_text) + '</b>' +
                esc(o.drop_address || '') + (o.drop_map ? ' · <a target="_blank" rel="noopener" href="' + esc(o.drop_map) + '">' + T.map + '</a>' : '') + '</div>';
        }
        var body = o.status === 'changes_proposed' ? proposalHtml(o) : itemsHtml(o), actions = '';
        var isNew = o.status === 'awaiting_confirmation' || o.status === 'placed';
        var codeRow = function (label) {
            return '<div class="o__actions" style="margin-top:8px"><input type="text" inputmode="numeric" maxlength="4" class="form-control" style="min-height:48px;flex:1;font-size:18px;letter-spacing:4px;text-align:center" placeholder="' + esc(T.codeHint) + '" aria-label="' + esc(T.codeHint) + '" data-code-for="' + o.id + '">' +
                '<button type="button" class="btn btn-primary" data-handover="' + o.id + '"' + (byStaff ? ' data-staff="1"' : '') + '>' + label + '</button></div>';
        };
        if (isNew) {
            var p = prepChoice[o.id] || defaultPrep;
            var opts = [10, 15, 20, 30, 45, 60];
            if (opts.indexOf(defaultPrep) < 0) opts.splice(2, 0, defaultPrep);
            // A new order is confirmed before payment, and can be changed; an older paid one is only accepted.
            var fresh = o.status === 'awaiting_confirmation';
            // Close by: an E-Ride rider (the default) or the store's own staff, each with what the customer pays.
            var who = '';
            if (fresh && o.own_offer) {
                var staff = !!staffChoice[o.id];
                who = '<div class="fw-bold" style="font-size:13px">' + T.whoBrings + ' (' + esc(o.own_offer.distance_text) + ')</div>' +
                    '<div class="o__who" style="margin-top:-6px">' + esc(o.drop_address || '') + '</div><div class="who">' +
                    '<button type="button" class="' + (staff ? '' : 'is-on') + '" data-who="rider" data-id="' + o.id + '"><i class="bi bi-bicycle"></i> ' + T.byRider + '<small>' + T.customerPays + ' ' + esc(o.own_offer.rider_fee_text) + '</small></button>' +
                    '<button type="button" class="' + (staff ? 'is-on' : '') + '" data-who="staff" data-id="' + o.id + '"><i class="bi bi-person-walking"></i> ' + T.byStaff + '<small>' + T.customerPays + ' ' + esc(o.own_offer.fee_text) + '</small></button></div>';
            }
            actions = who + '<div class="fw-bold" style="font-size:13px">' + T.readyIn + '</div><div class="prep">' +
                opts.map(function (m) { return '<button type="button" class="' + (m === p ? 'is-on' : '') + '" data-prep="' + m + '" data-id="' + o.id + '">' + m + '</button>'; }).join('') +
                '</div><div class="o__actions"><button type="button" class="btn btn-outline-secondary" data-reject="' + o.id + '">' + T.decline + '</button>' +
                (fresh ? '<button type="button" class="btn btn-outline-dark" data-change="' + o.id + '"><i class="bi bi-pencil"></i> ' + T.suggest + '</button>' : '') +
                '<button type="button" class="btn btn-primary" style="flex:2" data-accept="' + o.id + '">' + (fresh ? T.confirm : T.accept) + ' · ' + p + ' min</button></div>';
        } else if (o.status === 'changes_proposed') {
            actions = '<div class="o__wait"><i class="bi bi-hourglass-split"></i> ' + T.waitAnswer + '</div>';
        } else if (o.status === 'awaiting_payment') {
            actions = '<div class="o__wait"><i class="bi bi-hourglass-split"></i> ' + T.waitPay + '</div>';
        } else if (o.status === 'accepted' && here) {
            // Eat here or collect: tell them it is ready, then type their code when they are here.
            actions = (o.ready_told
                    ? '<div class="o__wait"><i class="bi bi-check2-circle"></i> ' + T.toldReady + '</div>'
                    : '<button type="button" class="btn btn-outline-dark w-100" style="min-height:48px" data-ready="' + o.id + '" data-here="1"><i class="bi bi-bell"></i> ' + T.readyHere + '</button>') +
                codeRow(T.handOver);
        } else if (o.status === 'accepted' && byStaff) {
            // Paid: cook, then a staff member leaves with it and asks for the code at the door.
            actions = '<div class="o__wait"><i class="bi bi-info-circle"></i> ' + T.staffHint + '</div>' +
                '<button type="button" class="btn btn-primary w-100" style="min-height:52px;margin-top:8px" data-out="' + o.id + '"><i class="bi bi-person-walking"></i> ' + T.staffLeave + '</button>' +
                codeRow(T.deliveredBtn) +
                (o.can_use_rider ? '<button type="button" class="o__link" style="margin-top:6px" data-userider="' + o.id + '" data-extra="' + (o.rider_extra > 0 ? esc(o.rider_extra_text) : '') + '">' + T.useRider + '</button>' : '');
        } else if (o.status === 'picked_up' && byStaff) {
            actions = '<div class="o__state" style="color:var(--ok)"><i class="bi bi-person-walking"></i>' + T.staffOut + '</div>' + codeRow(T.deliveredBtn);
        } else if (o.status === 'accepted') {
            actions = '<button type="button" class="btn btn-primary w-100" style="min-height:52px" data-ready="' + o.id + '"><i class="bi bi-bell"></i> ' + T.ready + '</button>';
        } else if (o.status === 'rider_requested' || o.status === 'picked_up') {
            actions = riderHtml(o);
        }
        if (o.paid && !isNew) head = head.replace('</div>', '') + '<span class="o__paid">' + T.paid + '</span></div>';
        var extra = contactHtml(o) + (o.can_cancel && !isNew ? '<button type="button" class="o__cancel" data-cancel="' + o.id + '">' + T.cancelOrder + '</button>' : '');
        var arrived = !rendered[o.id + o.status];
        var cls = isNew ? 'is-new' : (o.status === 'changes_proposed' || o.status === 'awaiting_payment' ? 'is-waiting' : '');
        return '<article class="o ' + cls + (arrived ? ' is-arrived' : '') + '" data-order="' + o.id + '">' + head + body + actions + extra + '</article>';
    }
    function doneRow(o) {
        var map = {delivered: ['ok', T.delivered], rejected: ['no', T.rejected], cancelled: ['no', T.cancelled], expired: ['no', T.expired], delivery_failed: ['no', T.failed]};
        var m = map[o.status] || ['no', o.status];
        if (o.return_code) {
            return '<div class="done-row" style="flex-wrap:wrap"><b>#' + o.ref + '</b><span class="text-muted">' + T.comingBack + '</span>' +
                '<span class="o__code"><b>' + esc(o.return_code) + '</b></span></div>';
        }
        return '<div class="done-row"><b>#' + o.ref + '</b><span class="text-muted">' + count(o.items_count) + ' · ' + esc(o.items_subtotal_text) + '</span><span class="tag ' + m[0] + '">' + m[1] + '</span></div>';
    }
    function lane(cls, title, list, render) {
        if (!list.length) return '';
        return '<section class="lane ' + cls + '"><h2>' + title + ' <span class="count">' + list.length + '</span></h2><div class="' + (render === doneRow ? 'd-grid gap-2' : 'grid') + '">' + list.map(render).join('') + '</div></section>';
    }
    function render() {
        var by = function (s) { return orders.filter(function (o) { return s.indexOf(o.status) >= 0; }); };
        var fresh = by(['awaiting_confirmation', 'placed']);
        var html = lane('is-new', T.newOrders, fresh, card) +
            lane('', T.waiting, by(['changes_proposed', 'awaiting_payment']), card) +
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
                var ids = d.orders.filter(function (o) { return o.status === 'placed' || o.status === 'awaiting_confirmation'; }).map(function (o) { return o.id; });
                // Paid since the last look: the kitchen can start now.
                d.orders.forEach(function (o) {
                    var was = lastStatus[o.id];
                    if (was === 'awaiting_payment' && o.status === 'accepted') {
                        beep();
                        partnerToast('#' + o.ref + ' ' + T.paidStart);
                        if ('Notification' in window && Notification.permission === 'granted' && document.hidden) new Notification('#' + o.ref + ' ' + T.paidStart, {tag: 'erp-paid-' + o.id});
                    }
                    lastStatus[o.id] = o.status;
                });
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
            el.textContent = (el.dataset.label || T.respondIn) + ' ' + mmss(left);
            el.classList.toggle('is-hot', el.dataset.hot === '1' && left < 90000);
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
        if (t.dataset.who) { staffChoice[t.dataset.id] = t.dataset.who === 'staff'; render(); return; }
        if (t.dataset.accept) {
            t.disabled = true;
            var o0 = orders.find(function (o) { return o.id === t.dataset.accept; });
            act('accept', t.dataset.accept, {prep_minutes: prepChoice[t.dataset.accept] || defaultPrep, by_store: !!(o0 && o0.own_offer && staffChoice[t.dataset.accept])}, o0 && o0.status === 'awaiting_confirmation' ? T.confirmed : T.accepted);
            return;
        }
        if (t.dataset.out) { t.disabled = true; act('out', t.dataset.out, {}, T.staffGone); return; }
        if (t.dataset.userider) {
            var extra = t.dataset.extra || '';
            if (!confirm(extra ? T.useRiderAsk + ' ' + extra : T.useRiderFree)) return;
            t.disabled = true;
            act('userider', t.dataset.userider, {}, T.riderInstead);
            return;
        }
        if (t.dataset.openriders) { t.disabled = true; act('openriders', t.dataset.openriders, {}, T.ridersOpened); return; }
        if (t.dataset.change) { openChanges(t.dataset.change); return; }
        if (t.dataset.cancel) { openReject(t.dataset.cancel, true); return; }
        if (t.dataset.ready) { t.disabled = true; act('ready', t.dataset.ready, {}, t.dataset.here ? T.customerTold : T.riderCalled); return; }
        if (t.dataset.handover) {
            var input = board.querySelector('[data-code-for="' + t.dataset.handover + '"]');
            var code = input ? input.value.trim() : '';
            if (code.length < 4) { if (input) input.focus(); return; }
            t.disabled = true;
            act('handover', t.dataset.handover, {code: code}, t.dataset.staff ? T.staffDelivered : T.handedOver);
            return;
        }
        if (t.dataset.reject) { openReject(t.dataset.reject, false); }
    });
    function openReject(id, cancelling) {
        rejecting = id; ring(false);
        var o = orders.find(function (x) { return x.id === id; }) || {};
        var m = document.getElementById('reject-modal');
        m.querySelector('[data-reject-title]').textContent = cancelling ? T.cancelTitle : T.declineTitle;
        var paidHint = o.refunds ? T.hintPaid : T.hintPaidNoRefund;
        m.querySelector('[data-reject-hint]').textContent = o.no_rider ? T.hintNoRider + ' ' + paidHint : (o.paid ? paidHint : T.hintUnpaid);
        m.querySelector('[data-reason-norider]').style.display = o.no_rider ? '' : 'none';
        m.querySelector('[data-reason-send]').textContent = cancelling ? T.cancelSend : T.declineSend;
        m.dataset.okText = cancelling ? T.cancelled2 : T.declined;
        bootstrap.Modal.getOrCreateInstance(m).show();
    }

    /* ---------- suggest changes: per dish fewer, none, or another dish instead */
    var cmEl = document.getElementById('changes-modal'), cmOrder = null, cmRows = [], cmSwaps = [];
    function money(n) { return Math.round(n).toLocaleString('en-US').replace(/,/g, ' ') + ' FCFA'; }
    function cmPrice(r) {
        if (r.swap) { var s = cmSwaps.find(function (x) { return x.id === r.swap; }); return s ? s.price : r.unit_price; }
        return r.unit_price;
    }
    function cmRender() {
        var box = cmEl.querySelector('[data-cm-rows]');
        box.innerHTML = cmRows.map(function (r, i) {
            var options = '<option value="">' + T.keep + ': ' + esc(r.name) + '</option>' + cmSwaps.filter(function (s) { return s.id !== r.item_id; }).map(function (s) {
                return '<option value="' + s.id + '"' + (r.swap === s.id ? ' selected' : '') + '>' + esc(s.name) + ' · ' + esc(s.price_text) + '</option>';
            }).join('');
            return '<div class="cm-row' + (r.quantity === 0 ? ' is-removed' : '') + '">' +
                '<div class="cm-top"><span>' + esc(r.name) + '</span><div class="cm-qty">' +
                '<button type="button" data-cm-minus="' + i + '" aria-label="-">−</button><b>' + r.quantity + '</b>' +
                '<button type="button" data-cm-plus="' + i + '" aria-label="+">+</button></div></div>' +
                '<select class="form-select form-select-sm" data-cm-swap="' + i + '" aria-label="' + esc(T.offer) + '">' + options + '</select></div>';
        }).join('');
        var total = cmRows.reduce(function (sum, r) { return sum + cmPrice(r) * r.quantity; }, 0);
        cmEl.querySelector('[data-cm-total]').textContent = money(total);
    }
    function openChanges(id) {
        var o = orders.find(function (x) { return x.id === id; });
        if (!o) return;
        ring(false); silencedUntil = Date.now() + 120000;
        cmOrder = id;
        cmRows = o.items.map(function (i) { return {name: i.name, item_id: i.item_id, quantity: i.quantity, unit_price: i.unit_price, swap: ''}; });
        cmSwaps = [];
        cmEl.querySelector('[data-cm-note]').value = '';
        cmEl.querySelector('[data-cm-rows]').innerHTML = '<div class="text-muted">' + T.loading + '…</div>';
        bootstrap.Modal.getOrCreateInstance(cmEl).show();
        fetch(url('swaps', id), {headers: {'Accept': 'application/json'}}).then(function (r) { return r.json(); })
            .then(function (d) { cmSwaps = d.items || []; cmRender(); })
            .catch(function () { cmRender(); });
    }
    cmEl.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) return;
        if (b.dataset.cmMinus) { var r = cmRows[+b.dataset.cmMinus]; r.quantity = Math.max(0, r.quantity - 1); cmRender(); }
        if (b.dataset.cmPlus) { var r2 = cmRows[+b.dataset.cmPlus]; r2.quantity = Math.min(50, r2.quantity + 1); cmRender(); }
    });
    cmEl.addEventListener('change', function (e) {
        if (e.target.dataset.cmSwap) { var r = cmRows[+e.target.dataset.cmSwap]; r.swap = e.target.value; if (r.swap && r.quantity === 0) r.quantity = 1; cmRender(); }
    });
    cmEl.querySelector('[data-cm-send]').addEventListener('click', function () {
        if (!cmOrder) return;
        var id = cmOrder;
        bootstrap.Modal.getInstance(cmEl).hide();
        act('propose', id, {
            changes: cmRows.map(function (r) { return {quantity: r.quantity, swap_item_id: r.swap || null}; }),
            note: cmEl.querySelector('[data-cm-note]').value.trim(),
            by_store: !!((orders.find(function (x) { return x.id === id; }) || {}).own_offer && staffChoice[id])
        }, T.changesSent);
        cmOrder = null;
    });
    cmEl.addEventListener('hidden.bs.modal', function () { render(); });
    var modalEl = document.getElementById('reject-modal');
    function sendReject(reason) {
        if (!rejecting || !reason) return;
        bootstrap.Modal.getInstance(modalEl).hide();
        act('reject', rejecting, {reason: reason}, modalEl.dataset.okText || T.declined);
        rejecting = null;
    }
    modalEl.querySelectorAll('[data-reason]').forEach(function (b) { b.addEventListener('click', function () { sendReject(b.dataset.reason); }); });
    modalEl.querySelector('[data-reason-send]').addEventListener('click', function () { sendReject(modalEl.querySelector('[data-reason-other]').value.trim()); });
    modalEl.addEventListener('hidden.bs.modal', function () { modalEl.querySelector('[data-reason-other]').value = ''; render(); });
})();
</script>
@endpush
