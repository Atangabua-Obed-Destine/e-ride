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
    .done .bi { font-size: 44px; color: var(--ok); }
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
    </select>
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
            <div id="paxList"></div>
            <div class="row g-2 mt-1">
                <div class="col-12"><label class="form-label small fw-semibold" for="bookerId">{{ translate('ID number of the person paying (optional)') }}</label><input id="bookerId" class="form-control text-uppercase" maxlength="40" autocomplete="off"></div>
                <div class="col-sm-7"><label class="form-label small fw-semibold" for="drop">{{ translate('Gets off at') }}</label><select id="drop" class="form-select"></select></div>
                <div class="col-sm-5"><span class="form-label small fw-semibold d-block">{{ translate('Paid by') }}</span>
                    @php($counterCash = \Modules\TripManagement\Lib\PaymentWays::cash())
                    <div class="seg">@if($counterCash)<input type="radio" name="method" id="m_cash" value="cash" checked><label for="m_cash">{{ translate('Cash') }}</label>@endif<input type="radio" name="method" id="m_momo" value="momo" {{ $counterCash ? '' : 'checked' }}><label for="m_momo">MoMo</label><input type="radio" name="method" id="m_orange" value="orange"><label for="m_orange">Orange</label></div></div>
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
            <div class="d-flex justify-content-center gap-2"><a class="btn btn-dark" id="printBtn" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ translate('Print the tickets') }}</a><button class="btn btn-primary" id="nextBtn">{{ translate('Next customer') }}</button></div>
        </div>
    </section>
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
    };
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
        var ready = picked.length && picked.every(function (s) { return pax[s] && (pax[s].name || '').trim().length >= 2; }) && fare() > 0;
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
            document.getElementById('onlineClosed').hidden = !d.online_closed || !d.open;
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
            drawMap(); refresh();
        });
    }
    function poll() { clearTimeout(timer); timer = setTimeout(function () { load(false).finally(poll); }, 5000); }

    document.getElementById('paxList').addEventListener('input', function (e) {
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
    document.getElementById('sellBtn').addEventListener('click', function () {
        var btn = this; btn.disabled = true;
        busPost(sellUrl.replace('__', dep), {
            seats: picked, drop_point_id: document.getElementById('drop').value,
            method: document.querySelector('input[name=method]:checked').value,
            booker_id_number: document.getElementById('bookerId').value.trim() || null,
            passengers: picked.map(function (s) { return {name: pax[s].name, phone: pax[s].phone || null, bags: pax[s].bags != null && pax[s].bags !== '' ? +pax[s].bags : null}; }),
        }).then(function (d) {
            if (!d.ok) { busToast(d.message); load(false); btn.disabled = false; return; }
            document.getElementById('saleForm').hidden = true;
            document.getElementById('saleDone').hidden = false;
            document.getElementById('doneText').textContent = d.message;
            document.getElementById('doneCode').textContent = d.code;
            document.getElementById('printBtn').href = d.print;
            picked = []; pax = {}; drawPax(); load(false); document.getElementById('bookerId').value = '';
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
