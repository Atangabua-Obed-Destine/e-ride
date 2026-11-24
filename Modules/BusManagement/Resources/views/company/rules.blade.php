@extends('busmanagement::company.layout')

@section('title', translate('Booking rules'))

@php
    $tiers = $rules['cancel_tiers'];
    $full = $tiers[0][0] ?? 24; $half = $tiers[1][0] ?? 6; $halfPct = $tiers[1][1] ?? 50;
    $isDirector = $busCompanyStaff->isDirector();
@endphp

@section('content')
<h1 class="fs-5 fw-bold mb-1">{{ translate('Company booking rules') }}</h1>
<p class="b-hint mb-3">{{ translate('One set of rules for your branches. Save them, then give them to the branches you choose. Each branch keeps its own seats kept for the counter.') }}</p>
<form method="POST" action="{{ route('bus-company.rules.save') }}" class="b-card p-3" style="max-width:720px">
    @csrf
    <fieldset @disabled(!$isDirector)>
    <div class="row g-2 mb-3">
        <div class="col-4"><label class="form-label small fw-semibold" for="free_bags">{{ translate('Free bags') }}</label><input id="free_bags" type="number" name="free_bags" class="form-control" min="0" max="5" value="{{ old('free_bags', $rules['free_bags']) }}"></div>
        <div class="col-4"><label class="form-label small fw-semibold" for="bag_kg">{{ translate('Up to (kg each)') }}</label><input id="bag_kg" type="number" name="bag_kg" class="form-control" min="5" max="50" value="{{ old('bag_kg', $rules['bag_kg']) }}"></div>
        <div class="col-4"><label class="form-label small fw-semibold" for="extra_bag_fee">{{ translate('Extra bag (FCFA)') }}</label><input id="extra_bag_fee" type="number" name="extra_bag_fee" class="form-control" min="0" value="{{ old('extra_bag_fee', $rules['extra_bag_fee']) }}"></div>
        <div class="col-6"><label class="form-label small fw-semibold" for="online_cutoff_minutes">{{ translate('Online sales stop (minutes before)') }}</label><input id="online_cutoff_minutes" type="number" name="online_cutoff_minutes" class="form-control" min="0" max="720" value="{{ old('online_cutoff_minutes', $rules['online_cutoff_minutes']) }}"></div>
    </div>
    <fieldset class="mb-3"><legend class="fw-semibold small mb-1">{{ translate('The ID of the person booking') }}</legend>
        @foreach(['eride' => translate('Trust E-Ride ID Verified'), 'agents' => translate('Our agents check the ID photo'), 'number' => translate('ID number only')] as $k => $label)
            <label class="form-check"><input class="form-check-input" type="radio" name="id_check" value="{{ $k }}" @checked($rules['id_check'] === $k)> {{ $label }}</label>
        @endforeach
    </fieldset>
    <fieldset class="mb-3"><legend class="fw-semibold small mb-1">{{ translate('Tickets') }}</legend>
        @foreach(['app' => translate('In the app'), 'collect' => translate('Collected at our counter')] as $k => $label)
            <label class="form-check"><input class="form-check-input" type="radio" name="tickets" value="{{ $k }}" @checked($rules['tickets'] === $k)> {{ $label }}</label>
        @endforeach
    </fieldset>
    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="paper_copy" name="paper_copy" value="1" @checked($rules['paper_copy'])>
        <label class="form-check-label small fw-semibold" for="paper_copy">{{ translate('Keep a paper copy') }}</label></div>
    <div class="fw-semibold small mb-2">{{ translate('When a passenger cancels') }}</div>
    <div class="row g-2 mb-3">
        <div class="col-12"><label class="form-label small" for="tier_full_hours">{{ translate('Everything back if they cancel at least this many hours before') }}</label><input id="tier_full_hours" type="number" name="tier_full_hours" class="form-control" min="0" max="168" value="{{ old('tier_full_hours', $full) }}"></div>
        <div class="col-6"><label class="form-label small" for="tier_half_pct">{{ translate('After that, % back') }}</label><input id="tier_half_pct" type="number" name="tier_half_pct" class="form-control" min="0" max="100" value="{{ old('tier_half_pct', $halfPct) }}"></div>
        <div class="col-6"><label class="form-label small" for="tier_half_hours">{{ translate('Until this many hours before') }}</label><input id="tier_half_hours" type="number" name="tier_half_hours" class="form-control" min="0" max="168" value="{{ old('tier_half_hours', $half) }}"></div>
    </div>
    <div class="fw-semibold small mb-1">{{ translate('Give these rules to') }}</div>
    <div class="d-flex flex-wrap gap-3 mb-3">
        @foreach($busBranches as $b)<label class="form-check"><input class="form-check-input" type="checkbox" name="apply_to[]" value="{{ $b->id }}"> {{ $b->branchLabel() }}</label>@endforeach
    </div>
    @if($isDirector)<button class="btn btn-primary">{{ translate('Save the rules') }}</button>@else<div class="b-hint">{{ translate('Only the director changes the company rules.') }}</div>@endif
    </fieldset>
</form>
@endsection
