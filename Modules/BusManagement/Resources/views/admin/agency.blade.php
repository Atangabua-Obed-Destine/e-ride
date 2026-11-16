@extends('adminmodule::layouts.master')

@section('title', $agency->name)

@php
    use Modules\BusManagement\Entities\BusAgency;
    use Modules\BusManagement\Service\AgencyService;
    $checks = $agency->checks ?? [];
    $checkLabel = [
        'licence' => [translate('Transport licence seen'), translate('The authorisation from the Ministry of Transport. Add a photo: it stays private, for E-Ride staff only.')],
        'office' => [translate('Office visited'), translate('The office pin is right and buses were seen in the yard.')],
        'manager_id' => [translate('Manager ID seen'), translate('The person who will hold the portal login.')],
    ];
    $checking = $agency->status === BusAgency::CHECKING;
@endphp

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <a href="{{ route('admin.bus.index') }}" class="fs-13">&larr; {{ translate('Bus agencies') }}</a>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-3">
            <h2 class="fs-22 mb-0 flex-grow-1">{{ $agency->name }}</h2>
            @if($agency->status === BusAgency::PAUSED)<span class="badge bg-danger-subtle text-danger-emphasis">{{ translate('Online booking paused') }}{{ $agency->pause_note ? ': ' . $agency->pause_note : '' }}</span>@endif
            @if($agency->status === BusAgency::LIVE)<span class="badge bg-success-subtle text-success-emphasis">{{ translate('Bookable') }}</span>@endif
            @if($agency->status === BusAgency::SETUP)<span class="badge bg-info-subtle text-info-emphasis">{{ translate('Portal open, setting up') }}</span>@endif
        </div>
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if(session('portal_login'))
            <div class="alert alert-success">{{ translate(key: 'Verified. The manager signs in at {url} with {login} and the password you set. Give it to them yourself.', replace: session('portal_login')) }}</div>
        @endif

        <div class="row g-3">
            <div class="col-lg-7">
                @if($checking)
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('1. Check the agency') }}</h5></div>
                    <div class="card-body">
                        @foreach(AgencyService::CHECKS as $c)
                            <div class="d-flex gap-3 align-items-start py-2 border-bottom">
                                <form method="POST" action="{{ route('admin.bus.check', $agency->id) }}">@csrf<input type="hidden" name="check" value="{{ $c }}"><input type="hidden" name="done" value="{{ isset($checks[$c]) ? 0 : 1 }}">
                                    <button class="btn btn-sm {{ isset($checks[$c]) ? 'btn-success' : 'btn-outline-secondary' }}" aria-label="{{ $checkLabel[$c][0] }}" aria-pressed="{{ isset($checks[$c]) ? 'true' : 'false' }}"><i class="bi {{ isset($checks[$c]) ? 'bi-check-lg' : 'bi-square' }}"></i></button></form>
                                <div class="flex-grow-1"><div class="fw-semibold">{{ $checkLabel[$c][0] }}</div><div class="fs-12 text-muted">{{ $checkLabel[$c][1] }}</div>
                                    @if(isset($checks[$c]))<div class="fs-12 text-success">{{ \Illuminate\Support\Carbon::parse($checks[$c]['at'])->format('d M Y, H:i') }}</div>@endif
                                    @if($c === 'licence')
                                        <form method="POST" action="{{ route('admin.bus.licence', $agency->id) }}" enctype="multipart/form-data" class="d-flex gap-2 mt-2">@csrf
                                            <input type="file" name="licence" class="form-control form-control-sm" accept="image/*,application/pdf" required aria-label="{{ translate('Licence photo') }}">
                                            <button class="btn btn-sm btn-outline-primary">{{ translate('Upload') }}</button></form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if($agency->licence_file)<a href="{{ route('admin.bus.licence.file', $agency->id) }}" target="_blank" rel="noopener" class="fs-13 d-inline-block mt-2">{{ translate('See the licence photo') }}</a>@endif
                    </div>
                </div>
                @endif

                <form method="POST" action="{{ route('admin.bus.details', $agency->id) }}" class="card mb-3">
                    @csrf
                    <div class="card-header"><h5 class="mb-0">{{ $checking ? translate('2. Departure city and commission') : translate('Agency and commission') }}</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="a_name">{{ translate('Name') }}</label><input id="a_name" name="name" class="form-control" required maxlength="120" value="{{ old('name', $agency->name) }}"></div>
                            <div class="col-md-6"><label class="form-label" for="a_city">{{ translate('Departure city (all its departures leave from here)') }}</label><input id="a_city" name="origin_city" class="form-control" required maxlength="80" value="{{ old('origin_city', $agency->origin_city) }}"></div>
                            <div class="col-md-8"><label class="form-label" for="a_addr">{{ translate('Office address') }}</label><input id="a_addr" name="address" class="form-control" maxlength="191" value="{{ old('address', $agency->address) }}"></div>
                            <div class="col-md-4"><label class="form-label" for="a_phone">{{ translate('Phone') }}</label><input id="a_phone" name="phone" class="form-control" maxlength="30" value="{{ old('phone', $agency->phone) }}"></div>
                            <div class="col-md-6"><label class="form-label" for="a_app">{{ translate('Commission on app tickets (% of the fare)') }}</label><input id="a_app" type="number" step="0.01" min="0" max="50" name="commission_app_pct" class="form-control" required value="{{ old('commission_app_pct', $agency->commission_app_pct) }}"></div>
                            <div class="col-md-6"><label class="form-label" for="a_ctr">{{ translate('Commission on counter tickets (% of the fare)') }}</label><input id="a_ctr" type="number" step="0.01" min="0" max="50" name="commission_counter_pct" class="form-control" required value="{{ old('commission_counter_pct', $agency->commission_counter_pct) }}"></div>
                            <div class="col-12"><label class="form-label" for="a_about">{{ translate('About (shown to passengers)') }}</label><textarea id="a_about" name="about" class="form-control" rows="2" maxlength="600">{{ old('about', $agency->about) }}</textarea></div>
                        </div>
                        <p class="fs-12 text-muted mt-2 mb-0">{{ translate(key: 'Defaults for new agencies: {app}% app, {counter}% counter (Bus settings).', replace: ['app' => $settings['commission_app_pct'], 'counter' => $settings['commission_counter_pct']]) }}</p>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-outline-primary">{{ translate('Save') }}</button></div>
                </form>

                @if($checking)
                <form method="POST" action="{{ route('admin.bus.verify', $agency->id) }}" class="card mb-3" autocomplete="off">
                    @csrf
                    <div class="card-header"><h5 class="mb-0">{{ translate('3. The manager, then open the portal') }}</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="m_name">{{ translate('Manager name') }}</label><input id="m_name" name="name" class="form-control" required maxlength="80" value="{{ old('name') }}"></div>
                            <div class="col-md-6"><label class="form-label" for="m_email">{{ translate('Manager email (to sign in)') }}</label><input id="m_email" type="email" name="email" class="form-control" required maxlength="120" value="{{ old('email') }}"></div>
                            <div class="col-md-6"><label class="form-label" for="m_pass">{{ translate('Password to give them') }}</label><input id="m_pass" type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password"></div>
                        </div>
                        <p class="fs-12 text-muted mt-2 mb-0">{{ translate('The portal opens at once. The agency becomes bookable by itself once it has connected its own Fapshi account and opened a departure with a bus.') }}</p>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary">{{ translate('Verify and open the portal') }}</button></div>
                </form>
                @endif
            </div>

            <div class="col-lg-5">
                <div class="card mb-3"><div class="card-body">
                    <h5 class="mb-3">{{ translate('Setup') }}</h5>
                    @foreach([
                        [translate('Portal open'), !$checking],
                        [translate('Fapshi connected'), (bool)$agency->fapshi_ok_at],
                        [translate(key: 'Buses: {n}', replace: ['n' => $agency->vehicles_count]), $agency->vehicles_count > 0],
                        [translate(key: 'Destinations: {n}', replace: ['n' => $agency->destinations_count]), $agency->destinations_count > 0],
                        [translate(key: 'Timetable lines: {n}', replace: ['n' => $agency->schedules_count]), $agency->schedules_count > 0],
                    ] as [$label, $done])
                        <div class="d-flex align-items-center gap-2 py-1"><i class="bi {{ $done ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' }}"></i><span>{{ $label }}</span></div>
                    @endforeach
                    @if($managers->isNotEmpty())
                        <div class="fs-13 text-muted mt-2">{{ translate('Managers') }}: {{ $managers->map(fn($m) => $m->user?->first_name . ' (' . AgencyService::loginOf($m->user) . ')')->implode(', ') }}</div>
                    @endif
                </div></div>

                @if(!$checking)
                <div class="card mb-3"><div class="card-body">
                    <h5 class="mb-1">{{ translate('Commission statements') }}</h5>
                    <p class="fs-12 text-muted">{{ translate(key: 'This month so far: {total} ({app} app and {counter} counter tickets).', replace: ['total' => getCurrencyFormat($thisMonth['total']), 'app' => $thisMonth['app']['tickets'], 'counter' => $thisMonth['counter']['tickets']]) }}</p>
                    @forelse($statements as $s)
                        <div class="py-2 border-top">
                            <div class="d-flex align-items-center gap-2"><span class="flex-grow-1 fw-semibold">{{ $s->month->translatedFormat('F Y') }}</span><b>{{ getCurrencyFormat($s->total) }}</b>
                                @if($s->status === 'paid')<span class="badge bg-success-subtle text-success-emphasis">{{ translate('Paid') }}</span>
                                @else<span class="badge {{ $s->due_on->lt(today()) ? 'bg-danger-subtle text-danger-emphasis' : 'bg-warning-subtle text-warning-emphasis' }}">{{ translate(key: 'Due {date}', replace: ['date' => $s->due_on->format('d M')]) }}</span>@endif</div>
                            <div class="fs-12 text-muted">{{ translate(key: '{app} app and {counter} counter tickets', replace: ['app' => $s->app_tickets, 'counter' => $s->counter_tickets]) }}{{ $s->reference ? ' · ' . $s->reference : '' }}</div>
                            @if($s->status === 'due')
                                <form method="POST" action="{{ route('admin.bus.statement.paid', $s->id) }}" class="d-flex gap-2 mt-1">@csrf
                                    <input name="reference" class="form-control form-control-sm" required maxlength="100" placeholder="{{ translate('Payment reference') }}" aria-label="{{ translate('Payment reference') }}">
                                    <button class="btn btn-sm btn-outline-success text-nowrap">{{ translate('Mark paid') }}</button></form>
                            @endif
                        </div>
                    @empty
                        <div class="fs-12 text-muted">{{ translate('No statement yet. The first comes on the 1st of next month.') }}</div>
                    @endforelse
                </div></div>

                <form method="POST" action="{{ route('admin.bus.pause', $agency->id) }}" class="card">
                    @csrf
                    <div class="card-body">
                        <h5 class="mb-2">{{ $agency->status === BusAgency::PAUSED ? translate('Online booking is paused') : translate('Pause online booking') }}</h5>
                        <p class="fs-12 text-muted">{{ translate('Passengers cannot book this agency in the app while paused. Its counter, boarding and tickets already sold keep working.') }}</p>
                        @if($agency->status === BusAgency::PAUSED)
                            <input type="hidden" name="paused" value="0"><button class="btn btn-success">{{ translate('Resume online booking') }}</button>
                        @else
                            <input type="hidden" name="paused" value="1">
                            <input name="note" class="form-control mb-2" maxlength="300" placeholder="{{ translate('Why (the agency sees this)') }}" aria-label="{{ translate('Why') }}">
                            <button class="btn btn-outline-danger">{{ translate('Pause') }}</button>
                        @endif
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
