@extends('hotelmanagement::partner.layout')
@section('title', translate('Coming nights'))

@php
    use Modules\HotelManagement\Entities\HotelStay;
    $rooms = $calendar['rooms'];
    $grid = $calendar['grid'];
    $dayList = $calendar['days'];
    $byType = $rooms->groupBy('room_type_id');
    $sourceLabel = ['eride' => 'E-Ride', 'phone' => translate('Phone'), 'whatsapp' => 'WhatsApp', 'walk_in' => translate('Walk-in'), 'other' => translate('Other'), 'blocked' => translate('Repairs')];
    $cell = function (?HotelStay $s) use ($sourceLabel) {
        if (!$s) return ['', 'free'];
        if ($s->status === HotelStay::KEPT) return [translate('Kept'), 'kept'];
        if ($s->source === HotelStay::BLOCKED) return [translate('Repairs'), 'ooo'];
        if ($s->status === HotelStay::IN_HOUSE) return [translate('In'), 'in'];
        return [$sourceLabel[$s->source] ?? '', 'booked'];
    };
    $prev = $from->copy()->subDays($days);
    $stayJs = [];
@endphp

@push('head')
<style>
    .nt-wrap { overflow-x: auto; }
    .nt { border-collapse: separate; border-spacing: 4px; min-width: 960px; }
    .nt th { font-size: 11px; color: var(--muted); font-weight: 600; text-align: center; white-space: nowrap; }
    .nt th b { display: block; color: var(--ink); }
    .nt td.rn { font-size: 13px; color: #3e4a48; white-space: nowrap; padding-right: 8px; }
    .nt td.tn { font-size: 13px; font-weight: 800; padding-top: 10px; white-space: nowrap; }
    .nt td.fc { font-size: 11px; font-weight: 700; text-align: center; padding-top: 10px; }
    .nt .c { width: 56px; height: 36px; border-radius: 7px; border: 0; font-size: 10.5px; font-weight: 700; display: flex; align-items: center; justify-content: center; padding: 0; }
    .nt .c.free { background: #fff; border: 1.5px solid #e3e7e7; color: var(--muted); }
    .nt .c.free:hover { border-color: var(--red); }
    .nt .c.booked { background: #dce5fb; color: #1d3e9e; }
    .nt .c.in { background: #c9d8fa; color: #1d3e9e; }
    .nt .c.kept { background: #fce7c3; color: #6b3f00; }
    .nt .c.ooo { background: #dde2e1; color: #4b5655; }
    .nt .c:focus-visible { outline: 3px solid #111; outline-offset: 1px; }
</style>
@endpush

@section('content')
<div class="row g-3">
    <div class="col-xl-9">
        <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <div class="flex-grow-1">
                <h1 class="h-h" style="font-size:22px">{{ translate('Coming nights') }}</h1>
                <div class="h-hint">{{ translate('Put phone, WhatsApp and walk-in bookings here so the app never shows a room you no longer have. Tap a free night to book from it.') }}</div>
            </div>
            <div class="d-flex gap-2">
                @if($from->gt(today()))<a class="btn btn-soft" href="{{ route('hotel-partner.nights', ['from' => $prev->max(today())->toDateString()]) }}"><i class="bi bi-chevron-left"></i> {{ translate('Earlier') }}</a>@endif
                <a class="btn btn-soft" href="{{ route('hotel-partner.nights', ['from' => $from->copy()->addDays($days)->toDateString()]) }}">{{ translate('Later') }} <i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
        <div class="h-card p-2 nt-wrap">
            <table class="nt">
                <thead><tr><th></th>@foreach($dayList as $d)<th scope="col"><b>{{ $d->translatedFormat('D') }}</b>{{ $d->format('j') }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse($types as $type)
                    @php $typeRooms = $byType[$type->id] ?? collect(); @endphp
                    @continue($typeRooms->isEmpty())
                    <tr>
                        <td class="tn">{{ $type->name }}</td>
                        @foreach($dayList as $d)
                            @php $free = $typeRooms->filter(fn($r) => !isset($grid[$r->id][$d->toDateString()]) && !($d->isToday() && $r->state === 'out_of_order'))->count(); @endphp
                            <td class="fc" style="color:{{ $free === 0 ? '#b42318' : ($free === 1 ? '#8a4f00' : '#15803d') }}">{{ $free === 0 ? translate('Full') : translate(key: '{n} free', replace: ['n' => $free]) }}</td>
                        @endforeach
                    </tr>
                    @foreach($typeRooms as $room)
                        <tr>
                            <td class="rn">{{ translate('Room') }} {{ $room->number }}</td>
                            @foreach($dayList as $d)
                                @php
                                    $s = $grid[$room->id][$d->toDateString()] ?? null;
                                    [$label, $cls] = $cell($s);
                                    if ($s && !isset($stayJs[$s->id])) {
                                        $stayJs[$s->id] = ['room' => $room->number, 'source' => $sourceLabel[$s->source] ?? $s->source, 'status' => $s->status, 'name' => $s->guest_name,
                                            'in' => $s->check_in->translatedFormat('D j M'), 'out' => $s->check_out->translatedFormat('D j M'),
                                            'cancel' => $s->source !== HotelStay::ERIDE && $s->status === HotelStay::BOOKED ? route('hotel-partner.stays.cancel', $s->id) : null,
                                            'inbox' => $s->inquiry_id ? route('hotel-partner.inbox', ['q' => $s->inquiry_id]) : null];
                                    }
                                @endphp
                                <td>
                                    @if($s)
                                        <button type="button" class="c {{ $cls }}" data-stay="{{ $s->id }}" aria-label="{{ translate('Room') }} {{ $room->number }} {{ $d->translatedFormat('D j') }}: {{ $label }}">{{ $label }}</button>
                                    @elseif($d->isToday() && $room->state === 'out_of_order')
                                        <span class="c ooo" title="{{ $room->state_note }}">{{ translate('Out of order') }}</span>
                                    @else
                                        <button type="button" class="c free" data-free="{{ $room->id }}" data-day="{{ $d->toDateString() }}" aria-label="{{ translate('Room') }} {{ $room->number }} {{ $d->translatedFormat('D j') }}: {{ translate('Free') }}"></button>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @empty
                    <tr><td class="p-4 h-hint">{{ translate('No rooms yet. The manager adds them in Setup.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-xl-3">
        <form method="POST" action="{{ route('hotel-partner.nights.book') }}" class="h-card p-3" style="border:2px solid var(--ink)" id="bookForm">
            @csrf
            <h2 class="h-h mb-2">{{ translate('Mark nights booked') }}</h2>
            <label for="room_id" class="small text-muted">{{ translate('Room') }}</label>
            <select id="room_id" name="room_id" class="form-select mb-2" required>
                @foreach($types as $type)
                    @if(($byType[$type->id] ?? collect())->isNotEmpty())
                    <optgroup label="{{ $type->name }}">
                        @foreach($byType[$type->id] as $room)<option value="{{ $room->id }}" @selected(old('room_id') === $room->id)>{{ translate('Room') }} {{ $room->number }}</option>@endforeach
                    </optgroup>
                    @endif
                @endforeach
            </select>
            <div class="row g-2 mb-2">
                <div class="col-6"><label for="check_in" class="small text-muted">{{ translate('Arrives') }}</label><input id="check_in" type="date" name="check_in" class="form-control" required min="{{ today()->toDateString() }}" value="{{ old('check_in', today()->toDateString()) }}"></div>
                <div class="col-6"><label for="check_out" class="small text-muted">{{ translate('Leaves') }}</label><input id="check_out" type="date" name="check_out" class="form-control" required value="{{ old('check_out', today()->addDay()->toDateString()) }}"></div>
            </div>
            <div class="small text-muted mb-1">{{ translate('Booked by') }}</div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                @foreach(['phone' => translate('Phone'), 'whatsapp' => 'WhatsApp', 'walk_in' => translate('At the desk'), 'other' => translate('Other'), 'blocked' => translate('Repairs, not for guests')] as $k => $label)
                    <span class="chip-opt"><input type="radio" name="source" id="src_{{ $k }}" value="{{ $k }}" @checked(old('source', 'phone') === $k)><label for="src_{{ $k }}">{{ $label }}</label></span>
                @endforeach
            </div>
            <label for="guest_name" class="small text-muted">{{ translate('Guest name (optional, only you see it)') }}</label>
            <input id="guest_name" name="guest_name" maxlength="80" class="form-control mb-3" value="{{ old('guest_name') }}">
            <button class="btn btn-primary w-100" style="min-height:46px">{{ translate('Save') }}</button>
        </form>
        <div class="h-card p-3 mt-3">
            <div class="fw-bold mb-1">{{ translate('What guests see') }}</div>
            <div class="h-hint">{{ translate('Only how many rooms of each type are free for their dates, never names or room numbers. If you do not touch your rooms for a while, the app shows Ask the reception instead of a number.') }}</div>
        </div>
    </div>
</div>

<div class="modal fade" id="stayModal" tabindex="-1" aria-labelledby="stayTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius:16px">
            <div class="modal-header"><h2 class="modal-title fs-6 fw-bold" id="stayTitle"></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button></div>
            <div class="modal-body" id="stayBody"></div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
(function () {
    var stays = {!! json_encode($stayJs) !!};
    var T = {!! json_encode(['room' => translate('Room'), 'remove' => translate('Remove this booking'), 'chat' => translate('Open the chat'),
        'eride' => translate('An E-Ride guest. Let the room go from the chat if needed.'), 'inHouse' => translate('In the house. Mark them as left on the Rooms board.')]) !!};
    var token = document.querySelector('meta[name=csrf-token]').content;
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var modal = new bootstrap.Modal(document.getElementById('stayModal'));
    document.querySelectorAll('[data-stay]').forEach(function (b) {
        b.addEventListener('click', function () {
            var s = stays[b.getAttribute('data-stay')];
            document.getElementById('stayTitle').textContent = T.room + ' ' + s.room + ' · ' + s.source;
            var html = '<div class="mb-2">' + esc(s.in) + ' → ' + esc(s.out) + (s.name ? '<br><b>' + esc(s.name) + '</b>' : '') + '</div>';
            if (s.cancel) html += '<form method="POST" action="' + s.cancel + '"><input type="hidden" name="_token" value="' + token + '"><button class="btn btn-soft w-100">' + esc(T.remove) + '</button></form>';
            else if (s.inbox) html += '<div class="h-hint mb-2">' + esc(T.eride) + '</div><a class="btn btn-soft w-100" href="' + s.inbox + '">' + esc(T.chat) + '</a>';
            else if (s.status === 'in_house') html += '<div class="h-hint">' + esc(T.inHouse) + '</div>';
            document.getElementById('stayBody').innerHTML = html;
            modal.show();
        });
    });
    document.querySelectorAll('[data-free]').forEach(function (b) {
        b.addEventListener('click', function () {
            var day = b.getAttribute('data-day'), next = new Date(day + 'T12:00:00'); next.setDate(next.getDate() + 1);
            document.getElementById('room_id').value = b.getAttribute('data-free');
            document.getElementById('check_in').value = day;
            document.getElementById('check_out').value = next.getFullYear() + '-' + ('0' + (next.getMonth() + 1)).slice(-2) + '-' + ('0' + next.getDate()).slice(-2);
            document.getElementById('bookForm').scrollIntoView({behavior: 'smooth', block: 'center'});
            document.getElementById('check_out').focus();
        });
    });
})();
</script>
@endpush
