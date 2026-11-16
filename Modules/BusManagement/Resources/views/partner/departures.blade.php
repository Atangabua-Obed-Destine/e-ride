@extends('busmanagement::partner.layout')

@section('title', translate('Departures'))

@php
    use Modules\BusManagement\Entities\BusSchedule;
    $classLabel = ['classic' => translate('Classic'), 'vip' => translate('VIP'), 'executive' => translate('Executive')];
    $dayNames = ['mon' => translate('Mon'), 'tue' => translate('Tue'), 'wed' => translate('Wed'), 'thu' => translate('Thu'), 'fri' => translate('Fri'), 'sat' => translate('Sat'), 'sun' => translate('Sun')];
    $hm = fn($t) => substr((string)$t, 0, 5);
    $byDay = $departures->groupBy(fn($d) => $d->date->toDateString());
    $statusTag = ['scheduled' => ['mute', translate('Scheduled')], 'boarding' => ['ok', translate('Boarding')], 'departed' => ['blue', translate('Departed')], 'cancelled' => ['bad', translate('Cancelled')]];
    $lineData = fn($l) => ['id' => $l->id, 'destination_id' => $l->destination_id, 'class' => $l->class, 'kind' => $l->kind, 'time' => $hm($l->time),
        'days' => $l->days, 'vehicle_id' => $l->vehicle_id, 'auto_accept' => $l->auto_accept, 'active' => $l->active];
    $depData = fn($d, $day, $n) => ['id' => $d->id, 'title' => $day->translatedFormat('D j') . ' · ' . $hm($d->time) . ' · ' . $d->destination?->city,
        'vehicle_id' => $d->vehicle_id, 'class' => $d->class, 'sold' => $n];
@endphp

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1"><h1 class="fs-5 fw-bold mb-0">{{ translate('Departures') }}</h1>
        <div class="b-hint">{{ translate('Set the weekly timetable once. Departures open for booking by themselves, some days ahead.') }}</div></div>
    @if($canEdit)
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#lineModal" data-line="">{{ translate('Add a departure time') }}</button>
        <button class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#extraModal">{{ translate('Add an extra departure') }}</button>
    @endif
</div>

@if($destinations->isEmpty())
    <div class="alert alert-warning">{{ translate('Add a destination with its fares first, then your departure times.') }} @if($canEdit)<a href="{{ route('bus-partner.destinations') }}" class="fw-bold">{{ translate('Destinations') }}</a>@endif</div>
@endif

<div class="b-card mb-3">
    <div class="p-3 pb-1"><h2 class="b-h">{{ translate('Weekly timetable') }}</h2></div>
    @forelse($lines as $l)
        <div class="d-flex flex-wrap align-items-center gap-3 px-3 py-2 border-top">
            <span class="fw-bold" style="width:96px">{{ $l->kind === BusSchedule::WHEN_FULL ? translate('From') . ' ' : '' }}{{ $hm($l->time) }}</span>
            <span class="flex-grow-1" style="min-width:180px"><span class="fw-bold small d-block">{{ $l->destination?->city }} · {{ $classLabel[$l->class] }}</span>
                <span class="b-hint">{{ $l->vehicle ? $l->vehicle->registration : translate('Bus decided on the day') }}</span></span>
            @if($l->kind === BusSchedule::WHEN_FULL)<span class="b-tag warn">{{ translate('Leaves when full') }}</span>@endif
            @if($l->auto_accept)<span class="b-tag blue">{{ translate('Auto-accept') }}</span>@endif
            <span class="d-flex gap-1">@foreach(BusSchedule::DAY_BITS as $k => $bit)<span class="b-tag {{ $l->days & $bit ? '' : 'mute' }}" style="{{ $l->days & $bit ? 'background:#1d2d2b;color:#fff' : '' }}">{{ mb_substr($dayNames[$k], 0, 2) }}</span>@endforeach</span>
            @if(!$l->active)<span class="b-tag mute">{{ translate('Stopped') }}</span>@endif
            @if($canEdit)<button class="btn btn-soft btn-sm" data-bs-toggle="modal" data-bs-target="#lineModal" data-line="{{ json_encode($lineData($l)) }}">{{ translate('Edit') }}</button>@endif
        </div>
    @empty
        <div class="px-3 pb-3 b-hint">{{ translate('No departure time yet.') }}</div>
    @endforelse
</div>

<div class="b-card p-3">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <a class="btn btn-soft btn-sm" href="{{ route('bus-partner.departures', ['week' => $start->copy()->subWeek()->toDateString()]) }}" aria-label="{{ translate('Previous week') }}"><i class="bi bi-chevron-left"></i></a>
        <h2 class="b-h">{{ translate(key: 'Week of {date}', replace: ['date' => $start->translatedFormat('j F')]) }}</h2>
        <a class="btn btn-soft btn-sm" href="{{ route('bus-partner.departures', ['week' => $start->copy()->addWeek()->toDateString()]) }}" aria-label="{{ translate('Next week') }}"><i class="bi bi-chevron-right"></i></a>
        @php $unset = $departures->filter(fn($d) => !$d->vehicle_id && $d->isOpen())->count(); @endphp
        @if($unset)<span class="b-tag warn ms-auto">{{ translate(key: '{n} without a bus: not bookable in the app', replace: ['n' => $unset]) }}</span>@endif
    </div>
    <div style="overflow-x:auto">
        <div style="display:grid;grid-template-columns:repeat(7,minmax(130px,1fr));gap:8px;min-width:930px">
            @for($i = 0; $i < 7; $i++)
                @php $day = $start->copy()->addDays($i); $list = $byDay->get($day->toDateString(), collect()); @endphp
                <div class="rounded-3 p-2 d-flex flex-column gap-2 {{ $day->isToday() ? 'border border-2 border-danger' : 'border' }}" style="min-height:220px;background:#fff">
                    <div class="d-flex justify-content-between small"><span class="fw-bold">{{ $day->translatedFormat('D j') }}</span><span class="text-muted">{{ translate(key: '{n} sold', replace: ['n' => $list->sum(fn($d) => $sold[$d->id] ?? 0)]) }}</span></div>
                    @foreach($list as $d)
                        @php $n = $sold[$d->id] ?? 0; $pct = $d->capacity ? round($n / $d->capacity * 100) : 0; @endphp
                        <button type="button" class="text-start border-0 rounded-3 p-2 w-100" style="background:{{ !$d->vehicle_id && $d->isOpen() ? '#fff4e5' : ($d->schedule_id ? '#f7f8f8' : '#eef2ff') }}"
                                @if($canEdit && $d->isOpen()) data-bs-toggle="modal" data-bs-target="#busModal" data-dep="{{ json_encode($depData($d, $day, $n)) }}" @endif>
                            <span class="d-flex justify-content-between small"><b>{{ $d->kind === BusSchedule::WHEN_FULL ? $hm($d->time) . '+' : $hm($d->time) }}</b><span class="text-muted">{{ $d->capacity ? $n . '/' . $d->capacity : '' }}</span></span>
                            <span class="d-block small">{{ $d->destination?->city }} {{ $classLabel[$d->class] }}</span>
                            <span class="d-block b-hint">{{ $d->vehicle?->registration ?? translate('Bus not set') }}{{ $d->schedule_id ? '' : ' · ' . translate('Extra') }}</span>
                            @if($d->capacity)<span class="d-block mt-1 rounded" style="height:5px;background:#e3e8e7"><span class="d-block rounded" style="height:5px;width:{{ $pct }}%;background:{{ $d->kind === BusSchedule::WHEN_FULL ? '#f59e0b' : '#1d2d2b' }}"></span></span>@endif
                            @if($d->status !== 'scheduled')<span class="b-tag {{ $statusTag[$d->status][0] }} mt-1">{{ $statusTag[$d->status][1] }}</span>@endif
                        </button>
                    @endforeach
                </div>
            @endfor
        </div>
    </div>
    @if($canEdit)<div class="b-hint mt-2">{{ translate('Click a departure to put a bus on it or change it. A bus with fewer seats than those already sold is refused.') }}</div>@endif
</div>

@if($canEdit)
<div class="modal fade" id="lineModal" tabindex="-1" aria-labelledby="lineTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" id="lineForm" data-base="{{ route('bus-partner.departures.line') }}">
        @csrf
        <div class="modal-header"><h2 class="modal-title fs-6 fw-bold" id="lineTitle">{{ translate('Departure time') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button></div>
        <div class="modal-body">
            <div class="row g-2">
                <div class="col-7"><label class="form-label small fw-semibold" for="l_dest">{{ translate('To') }}</label><select id="l_dest" name="destination_id" class="form-select" required>@foreach($destinations as $d)<option value="{{ $d->id }}">{{ $d->city }}</option>@endforeach</select></div>
                <div class="col-5"><label class="form-label small fw-semibold" for="l_class">{{ translate('Class') }}</label><select id="l_class" name="class" class="form-select">@foreach($classLabel as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-5"><label class="form-label small fw-semibold" for="l_time">{{ translate('Time') }}</label><input id="l_time" type="time" name="time" class="form-control" required value="06:00"></div>
                <div class="col-7"><span class="form-label small fw-semibold d-block">{{ translate('It leaves') }}</span>
                    <div class="seg"><input type="radio" name="kind" id="l_fixed" value="fixed" checked><label for="l_fixed">{{ translate('At the time') }}</label><input type="radio" name="kind" id="l_full" value="when_full"><label for="l_full">{{ translate('When full') }}</label></div></div>
                <div class="col-12"><span class="form-label small fw-semibold d-block">{{ translate('Days') }}</span>
                    <div class="d-flex flex-wrap gap-2">@foreach(BusSchedule::DAY_BITS as $k => $bit)<label class="border rounded-pill px-2 py-1 small fw-semibold d-inline-flex align-items-center gap-1" style="cursor:pointer"><input type="checkbox" class="form-check-input m-0" name="day[]" value="{{ $bit }}" data-bit="{{ $bit }}" checked>{{ $dayNames[$k] }}</label>@endforeach</div></div>
                <div class="col-12"><label class="form-label small fw-semibold" for="l_bus">{{ translate('Bus') }}</label><select id="l_bus" name="vehicle_id" class="form-select"><option value="">{{ translate('Decide on the day') }}</option>@foreach($buses as $b)<option value="{{ $b->id }}" data-class="{{ $b->class }}">{{ $b->registration }}{{ $b->name ? ' · ' . $b->name : '' }} · {{ $b->total_seats }} · {{ $classLabel[$b->class] }}</option>@endforeach</select>
                    <div class="b-hint mt-1">{{ translate('Without a bus, its departures cannot be booked in the app until you put one on (no seat plan yet).') }}</div></div>
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="l_auto" name="auto_accept" value="1"><label class="form-check-label small fw-semibold" for="l_auto">{{ translate('Auto-accept: free seats are confirmed at once, no answer needed') }}</label></div></div>
                <div class="col-12 d-none" id="l_activeBox"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="l_active" name="active" value="1" checked><label class="form-check-label small fw-semibold" for="l_active">{{ translate('Running (switch off to stop opening new departures)') }}</label></div></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">{{ translate('Cancel') }}</button><button class="btn btn-primary">{{ translate('Save') }}</button></div>
    </form></div>
</div>

<div class="modal fade" id="extraModal" tabindex="-1" aria-labelledby="extraTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('bus-partner.departures.extra') }}">
        @csrf
        <div class="modal-header"><h2 class="modal-title fs-6 fw-bold" id="extraTitle">{{ translate('Extra departure') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button></div>
        <div class="modal-body">
            <div class="b-hint mb-2">{{ translate('Once, outside the timetable: busy days before a holiday, a group.') }}</div>
            <div class="row g-2">
                <div class="col-7"><label class="form-label small fw-semibold" for="x_dest">{{ translate('To') }}</label><select id="x_dest" name="destination_id" class="form-select" required>@foreach($destinations as $d)<option value="{{ $d->id }}">{{ $d->city }}</option>@endforeach</select></div>
                <div class="col-5"><label class="form-label small fw-semibold" for="x_class">{{ translate('Class') }}</label><select id="x_class" name="class" class="form-select">@foreach($classLabel as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label small fw-semibold" for="x_date">{{ translate('Date') }}</label><input id="x_date" type="date" name="date" class="form-control" required min="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}"></div>
                <div class="col-6"><label class="form-label small fw-semibold" for="x_time">{{ translate('Time') }}</label><input id="x_time" type="time" name="time" class="form-control" required value="15:00"></div>
                <div class="col-12"><div class="seg"><input type="radio" name="kind" id="x_fixed" value="fixed" checked><label for="x_fixed">{{ translate('Leaves at the time') }}</label><input type="radio" name="kind" id="x_full" value="when_full"><label for="x_full">{{ translate('Leaves when full') }}</label></div></div>
                <div class="col-12"><label class="form-label small fw-semibold" for="x_bus">{{ translate('Bus') }}</label><select id="x_bus" name="vehicle_id" class="form-select"><option value="">{{ translate('Decide later') }}</option>@foreach($buses as $b)<option value="{{ $b->id }}">{{ $b->registration }} · {{ $b->total_seats }} · {{ $classLabel[$b->class] }}</option>@endforeach</select></div>
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="x_auto" name="auto_accept" value="1"><label class="form-check-label small fw-semibold" for="x_auto">{{ translate('Auto-accept') }}</label></div></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">{{ translate('Cancel') }}</button><button class="btn btn-primary">{{ translate('Add') }}</button></div>
    </form></div>
</div>

<div class="modal fade" id="busModal" tabindex="-1" aria-labelledby="busTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" id="busForm" data-base="{{ url('partner/bus-agency/departures') }}">
        @csrf
        <div class="modal-header"><h2 class="modal-title fs-6 fw-bold" id="busTitle">{{ translate('Bus for this departure') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button></div>
        <div class="modal-body">
            <div class="b-hint mb-2" id="busSold"></div>
            <label class="form-label small fw-semibold" for="b_bus">{{ translate('Bus') }}</label>
            <select id="b_bus" name="vehicle_id" class="form-select"><option value="">{{ translate('No bus yet') }}</option>@foreach($buses as $b)<option value="{{ $b->id }}" data-class="{{ $b->class }}">{{ $b->registration }}{{ $b->name ? ' · ' . $b->name : '' }} · {{ $b->total_seats }} · {{ $classLabel[$b->class] }}</option>@endforeach</select>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">{{ translate('Cancel') }}</button><button class="btn btn-primary">{{ translate('Save') }}</button></div>
    </form></div>
</div>
@endif
@endsection

@if($canEdit)
@push('script')
<script>
(function () {
    var lineModal = document.getElementById('lineModal');
    if (lineModal) lineModal.addEventListener('show.bs.modal', function (e) {
        var raw = e.relatedTarget && e.relatedTarget.dataset.line, l = raw ? JSON.parse(raw) : null, f = document.getElementById('lineForm');
        f.action = f.dataset.base + (l ? '/' + l.id : '');
        document.getElementById('lineTitle').textContent = l ? @json(translate('Change a departure time')) : @json(translate('Add a departure time'));
        if (l) {
            f.destination_id.value = l.destination_id; f.class.value = l.class; f.time.value = l.time; f.vehicle_id.value = l.vehicle_id || '';
            f.querySelector('#l_' + (l.kind === 'when_full' ? 'full' : 'fixed')).checked = true;
            f.querySelectorAll('[data-bit]').forEach(function (cb) { cb.checked = !!(l.days & +cb.dataset.bit); });
            f.auto_accept.checked = !!l.auto_accept; f.active.checked = !!l.active;
        }
        document.getElementById('l_activeBox').classList.toggle('d-none', !l);
    });
    var busModal = document.getElementById('busModal');
    if (busModal) busModal.addEventListener('show.bs.modal', function (e) {
        var d = JSON.parse(e.relatedTarget.dataset.dep), f = document.getElementById('busForm');
        f.action = f.dataset.base + '/' + d.id + '/bus';
        document.getElementById('busTitle').textContent = d.title;
        document.getElementById('busSold').textContent = d.sold ? d.sold + ' ' + @json(translate('seats already sold: the new bus must have them.')) : @json(translate('No seat sold yet.'));
        f.vehicle_id.value = d.vehicle_id || '';
        f.vehicle_id.querySelectorAll('option[data-class]').forEach(function (o) { o.disabled = o.dataset.class !== d.class; });
    });
})();
</script>
@endpush
@endif
