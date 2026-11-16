@extends('busmanagement::partner.layout')

@section('title', translate('Lockdowns and ghost towns'))

@php
    use Modules\BusManagement\Entities\BusClosure;
    use Modules\BusManagement\Entities\BusSchedule;
    use Modules\BusManagement\Service\ClosureService;
    $closures = app(ClosureService::class);
    $dayNames = ['mon' => translate('Mon'), 'tue' => translate('Tue'), 'wed' => translate('Wed'), 'thu' => translate('Thu'), 'fri' => translate('Fri'), 'sat' => translate('Sat'), 'sun' => translate('Sun')];
    $start = $area ? $area->starts_at->max(now()) : now()->addDay()->startOfDay()->setTime(6, 0);
    $end = $area ? $area->ends_at : now()->addDay()->startOfDay()->setTime(18, 0);
@endphp

@push('head')
<style>
    .chipset { display: flex; flex-wrap: wrap; gap: 6px; }
    .chipset input { position: absolute; opacity: 0; pointer-events: none; }
    .chipset label { border: 1px solid #dde3e2; border-radius: 999px; padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer; margin: 0; background: #fff; }
    .chipset input:checked + label { background: #1d2d2b; color: #fff; border-color: #1d2d2b; }
    .chipset input:focus-visible + label { outline: 2px solid var(--red); outline-offset: 2px; }
    .outcome { border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; display: block; cursor: pointer; margin-bottom: 8px; }
    .outcome:has(input:checked) { border-color: var(--red); background: #fff6f6; }
    .preview { background: #f4f6f6; border-radius: 12px; padding: 12px; }
    .preview b { font-size: 20px; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1"><h1 class="fs-5 fw-bold mb-0">{{ translate('Lockdowns and ghost towns') }}</h1>
        <div class="b-hint">{{ translate('Declare a period with no travel. Departures inside stop selling, and your passengers are moved, refunded or warned.') }}</div></div>
</div>

@foreach($suggestions as $s)
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
        <div class="flex-grow-1"><b>{{ translate('E-Ride reports') }}: {{ $s->title }}</b> · {{ $s->starts_at->translatedFormat('D j M H:i') }} - {{ $s->ends_at->translatedFormat('D j M H:i') }}
            @if($s->reason)<div class="small">{{ $s->reason }}</div>@endif</div>
        <a class="btn btn-dark btn-sm" href="{{ route('bus-partner.closures', ['area' => $s->id]) }}#declare">{{ translate('Handle it') }}</a>
    </div>
@endforeach

<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('bus-partner.closures.store') }}" class="b-card p-3" id="declare">
            @csrf
            @if($area)<input type="hidden" name="safety_area_id" value="{{ $area->id }}">@endif
            <h2 class="b-h mb-2">{{ translate('Declare a period with no travel') }}</h2>

            <div class="fw-semibold small mb-1">{{ translate('What is happening') }}</div>
            <div class="chipset mb-2" role="radiogroup" aria-label="{{ translate('What is happening') }}">
                @foreach(BusClosure::KINDS as $k)
                    <input type="radio" name="kind" id="k_{{ $k }}" value="{{ $k }}" @checked(old('kind', $area ? 'lockdown' : 'lockdown') === $k)><label for="k_{{ $k }}">{{ ClosureService::kindLabel($k) }}</label>
                @endforeach
            </div>
            <label class="form-label small fw-semibold" for="note">{{ translate('A word for passengers (optional)') }}</label>
            <input id="note" name="note" class="form-control mb-3" maxlength="300" value="{{ old('note', $area?->title) }}" placeholder="{{ translate('For example: lockdown announced in Bamenda') }}">

            <div class="seg mb-2"><input type="radio" name="repeat" id="r0" value="0" @checked(!old('repeat'))><label for="r0">{{ translate('A period') }}</label>
                <input type="radio" name="repeat" id="r1" value="1" @checked(old('repeat'))><label for="r1">{{ translate('A day every week') }}</label></div>
            <div class="row g-2 mb-3" data-when="0">
                <div class="col-6 col-md-3"><label class="form-label small" for="start_date">{{ translate('From') }}</label><input id="start_date" type="date" name="start_date" class="form-control" value="{{ old('start_date', $start->toDateString()) }}"></div>
                <div class="col-6 col-md-3"><label class="form-label small" for="start_time">{{ translate('at') }}</label><input id="start_time" type="time" name="start_time" class="form-control" value="{{ old('start_time', $start->format('H:i')) }}"></div>
                <div class="col-6 col-md-3"><label class="form-label small" for="end_date">{{ translate('To') }}</label><input id="end_date" type="date" name="end_date" class="form-control" value="{{ old('end_date', $end->toDateString()) }}"></div>
                <div class="col-6 col-md-3"><label class="form-label small" for="end_time">{{ translate('at') }}</label><input id="end_time" type="time" name="end_time" class="form-control" value="{{ old('end_time', $end->format('H:i')) }}"></div>
            </div>
            <div class="mb-3" data-when="1" hidden>
                <div class="chipset mb-2">
                    @foreach(BusSchedule::DAY_BITS as $k => $bit)
                        <input type="checkbox" name="days[]" id="d_{{ $k }}" value="{{ $bit }}" @checked($k === 'mon')><label for="d_{{ $k }}">{{ $dayNames[$k] }}</label>
                    @endforeach
                </div>
                <div class="row g-2">
                    <div class="col-6 col-md-3"><label class="form-label small" for="day_from">{{ translate('From (hour)') }}</label><input id="day_from" type="time" name="day_from" class="form-control" value="00:00"></div>
                    <div class="col-6 col-md-3"><label class="form-label small" for="day_to">{{ translate('To (hour)') }}</label><input id="day_to" type="time" name="day_to" class="form-control" value="23:59"></div>
                    <div class="col-6 col-md-3"><label class="form-label small" for="from_date">{{ translate('Starting') }}</label><input id="from_date" type="date" name="from_date" class="form-control" value="{{ today()->toDateString() }}"></div>
                    <div class="col-6 col-md-3"><label class="form-label small" for="until_date">{{ translate('Until (optional)') }}</label><input id="until_date" type="date" name="until_date" class="form-control"></div>
                </div>
            </div>

            <div class="seg mb-2"><input type="radio" name="scope" id="s_all" value="all" checked><label for="s_all">{{ translate('All destinations') }}</label>
                <input type="radio" name="scope" id="s_some" value="some"><label for="s_some">{{ translate('Some') }}</label></div>
            <div class="chipset mb-3" data-scope hidden>
                @foreach($destinations as $d)<input type="checkbox" name="destination_ids[]" id="dst_{{ $d->id }}" value="{{ $d->id }}"><label for="dst_{{ $d->id }}">{{ $d->city }}</label>@endforeach
            </div>

            <div class="fw-semibold small mb-1">{{ translate('Your passengers') }}</div>
            <label class="outcome"><input type="radio" name="outcome" value="move" class="me-2" checked><b>{{ translate('Move them to the next departure') }}</b>
                <div class="b-hint ms-4">{{ translate('Same destination and class, same seat when free. They keep the new time or cancel for a full refund.') }}</div></label>
            <label class="outcome"><input type="radio" name="outcome" value="cancel" class="me-2"><b>{{ translate('Cancel and refund everyone') }}</b>
                <div class="b-hint ms-4">{{ translate('App passengers are refunded in full by you, by Mobile Money. Counter passengers get their money back at the counter.') }}</div></label>
            <label class="outcome"><input type="radio" name="outcome" value="at_risk" class="me-2"><b>{{ translate('Not sure yet: put the departures at risk') }}</b>
                <div class="b-hint ms-4">{{ translate('Sales stop and passengers are warned, with a free cancellation. Decide by the time below, or they are moved.') }}</div>
                <div class="row g-2 ms-3 mt-1" data-decide hidden>
                    <div class="col-6"><label class="form-label small" for="decide_date">{{ translate('Decide by') }}</label><input id="decide_date" type="date" name="decide_date" class="form-control" value="{{ $start->copy()->subDay()->toDateString() }}"></div>
                    <div class="col-6"><label class="form-label small" for="decide_time">{{ translate('at') }}</label><input id="decide_time" type="time" name="decide_time" class="form-control" value="20:00"></div>
                </div></label>

            <div class="preview mt-3" aria-live="polite" id="preview"><span class="b-hint">{{ translate('Checking which departures this touches...') }}</span></div>
            <button class="btn btn-primary w-100 mt-3" id="declareBtn">{{ translate('Declare') }}</button>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="b-card p-3">
            <h2 class="b-h mb-2">{{ translate('Declared') }}</h2>
            @forelse($list as $c)
                <a href="{{ route('bus-partner.closures.show', $c->id) }}" class="d-block text-decoration-none text-reset py-2 border-top">
                    <div class="d-flex gap-2 align-items-center"><b class="flex-grow-1">{{ ClosureService::kindLabel($c->kind) }}{{ $c->note ? ': ' . $c->note : '' }}</b>
                        @if($c->status === 'lifted')<span class="b-tag mute">{{ translate('Lifted') }}</span>
                        @elseif($c->isAtRisk())<span class="b-tag warn">{{ translate('At risk') }}</span>
                        @else<span class="b-tag bad">{{ translate('No travel') }}</span>@endif</div>
                    <div class="b-hint">{{ $closures->notice($c)['when'] }} · {{ translate(key: '{n} departure(s)', replace: ['n' => $counts[$c->id] ?? 0]) }}</div>
                </a>
            @empty
                <div class="b-hint">{{ translate('Nothing declared.') }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
(function () {
    var form = document.getElementById('declare'), box = document.getElementById('preview'), timer = null;
    var T = {departures: @json(translate(key: '{n} departure(s) stop', replace: ['n' => '{n}'])),
        passengers: @json(translate(key: '{n} passenger(s): {app} in the app, {counter} at the counter', replace: ['n' => '{n}', 'app' => '{app}', 'counter' => '{counter}'])),
        waiting: @json(translate(key: '{n} request(s) waiting will be declined', replace: ['n' => '{n}'])), none: @json(translate('No departure falls inside. You can still declare it: new departures inside will not be sold.'))};
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    function sync() {
        var rep = form.querySelector('input[name=repeat]:checked').value === '1';
        form.querySelectorAll('[data-when]').forEach(function (el) { el.hidden = (el.dataset.when === '1') !== rep; });
        form.querySelector('[data-scope]').hidden = form.querySelector('input[name=scope]:checked').value !== 'some';
        form.querySelector('[data-decide]').hidden = form.querySelector('input[name=outcome]:checked').value !== 'at_risk';
    }
    function preview() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            fetch(@json(route('bus-partner.closures.preview')), {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}, body: new FormData(form)})
                .then(function (r) { return r.json(); }).then(function (d) {
                    if (!d.ok) { box.innerHTML = '<span class="text-danger small">' + esc(d.message) + '</span>'; return; }
                    if (!d.departures) { box.innerHTML = '<span class="b-hint">' + T.none + '</span>'; return; }
                    box.innerHTML = '<div class="fw-bold">' + esc(T.departures.replace('{n}', d.departures)) + '</div>' +
                        '<div>' + esc(T.passengers.replace('{n}', d.passengers).replace('{app}', d.app).replace('{counter}', d.counter)) + '</div>' +
                        (d.waiting ? '<div>' + esc(T.waiting.replace('{n}', d.waiting)) + '</div>' : '') +
                        '<div class="b-hint mt-1">' + d.list.map(esc).join(' · ') + '</div>';
                }).catch(function () {});
        }, 350);
    }
    form.addEventListener('change', function () { sync(); preview(); });
    form.addEventListener('input', preview);
    form.addEventListener('submit', function () { document.getElementById('declareBtn').disabled = true; });
    sync(); preview();
})();
</script>
@endpush
