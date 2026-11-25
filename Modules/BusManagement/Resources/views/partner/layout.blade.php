@php
    use Modules\BusManagement\Entities\BusAgency;
    use Modules\BusManagement\Entities\BusStaff;
    $role = $busStaff->role;
    // Counter screens: the manager and counter agents (not boarding agents, not the accountant).
    $desk = in_array($role, [BusStaff::MANAGER, BusStaff::COUNTER], true);
    $paperOn = $desk && Route::has('bus-partner.paper') && \Modules\BusManagement\Service\PaperService::on($busAgency);
    $nav = array_values(array_filter([
        Route::has('bus-partner.today') && $desk ? ['bus-partner.today', 'bi-bell', translate('Today')] : null,
        Route::has('bus-partner.sell') && $desk ? ['bus-partner.sell', 'bi-ticket-perforated', translate('Quick sell')] : null,
        Route::has('bus-partner.collect') && $desk && \Modules\BusManagement\Lib\BusSettings::rules($busAgency->rules)['tickets'] === 'collect'
            ? ['bus-partner.collect', 'bi-printer', translate('Collect')] : null,
        $paperOn ? ['bus-partner.paper', 'bi-journal-check', translate('Paper sheets')] : null,
        Route::has('bus-partner.boarding') && $role !== BusStaff::ACCOUNTANT ? ['bus-partner.boarding', 'bi-qr-code-scan', translate('Boarding')] : null,
        ['bus-partner.departures', 'bi-calendar-week', translate('Departures')],
        $role === BusStaff::MANAGER ? ['bus-partner.destinations', 'bi-signpost-split', translate('Destinations')] : null,
        $role === BusStaff::MANAGER ? ['bus-partner.fleet', 'bi-bus-front', translate('Fleet')] : null,
        $role === BusStaff::MANAGER ? ['bus-partner.payments', 'bi-wallet2', translate('Payments')] : null,
        // Accounting: the accounting module of all our systems (/partner/accounting), opened on this branch's books.
        in_array($role, [BusStaff::MANAGER, BusStaff::ACCOUNTANT], true) && Route::has('bus-partner.accounting') ? ['bus-partner.accounting', 'bi-journal-text', translate('Accounting')] : null,
        $role === BusStaff::MANAGER ? ['bus-partner.staff', 'bi-people', translate('Staff')] : null,
        $desk ? ['bus-partner.share', 'bi-share', translate('Share')] : null,
    ]));
    $statusLabel = match (true) {
        $busAgency->status === BusAgency::LIVE && $busAgency->online_paused_at !== null => [translate('App sales paused'), 'is-paused'],
        default => match ($busAgency->status) {
        BusAgency::LIVE => [translate('Taking bookings'), 'is-live'],
        BusAgency::PAUSED => [translate('Online booking paused'), 'is-paused'],
        default => [translate('Setting up'), 'is-setup'],
        },
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#111114">
    <title>@yield('title') · {{ $busAgency->displayName() }}</title>
    <link rel="icon" href="{{ dynamicAsset('public/assets/admin-module/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap-icons.min.css') }}">
    @include('busmanagement::partner._portal_style')
    @stack('head')
</head>
<body>
<header class="b-top">
    <div class="b-top__in">
        <div class="b-bar">
            <div class="b-logo">@if($busAgency->logo)<img src="{{ dynamicStorage('storage/app/public/bus/logo/' . $busAgency->logo) }}" alt="">@else{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(preg_replace('/[^A-Za-z]/', '', $busAgency->name), 0, 2)) }}@endif</div>
            <div class="b-name text-truncate">{{ $busAgency->displayName() }}<small>{{ translate('Partner portal') }} · {{ $busAgency->origin_city }}</small></div>
            <span class="b-pill {{ $statusLabel[1] }} ms-auto">{{ $statusLabel[0] }}</span>
            <span class="d-none d-md-inline small text-white-50">{{ auth()->user()->first_name }} · {{ translate(ucfirst($busStaff->role)) }}</span>
            <form action="{{ route('bus-partner.logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm text-white-50 px-1" title="{{ translate('Sign_out') }}" aria-label="{{ translate('Sign_out') }}"><i class="bi bi-box-arrow-right fs-5"></i></button>
            </form>
        </div>
        <nav class="b-nav" aria-label="{{ translate('Partner_menu') }}">
            @foreach($nav as [$routeName, $icon, $label])
                <a href="{{ route($routeName) }}" class="{{ request()->routeIs($routeName . '*') ? 'active' : '' }}"><i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}@if($routeName === 'bus-partner.today')<span class="b-badge" data-requests-badge></span>@elseif($routeName === 'bus-partner.paper')<span class="b-badge" data-paper-badge></span>@endif</a>
            @endforeach
        </nav>
    </div>
</header>

<main class="b-main">
    @if($busAgency->status === BusAgency::PAUSED)
        <div class="alert alert-danger">{{ translate('Passengers cannot book you in the E-Ride app right now. Your counter, boarding and the tickets already sold keep working.') }}
            @if($busAgency->pause_note)<b>{{ $busAgency->pause_note }}</b>@endif</div>
    @elseif($busAgency->status === BusAgency::SETUP && $busStaff->role === BusStaff::MANAGER)
        <div class="alert alert-warning">{{ translate('Not bookable in the app yet. Connect your Fapshi account in Payments and open a departure with a bus: E-Ride then shows you to passengers.') }}</div>
    @endif
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if($desk && Route::has('bus-partner.paper'))
        @include('busmanagement::partner._paper_alarm')
    @endif
    @yield('content')
</main>

<script src="{{ dynamicAsset('public/assets/admin-module/js/bootstrap.bundle.min.js') }}"></script>
@include('busmanagement::partner._portal_js')
@include('busmanagement::partner._seatplan_js')
@if(Route::has('bus-partner.today') && $desk)
<script>
    // The menu badge: seat requests waiting for an answer (Today refreshes it faster itself).
    window.busSetBadge = function (n) {
        document.querySelectorAll('[data-requests-badge]').forEach(function (b) { b.textContent = n; b.style.display = n > 0 ? 'inline-flex' : 'none'; });
        document.title = (n > 0 ? '(' + n + ') ' : '') + document.title.replace(/^\(\d+\) /, '');
    };
    if (!window.busTodayPage) {
        (function poll() {
            fetch(@json(route('bus-partner.today.feed', ['count' => 1])), {headers: {'Accept': 'application/json'}})
                .then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d) busSetBadge(d.count); }).catch(function () {})
                .finally(function () { setTimeout(poll, 30000); });
        })();
    }
</script>
@endif
@stack('script')
@if (session('toast'))
    <script>busToast(@json(session('toast')));</script>
@endif
</body>
</html>
