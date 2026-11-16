@extends('busmanagement::partner.layout')

@section('title', translate('Destinations'))

@php
    $open = session('open');
    $tiers = $rules['cancel_tiers'];
    $full = collect($tiers)->firstWhere(1, 100)[0] ?? 18;
    $half = collect($tiers)->first(fn($t) => $t[1] > 0 && $t[1] < 100) ?? [2, 50];
@endphp

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1"><h1 class="fs-5 fw-bold mb-0">{{ translate(key: 'From {city} to', replace: ['city' => $busAgency->origin_city]) }}</h1>
        <div class="b-hint">{{ translate(key: 'Every departure leaves from {city}, your office. Passengers get off at the drop-off points you list, in order, at the fare you set for each class.', replace: ['city' => $busAgency->origin_city]) }}</div></div>
</div>

<div class="d-flex flex-wrap gap-3 align-items-start">
    <div class="d-flex flex-column gap-3" style="flex:3 1 640px;min-width:0">
        @foreach($destinations as $d)
            @include('busmanagement::partner._destination_form', ['d' => $d, 'expanded' => $open === $d->id])
        @endforeach
        @include('busmanagement::partner._destination_form', ['d' => null, 'expanded' => $open === 'new' || $destinations->isEmpty()])
    </div>

    <form method="POST" action="{{ route('bus-partner.destinations.rules') }}" class="b-card p-3" style="flex:1 1 300px;min-width:0">
        @csrf
        <h2 class="b-h mb-1">{{ translate('Booking rules') }}</h2>
        <div class="b-hint mb-3">{{ translate('For every destination. Passengers see them before they book.') }}</div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small fw-semibold" for="free_bags">{{ translate('Free bags') }}</label><input id="free_bags" type="number" name="free_bags" class="form-control" min="0" max="5" value="{{ old('free_bags', $rules['free_bags']) }}"></div>
            <div class="col-6"><label class="form-label small fw-semibold" for="bag_kg">{{ translate('Up to (kg each)') }}</label><input id="bag_kg" type="number" name="bag_kg" class="form-control" min="5" max="50" value="{{ old('bag_kg', $rules['bag_kg']) }}"></div>
            <div class="col-12"><label class="form-label small fw-semibold" for="extra_bag_fee">{{ translate('Extra bag (FCFA)') }}</label><input id="extra_bag_fee" type="number" name="extra_bag_fee" class="form-control" min="0" value="{{ old('extra_bag_fee', $rules['extra_bag_fee']) }}"></div>
            <div class="col-6"><label class="form-label small fw-semibold" for="counter_seats">{{ translate('Seats kept for the counter') }}</label><input id="counter_seats" type="number" name="counter_seats" class="form-control" min="0" max="80" value="{{ old('counter_seats', $rules['counter_seats']) }}"></div>
            <div class="col-6"><label class="form-label small fw-semibold" for="online_cutoff_minutes">{{ translate('Online sales stop (minutes before)') }}</label><input id="online_cutoff_minutes" type="number" name="online_cutoff_minutes" class="form-control" min="0" max="720" value="{{ old('online_cutoff_minutes', $rules['online_cutoff_minutes']) }}"></div>
        </div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="require_id" name="require_id" value="1" @checked(old('require_id', $rules['require_id']))><label class="form-check-label small fw-semibold" for="require_id">{{ translate('Ask every passenger for their ID card number') }}</label></div>
        <div class="fw-semibold small mb-2">{{ translate('When a passenger cancels') }}</div>
        <div class="row g-2 mb-2">
            <div class="col-12"><label class="form-label small" for="tier_full_hours">{{ translate('Everything back if they cancel at least this many hours before') }}</label><input id="tier_full_hours" type="number" name="tier_full_hours" class="form-control" min="0" max="168" value="{{ old('tier_full_hours', $full) }}"></div>
            <div class="col-6"><label class="form-label small" for="tier_half_pct">{{ translate('After that, % back') }}</label><input id="tier_half_pct" type="number" name="tier_half_pct" class="form-control" min="0" max="100" value="{{ old('tier_half_pct', $half[1]) }}"></div>
            <div class="col-6"><label class="form-label small" for="tier_half_hours">{{ translate('Until this many hours before') }}</label><input id="tier_half_hours" type="number" name="tier_half_hours" class="form-control" min="0" max="168" value="{{ old('tier_half_hours', $half[0]) }}"></div>
        </div>
        <div class="b-hint mb-3">{{ translate('After that, nothing back. If you cancel a departure, you refund every passenger 100%, whatever these rules say.') }}</div>
        <button class="btn btn-dark w-100">{{ translate('Save the rules') }}</button>
    </form>
</div>
@endsection

@push('script')
<script>
document.querySelectorAll('[data-dest]').forEach(function (form) {
    var body = form.querySelector('[data-points]'), tpl = form.querySelector('template');
    function renumber() {
        body.querySelectorAll('[data-point]').forEach(function (row, i) {
            row.querySelectorAll('[data-f]').forEach(function (inp) { inp.name = 'points[' + i + '][' + inp.dataset.f + ']'; });
        });
    }
    form.querySelector('[data-add-point]').addEventListener('click', function () {
        body.appendChild(tpl.content.firstElementChild.cloneNode(true)); renumber();
    });
    body.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-remove-point]');
        if (btn && body.querySelectorAll('[data-point]').length > 1) { btn.closest('[data-point]').remove(); renumber(); }
    });
    renumber();
});
</script>
@endpush
