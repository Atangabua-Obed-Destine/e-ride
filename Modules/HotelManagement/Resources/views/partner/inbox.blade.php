@extends('hotelmanagement::partner.layout')
@section('title', translate('Inbox'))

@php
    use Modules\HotelManagement\Entities\HotelInquiry;
    $statusTag = [
        'open' => [translate('New, not answered'), 'bad'],
        'answered' => [translate('Answered'), 'mute'],
        'kept' => [translate('Room kept'), 'warn'],
        'arrived' => [translate('Checked in'), 'blue'],
        'closed' => [translate('Closed'), 'mute'],
    ];
@endphp

@push('head')
<style>
    .ib-list { max-height: 78vh; overflow-y: auto; }
    .ib-item { display: block; padding: 10px; border-radius: 10px; color: var(--ink); text-decoration: none; }
    .ib-item:hover { background: #f4f6f6; color: var(--ink); }
    .ib-item.is-on { background: #fdecec; }
    .ib-dot { width: 8px; height: 8px; border-radius: 4px; background: var(--red); display: inline-block; }
    .ib-chat { background: #f7f8f8; min-height: 380px; max-height: 60vh; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
    .ib-msg { max-width: 78%; border-radius: 14px; padding: 9px 12px; background: #fff; border: 1px solid var(--line); white-space: pre-wrap; word-wrap: break-word; }
    .ib-msg.me { align-self: flex-end; background: var(--red); color: #fff; border-color: var(--red); }
    .ib-msg small { display: block; font-size: 11px; opacity: .75; margin-top: 2px; }
    .ib-card { max-width: 85%; border-radius: 14px; padding: 12px; background: #fff; border: 1.5px solid var(--line); }
    .ib-card.req { border-color: #1d4ed8; }
    .ib-card.kept { border-color: #e8b562; background: #fff8ec; }
    .ib-card .k { font-size: 11px; font-weight: 800; letter-spacing: .3px; }
    .ib-sys { align-self: center; font-size: 12px; color: var(--muted); background: #e9edec; border-radius: 10px; padding: 4px 10px; }
</style>
@endpush

@section('content')
<div class="row g-3">
    <div class="col-lg-3">
        <div class="h-card p-2">
            <div class="p-2">
                <h1 class="h-h">{{ translate('Inbox') }}</h1>
                @php $mins = \Modules\HotelManagement\Service\InquiryService::answerMinutes($hotel); @endphp
                <div class="h-hint">@if($mins){{ translate(key: 'Your first answer comes in about {n} min on average. Guests see it.', replace: ['n' => $mins]) }}@else{{ translate('Answer fast: guests see how long you take.') }}@endif</div>
            </div>
            <div class="ib-list">
                @forelse($list as $q)
                    <a href="{{ route('hotel-partner.inbox', ['q' => $q->id]) }}" class="ib-item {{ $current && $current->id === $q->id ? 'is-on' : '' }}">
                        <span class="d-flex align-items-center gap-2">
                            @if($q->unread_hotel > 0)<span class="ib-dot" aria-label="{{ translate('Unread') }}"></span>@endif
                            <span class="fw-bold flex-grow-1 text-truncate">{{ $q->customer?->first_name }} {{ \Illuminate\Support\Str::substr((string)$q->customer?->last_name, 0, 1) }}.</span>
                            <span class="small text-muted">{{ $q->last_message_at?->diffForHumans(null, true) }}</span>
                        </span>
                        <span class="d-block small text-truncate">{{ $q->roomType?->name }} · {{ $q->check_in->translatedFormat('D j') }} → {{ $q->check_out->translatedFormat('D j') }} · {{ translate(key: '{n} guest(s)', replace: ['n' => $q->guests]) }}</span>
                        <span class="h-tag {{ $statusTag[$q->status][1] }} mt-1">{{ $statusTag[$q->status][0] }}</span>
                    </a>
                @empty
                    <div class="p-3 h-hint">{{ translate('No questions yet. When a guest asks about a room in the app, it rings here.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    @if($current)
    <div class="col-lg-6">
        <div class="h-card d-flex flex-column">
            <div class="p-3 border-bottom d-flex align-items-center gap-2 flex-wrap">
                <div class="flex-grow-1">
                    <div class="fw-bold" id="gName"></div>
                    <div class="h-hint" id="gSub"></div>
                </div>
                <a class="btn btn-soft" id="gCall" href="#"><i class="bi bi-telephone"></i> {{ translate('Call') }}</a>
            </div>
            <div class="ib-chat" id="chat" aria-live="polite"></div>
            <div class="p-3 border-top" id="composer">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <button type="button" class="btn btn-soft btn-sm" data-quick="{{ translate('Yes, it is available for your dates.') }}">{{ translate('Yes, available') }}</button>
                    <button type="button" class="btn btn-soft btn-sm" data-quick="{{ translate('Please call us at the reception.') }}">{{ translate('Please call us') }}</button>
                    <button type="button" class="btn btn-soft btn-sm" data-quick="{{ translate('Breakfast is included.') }}">{{ translate('Breakfast included') }}</button>
                </div>
                <form id="sendForm" class="d-flex gap-2">
                    <label for="body" class="visually-hidden">{{ translate('Message') }}</label>
                    <textarea id="body" rows="1" maxlength="1000" class="form-control" placeholder="{{ translate('Write to the guest') }}" style="resize:none"></textarea>
                    <button class="btn btn-primary px-4">{{ translate('Send') }}</button>
                </form>
            </div>
            <div class="p-3 border-top h-hint d-none" id="closedNote"></div>
        </div>
    </div>

    <div class="col-lg-3" id="actions">
        <div class="h-card p-3 mb-3">
            <h2 class="h-h mb-2">{{ translate('Send the price') }}</h2>
            <label for="amount" class="small text-muted" id="amountLabel"></label>
            <input id="amount" inputmode="numeric" class="form-control mb-1" placeholder="FCFA">
            <div class="h-hint mb-2">{{ translate('The total for the dates the guest asked for. Paid at the hotel.') }}</div>
            <button type="button" class="btn btn-soft w-100" id="priceBtn" style="border:1.5px solid var(--red);color:#b91c1c;background:#fff">{{ translate('Send price card') }}</button>
        </div>

        @if($hotel->usesPos())
        <div class="h-card p-3 mb-3" style="border:2px solid #e8b562">
            <h2 class="h-h mb-1">{{ translate('Keep or book in POS Innova') }}</h2>
            <div class="h-hint">{{ translate('This request is on the POS hotel home. Keep or book the room there: the guest is told at once.') }}</div>
            @foreach($current->stays()->whereNotNull('pos_ref')->whereIn('status', \Modules\HotelManagement\Entities\HotelStay::HOLDING)->get() as $ps)
                <div class="small mt-2"><span class="h-tag {{ $ps->status === 'kept' ? 'warn' : ($ps->status === 'booked' ? 'blue' : 'ok') }}">{{ $ps->status === 'kept' ? translate('Kept') : ($ps->status === 'booked' ? translate('Booked') : translate('In the house')) }}</span>
                    POS {{ $ps->pos_ref }}@if($ps->status === 'kept' && $ps->kept_until) · {{ translate(key: 'until {time}', replace: ['time' => $ps->kept_until->format('d/m H:i')]) }}@endif</div>
            @endforeach
        </div>
        @endif
        <div class="h-card p-3 mb-3" style="border:2px solid #e8b562" @if($hotel->usesPos()) hidden @endif>
            <h2 class="h-h mb-2" id="keepTitle">{{ translate('Keep a room') }}</h2>
            <div id="keptNow" class="mb-2"></div>
            <label for="kType" class="small text-muted">{{ translate('Room type') }}</label>
            <select id="kType" class="form-select mb-2">
                @foreach($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
            </select>
            <label for="kRoom" class="small text-muted">{{ translate('Room') }}</label>
            <select id="kRoom" class="form-select mb-2"></select>
            <div class="row g-2 mb-1">
                <div class="col-7"><label for="kDate" class="small text-muted">{{ translate('Kept until') }}</label><input id="kDate" type="date" class="form-control"></div>
                <div class="col-5"><label for="kTime" class="small text-muted">{{ translate('At') }}</label><input id="kTime" type="time" class="form-control"></div>
            </div>
            <div class="h-hint mb-2" id="keepHint"></div>
            <button type="button" class="btn btn-primary w-100" id="keepBtn" style="min-height:46px">{{ translate('Keep it and tell the guest') }}</button>
        </div>

        <div class="h-card p-3">
            <button type="button" class="btn btn-soft w-100 mb-2" data-close="full">{{ translate('Full on those dates') }}</button>
            <button type="button" class="btn btn-soft w-100" data-close="hotel">{{ translate('Close the question') }}</button>
        </div>
    </div>
    @else
    <div class="col-lg-9">
        <div class="h-card p-5 text-center">
            <i class="bi bi-chat-dots fs-1 text-muted" aria-hidden="true"></i>
            <div class="fw-bold mt-2">{{ translate('Choose a conversation') }}</div>
            <div class="h-hint">{{ translate('Turn the bell on at the top to hear new messages.') }}</div>
        </div>
    </div>
    @endif
</div>
@endsection

@if($current)
@push('script')
<script>
(function () {
    var data = {!! json_encode($chat) !!};
    var U = {!! json_encode([
        'messages' => route('hotel-partner.inbox.messages', $current->id), 'send' => route('hotel-partner.inbox.send', $current->id),
        'price' => route('hotel-partner.inbox.price', $current->id), 'keep' => route('hotel-partner.inbox.keep', $current->id),
        'close' => route('hotel-partner.inbox.close', $current->id),
    ]) !!};
    var T = {!! json_encode([
        'request' => translate('ROOM REQUEST'), 'price' => translate('PRICE SENT'), 'kept' => translate('ROOM KEPT'),
        'nights' => translate(key: '{n} night(s)', replace: ['n' => '{n}']), 'guests' => translate(key: '{n} guest(s)', replace: ['n' => '{n}']), 'rooms' => translate(key: '{n} room(s)', replace: ['n' => '{n}']), 'arriveAt' => translate(key: 'Arrives around {time}', replace: ['time' => '{time}']),
        'afternoon' => translate('Arrives in the afternoon'), 'evening' => translate('Arrives in the evening'), 'late' => translate('Arrives late at night'),
        'freeThen' => translate(key: '{n} free when asked', replace: ['n' => '{n}']), 'until' => translate(key: 'Until {time}', replace: ['time' => '{time}']), 'paidAtHotel' => translate('Paid at the hotel.'),
        'relGuest' => translate('The guest let the room go.'), 'relExpired' => translate('The kept time passed. The room is free again.'),
        'relDidnt' => translate('Marked as did not come. The room is free again.'), 'arrived' => translate('Checked in.'),
        'closedFull' => translate('You said: full on those dates.'), 'closedHotel' => translate('You closed the question.'),
        'closed' => translate('This conversation is closed.'), 'verified' => translate('E-Ride ID Verified'), 'rides' => translate(key: '{n} rides with E-Ride', replace: ['n' => '{n}']),
        'onWay' => translate('On the way in an E-Ride'), 'keptNow' => translate('Kept now'), 'keepMove' => translate('Move the time'),
        'any' => translate('Any free one'), 'noneFree' => translate('None free on those nights'), 'total' => translate('Total for'),
        'sure' => translate('Close this question? Rooms kept for this guest are let go.'),
        'keepToday' => translate('For today, at most the hours set in Setup.'), 'keepLater' => translate('At most until the evening of the arrival day.'),
    ]) !!};
    var keepMax = {{ (int)$hotel->keep_max_hours }};
    var chat = document.getElementById('chat');
    var lastId = 0;
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var day = function (ymd) { return new Date(ymd + 'T12:00:00').toLocaleDateString(undefined, {weekday: 'short', day: 'numeric', month: 'short'}); };
    var hm = function (iso) { return new Date(iso).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}); };
    var money = function (n) { return 'FCFA ' + Number(n).toLocaleString('en'); };
    var arrival = function (a) { return !a ? '' : (T[a] || T.arriveAt.replace('{time}', a)); };
    var n = function (tpl, v) { return tpl.replace('{n}', v); };

    function render(m) {
        var d = m.data || {}, el = document.createElement('div');
        if (m.kind === 'request') {
            el.className = 'ib-card req';
            el.innerHTML = '<div class="k" style="color:#1d4ed8">' + T.request + '</div><div class="fw-bold">' + esc(d.room_type) + ' · ' + n(T.rooms, d.rooms) + '</div>' +
                '<div class="small">' + day(d.check_in) + ' → ' + day(d.check_out) + ' · ' + n(T.nights, d.nights) + ' · ' + n(T.guests, d.guests) + '</div>' +
                (d.arrival ? '<div class="small">' + esc(arrival(d.arrival)) + '</div>' : '') +
                (m.body ? '<div class="mt-1">' + esc(m.body) + '</div>' : '') +
                '<div class="small fw-bold mt-1" style="color:#15803d">' + n(T.freeThen, d.free) + '</div><small class="text-muted">' + hm(m.at) + '</small>';
        } else if (m.kind === 'price') {
            el.className = 'ib-card align-self-end';
            el.innerHTML = '<div class="k text-muted">' + T.price + '</div><div class="fw-bold fs-5" style="color:#b91c1c">' + money(d.amount) + '</div>' +
                '<div class="small text-muted">' + n(T.nights, d.nights) + ' · ' + T.paidAtHotel + '</div><small class="text-muted">' + hm(m.at) + (m.by ? ' · ' + esc(m.by) : '') + '</small>';
        } else if (m.kind === 'kept') {
            el.className = 'ib-card kept align-self-end';
            el.innerHTML = '<div class="k" style="color:#8a4f00">' + T.kept + '</div><div class="fw-bold">' + esc(d.room_type) + ' · ' + n(T.rooms, d.rooms) + '</div>' +
                '<div class="small">' + T.until.replace('{time}', hm(d.until)) + ', ' + day(d.until.substring(0, 10)) + '</div><small class="text-muted">' + hm(m.at) + (m.by ? ' · ' + esc(m.by) : '') + '</small>';
        } else if (m.kind === 'released' || m.kind === 'arrived' || m.kind === 'closed') {
            el.className = 'ib-sys';
            var r = d.reason;
            el.textContent = m.kind === 'arrived' ? T.arrived : m.kind === 'closed' ? (r === 'full' ? T.closedFull : T.closedHotel)
                : (r === 'guest' ? T.relGuest : r === 'expired' ? T.relExpired : T.relDidnt);
        } else {
            el.className = 'ib-msg' + (m.sender === 'hotel' ? ' me' : '');
            el.innerHTML = esc(m.body) + '<small>' + hm(m.at) + (m.sender === 'hotel' && m.by ? ' · ' + esc(m.by) : '') + '</small>';
        }
        chat.appendChild(el);
        lastId = Math.max(lastId, m.id);
    }

    function paint(q) {
        document.getElementById('gName').textContent = q.guest;
        document.getElementById('gSub').innerHTML = (q.verified ? '<span class="h-tag ok me-1"><i class="bi bi-patch-check-fill"></i>' + T.verified + '</span>' : '') +
            n(T.rides, q.rides) + (q.on_the_way ? ' <span class="h-tag blue ms-1"><i class="bi bi-car-front"></i>' + T.onWay + '</span>' : '');
        var call = document.getElementById('gCall');
        if (q.phone) { call.href = 'tel:' + q.phone.replace(/\s/g, ''); call.classList.remove('d-none'); } else { call.classList.add('d-none'); }
        var open = ['open', 'answered', 'kept', 'arrived'].indexOf(q.status) >= 0;
        document.getElementById('composer').classList.toggle('d-none', !open);
        document.getElementById('actions').classList.toggle('d-none', !open || q.status === 'arrived');
        var note = document.getElementById('closedNote');
        note.classList.toggle('d-none', open); note.textContent = T.closed;
        document.getElementById('amountLabel').textContent = T.total + ' ' + day(q.check_in) + ' → ' + day(q.check_out) + ' (' + n(T.nights, q.nights) + ')';
        var kn = document.getElementById('keptNow');
        var kept = q.kept.filter(function (k) { return k.status === 'kept'; });
        kn.innerHTML = kept.length ? '<span class="h-tag warn">' + T.keptNow + ': ' + kept.map(function (k) { return esc(k.room); }).join(', ') + ' · ' + T.until.replace('{time}', hm(kept[0].until)) + '</span>' : '';
        document.getElementById('keepBtn').textContent = kept.length ? T.keepMove : {!! json_encode(translate('Keep it and tell the guest')) !!};
        // Keep until: today + 2 h (or the arrival evening).
        var n0 = new Date(), today = n0.getFullYear() + '-' + ('0' + (n0.getMonth() + 1)).slice(-2) + '-' + ('0' + n0.getDate()).slice(-2), kd = document.getElementById('kDate'), kt = document.getElementById('kTime');
        var ymd = function (t) { return t.getFullYear() + '-' + ('0' + (t.getMonth() + 1)).slice(-2) + '-' + ('0' + t.getDate()).slice(-2); };
        var hhmm = function (t) { return ('0' + t.getHours()).slice(-2) + ':' + ('0' + t.getMinutes()).slice(-2); };
        if (!kd.value && kept.length) {
            var ku = new Date(kept[0].until); kd.value = ymd(ku); kt.value = hhmm(ku);
        } else if (!kd.value) {
            if (q.check_in <= today) { var t = new Date(Date.now() + 2 * 3600e3); kd.value = today; kt.value = ('0' + t.getHours()).slice(-2) + ':' + ('0' + t.getMinutes()).slice(-2); }
            else { kd.value = q.check_in; kt.value = '20:00'; }
        }
        document.getElementById('keepHint').textContent = q.check_in <= today ? T.keepToday + ' (' + keepMax + ' h)' : T.keepLater;
        var kType = document.getElementById('kType');
        if (q.room_type_id && !kType.dataset.touched) kType.value = q.room_type_id;
        fillRooms(q);
    }
    function fillRooms(q) {
        var type = document.getElementById('kType').value, sel = document.getElementById('kRoom');
        var free = q.free_rooms.filter(function (r) { return r.type === type; });
        sel.innerHTML = '<option value="">' + (free.length ? T.any + ' (' + free.length + ')' : T.noneFree) + '</option>' +
            (q.rooms === 1 ? free.map(function (r) { return '<option value="' + r.id + '">' + esc(r.number) + '</option>'; }).join('') : '');
    }
    document.getElementById('kType').addEventListener('change', function () { this.dataset.touched = '1'; fillRooms(data.inquiry); });

    function load(d) {
        data.inquiry = d.inquiry;
        var atBottom = chat.scrollHeight - chat.scrollTop - chat.clientHeight < 80;
        d.messages.forEach(function (m) { if (m.id > lastId) render(m); });
        paint(d.inquiry);
        if (atBottom || d.messages.length) chat.scrollTop = chat.scrollHeight;
    }
    load(data);
    chat.scrollTop = chat.scrollHeight;
    function refresh() {
        return fetch(U.messages + '?after=' + lastId, {headers: {'Accept': 'application/json'}}).then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) { if (d && d.ok) load(d); }).catch(function () {});
    }
    (function poll() { setTimeout(function () { refresh().finally(poll); }, 4000); })();
    var after = function (d) {
        if (!d.ok) { hotelToast(d.message || 'Error'); return; }
        refresh();
    };

    var body = document.getElementById('body');
    document.getElementById('sendForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var text = body.value.trim(); if (!text) return;
        body.value = '';
        hotelPost(U.send, {body: text}).then(after);
    });
    body.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); document.getElementById('sendForm').requestSubmit(); } });
    document.querySelectorAll('[data-quick]').forEach(function (b) { b.addEventListener('click', function () { body.value = b.getAttribute('data-quick'); body.focus(); }); });
    document.getElementById('priceBtn').addEventListener('click', function () {
        hotelPost(U.price, {amount: document.getElementById('amount').value}).then(function (d) { if (d.ok) document.getElementById('amount').value = ''; after(d); });
    });
    document.getElementById('keepBtn').addEventListener('click', function () {
        hotelPost(U.keep, {room_type_id: document.getElementById('kType').value, room_id: document.getElementById('kRoom').value,
            date: document.getElementById('kDate').value, time: document.getElementById('kTime').value}).then(after);
    });
    document.querySelectorAll('[data-close]').forEach(function (b) {
        b.addEventListener('click', function () { if (confirm(T.sure)) hotelPost(U.close, {reason: b.getAttribute('data-close')}).then(after); });
    });
})();
</script>
@endpush
@endif
