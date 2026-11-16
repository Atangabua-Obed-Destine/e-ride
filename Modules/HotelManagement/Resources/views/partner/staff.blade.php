@extends('hotelmanagement::partner.layout')
@section('title', translate('Staff'))

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="h-card p-3">
            <h1 class="h-h mb-1">{{ translate('Staff') }}</h1>
            <div class="h-hint mb-3">{{ translate('Everyone has their own login. Reception answers guests and runs the rooms board; managers also set up rooms and staff.') }}</div>
            @foreach($staff as $s)
                <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-top">
                    <div class="flex-grow-1">
                        <div class="fw-bold">{{ $s->user?->first_name }}
                            <span class="h-tag {{ $s->role === 'manager' ? 'blue' : 'mute' }}">{{ $s->role === 'manager' ? translate('Manager') : translate('Reception') }}</span>
                            @unless($s->active)<span class="h-tag bad">{{ translate('Access off') }}</span>@endunless
                        </div>
                        <div class="small text-muted">{{ str_replace('@' . \Modules\HotelManagement\Service\PartnerService::USERNAME_DOMAIN, '', (string)$s->user?->email) }}</div>
                    </div>
                    <form method="POST" action="{{ route('hotel-partner.staff.password', $s->id) }}" class="d-flex gap-1">
                        @csrf
                        <label for="pw{{ $s->id }}" class="visually-hidden">{{ translate('New password') }}</label>
                        <input id="pw{{ $s->id }}" type="password" name="password" minlength="8" maxlength="100" required class="form-control form-control-sm" style="width:140px" placeholder="{{ translate('New password') }}" autocomplete="new-password">
                        <button class="btn btn-soft btn-sm">{{ translate('Set') }}</button>
                    </form>
                    @if($s->id !== $hotelStaff->id)
                        <form method="POST" action="{{ route('hotel-partner.staff.toggle', $s->id) }}">@csrf
                            <button class="btn btn-soft btn-sm">{{ $s->active ? translate('Turn off') : translate('Turn on') }}</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    <div class="col-lg-5">
        <form method="POST" action="{{ route('hotel-partner.staff.store') }}" class="h-card p-3">
            @csrf
            <h2 class="h-h mb-2">{{ translate('Add someone') }}</h2>
            <label for="name" class="small text-muted">{{ translate('Name') }}</label>
            <input id="name" name="name" maxlength="80" required class="form-control mb-2" value="{{ old('name') }}">
            <label for="email" class="small text-muted">{{ translate('Email (optional: without one, E-Ride makes a username)') }}</label>
            <input id="email" type="email" name="email" maxlength="120" class="form-control mb-2" value="{{ old('email') }}">
            <div class="small text-muted mb-1">{{ translate('Role') }}</div>
            <div class="d-flex gap-2 mb-2">
                <span class="chip-opt"><input type="radio" name="role" id="r_rec" value="reception" @checked(old('role', 'reception') === 'reception')><label for="r_rec">{{ translate('Reception') }}</label></span>
                <span class="chip-opt"><input type="radio" name="role" id="r_man" value="manager" @checked(old('role') === 'manager')><label for="r_man">{{ translate('Manager') }}</label></span>
            </div>
            <label for="password" class="small text-muted">{{ translate('Password (8 characters or more)') }}</label>
            <input id="password" type="password" name="password" minlength="8" maxlength="100" required class="form-control mb-3" autocomplete="new-password">
            <button class="btn btn-primary w-100" style="min-height:46px">{{ translate('Add') }}</button>
            <div class="h-hint mt-2">{{ translate('They sign in at') }} <b>{{ route('hotel-partner.login') }}</b></div>
        </form>
    </div>
</div>
@endsection
