@extends('busmanagement::partner.layout')

@section('title', translate('Quick sell'))

@php
    use Modules\BusManagement\Service\ScheduleService;
    $label = fn($d) => $d->leavesAt()->translatedFormat('D j, H:i') . ' · ' . $d->destination?->city . ' · ' . ScheduleService::classLabel($d->class) . ' · ' . $d->vehicle?->registration;
@endphp

@push('head')
<style>
    .s-grid { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 16px; align-items: start; }
    @media (max-width: 860px) { .s-grid { grid-template-columns: minmax(0, 1fr); } }
    .pax { border-top: 1px solid var(--line); padding: 10px 0; }
    .pax-seat { width: 38px; height: 34px; border-radius: 8px; background: var(--red); color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; flex: none; }
    .total { font-size: 26px; font-weight: 800; }
    .done { text-align: center; padding: 22px 10px; }
    .done > .bi { font-size: 44px; color: var(--ok); }
    .payer { background: #f4f6f5; border-radius: 10px; padding: 10px; margin: 6px 0 4px; }
    .payer-why { font-size: 12px; color: var(--muted); }
    /* The paper sheet of the chosen bus: what a new sheet looks like now, printed from here. */
    .ps { color: #111; font-size: 12px; line-height: 1.35; }
    .ps-scroll { overflow-x: auto; padding-bottom: 4px; }
    .ps-meta { display: flex; flex-wrap: wrap; gap: 2px 14px; margin: 2px 0 8px; color: #333; }
    .ps-rules { border: 2px solid #111; border-radius: 6px; padding: 6px 10px; margin-bottom: 8px; font-weight: 700; }
    .ps-legend { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 8px; font-size: 11px; }
    .ps-legend i { display: inline-block; width: 16px; height: 12px; border: 1px solid #333; vertical-align: -2px; margin-right: 4px; background: #fff; }
    .ps .sp { --sp-w: 62px; display: inline-flex; flex-direction: column; gap: 4px; border: 2px solid #333; border-radius: 6px; padding: 8px; background: #fff; }
    .ps .sp-cell { height: 44px; border: 1px solid #333; border-radius: 4px; background: #fff; color: #111; padding: 2px 4px; font-weight: 400; font-size: 12px;
        flex-direction: column; align-items: flex-start; justify-content: flex-start; position: relative; opacity: 1; }
    .ps .sp-cell b { font-size: 13px; }
    .ps .sp-cell small { font-size: 9px; line-height: 1.1; overflow: hidden; max-height: 20px; max-width: 100%; background: rgba(255, 255, 255, .85); padding: 0 2px; text-align: left; }
    .ps .sp-aisle { width: 16px; border: 0; }
    .ps .sp-driver { border-radius: 22px; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; background: #fff; color: #111; }
    .ps .sp-empty { border-color: transparent; background: none; }
    /* The back row spans the whole bus, aisle included, like in the real bus. */
    .ps .sp-row-back { contain: inline-size; width: 100%; }
    .ps .sp-row-back .sp-cell { flex: 1 1 0; width: auto; min-width: 0; }
    .ps .sp-door { border-style: dashed; align-items: center; justify-content: center; font-size: 9px; background: #fff; color: #111; }
    .ps .sp-cabin { background: none; color: #111; padding: 0 2px; }
    .ps .sp-cabin b { color: #111; }
    .ps .sold, .ps-legend .sold { background: repeating-linear-gradient(135deg, #fff 0 6px, #d1d5db 6px 7px); }
    .ps .sp-cell.sold::after { content: "\2715"; position: absolute; top: 1px; right: 4px; font-size: 14px; font-weight: 800; }
    .ps .kept, .ps-legend .kept { background: #e5e7eb; }
    .ps-held { margin-top: 8px; border: 2px dashed #9a4a06; border-radius: 6px; padding: 6px 10px; }
    .ps-foot { margin-top: 8px; font-size: 11px; color: #333; }
    #psPrint { display: none; }
    /* The tickets as they will print, checked before the sale goes through. */
    .cf-ticket { border: 2px dashed #1d2d2b; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; background: #fff; }
    .cf-ticket .ag { font-weight: 800; text-align: center; }
    .cf-ticket .rt { text-align: center; font-weight: 700; color: #333; }
    .cf-ticket .st { text-align: center; font-size: 22px; font-weight: 900; margin: 2px 0 6px; }
    .cf-ticket table { width: 100%; font-size: 13px; }
    .cf-ticket td { padding: 2px 0; vertical-align: top; }
    .cf-ticket td:first-child { color: #555; width: 42%; }
    .cf-ticket td:last-child { font-weight: 700; }
    .cf-ticket .why { font-size: 11.5px; color: #555; margin-top: 4px; }
    .cf-sum { display: flex; justify-content: space-between; align-items: baseline; border-top: 1px solid var(--line); padding-top: 10px; margin-top: 4px; }
    .cf-tick { display: flex; gap: 10px; align-items: center; padding: 10px; border-radius: 8px; margin-top: 10px; cursor: pointer; font-weight: 700; font-size: 14px; }
    .cf-tick input { width: 1.5em; height: 1.5em; margin: 0; flex: none; }
    .cf-tick.money { background: #ecfdf3; border: 2px solid #16a34a; }
    .cf-tick.paper { background: #fffaf0; border: 2px solid #f59e0b; }
    @media print {
        body.ps-printing > *:not(#psPrint) { display: none !important; }
        body.ps-printing #psPrint { display: block; padding: 0; }
        body.ps-printing { background: #fff !important; padding: 0 !important; }
        #psPrint .ps-scroll { overflow: visible; }
        #psPrint .sp { --sp-w: 76px; }
        #psPrint .sp-cell { height: 48px; }
        #psPrint .ps-head { font-size: 18px !important; }
        @page { margin: 8mm; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endpush

@section('content')
@if($departures->isEmpty())
    <div class="b-card empty p-4 text-center">
        <h1 class="fs-5 fw-bold">{{ translate('Quick sell') }}</h1>
        <p class="b-hint mb-0">{{ translate('No departure with a bus in the coming days. Put a bus on a departure first, in Departures.') }}</p>
    </div>
@else
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="fs-5 fw-bold mb-0 me-2">{{ translate('Quick sell') }}</h1>
    <label class="visually-hidden" for="depPick">{{ translate('Departure') }}</label>
    <select id="depPick" class="form-select" style="max-width:520px">
        @foreach($departures->groupBy(fn($d) => $d->date->toDateString()) as $date => $list)
            <optgroup label="{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F') }}">
                @foreach($list as $d)<option value="{{ $d->id }}" @selected($current && $current->id === $d->id)>{{ $label($d) }}</option>@endforeach
            </optgroup>
        @endforeach
        @if($left->isNotEmpty())
            <optgroup label="{{ translate('Left already: type in paper sales') }}">
                @foreach($left as $d)<option value="{{ $d->id }}" @selected($current && $current->id === $d->id)>{{ $label($d) }}</option>@endforeach
            </optgroup>
        @endif
    </select>
    <span class="b-tag bad" id="lateTag" hidden>{{ translate('This bus has left: you are typing in sales made on paper') }}</span>
    <span class="b-tag warn" id="onlineClosed" hidden>{{ translate('Closed in the app: counter only now') }}</span>
</div>

<div class="s-grid">
    <section class="b-card p-3" aria-labelledby="mapTitle">
        <h2 class="b-h mb-2" id="mapTitle">{{ translate('Tap the seats') }}</h2>
        <div class="sp-legend mb-2">
            <span><i style="background:#22c55e"></i>{{ translate('Free') }}</span>
            <span><i style="background:#22c55e;box-shadow:inset 0 0 0 3px #1d2d2b"></i>{{ translate('Kept for the counter') }}</span>
            <span><i style="background:#f59e0b"></i>{{ translate('Held (app request)') }}</span>
            <span><i style="background:#2563eb"></i>{{ translate('Sold in the app') }}</span>
            <span><i style="background:#1d2d2b"></i>{{ translate('Sold at the counter') }}</span>
            <span><i style="background:#9ca3af"></i>{{ translate('On board') }}</span>
        </div>
        <div id="map" class="d-flex flex-column align-items-center gap-2"></div>
        <div class="b-hint mt-2" id="mapNote" aria-live="polite"></div>
    </section>

    <section class="b-card p-3" aria-labelledby="saleTitle">
        <div id="saleForm">
            <h2 class="b-h mb-1" id="saleTitle">{{ translate('Passengers') }}</h2>
            <div class="b-hint mb-2" id="pickHint">{{ translate('Choose one or more seats on the plan.') }}</div>
            <div class="form-check form-switch mb-1">
                <input class="form-check-input" type="checkbox" role="switch" id="payerOnly">
                <label class="form-check-label small fw-semibold" for="payerOnly">{{ translate('Take only the details of the person paying') }}</label>
                <div class="payer-why">{{ translate('All the tickets carry their name, and say the passenger names were not taken.') }}</div>
            </div>
            <div id="paxList"></div>
            <div class="row g-2 mt-1">
                <div class="col-12"><label class="form-label small fw-semibold" for="bookerId">{{ translate('ID number of the person paying (optional)') }}</label><input id="bookerId" class="form-control text-uppercase" maxlength="40" autocomplete="off"></div>
                <div class="col-sm-7"><label class="form-label small fw-semibold" for="drop">{{ translate('Gets off at') }}</label><select id="drop" class="form-select"></select></div>
                <div class="col-sm-5"><label class="form-label small fw-semibold" for="method">{{ translate('Paid by') }}</label>
                    <select id="method" class="form-select">
                        <option value="cash" selected>{{ translate('Cash') }}</option>
                        <option value="momo">MTN Mobile Money</option>
                        <option value="orange">Orange Money</option>
                    </select></div>
            </div>
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" role="switch" id="paperSale">
                <label class="form-check-label small fw-semibold" for="paperSale">{{ translate('Typing in a sale made on paper') }}</label>
            </div>
            <div class="d-flex align-items-center gap-3 mt-3">
                <div class="flex-grow-1"><div class="b-hint">{{ translate('Total') }}</div><div class="total" id="total">0</div></div>
                <button class="btn btn-primary btn-lg" id="sellBtn" disabled>{{ translate('Sell') }}</button>
            </div>
        </div>
        <div id="saleDone" class="done" hidden>
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <h2 class="fs-5 fw-bold mt-2" id="doneText"></h2>
            <div class="b-hint mb-3" id="doneCode"></div>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-2"><a class="btn btn-dark" id="printBtn" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ translate('Print the tickets') }}</a><button class="btn btn-primary" id="nextBtn">{{ translate('Next customer') }}</button></div>
        </div>
    </section>
</div>

<section class="b-card p-3 mt-3" aria-labelledby="psTitle">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <h2 class="b-h mb-0 flex-grow-1" id="psTitle"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> {{ translate('Paper sheet of this bus') }}</h2>
        <a class="btn btn-outline-dark btn-sm" id="psCheck" href="#"><i class="bi bi-list-check"></i> {{ translate('Check it') }}</a>
        <button type="button" class="btn btn-dark btn-sm" id="psPrintBtn"><i class="bi bi-printer"></i> {{ translate('Print the sheet') }}</button>
    </div>
    <div class="b-hint mb-2">{{ translate('What a new sheet looks like now. Printing starts a fresh sheet: E-Ride counts the sold seats as pencilled.') }} <span id="psChecked"></span></div>
    <div id="psSheet" class="ps"></div>
</section>

<div class="modal fade" id="cfModal" tabindex="-1" aria-labelledby="cfTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5 fw-bold" id="cfTitle">{{ translate('Check the tickets before you sell') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
            </div>
            <div class="modal-body">
                <div id="cfTickets"></div>
                <div class="b-hint">{{ translate('The ticket code and QR are added when you confirm.') }}</div>
                <div class="cf-sum"><span class="fw-semibold" id="cfCount"></span><span class="total" id="cfTotal"></span></div>
                <label class="cf-tick money" for="cfMoney">
                    <input class="form-check-input" type="checkbox" id="cfMoney">
                    <span><i class="bi bi-cash-coin" aria-hidden="true"></i> <span id="cfMoneyText"></span></span>
                </label>
                @if($paper)
                    <label class="cf-tick paper" for="paperOk">
                        <input class="form-check-input" type="checkbox" id="paperOk">
                        <span><i class="bi bi-pencil-fill" aria-hidden="true"></i> <span id="cfPaperText"></span></span>
                    </label>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ translate('Change something') }}</button>
                <button type="button" class="btn btn-primary btn-lg" id="cfSell" disabled>{{ translate('Confirm the sale') }}</button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('script')
@if($departures->isNotEmpty())
<script>
(function () {
    var T = {
        name: @json(translate('Full name')), phone: @json(translate('Phone')), id: @json(translate('ID number')), bags: @json(translate('Bags')),
        seat: @json(translate('Seat')), held: @json(translate('Held for an app request')), sold: @json(translate('Sold')),
        gone: @json(translate(key: 'Seat {seat} was just taken. Choose another one.', replace: ['seat' => '{seat}'])), pick: @json(translate('Choose one or more seats on the plan.')),
        n: @json(translate(key: '{n} seat(s)', replace: ['n' => '{n}'])), closedDep: @json(translate('This departure has left or was cancelled.')), board: @json(translate('On board')),
        payer: @json(translate('Person paying')), payerName: @json(translate('Name of the person paying')), inPayer: @json(translate('In the name of the person paying')),
        sheet: @json(translate('Seat sheet')), bus: @json(translate('Bus')), printedAt: @json(translate('Printed')), checkedAt: @json(translate('Last checked')),
        rulesTxt: @json(translate('Mark in pencil. Erase when E-Ride frees a seat. Check this sheet against E-Ride before selling on paper.')),
        lSold: @json(translate('Sold in E-Ride when printed')), lKept: @json(translate('Kept for the counter')), lFree: @json(translate('Free when printed')),
        pencilNow: @json(translate('Pencil these seats now')), heldWhy: @json(translate('Accepted, waiting for payment. Erase them if E-Ride frees them.')),
        foot: @json(translate('Paper sales: write the name and phone in the seat box. Type them into E-Ride as soon as it is back (Quick sell).')),
    };
    T.leaves = @json(translate('Leaves')); T.getsOff = @json(translate('Gets off at')); T.fareL = @json(translate('Fare')); T.passenger = @json(translate('Passenger'));
    T.paidBy = @json(translate('Paid by')); T.phoneL = @json(translate('Phone'));
    T.payerWhy = @json(translate(key: 'Name of the person who paid for {n} seat(s). The passenger names were not taken.', replace: ['n' => '{n}']));
    T.tickets = @json(translate(key: '{n} ticket(s)', replace: ['n' => '{n}']));
    T.money = @json(translate(key: 'I collected {amount} ({method}) and everything is in order', replace: ['amount' => '{amount}', 'method' => '{method}']));
    T.pencil = @json(translate(key: 'Seats {seats} are pencilled on the paper sheet', replace: ['seats' => '{seats}']));
    var printedUrl = @json(url('partner/bus-agency/paper/__/printed')), checkUrl = @json(url('partner/bus-agency/paper/__'));
    var payerOnly = false, payer = {};
    var seatsUrl = @json(url('partner/bus-agency/sell/__/seats')), sellUrl = @json(url('partner/bus-agency/sell/__'));
    var rules = @json(\Modules\BusManagement\Lib\BusSettings::rules($busAgency->rules));
    var dep = document.getElementById('depPick').value, state = null, picked = [], pax = {}, timer = null;
    var money = function (n) { return Math.round(n).toLocaleString() + ' FCFA'; };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

    function fare() {
        var sel = document.getElementById('drop'), p = state && state.drop_points.find(function (x) { return x.id === sel.value; });
        return p ? p.fare : 0;
    }
    function total() {
        var f = fare();
        return picked.reduce(function (sum, s) { var b = +(pax[s] && pax[s].bags != null ? pax[s].bags : rules.free_bags); return sum + f + Math.max(0, b - rules.free_bags) * rules.extra_bag_fee; }, 0);
    }
    function drawMap() {
        var kept = {}; state.counter_kept.forEach(function (s) { kept[s] = true; });
        busSeatPlanRender(document.getElementById('map'), state.plan, {seat: function (c, el) {
            var t = state.taken[c.n];
            if (t) {
                el.classList.add('st-' + t.state);
                el.disabled = true;
                el.title = t.state === 'held' ? T.held : (t.state === 'boarded' ? T.board : T.sold) + ': ' + t.name;
                el.setAttribute('aria-label', T.seat + ' ' + c.n + ', ' + el.title);
            } else {
                if (kept[c.n]) el.classList.add('st-kept');
                if (picked.indexOf(c.n) >= 0) { el.classList.add('st-picked'); el.setAttribute('aria-pressed', 'true'); } else el.setAttribute('aria-pressed', 'false');
                el.disabled = !state.open;
                el.addEventListener('click', function () { toggle(c.n); });
            }
        }});
    }
    function drawPax() {
        var box = document.getElementById('paxList');
        document.getElementById('pickHint').textContent = picked.length ? T.n.replace('{n}', picked.length) : T.pick;
        if (payerOnly) {
            box.innerHTML = !picked.length ? '' : '<div class="payer"><div class="small fw-bold mb-1">' + T.payer + '</div><div class="row g-2">' +
                '<div class="col-sm-6"><input class="form-control" data-p="name" required maxlength="120" placeholder="' + T.payerName + '" aria-label="' + T.payerName + '" value="' + esc(payer.name) + '"></div>' +
                '<div class="col-sm-6"><input class="form-control" data-p="phone" inputmode="tel" maxlength="20" placeholder="' + T.phone + '" aria-label="' + T.phone + '" value="' + esc(payer.phone) + '"></div></div></div>' +
                picked.map(function (s) {
                    var p = pax[s] || {};
                    return '<div class="pax d-flex gap-2 align-items-center" data-seat="' + esc(s) + '"><div class="pax-seat" aria-label="' + T.seat + ' ' + esc(s) + '">' + esc(s) + '</div>' +
                        '<div class="flex-grow-1 small text-truncate" data-payer-name>' + esc(payer.name || T.inPayer) + '</div>' +
                        '<input class="form-control" style="width:80px" data-f="bags" type="number" min="0" max="9" aria-label="' + T.bags + ', ' + T.seat + ' ' + esc(s) + '" title="' + T.bags + '" value="' + esc(p.bags != null ? p.bags : rules.free_bags) + '"></div>';
                }).join('');
            refresh();
            return;
        }
        box.innerHTML = picked.map(function (s, i) {
            var p = pax[s] || {};
            return '<div class="pax d-flex gap-2 align-items-start" data-seat="' + esc(s) + '"><div class="pax-seat" aria-label="' + T.seat + ' ' + esc(s) + '">' + esc(s) + '</div><div class="row g-2 flex-grow-1">' +
                '<div class="col-sm-6"><input class="form-control" data-f="name" required maxlength="120" placeholder="' + T.name + '" aria-label="' + T.name + ', ' + T.seat + ' ' + esc(s) + '" value="' + esc(p.name) + '"></div>' +
                '<div class="col-sm-6"><input class="form-control" data-f="phone" inputmode="tel" maxlength="20" placeholder="' + T.phone + '" aria-label="' + T.phone + '" value="' + esc(p.phone) + '"></div>' +
                '<div class="col-4"><label class="visually-hidden">' + T.bags + '</label><input class="form-control" data-f="bags" type="number" min="0" max="9" aria-label="' + T.bags + '" title="' + T.bags + '" value="' + esc(p.bags != null ? p.bags : rules.free_bags) + '"></div>' +
                '</div></div>';
        }).join('');
        var first = box.querySelector('[data-f=name]');
        if (first && !first.value && document.activeElement && document.activeElement.classList.contains('sp-seat')) { /* keep tapping seats */ }
        refresh();
    }
    function refresh() {
        document.getElementById('total').textContent = money(total());
        var named = payerOnly ? (payer.name || '').trim().length >= 2 : picked.every(function (s) { return pax[s] && (pax[s].name || '').trim().length >= 2; });
        var ready = picked.length && named && fare() > 0;
        document.getElementById('sellBtn').disabled = !ready;
    }
    function toggle(n) {
        var i = picked.indexOf(n);
        if (i >= 0) { picked.splice(i, 1); delete pax[n]; } else { picked.push(n); pax[n] = pax[n] || {}; }
        picked.sort(function (a, b) { return a - b; });
        drawMap(); drawPax();
    }
    function load(first) {
        return fetch(seatsUrl.replace('__', dep), {headers: {'Accept': 'application/json'}}).then(function (r) { return r.json(); }).then(function (d) {
            state = d;
            document.getElementById('onlineClosed').hidden = !d.online_closed || !d.open || d.late;
            document.getElementById('lateTag').hidden = !d.late;
            var ps = document.getElementById('paperSale');
            if (d.late) { ps.checked = true; ps.disabled = true; } else if (ps.disabled) { ps.disabled = false; ps.checked = false; }
            document.getElementById('mapNote').textContent = d.open ? '' : T.closedDep;
            if (first) {
                document.getElementById('drop').innerHTML = d.drop_points.map(function (p) { return '<option value="' + p.id + '">' + esc(p.name) + ' · ' + money(p.fare) + '</option>'; }).join('');
                var last = d.drop_points.length ? d.drop_points[d.drop_points.length - 1].id : null;
                if (last) document.getElementById('drop').value = last;
            }
            // Seats someone else took meanwhile drop out of the sale.
            picked.slice().forEach(function (s) {
                if (d.taken[s]) { picked.splice(picked.indexOf(s), 1); delete pax[s]; busToast(T.gone.replace('{seat}', s)); drawPax(); }
            });
            drawMap(); refresh(); drawSheet();
        });
    }

    // ---- The paper sheet of this bus (the same as Paper sheets > Print), drawn from the live seats.
    function stamp() {
        var d = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; };
        return p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    }
    function sheetHtml(withPrinted) {
        var sh = state.sheet || {}, held = [];
        Object.keys(state.taken).forEach(function (n) { if (state.taken[n].state === 'held') held.push(n); });
        held.sort(function (a, b) { return a - b; });
        return '<div class="ps-head fw-bold" style="font-size:15px">' + esc(sh.agency) + ' · ' + T.sheet + '</div>' +
            '<div class="ps-meta"><b>' + esc(sh.route) + '</b><span>' + esc(sh.when) + '</span><span>' + esc(sh.class) + ' · ' + T.bus + ': <b>' + esc(sh.bus) + '</b></span>' +
            (withPrinted ? '<span>' + T.printedAt + ': ' + stamp() + '</span>' : '') + '</div>' +
            '<div class="ps-rules">' + T.rulesTxt + '</div>' +
            '<div class="ps-legend"><span><i class="sold"></i>' + T.lSold + '</span><span><i class="kept"></i>' + T.lKept + '</span><span><i></i>' + T.lFree + '</span></div>' +
            '<div class="ps-scroll"><div data-ps-map></div></div>' +
            (held.length ? '<div class="ps-held"><b>' + T.pencilNow + ':</b> ' + held.map(esc).join(', ') + ' · ' + T.heldWhy + '</div>' : '') +
            '<div class="ps-foot">' + T.foot + '</div>';
    }
    function fillSheet(root) {
        var sold = {}, kept = {};
        Object.keys(state.taken).forEach(function (n) { var t = state.taken[n]; if (t.state !== 'held') sold[n] = t.name || ''; });
        state.counter_kept.forEach(function (s) { kept[s] = true; });
        busSeatPlanRender(root.querySelector('[data-ps-map]'), state.plan, {seat: function (c, el) {
            el.innerHTML = '<b>' + esc(c.n) + '</b>' + (sold[c.n] !== undefined ? '<small>' + esc(sold[c.n]) + '</small>' : '');
            el.tabIndex = -1; el.disabled = true;
            if (sold[c.n] !== undefined) el.classList.add('sold'); else if (kept[c.n]) el.classList.add('kept');
        }});
    }
    var sheetSig = null;
    function drawSheet() {
        var box = document.getElementById('psSheet');
        if (!state || !state.plan || !state.plan.decks) { box.innerHTML = ''; return; }
        var sig = dep + JSON.stringify(state.taken) + JSON.stringify(state.counter_kept) + JSON.stringify(state.sheet);
        if (sig === sheetSig) return;
        sheetSig = sig;
        box.innerHTML = sheetHtml(false);
        fillSheet(box);
        document.getElementById('psCheck').href = checkUrl.replace('__', dep);
        document.getElementById('psChecked').textContent = state.sheet && state.sheet.checked ? T.checkedAt + ': ' + state.sheet.checked : '';
    }
    function printSheet() {
        var btn = document.getElementById('psPrintBtn'); btn.disabled = true;
        busPost(printedUrl.replace('__', dep), {}).then(function () { return load(false); }).then(function () {
            var root = document.getElementById('psPrint');
            if (!root) { root = document.createElement('div'); root.id = 'psPrint'; root.className = 'ps'; document.body.appendChild(root); }
            root.innerHTML = sheetHtml(true);
            fillSheet(root);
            document.body.classList.add('ps-printing');
            window.print();
            if (window.busPaperRefresh) window.busPaperRefresh();
        }).finally(function () { btn.disabled = false; });
    }
    window.addEventListener('afterprint', function () { document.body.classList.remove('ps-printing'); });
    document.getElementById('psPrintBtn').addEventListener('click', printSheet);
    function poll() { clearTimeout(timer); timer = setTimeout(function () { load(false).finally(poll); }, 5000); }

    document.getElementById('payerOnly').addEventListener('change', function () { payerOnly = this.checked; drawPax(); });
    document.getElementById('paxList').addEventListener('input', function (e) {
        if (e.target.dataset.p) {
            payer[e.target.dataset.p] = e.target.value;
            document.querySelectorAll('#paxList [data-payer-name]').forEach(function (el) { el.textContent = payer.name || T.inPayer; });
            refresh(); return;
        }
        var row = e.target.closest('[data-seat]'); if (!row) return;
        pax[row.dataset.seat] = pax[row.dataset.seat] || {};
        pax[row.dataset.seat][e.target.dataset.f] = e.target.value;
        refresh();
    });
    document.getElementById('drop').addEventListener('change', refresh);
    document.getElementById('depPick').addEventListener('change', function () {
        dep = this.value; picked = []; pax = {}; drawPax();
        history.replaceState(null, '', '?departure=' + dep);
        load(true).then(poll);
    });
    var cfModal = new bootstrap.Modal(document.getElementById('cfModal')), paperBox = document.getElementById('paperOk');
    function cfReady() {
        document.getElementById('cfSell').disabled = !(document.getElementById('cfMoney').checked && (!paperBox || paperBox.checked));
    }
    document.getElementById('cfMoney').addEventListener('change', cfReady);
    if (paperBox) paperBox.addEventListener('change', cfReady);
    document.getElementById('sellBtn').addEventListener('click', function () {
        var sh = state.sheet || {}, f = fare(), dropSel = document.getElementById('drop'), methodSel = document.getElementById('method');
        var dropName = (state.drop_points.find(function (x) { return x.id === dropSel.value; }) || {}).name || '';
        var methodName = methodSel.options[methodSel.selectedIndex].text;
        document.getElementById('cfTickets').innerHTML = picked.map(function (s) {
            var p = payerOnly ? payer : (pax[s] || {});
            var bags = +(pax[s] && pax[s].bags != null && pax[s].bags !== '' ? pax[s].bags : rules.free_bags);
            var seatFare = f + Math.max(0, bags - rules.free_bags) * rules.extra_bag_fee;
            return '<div class="cf-ticket"><div class="ag">' + esc(sh.agency) + '</div><div class="rt">' + esc(sh.route) + '</div>' +
                '<div class="st">' + T.seat + ' ' + esc(s) + '</div><table>' +
                '<tr><td>' + (payerOnly ? T.paidBy : T.passenger) + '</td><td>' + esc(p.name) + '</td></tr>' +
                (p.phone ? '<tr><td>' + T.phoneL + '</td><td>' + esc(p.phone) + '</td></tr>' : '') +
                '<tr><td>' + T.leaves + '</td><td>' + esc(sh.when) + '</td></tr>' +
                '<tr><td>' + T.getsOff + '</td><td>' + esc(dropName) + '</td></tr>' +
                '<tr><td>' + T.bus + '</td><td>' + esc(sh.class) + ' · ' + esc(sh.bus) + '</td></tr>' +
                '<tr><td>' + T.bags + '</td><td>' + bags + '</td></tr>' +
                '<tr><td>' + T.fareL + '</td><td>' + money(seatFare) + '</td></tr></table>' +
                (payerOnly ? '<div class="why">' + esc(T.payerWhy.replace('{n}', picked.length)) + '</div>' : '') + '</div>';
        }).join('');
        document.getElementById('cfCount').textContent = T.tickets.replace('{n}', picked.length);
        document.getElementById('cfTotal').textContent = money(total());
        document.getElementById('cfMoneyText').textContent = T.money.replace('{amount}', money(total())).replace('{method}', methodName);
        if (paperBox) { document.getElementById('cfPaperText').textContent = T.pencil.replace('{seats}', picked.join(', ')); paperBox.checked = false; }
        document.getElementById('cfMoney').checked = false;
        cfReady();
        cfModal.show();
    });
    document.getElementById('cfSell').addEventListener('click', function () {
        var btn = this; btn.disabled = true;
        busPost(sellUrl.replace('__', dep), {
            seats: picked, drop_point_id: document.getElementById('drop').value,
            method: document.getElementById('method').value,
            collected: document.getElementById('cfMoney').checked,
            booker_id_number: document.getElementById('bookerId').value.trim() || null,
            paper: !!(paperBox && paperBox.checked),
            paper_sale: document.getElementById('paperSale').checked,
            payer_only: payerOnly,
            passengers: picked.map(function (s) {
                var bags = pax[s].bags != null && pax[s].bags !== '' ? +pax[s].bags : null;
                return payerOnly ? {name: payer.name, phone: payer.phone || null, bags: bags} : {name: pax[s].name, phone: pax[s].phone || null, bags: bags};
            }),
        }).then(function (d) {
            if (!d.ok) { busToast(d.message); load(false); btn.disabled = false; return; }
            cfModal.hide();
            document.getElementById('saleForm').hidden = true;
            document.getElementById('saleDone').hidden = false;
            document.getElementById('doneText').textContent = d.message;
            document.getElementById('doneCode').textContent = d.code;
            document.getElementById('printBtn').href = d.print;
            picked = []; pax = {}; payer = {}; drawPax(); load(false); document.getElementById('bookerId').value = '';
            if (paperBox) paperBox.checked = false;
            document.getElementById('cfMoney').checked = false;
            document.getElementById('method').value = 'cash';
            if (!document.getElementById('paperSale').disabled) document.getElementById('paperSale').checked = false;
            document.getElementById('printBtn').focus();
        }).catch(function () { btn.disabled = false; });
    });
    document.getElementById('nextBtn').addEventListener('click', function () {
        document.getElementById('saleDone').hidden = true;
        document.getElementById('saleForm').hidden = false;
        document.querySelector('#map .sp-seat:not(:disabled)')?.focus();
    });
    load(true).then(poll);
})();
</script>
@endif
@endpush
