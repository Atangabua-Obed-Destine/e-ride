@extends('adminmodule::layouts.master')

@section('title', translate('Hotel partners'))

@php
    use Modules\HotelManagement\Entities\Hotel;
    $statusTag = [
        Hotel::SETUP => ['bg-info-subtle text-info-emphasis', translate('Setting up')],
        Hotel::LIVE => ['bg-success-subtle text-success-emphasis', translate('Live')],
        Hotel::PAUSED => ['bg-danger-subtle text-danger-emphasis', translate('Paused')],
    ];
@endphp

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-end gap-3 mb-3">
            <div class="flex-grow-1">
                <h2 class="fs-22 mb-1">{{ translate('Hotel partners') }}</h2>
                <p class="fs-13 text-muted mb-0">{{ translate('Free for hotels, and no money in E-Ride. Partner hotels show their rooms and what is free, answer guests in the app, keep rooms for them, and E-Ride takes the guests there.') }}</p>
            </div>
            <div class="d-flex gap-2">
                <div class="card px-3 py-2"><div class="fs-20 fw-bold">{{ $totals['live'] }}</div><div class="fs-12 text-muted">{{ translate('live partners') }}</div></div>
                <div class="card px-3 py-2"><div class="fs-20 fw-bold">{{ $totals['asked'] }}</div><div class="fs-12 text-muted">{{ translate('questions, 30 days') }}</div></div>
                <div class="card px-3 py-2"><div class="fs-20 fw-bold text-success">{{ $totals['arrived'] }}</div><div class="fs-12 text-muted">{{ translate('E-Ride guests arrived, 30 days') }}</div></div>
            </div>
        </div>
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if(session('hotel_login'))
            @php $l = session('hotel_login'); @endphp
            <div class="alert alert-success">
                <div class="fw-bold mb-1">{{ translate(key: '{hotel} is a partner hotel now.', replace: ['hotel' => $l['hotel']]) }}</div>
                {{ translate('Give the manager this address and login, with the password you chose. It is shown only now.') }}
                <div class="mt-1"><code>{{ $l['url'] }}</code> · <b>{{ $l['login'] }}</b></div>
            </div>
        @endif
        @if(!$settings['enabled'])
            <div class="alert alert-warning">{{ translate('Hotel partners are off in the app: guests see hotels as before. Partners can still set up. Turn it on in the settings below when the first one is ready.') }}</div>
        @endif

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Partner hotels') }}</h5></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light"><tr>
                                <th>{{ translate('Hotel') }}</th><th>{{ translate('Status') }}</th><th class="text-end">{{ translate('Rooms') }}</th>
                                <th>{{ translate('Rooms updated') }}</th><th>{{ translate('Answers in') }}</th><th class="text-end">{{ translate('Questions') }}</th><th class="text-end">{{ translate('Arrived') }}</th><th></th>
                            </tr></thead>
                            <tbody>
                            @forelse($rows as $r)
                                @php $h = $r['hotel']; @endphp
                                <tr>
                                    <td><div class="fw-semibold">{{ $h->name }}</div><div class="fs-12 text-muted">{{ $h->place?->address }}</div>
                                        {{-- Room service: the hotel's own restaurant on Food & Shops. --}}
                                        <form method="POST" action="{{ route('admin.hotel.restaurant', $h->id) }}" class="d-flex gap-1 mt-1">@csrf
                                            <label class="visually-hidden" for="rs{{ $h->id }}">{{ translate('Restaurant (room service)') }}</label>
                                            <select id="rs{{ $h->id }}" name="store_id" class="form-select form-select-sm" style="max-width:200px">
                                                <option value="">{{ translate('No restaurant (room service off)') }}</option>
                                                @foreach($stores as $st)<option value="{{ $st->id }}" @selected($h->restaurant_store_id === $st->id)>{{ $st->name }}</option>@endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-secondary">{{ translate('Save') }}</button>
                                        </form>
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusTag[$h->status][0] }}">{{ $statusTag[$h->status][1] }}</span>
                                        @if($r['slow'])<span class="badge bg-warning-subtle text-warning-emphasis">{{ translate(key: '{n} waiting too long', replace: ['n' => $r['slow']]) }}</span>@endif
                                        @if($h->status === Hotel::SETUP)<div class="fs-12 text-muted">{{ translate(key: '{n} of 4 steps done', replace: ['n' => count(array_filter($r['checklist']))]) }}</div>@endif
                                        @if($h->pause_note)<div class="fs-12 text-muted">{{ $h->pause_note }}</div>@endif
                                    </td>
                                    <td class="text-end">{{ $r['rooms'] }}</td>
                                    <td class="{{ $r['fresh'] ? '' : 'text-danger fw-semibold' }}">{{ $h->board_touched_at ? $h->board_touched_at->diffForHumans() : translate('Never') }}</td>
                                    <td>{{ $r['answer'] ? translate(key: '{n} min', replace: ['n' => $r['answer']]) : '-' }}</td>
                                    <td class="text-end">{{ $r['asked'] }}</td>
                                    <td class="text-end fw-semibold">{{ $r['arrived'] }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.hotel.pause', $h->id) }}" class="d-flex gap-1 justify-content-end">
                                            @csrf
                                            @if($h->status === Hotel::PAUSED)
                                                <input type="hidden" name="pause" value="0"><button class="btn btn-sm btn-outline-success">{{ translate('Resume') }}</button>
                                            @else
                                                <input type="hidden" name="pause" value="1">
                                                <label class="visually-hidden" for="pn{{ $h->id }}">{{ translate('Reason') }}</label>
                                                <input id="pn{{ $h->id }}" name="note" maxlength="300" class="form-control form-control-sm" style="width:150px" placeholder="{{ translate('Reason (they see it)') }}">
                                                <button class="btn btn-sm btn-outline-danger">{{ translate('Pause') }}</button>
                                            @endif
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4">{{ translate('No partner hotel yet. Make one from a verified place to stay, on the right.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer fs-12 text-muted">{{ translate('Rooms updated in red: the hotel has not touched its rooms lately, so guests see Ask the reception instead of a number.') }}</div>
                </div>
            </div>

            <div class="col-xl-4">
                <form method="POST" action="{{ route('admin.hotel.store') }}" class="card mb-3">
                    @csrf
                    <div class="card-header"><h5 class="mb-0">{{ translate('Make a hotel a partner') }}</h5></div>
                    <div class="card-body">
                        <p class="fs-12 text-muted">{{ translate('Start from a verified place to stay. To add a new one, create it first in Verified listings (from Google or by hand), after checking the place and the manager.') }}</p>
                        <label class="form-label" for="place_id">{{ translate('Verified place to stay') }}</label>
                        <select id="place_id" name="place_id" class="form-select mb-3" required>
                            <option value="">{{ translate('Choose') }}</option>
                            @foreach($candidates as $p)<option value="{{ $p->id }}" @selected(old('place_id') === $p->id)>{{ $p->name }}@if($p->address) · {{ \Illuminate\Support\Str::limit($p->address, 40) }}@endif</option>@endforeach
                        </select>
                        <label class="form-label" for="manager_name">{{ translate('Manager name') }}</label>
                        <input id="manager_name" name="manager_name" maxlength="80" required class="form-control mb-3" value="{{ old('manager_name') }}">
                        <label class="form-label" for="email">{{ translate('Manager email (optional: without one, a username is made)') }}</label>
                        <input id="email" type="email" name="email" maxlength="120" class="form-control mb-3" value="{{ old('email') }}">
                        <label class="form-label" for="password">{{ translate('Password for the manager (8 characters or more)') }}</label>
                        <input id="password" type="password" name="password" minlength="8" maxlength="100" required class="form-control" autocomplete="new-password">
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary">{{ translate('Make partner and open the portal') }}</button></div>
                </form>

                <form method="POST" action="{{ route('admin.hotel.settings') }}" class="card">
                    @csrf
                    <div class="card-header"><h5 class="mb-0">{{ translate('Hotel settings') }}</h5></div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="hotel_enabled" name="enabled" value="1" @checked($settings['enabled'])><label class="form-check-label fw-semibold" for="hotel_enabled">{{ translate('Hotel partners on in the app') }}</label></div>
                        @foreach([
                            'stale_hours' => translate('Hours without a rooms update before guests stop seeing counts'),
                            'slow_hours' => translate('Hours a guest waits before the hotel is flagged as slow'),
                            'max_rooms' => translate('Rooms in one question, at most'),
                            'max_nights' => translate('Nights in one question, at most'),
                            'days_ahead' => translate('How many days ahead guests can ask'),
                            'max_open' => translate('Questions a guest can have going at once'),
                        ] as $key => $label)
                            <label class="form-label fs-13" for="s_{{ $key }}">{{ $label }}</label>
                            <input id="s_{{ $key }}" type="number" name="{{ $key }}" class="form-control mb-2" required value="{{ $settings[$key] }}">
                        @endforeach
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary">{{ translate('Save') }}</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
