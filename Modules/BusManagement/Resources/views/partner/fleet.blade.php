@extends('busmanagement::partner.layout')

@section('title', translate('Fleet'))

@php
    use Modules\BusManagement\Entities\BusVehicle;
    $v = fn(string $key, $default = null) => old($key, $bus?->{$key} ?? $default);
    $doors = old('door_positions', $bus?->door_positions ?? ($creating ? [['row' => 1, 'side' => 'right']] : []));
    $amen = old('amenities', $bus?->amenities ?? ['air_con']);
    $statusTag = ['ready' => ['ok', translate('Ready')], 'garage' => ['warn', translate('In the garage')], 'retired' => ['mute', translate('Retired')]];
    $classLabel = ['classic' => translate('Classic'), 'vip' => translate('VIP'), 'executive' => translate('Executive')];
    $amenLabel = ['air_con' => translate('Air con'), 'usb' => translate('USB'), 'wifi' => translate('Wi-Fi'), 'toilet' => translate('Toilet'), 'tv' => translate('TV'), 'water' => translate('Water'), 'reclining' => translate('Reclining seats')];
@endphp

@section('content')
<div class="d-flex flex-wrap gap-3 align-items-start">
    <div class="b-card p-3" style="flex:1 1 220px;min-width:0;max-width:320px">
        <div class="d-flex align-items-center mb-2"><h2 class="b-h flex-grow-1">{{ translate('Buses') }}</h2><a class="btn btn-primary btn-sm" href="{{ route('bus-partner.fleet', ['new' => 1]) }}">{{ translate('Add a bus') }}</a></div>
        @forelse($buses as $b)
            <a href="{{ route('bus-partner.fleet', ['bus' => $b->id]) }}" class="d-flex align-items-center gap-2 p-2 mb-2 rounded-3 text-decoration-none text-reset border {{ $bus?->id === $b->id ? 'border-danger bg-danger-subtle' : '' }}" style="{{ $bus?->id === $b->id ? 'background:#fff7f7!important' : '' }}">
                <span class="flex-grow-1 min-w-0"><span class="d-block fw-bold small">{{ $b->registration }}</span><span class="d-block b-hint">{{ $b->name ? $b->name . ' · ' : '' }}{{ translate(key: '{n} seats', replace: ['n' => $b->total_seats]) }} · {{ $classLabel[$b->class] }}</span></span>
                <span class="b-tag {{ $statusTag[$b->status][0] }}">{{ $statusTag[$b->status][1] }}</span>
            </a>
        @empty
            <p class="b-hint mb-0">{{ translate('No bus yet. Add your first one: its seat plan is what passengers and your counter will pick seats on.') }}</p>
        @endforelse
    </div>

    <form method="POST" action="{{ route('bus-partner.fleet.save', $bus?->id) }}" class="d-flex flex-wrap gap-3 align-items-start" style="flex:4 1 760px;min-width:0" id="busForm">
        @csrf
        <div class="w-100 d-flex flex-wrap align-items-center gap-2">
            <div class="flex-grow-1" style="min-width:240px"><h1 class="fs-5 fw-bold mb-0">{{ $bus ? $bus->registration : translate('Add a bus') }}</h1>
                <div class="b-hint">{{ $bus && $upcoming ? translate(key: 'On {n} upcoming departures. Departures with sold seats keep the plan they were sold on.', replace: ['n' => $upcoming]) : translate('Describe the bus once. The plan on the right is exactly what passengers and your counter will see.') }}</div></div>
            <button type="submit" class="btn btn-primary">{{ translate('Save the bus') }}</button>
        </div>

        <div class="b-card p-3" style="flex:1 1 230px;min-width:0">
            <h2 class="b-h mb-3">{{ translate('Bus information') }}</h2>
            <div class="mb-3"><label class="form-label fw-semibold small" for="registration">{{ translate('Registration number') }}</label><input id="registration" name="registration" class="form-control" required maxlength="20" value="{{ $v('registration') }}" placeholder="NW 123 AB"></div>
            <div class="mb-3"><label class="form-label fw-semibold small" for="name">{{ translate('Bus name (optional)') }}</label><input id="name" name="name" class="form-control" maxlength="60" value="{{ $v('name') }}" placeholder="{{ translate('Coaster 3') }}"></div>
            <div class="mb-3"><span class="form-label fw-semibold small d-block">{{ translate('Class') }}</span>
                <div class="seg">@foreach(BusVehicle::CLASSES as $c)<input type="radio" name="class" id="class_{{ $c }}" value="{{ $c }}" @checked($v('class', 'classic') === $c)><label for="class_{{ $c }}">{{ $classLabel[$c] }}</label>@endforeach</div></div>
            <div class="mb-3"><span class="form-label fw-semibold small d-block">{{ translate('Status') }}</span>
                <div class="seg">@foreach(BusVehicle::STATUSES as $s)<input type="radio" name="status" id="status_{{ $s }}" value="{{ $s }}" @checked($v('status', 'ready') === $s)><label for="status_{{ $s }}">{{ $statusTag[$s][1] }}</label>@endforeach</div>
                <div class="b-hint mt-1">{{ translate('A bus in the garage cannot be put on a departure.') }}</div></div>
            <div class="mb-3"><label class="form-label fw-semibold small" for="cargo_kg">{{ translate('Luggage hold (kg)') }}</label><input id="cargo_kg" type="number" name="cargo_kg" class="form-control" min="0" max="20000" value="{{ $v('cargo_kg', 500) }}"></div>
            <span class="form-label fw-semibold small d-block">{{ translate('On board') }}</span>
            <div class="d-flex flex-wrap gap-2">@foreach(BusVehicle::AMENITIES as $a)<label class="border rounded-pill px-3 py-1 small fw-semibold d-inline-flex align-items-center gap-2" style="cursor:pointer"><input type="checkbox" class="form-check-input m-0" name="amenities[]" value="{{ $a }}" @checked(in_array($a, (array)$amen, true))>{{ $amenLabel[$a] }}</label>@endforeach</div>
        </div>

        <div class="b-card p-3" style="flex:1.3 1 320px;min-width:0">
            <h2 class="b-h mb-2">{{ translate('Seat layout configuration') }}</h2>
            <div class="b-hint mb-2">{{ translate('Start from a common bus, then adjust:') }}</div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-soft btn-sm" data-tpl='{"total_seats":15,"seats_per_row":3,"aisle_position":2,"has_rear_seats":true,"rear_seats_count":4,"driver_position":"front_left","first_seat_position":"front_row","is_double_decker":false,"doors":[{"row":1,"side":"right"}]}'>Hiace · 15</button>
                <button type="button" class="btn btn-soft btn-sm" data-tpl='{"total_seats":30,"seats_per_row":4,"aisle_position":2,"has_rear_seats":true,"rear_seats_count":5,"driver_position":"front_left","first_seat_position":"front_row","is_double_decker":false,"doors":[{"row":1,"side":"right"}]}'>Coaster · 30</button>
                <button type="button" class="btn btn-soft btn-sm" data-tpl='{"total_seats":70,"seats_per_row":5,"aisle_position":2,"has_rear_seats":true,"rear_seats_count":6,"driver_position":"separate","first_seat_position":"front_row","is_double_decker":false,"doors":[{"row":7,"side":"right"}]}'>{{ translate('Large coach') }} · 70</button>
                <button type="button" class="btn btn-soft btn-sm" data-tpl='{"total_seats":30,"seats_per_row":3,"aisle_position":2,"has_rear_seats":false,"rear_seats_count":5,"driver_position":"separate","first_seat_position":"front_row","is_double_decker":false,"doors":[]}'>VIP 2 + 1 · 30</button>
                <button type="button" class="btn btn-soft btn-sm" data-tpl='{"total_seats":80,"seats_per_row":4,"aisle_position":2,"has_rear_seats":true,"rear_seats_count":5,"driver_position":"separate","first_seat_position":"front_row","is_double_decker":true,"upper_deck_seats":40,"doors":[{"row":5,"side":"right"}]}'>{{ translate('Double decker') }} · 80</button>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label fw-semibold small" for="total_seats">{{ translate('Seat capacity') }}</label><input id="total_seats" type="number" name="total_seats" class="form-control" min="4" max="100" required value="{{ $v('total_seats', 30) }}"><div class="b-hint">{{ translate('All passenger seats, both decks') }}</div></div>
                <div class="col-6"><span class="form-label fw-semibold small d-block">{{ translate('Driver position') }}</span>
                    <select name="driver_position" class="form-select" id="driver_position">
                        <option value="separate" @selected($v('driver_position', 'front_left') === 'separate')>{{ translate('Separate cabin (large coach)') }}</option>
                        <option value="front_left" @selected($v('driver_position', 'front_left') === 'front_left')>{{ translate('Front row left (minibus, Coaster)') }}</option>
                        <option value="front_right" @selected($v('driver_position', 'front_left') === 'front_right')>{{ translate('Front row right (right-hand drive)') }}</option>
                    </select></div>
                <div class="col-6"><label class="form-label fw-semibold small" for="seats_per_row">{{ translate('Seats per row') }}</label><input id="seats_per_row" type="number" name="seats_per_row" class="form-control" min="2" max="6" required value="{{ $v('seats_per_row', 4) }}"><div class="b-hint">{{ translate('Typically 4 (2 + 2), or 3 (2 + 1) for VIP') }}</div></div>
                <div class="col-6"><label class="form-label fw-semibold small" for="aisle_position">{{ translate('Aisle after seat') }}</label><input id="aisle_position" type="number" name="aisle_position" class="form-control" min="1" max="5" required value="{{ $v('aisle_position', 2) }}"><div class="b-hint">{{ translate('2 = the aisle comes after the 2nd seat') }}</div></div>
            </div>
            <div class="mb-2"><span class="form-label fw-semibold small d-block">{{ translate('Where seat 1 starts') }}</span>
                <select name="first_seat_position" class="form-select" id="first_seat_position">
                    <option value="front_row" @selected($v('first_seat_position', 'front_row') === 'front_row')>{{ translate('Front row: opposite the driver, next to the entrance') }}</option>
                    <option value="second_row" @selected($v('first_seat_position', 'front_row') === 'second_row')>{{ translate('Second row: no passenger seats in the front row') }}</option>
                </select></div>
            <div class="d-flex align-items-center gap-3 py-2 border-top">
                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="has_rear_seats" name="has_rear_seats" value="1" @checked($v('has_rear_seats', true))><label class="form-check-label fw-semibold small" for="has_rear_seats">{{ translate('Back row seats') }}</label></div>
                <div class="d-flex align-items-center gap-2 ms-auto" id="rearBox"><label class="small" for="rear_seats_count">{{ translate('Seats in the back row') }}</label><input id="rear_seats_count" type="number" name="rear_seats_count" class="form-control" style="width:80px" min="3" max="6" value="{{ $v('rear_seats_count', 5) }}"></div>
            </div>
            <div class="d-flex align-items-center gap-3 py-2 border-top">
                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="is_double_decker" name="is_double_decker" value="1" @checked($v('is_double_decker', false))><label class="form-check-label fw-semibold small" for="is_double_decker">{{ translate('Double decker') }}</label></div>
                <div class="d-flex align-items-center gap-2 ms-auto" id="upperBox"><label class="small" for="upper_deck_seats">{{ translate('Seats upstairs') }}</label><input id="upper_deck_seats" type="number" name="upper_deck_seats" class="form-control" style="width:80px" min="0" max="99" value="{{ $v('upper_deck_seats', 0) }}"></div>
            </div>
            <div class="py-2 border-top">
                <div class="d-flex align-items-center mb-1"><span class="fw-semibold small flex-grow-1">{{ translate('Side doors') }}</span><button type="button" class="btn btn-soft btn-sm" id="addDoor">{{ translate('Add door') }}</button></div>
                <div class="b-hint mb-2">{{ translate('A door takes one side of its row. The other side keeps its seats.') }}</div>
                <div id="doors">
                    @foreach(array_values((array)$doors) as $i => $d)
                        <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded-3" style="background:#f7f8f8" data-door>
                            <span class="small fw-bold">{{ translate('Row') }}</span>
                            <input type="number" class="form-control" style="width:80px" min="1" max="30" name="door_positions[{{ $i }}][row]" value="{{ $d['row'] ?? 1 }}" aria-label="{{ translate('Row of the door') }}">
                            <select class="form-select" style="width:auto" name="door_positions[{{ $i }}][side]" aria-label="{{ translate('Side of the door') }}"><option value="left" @selected(($d['side'] ?? '') === 'left')>{{ translate('Left') }}</option><option value="right" @selected(($d['side'] ?? 'right') === 'right')>{{ translate('Right') }}</option></select>
                            <button type="button" class="btn btn-sm text-danger ms-auto fw-bold" data-remove-door>{{ translate('Remove') }}</button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="b-card p-3 text-center" style="flex:1 1 260px;min-width:0;position:sticky;top:120px">
            <div class="d-flex align-items-center mb-1"><h2 class="b-h flex-grow-1 text-start">{{ translate('Seat layout preview') }}</h2><span class="b-tag blue" id="planBadge"></span></div>
            <div class="b-hint text-start mb-2" id="planSummary"></div>
            <div id="planPreview" class="d-inline-flex flex-column align-items-center"></div>
            <div class="alert alert-warning small text-start mt-2 mb-0 d-none" id="planWarn"></div>
        </div>
    </form>
</div>

<template id="doorTpl">
    <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded-3" style="background:#f7f8f8" data-door>
        <span class="small fw-bold">{{ translate('Row') }}</span>
        <input type="number" class="form-control" style="width:80px" min="1" max="30" data-name="row" value="4" aria-label="{{ translate('Row of the door') }}">
        <select class="form-select" style="width:auto" data-name="side" aria-label="{{ translate('Side of the door') }}"><option value="left">{{ translate('Left') }}</option><option value="right" selected>{{ translate('Right') }}</option></select>
        <button type="button" class="btn btn-sm text-danger ms-auto fw-bold" data-remove-door>{{ translate('Remove') }}</button>
    </div>
</template>
@endsection

@push('script')
<script>
(function () {
    var form = document.getElementById('busForm'), doors = document.getElementById('doors');
    var field = function (n) { return form.querySelector('[name="' + n + '"]'); };
    function renumberDoors() {
        doors.querySelectorAll('[data-door]').forEach(function (row, i) {
            var r = row.querySelector('input'), s = row.querySelector('select');
            r.name = 'door_positions[' + i + '][row]'; s.name = 'door_positions[' + i + '][side]';
        });
    }
    function addDoor(row, side) {
        var node = document.getElementById('doorTpl').content.firstElementChild.cloneNode(true);
        node.querySelector('input').value = row || 4;
        node.querySelector('select').value = side || 'right';
        doors.appendChild(node); renumberDoors();
    }
    function config() {
        var c = {
            total_seats: +field('total_seats').value, seats_per_row: +field('seats_per_row').value, aisle_position: +field('aisle_position').value,
            driver_position: field('driver_position').value, first_seat_position: field('first_seat_position').value,
            has_rear_seats: field('has_rear_seats').checked, rear_seats_count: +field('rear_seats_count').value,
            is_double_decker: field('is_double_decker').checked, upper_deck_seats: +field('upper_deck_seats').value, door_positions: []
        };
        doors.querySelectorAll('[data-door]').forEach(function (row) { c.door_positions.push({row: +row.querySelector('input').value, side: row.querySelector('select').value}); });
        return c;
    }
    function refresh() {
        var c = config();
        document.getElementById('rearBox').style.visibility = c.has_rear_seats ? 'visible' : 'hidden';
        document.getElementById('upperBox').style.visibility = c.is_double_decker ? 'visible' : 'hidden';
        var plan = busSeatPlan(c);
        busSeatPlanRender(document.getElementById('planPreview'), plan);
        var left = Math.max(1, Math.min(c.aisle_position, c.seats_per_row)), right = c.seats_per_row - left;
        document.getElementById('planBadge').textContent = c.driver_position === 'separate' ? @json(translate('Separate driver cabin')) : @json(translate('Driver in the front row'));
        document.getElementById('planSummary').textContent = @json(translate('Seats')) + ': ' + plan.total + ' · ' + @json(translate('Rows')) + ': ' + left + (right > 0 ? ' + ' + right : '')
            + (c.has_rear_seats ? ' · ' + @json(translate('Back row')) + ': ' + c.rear_seats_count : '') + (c.is_double_decker ? ' · ' + @json(translate('Upstairs')) + ': ' + c.upper_deck_seats : '');
        var warn = document.getElementById('planWarn'), msg = '';
        if (c.aisle_position >= c.seats_per_row) msg = @json(translate('The aisle comes after the last seat of the row. Lower the number in Aisle after seat.'));
        else if (c.has_rear_seats && c.rear_seats_count >= c.total_seats) msg = @json(translate('The back row has more seats than the bus.'));
        else if (c.is_double_decker && (c.upper_deck_seats < 1 || c.upper_deck_seats >= c.total_seats)) msg = @json(translate('Put some seats on each deck.'));
        warn.textContent = msg; warn.classList.toggle('d-none', !msg);
    }
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    document.getElementById('addDoor').addEventListener('click', function () { addDoor(); refresh(); });
    doors.addEventListener('click', function (e) {
        if (e.target.closest('[data-remove-door]')) { e.target.closest('[data-door]').remove(); renumberDoors(); refresh(); }
    });
    document.querySelectorAll('[data-tpl]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var t = JSON.parse(btn.dataset.tpl);
            ['total_seats', 'seats_per_row', 'aisle_position', 'rear_seats_count', 'driver_position', 'first_seat_position'].forEach(function (k) { field(k).value = t[k]; });
            field('upper_deck_seats').value = t.upper_deck_seats || 0;
            field('has_rear_seats').checked = !!t.has_rear_seats;
            field('is_double_decker').checked = !!t.is_double_decker;
            doors.innerHTML = '';
            (t.doors || []).forEach(function (d) { addDoor(d.row, d.side); });
            refresh();
        });
    });
    refresh();
})();
</script>
@endpush
