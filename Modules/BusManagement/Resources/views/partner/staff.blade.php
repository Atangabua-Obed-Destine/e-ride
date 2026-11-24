@extends('busmanagement::partner.layout')

@section('title', translate('Staff'))

@php
    use Modules\BusManagement\Service\AgencyService;
    $roleLabel = ['manager' => translate('Manager'), 'counter' => translate('Counter agent'), 'boarding' => translate('Boarding agent'), 'accountant' => translate('Accountant')];
    $roleHint = ['manager' => translate('Everything, including fares, buses, payments and staff.'), 'counter' => translate('Seat requests, quick sell, boarding, and seeing departures.'), 'boarding' => translate('Boarding only: scanning tickets and the passenger list.'), 'accountant' => translate('Accounts only: spending, agent cash counts, statements and reports.')];
@endphp

@section('content')
@if(session('new_login'))
    <div class="alert alert-success">{{ translate(key: '{name} can sign in at {url} with {login} and the password you chose.', replace: ['name' => session('new_login')['name'], 'url' => route('bus-partner.login'), 'login' => session('new_login')['login']]) }}</div>
@endif
<div class="d-flex flex-wrap gap-3 align-items-start">
    <div class="b-card" style="flex:2 1 520px;min-width:0">
        <div class="p-3 pb-1"><h1 class="b-h">{{ translate('Staff') }}</h1></div>
        @foreach($staff as $s)
            <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-top {{ $s->active ? '' : 'opacity-50' }}">
                <span class="flex-grow-1" style="min-width:180px"><span class="fw-bold small d-block">{{ $s->user?->first_name }}{{ $s->id === $busStaff->id ? ' (' . translate('you') . ')' : '' }}</span>
                    <span class="b-hint">{{ AgencyService::loginOf($s->user) }}</span></span>
                <span class="b-tag {{ $s->role === 'manager' ? 'blue' : 'mute' }}">{{ $roleLabel[$s->role] }}</span>
                @if($s->id !== $busStaff->id)
                    <form method="POST" action="{{ route('bus-partner.staff.password', $s->id) }}" class="d-flex gap-1">@csrf<input type="password" name="password" class="form-control form-control-sm" style="width:150px" minlength="8" placeholder="{{ translate('New password') }}" aria-label="{{ translate('New password') }}" required autocomplete="new-password"><button class="btn btn-soft btn-sm">{{ translate('Set') }}</button></form>
                    <form method="POST" action="{{ route('bus-partner.staff.toggle', $s->id) }}">@csrf<button class="btn btn-sm {{ $s->active ? 'btn-soft' : 'btn-dark' }}">{{ $s->active ? translate('Switch off') : translate('Switch on') }}</button></form>
                @endif
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('bus-partner.staff.store') }}" class="b-card p-3" style="flex:1 1 300px;min-width:0" autocomplete="off">
        @csrf
        <h2 class="b-h mb-2">{{ translate('Add someone') }}</h2>
        <div class="mb-2"><label class="form-label small fw-semibold" for="s_name">{{ translate('Name') }}</label><input id="s_name" name="name" class="form-control" required maxlength="80" value="{{ old('name') }}"></div>
        <div class="mb-2"><label class="form-label small fw-semibold" for="s_email">{{ translate('Email (optional)') }}</label><input id="s_email" type="email" name="email" class="form-control" maxlength="120" value="{{ old('email') }}"><div class="b-hint">{{ translate('No email: leave it empty and they get a username.') }}</div></div>
        <div class="mb-2"><span class="form-label small fw-semibold d-block">{{ translate('Role') }}</span>
            @foreach($roleLabel as $k => $label)
                <label class="d-flex gap-2 align-items-start mb-1 small" style="cursor:pointer"><input type="radio" name="role" value="{{ $k }}" class="form-check-input mt-1" @checked(old('role', 'counter') === $k)><span><b>{{ $label }}</b><span class="d-block b-hint">{{ $roleHint[$k] }}</span></span></label>
            @endforeach</div>
        <div class="mb-3"><label class="form-label small fw-semibold" for="s_pass">{{ translate('Password') }}</label><input id="s_pass" type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password"><div class="b-hint">{{ translate('At least 8 characters. Give it to them yourself.') }}</div></div>
        <button class="btn btn-primary w-100">{{ translate('Add') }}</button>
    </form>
</div>
@endsection
