@extends('busmanagement::company.layout')

@section('title', translate('Staff'))

@php
    use Modules\BusManagement\Entities\BusCompanyStaff;
    use Modules\BusManagement\Entities\BusStaff;
    use Modules\BusManagement\Service\AgencyService;
    $canAct = $busCompanyStaff->canAct();
    $isDirector = $busCompanyStaff->isDirector();
    $roleLabel = fn($r) => match ($r) { BusStaff::MANAGER => translate('Manager'), BusStaff::COUNTER => translate('Counter agent'), BusStaff::ACCOUNTANT => translate('Accountant'), default => translate('Boarding agent') };
@endphp

@section('content')
<h1 class="fs-5 fw-bold mb-2">{{ translate('Staff') }}</h1>
@if(session('new_login'))
    <div class="alert alert-success">{{ translate(key: 'Login made for {branch}. They sign in at {url} with {login} and the password you set. Give it to them yourself.', replace: session('new_login')) }}</div>
@endif
@include('busmanagement::company._filters')

<section class="b-card p-3 mb-3" aria-labelledby="sBranch">
    <h2 class="b-h mb-2" id="sBranch">{{ translate('Branch staff') }}</h2>
    <div class="ho-scroll"><table class="ho-table">
        <thead><tr><th>{{ translate('Name') }}</th><th>{{ translate('Branch') }}</th><th>{{ translate('Role') }}</th><th>{{ translate('Login') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($branchStaff as $s)
            <tr class="{{ $s->active ? '' : 'opacity-50' }}">
                <td><b>{{ $s->user?->first_name }} {{ $s->user?->last_name }}</b></td>
                <td>{{ $s->agency?->branchLabel() }}</td>
                <td>{{ $roleLabel($s->role) }}</td>
                <td class="small">{{ $s->user ? AgencyService::loginOf($s->user) : '' }}</td>
                <td class="text-end text-nowrap">
                    @if($canAct)
                        <button class="btn btn-soft btn-sm" data-pass="{{ route('bus-company.staff.branch.password', $s->id) }}">{{ translate('New password') }}</button>
                        <button class="btn btn-sm {{ $s->active ? 'btn-outline-danger' : 'btn-outline-success' }}" data-toggle="{{ route('bus-company.staff.branch.toggle', $s->id) }}" data-active="{{ $s->active ? 0 : 1 }}">{{ $s->active ? translate('Switch off') : translate('Switch on') }}</button>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @if($canAct)
        <form method="POST" action="{{ route('bus-company.staff.branch') }}" class="row g-2 mt-2 border-top pt-3">
            @csrf
            <div class="col-12 fw-bold small">{{ translate('Add someone to a branch') }}</div>
            <div class="col-md-3"><select name="agency_id" class="form-select" required aria-label="{{ translate('Branch') }}">@foreach($busBranches as $b)<option value="{{ $b->id }}">{{ $b->branchLabel() }}</option>@endforeach</select></div>
            <div class="col-md-3"><input name="name" class="form-control" required maxlength="80" placeholder="{{ translate('Name') }}" aria-label="{{ translate('Name') }}" value="{{ old('name') }}"></div>
            <div class="col-md-2"><select name="role" class="form-select" aria-label="{{ translate('Role') }}">@foreach(BusStaff::ROLES as $r)<option value="{{ $r }}">{{ $roleLabel($r) }}</option>@endforeach</select></div>
            <div class="col-md-2"><input name="email" type="email" class="form-control" maxlength="120" placeholder="{{ translate('Email (optional)') }}" aria-label="{{ translate('Email (optional)') }}"></div>
            <div class="col-md-2"><input name="password" type="password" class="form-control" required minlength="8" placeholder="{{ translate('Password') }}" aria-label="{{ translate('Password') }}" autocomplete="new-password"></div>
            <div class="col-12"><button class="btn btn-primary btn-sm">{{ translate('Make the login') }}</button></div>
        </form>
    @endif
</section>

@if($isDirector)
<section class="b-card p-3" aria-labelledby="sHead">
    <h2 class="b-h mb-1" id="sHead">{{ translate('Head office') }}</h2>
    <p class="b-hint mb-2">{{ translate('A regional manager sees and acts on their branches only, without money or company settings. An accountant sees reports and statements and changes nothing.') }}</p>
    <div class="ho-scroll"><table class="ho-table">
        <tbody>
        @foreach($headOffice as $s)
            <tr class="{{ $s->active ? '' : 'opacity-50' }}">
                <td><b>{{ $s->user?->first_name }}</b><div class="b-hint">{{ $s->user ? AgencyService::loginOf($s->user) : '' }}</div></td>
                <td>{{ BusCompanyStaff::roleLabel($s->role) }}@if($s->role === BusCompanyStaff::REGIONAL)<div class="b-hint">{{ $busBranches->whereIn('id', (array)$s->agency_ids)->map->branchLabel()->implode(', ') }}</div>@endif</td>
                <td class="text-end text-nowrap">
                    <button class="btn btn-soft btn-sm" data-pass="{{ route('bus-company.staff.company.password', $s->id) }}">{{ translate('New password') }}</button>
                    @if($s->id !== $busCompanyStaff->id)<button class="btn btn-sm {{ $s->active ? 'btn-outline-danger' : 'btn-outline-success' }}" data-toggle="{{ route('bus-company.staff.company.toggle', $s->id) }}" data-active="{{ $s->active ? 0 : 1 }}">{{ $s->active ? translate('Switch off') : translate('Switch on') }}</button>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <form method="POST" action="{{ route('bus-company.staff.company') }}" class="row g-2 mt-2 border-top pt-3">
        @csrf
        <div class="col-12 fw-bold small">{{ translate('Add someone to the head office') }}</div>
        <div class="col-md-3"><input name="name" class="form-control" required maxlength="80" placeholder="{{ translate('Name') }}" aria-label="{{ translate('Name') }}"></div>
        <div class="col-md-3"><select name="role" class="form-select" aria-label="{{ translate('Role') }}" id="hoRole">@foreach(BusCompanyStaff::ROLES as $r)<option value="{{ $r }}" @selected($r === BusCompanyStaff::REGIONAL)>{{ BusCompanyStaff::roleLabel($r) }}</option>@endforeach</select></div>
        <div class="col-md-3"><input name="email" type="email" class="form-control" maxlength="120" placeholder="{{ translate('Email (optional)') }}" aria-label="{{ translate('Email (optional)') }}"></div>
        <div class="col-md-3"><input name="password" type="password" class="form-control" required minlength="8" placeholder="{{ translate('Password') }}" aria-label="{{ translate('Password') }}" autocomplete="new-password"></div>
        <div class="col-12" id="hoBranches"><div class="small fw-semibold mb-1">{{ translate('Their branches') }}</div>
            <div class="d-flex flex-wrap gap-3">@foreach($busBranches as $b)<label class="form-check"><input class="form-check-input" type="checkbox" name="agency_ids[]" value="{{ $b->id }}"> {{ $b->branchLabel() }}</label>@endforeach</div></div>
        <div class="col-12"><button class="btn btn-primary btn-sm">{{ translate('Make the login') }}</button></div>
    </form>
</section>
@endif
@endsection

@push('script')
<script>
(function () {
    var role = document.getElementById('hoRole'), box = document.getElementById('hoBranches');
    if (role) { var f = function () { box.hidden = role.value !== 'regional'; }; role.addEventListener('change', f); f(); }
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-toggle]'), p = e.target.closest('[data-pass]');
        if (t) {
            t.disabled = true;
            busPost(t.dataset.toggle, {active: t.dataset.active === '1'}).then(function (d) { busToast(d.message); if (d.ok) setTimeout(function () { location.reload(); }, 700); else t.disabled = false; });
        }
        if (p) {
            var pw = prompt(@json(translate('New password (at least 8 characters)')));
            if (!pw) return;
            busPost(p.dataset.pass, {password: pw}).then(function (d) { busToast(d.message); });
        }
    });
})();
</script>
@endpush
