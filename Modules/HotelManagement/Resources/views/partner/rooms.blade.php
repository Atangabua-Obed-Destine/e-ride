@extends('hotelmanagement::partner.layout')
@section('title', translate('Rooms'))

@php
    use Modules\HotelManagement\Entities\HotelStay;
    $look = [
        'free' => [translate('Free'), '#15803d', '#f3fbf6', '#9ed4b3'],
        'kept' => [translate('Kept'), '#8a4f00', '#fff8ec', '#e8b562'],
        'booked' => [translate('Arriving'), '#0f766e', '#eefaf8', '#99d5cc'],
        'occupied' => [translate('Occupied'), '#1d4ed8', '#f2f5fd', '#a9bdf0'],
        'to_clean' => [translate('To clean'), '#6d28d9', '#f7f3fe', '#c9b2f2'],
        'out_of_order' => [translate('Out of order'), '#4b5655', '#f1f3f3', '#c3cbca'],
    ];
    $sourceLabel = ['eride' => 'E-Ride', 'phone' => translate('Phone'), 'whatsapp' => 'WhatsApp', 'walk_in' => translate('Walk-in'), 'other' => translate('Other'), 'blocked' => translate('Blocked')];
    $note = function ($r) use ($sourceLabel) {
        $s = $r['stay'];
        return match ($r['status']) {
            'kept' => ($s?->guest_name ? $s->guest_name . ' · ' : '') . translate(key: 'Until {time}', replace: ['time' => $s?->kept_until?->format('H:i')]),
            'booked' => trim(($s?->guest_name ? $s->guest_name . ' · ' : '') . ($sourceLabel[$s?->source] ?? '')),
            'occupied' => $s && $s->check_out->lte(today()) ? translate('Leaves today') : ($s ? translate('Leaves') . ' ' . $s->check_out->translatedFormat('D j') : ''),
            'to_clean' => $r['room']->state_at ? translate('Since') . ' ' . $r['room']->state_at->format('H:i') : '',
            'out_of_order' => $s && $s->source === HotelStay::BLOCKED ? translate('Blocked') : ($r['room']->state_note ?? ''),
            default => translate('Ready'),
        };
    };
    $floors = collect($board['rooms'])->groupBy(fn($r) => $r['room']->floor ?: translate('Rooms'));
    $roomsJs = collect($board['rooms'])->map(fn($r) => [
        'id' => $r['room']->id, 'number' => $r['room']->number, 'type' => $r['room']->type?->name, 'status' => $r['status'], 'state' => $r['room']->state,
        'note' => $note($r), 'label' => $look[$r['status']][0],
        'stay' => $r['stay'] ? ['id' => $r['stay']->id, 'status' => $r['stay']->status, 'source' => $r['stay']->source, 'inquiry' => $r['stay']->inquiry_id] : null,
    ])->values();
@endphp

@push('head')
<style>
    .rb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(128px, 1fr)); gap: 10px; }
    .rb-room { min-height: 92px; border-radius: 12px; border: 1.5px solid; padding: 10px; display: flex; flex-direction: column; align-items: flex-start; gap: 3px; text-align: left; width: 100%; }
    .rb-room:focus-visible { outline: 3px solid #111; outline-offset: 1px; }
    .rb-no { font-size: 17px; font-weight: 800; color: var(--ink); }
    .rb-lab { font-size: 11px; font-weight: 700; }
    .rb-type { font-size: 12px; color: #3e4a48; }
    .rb-note { font-size: 11.5px; color: var(--muted); }
    .rb-count { font-size: 13px; font-weight: 700; border-radius: 8px; padding: 6px 10px; }
    .rb-row { border: 1.5px solid var(--line); border-radius: 12px; padding: 12px; }
</style>
@endpush

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <div class="flex-grow-1">
                <h1 class="h-h" style="font-size:22px">{{ translate('Rooms today') }} · {{ today()->translatedFormat('D j M') }}</h1>
                <div class="h-hint">{{ translate('Tap a room to change it. Guests in the app see how many are free.') }}
                    @if($hotel->board_touched_at) {{ translate('Last change') }} {{ $hotel->board_touched_at->diffForHumans() }}.@endif</div>
            </div>
            <div class="d-flex flex-wrap gap-1">
                @foreach($look as $key => [$label, $ink, $bg])
                    @if(($counts[$key] ?? 0) > 0)<span class="rb-count" style="color:{{ $ink }};background:{{ $bg }}">{{ $counts[$key] }} {{ $label }}</span>@endif
                @endforeach
            </div>
        </div>
        @forelse($floors as $floor => $rooms)
            <div class="h-card p-3 mb-3">
                <div class="fw-bold mb-2">{{ $floor }}</div>
                <div class="rb-grid">
                    @foreach($rooms as $r)
                        @php [$label, $ink, $bg, $line] = $look[$r['status']]; @endphp
                        <button type="button" class="rb-room" style="background:{{ $bg }};border-color:{{ $line }}" data-room="{{ $r['room']->id }}"
                                aria-label="{{ translate('Room') }} {{ $r['room']->number }}, {{ $label }}">
                            <span class="d-flex justify-content-between w-100"><span class="rb-no">{{ $r['room']->number }}</span><span class="rb-lab" style="color:{{ $ink }}">{{ $label }}</span></span>
                            <span class="rb-type">{{ $r['room']->type?->name }}</span>
                            <span class="rb-note">{{ $note($r) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="h-card p-4 text-center">
                <div class="fw-bold mb-1">{{ translate('No rooms yet') }}</div>
                <div class="h-hint">{{ translate('The manager adds room types and their room numbers in Setup.') }}</div>
            </div>
        @endforelse
    </div>

    <div class="col-lg-4">
        <div class="h-card p-3 mb-3">
            <h2 class="h-h mb-2">{{ translate('Kept for guests') }}</h2>
            @forelse($board['kept'] as $s)
                @php $onWay = $s->inquiry ? app(\Modules\HotelManagement\Service\InquiryService::class)->onTheWay($s->inquiry) : false; @endphp
                <div class="rb-row mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold flex-grow-1">{{ $s->guest_name ?? translate('Guest') }} · {{ translate(key: '{n} guest(s)', replace: ['n' => $s->guests]) }}</span>
                        @if($onWay)<span class="h-tag blue"><i class="bi bi-car-front"></i>{{ translate('On the way') }}</span>
                        @else<span class="h-tag warn">{{ translate('Until') }} {{ $s->kept_until?->format('H:i') }}</span>@endif
                    </div>
                    <div class="h-hint mb-2">{{ translate(key: 'Room {room} · {type} · until {date}', replace: ['room' => $s->room?->number, 'type' => $s->type?->name, 'date' => $s->check_out->translatedFormat('D j')]) }}</div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('hotel-partner.stays.arrive', $s->id) }}" class="flex-grow-1">@csrf<button class="btn btn-primary w-100">{{ translate('Arrived') }}</button></form>
                        @if($s->inquiry_id)<a href="{{ route('hotel-partner.inbox', ['q' => $s->inquiry_id]) }}" class="btn btn-soft flex-grow-1">{{ translate('Chat') }}</a>@endif
                        <form method="POST" action="{{ route('hotel-partner.stays.no-show', $s->id) }}" onsubmit="return confirm({{ json_encode(translate('Free the room: the guest did not come.')) }})">@csrf<button class="btn btn-soft">{{ translate('Did not come') }}</button></form>
                    </div>
                </div>
            @empty
                <div class="h-hint">{{ translate('No room kept right now. Keep one from a chat in the Inbox.') }}</div>
            @endforelse
        </div>

        @if($board['arriving']->isNotEmpty())
        <div class="h-card p-3 mb-3">
            <h2 class="h-h mb-2">{{ translate('Arriving today') }}</h2>
            @foreach($board['arriving'] as $s)
                <div class="rb-row mb-2">
                    <div class="fw-bold">{{ translate('Room') }} {{ $s->room?->number }} · {{ $s->guest_name ?: ($sourceLabel[$s->source] ?? '') }}</div>
                    <div class="h-hint mb-2">{{ translate(key: '{source} · until {date}', replace: ['source' => $sourceLabel[$s->source] ?? '', 'date' => $s->check_out->translatedFormat('D j')]) }}</div>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('hotel-partner.stays.arrive', $s->id) }}" class="flex-grow-1">@csrf<button class="btn btn-primary w-100">{{ translate('Arrived') }}</button></form>
                        <form method="POST" action="{{ route('hotel-partner.stays.no-show', $s->id) }}" class="flex-grow-1">@csrf<button class="btn btn-soft w-100">{{ translate('Did not come') }}</button></form>
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        <div class="h-card p-3">
            <h2 class="h-h mb-2">{{ translate('Leaving today') }}</h2>
            @forelse($board['leaving'] as $s)
                <div class="d-flex align-items-center gap-2 py-1">
                    <span class="flex-grow-1 small">{{ translate(key: 'Room {room}, out by {time}', replace: ['room' => $s->room?->number, 'time' => $hotel->check_out_by]) }}@if($s->guest_name) · {{ $s->guest_name }}@endif</span>
                    <form method="POST" action="{{ route('hotel-partner.stays.leave', $s->id) }}">@csrf<button class="btn btn-soft btn-sm">{{ translate('Left') }}</button></form>
                </div>
            @empty
                <div class="h-hint">{{ translate('Nobody leaves today.') }}</div>
            @endforelse
            <div class="h-hint mt-2">{{ translate('Left sends the room to cleaning. Mark it clean when it is ready, and it is free again.') }}</div>
        </div>
    </div>
</div>

<div class="modal fade" id="roomModal" tabindex="-1" aria-labelledby="roomTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5 fw-bold" id="roomTitle"></h2>
                    <div class="h-hint" id="roomSub"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-2" id="roomBody"></div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
(function () {
    var rooms = {!! json_encode($roomsJs) !!};
    var T = {!! json_encode([
        'room' => translate('Room'), 'arrived' => translate('Arrived'), 'noShow' => translate('Did not come'), 'left' => translate('Guest left'),
        'chat' => translate('Open the chat'), 'walkIn' => translate('Walk-in: occupied now'), 'nights' => translate('Nights'), 'guestName' => translate('Guest name (optional)'),
        'clean' => translate('Clean: ready for guests'), 'toClean' => translate('To clean'), 'ooo' => translate('Out of order'), 'why' => translate('What is wrong (optional)'),
        'remove' => translate('Remove this booking'), 'book' => translate('Book later nights in Coming nights'), 'save' => translate('Save'),
        'eride' => translate('An E-Ride guest: answer or let the room go from the chat.'),
    ]) !!};
    var U = {!! json_encode([
        'state' => route('hotel-partner.rooms.state', ['room' => '__R__']), 'walk' => route('hotel-partner.rooms.walk-in', ['room' => '__R__']),
        'arrive' => route('hotel-partner.stays.arrive', ['stay' => '__S__']), 'noShow' => route('hotel-partner.stays.no-show', ['stay' => '__S__']),
        'leave' => route('hotel-partner.stays.leave', ['stay' => '__S__']), 'cancel' => route('hotel-partner.stays.cancel', ['stay' => '__S__']),
        'inbox' => route('hotel-partner.inbox'), 'nights' => route('hotel-partner.nights'),
    ]) !!};
    var token = document.querySelector('meta[name=csrf-token]').content;
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var form = function (url, label, cls, extra) {
        return '<form method="POST" action="' + url + '"><input type="hidden" name="_token" value="' + token + '">' + (extra || '') +
            '<button class="btn ' + (cls || 'btn-soft') + ' w-100" style="min-height:46px">' + esc(label) + '</button></form>';
    };
    var byId = {}; rooms.forEach(function (r) { byId[r.id] = r; });
    var modal = new bootstrap.Modal(document.getElementById('roomModal'));
    document.querySelectorAll('[data-room]').forEach(function (b) {
        b.addEventListener('click', function () {
            var r = byId[b.getAttribute('data-room')], s = r.stay, html = '';
            var st = U.state.replace('__R__', r.id);
            document.getElementById('roomTitle').textContent = T.room + ' ' + r.number + ' · ' + r.label;
            document.getElementById('roomSub').textContent = (r.type || '') + (r.note ? ' · ' + r.note : '');
            if (s && (s.status === 'kept' || s.status === 'booked') && s.source !== 'blocked') {
                html += form(U.arrive.replace('__S__', s.id), T.arrived, 'btn-primary');
                html += form(U.noShow.replace('__S__', s.id), T.noShow);
                if (s.inquiry) html += '<a class="btn btn-soft w-100 d-flex align-items-center justify-content-center" style="min-height:46px" href="' + U.inbox + '?q=' + s.inquiry + '">' + esc(T.chat) + '</a>';
                else if (s.status === 'booked') html += form(U.cancel.replace('__S__', s.id), T.remove);
            } else if (s && s.status === 'booked' && s.source === 'blocked') {
                html += form(U.cancel.replace('__S__', s.id), T.remove);
            } else if (s && s.status === 'in_house') {
                html += form(U.leave.replace('__S__', s.id), T.left, 'btn-primary');
            } else {
                if (r.state !== 'out_of_order') {
                    html += '<form method="POST" action="' + U.walk.replace('__R__', r.id) + '" class="border rounded-3 p-2"><input type="hidden" name="_token" value="' + token + '">' +
                        '<div class="row g-2 mb-2"><div class="col-4"><label class="small fw-semibold" for="wn">' + esc(T.nights) + '</label><input id="wn" type="number" name="nights" min="1" max="60" value="1" class="form-control"></div>' +
                        '<div class="col-8"><label class="small fw-semibold" for="wg">' + esc(T.guestName) + '</label><input id="wg" name="guest_name" maxlength="80" class="form-control"></div></div>' +
                        '<button class="btn btn-primary w-100" style="min-height:46px">' + esc(T.walkIn) + '</button></form>';
                }
                if (r.state !== 'ready') html += form(st, T.clean, r.state === 'to_clean' ? 'btn-primary' : 'btn-soft', '<input type="hidden" name="state" value="ready">');
                if (r.state !== 'to_clean') html += form(st, T.toClean, 'btn-soft', '<input type="hidden" name="state" value="to_clean">');
                if (r.state !== 'out_of_order') {
                    html += '<form method="POST" action="' + st + '" class="d-flex gap-2"><input type="hidden" name="_token" value="' + token + '"><input type="hidden" name="state" value="out_of_order">' +
                        '<label class="visually-hidden" for="wo">' + esc(T.why) + '</label><input id="wo" name="note" maxlength="120" class="form-control" placeholder="' + esc(T.why) + '">' +
                        '<button class="btn btn-soft text-nowrap">' + esc(T.ooo) + '</button></form>';
                }
                html += '<a class="small fw-bold text-danger text-decoration-none mt-1" href="' + U.nights + '">' + esc(T.book) + '</a>';
            }
            document.getElementById('roomBody').innerHTML = html;
            modal.show();
        });
    });
    // Kept rooms, arrivals and expiries change on their own: refresh while nothing is open.
    setInterval(function () { if (!document.querySelector('.modal.show')) window.location.reload(); }, 60000);
})();
</script>
@endpush
