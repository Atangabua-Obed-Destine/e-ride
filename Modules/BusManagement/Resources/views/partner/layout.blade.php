@php
    use Modules\BusManagement\Entities\BusAgency;
    use Modules\BusManagement\Entities\BusStaff;
    $role = $busStaff->role;
    // Counter screens: the manager and counter agents (not boarding agents, not the accountant).
    $desk = in_array($role, [BusStaff::MANAGER, BusStaff::COUNTER], true);
    // The menus, grouped (Counter, Trips, Money, Team; Modules/BusManagement/Lib/PortalMenu.php): the
    // phone's row of links and, on a wide screen, the menus, icon rail and "Go to…" of the desktop
    // workspace (resources/views/layouts/partials/desktop.blade.php, posinnova's).
    $menus = \Modules\BusManagement\Lib\PortalMenu::branch($busAgency, $role);
    $nav = \Modules\BusManagement\Lib\PortalMenu::flat($menus);
    $badgeOf = ['bus-partner.today' => 'requests', 'bus-partner.paper' => 'paper'];
    $deskCfg = [
        'home' => route(\Modules\BusManagement\Lib\PortalMenu::branchHome($role)),
        'brand' => $busAgency->displayName(),
        'who' => auth()->id() . ':' . $busAgency->id,
        'suffix' => ' · ' . $busAgency->displayName(),
        'place' => ['bi-shop', $busAgency->displayName() . ' · ' . $busAgency->origin_city],
        'user' => trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) . ' · ' . translate(ucfirst($busStaff->role)),
        // Every accounting screen belongs to the one "Accounting" tab.
        'alias' => ['partner/accounting' => 'partner/bus-agency/accounting'],
        'side' => array_map(fn ($m) => ['heading' => $m[1], 'links' => array_map(fn ($l) => [route($l[0]), $l[1], $l[2], request()->routeIs($l[0] . '*'), $badgeOf[$l[0]] ?? null], $m[2])], $menus),
    ];
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
    @include('layouts.partials.desktop', ['part' => 'head'])
    {{-- busToast, busPost, busDesk: in the head, for the scripts of the page itself. --}}
    @include('busmanagement::partner._portal_js')
    @stack('head')
</head>
<body>
@include('layouts.partials.desktop', ['part' => 'titlebar', 'desk' => $deskCfg])
<div class="thetop">
@include('layouts.partials.desktop', ['part' => 'side', 'desk' => $deskCfg, 'piClass' => 'pi-desk-only', 'piLabel' => translate('Partner_menu')])
<main>
<header class="b-top" id="pi-header">
    <div class="b-top__in">
        <div class="b-bar">
            @include('layouts.partials.desktop', ['part' => 'toolbar'])
            <div class="b-logo">@if($busAgency->logo)<img src="{{ dynamicStorage('storage/app/public/bus/logo/' . $busAgency->logo) }}" alt="">@else{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(preg_replace('/[^A-Za-z]/', '', $busAgency->name), 0, 2)) }}@endif</div>
            <div class="b-name text-truncate">{{ $busAgency->displayName() }}<small>{{ translate('Partner portal') }} · {{ $busAgency->origin_city }}</small></div>
            <span class="b-pill {{ $statusLabel[1] }} ms-auto">{{ $statusLabel[0] }}</span>
            @include('layouts.partials.desktop', ['part' => 'go', 'piClass' => 'text-white-50'])
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
@include('layouts.partials.desktop', ['part' => 'tabs'])

<div class="b-main" id="scrollable-container">
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
</div>
</main>
</div>

<script src="{{ dynamicAsset('public/assets/admin-module/js/bootstrap.bundle.min.js') }}"></script>
@include('busmanagement::partner._seatplan_js')
@if(Route::has('bus-partner.today') && $desk)
<script>
    // The menu badge: seat requests waiting for an answer (Today refreshes it faster itself).
    window.busSetBadge = function (n) {
        document.querySelectorAll('[data-requests-badge]').forEach(function (b) { b.textContent = n; b.style.display = n > 0 ? 'inline-flex' : 'none'; });
        document.querySelectorAll('#side-bar [data-badge="requests"]').forEach(function (a) { a.setAttribute('data-count', n > 0 ? n : ''); });
        document.title = (n > 0 ? '(' + n + ') ' : '') + document.title.replace(/^\(\d+\) /, '');
    };
    // Not inside a workspace tab: the window around it counts them already.
    if (!window.busTodayPage && !document.documentElement.classList.contains('pi-framed')) {
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
@include('layouts.partials.desktop', ['part' => 'status', 'desk' => $deskCfg])
</body>
</html>
