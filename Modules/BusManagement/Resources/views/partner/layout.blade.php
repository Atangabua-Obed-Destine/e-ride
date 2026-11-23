@php
    use Modules\BusManagement\Entities\BusAgency;
    use Modules\BusManagement\Entities\BusStaff;
    $role = $busStaff->role;
    $nav = array_values(array_filter([
        Route::has('bus-partner.today') && $role !== BusStaff::BOARDING ? ['bus-partner.today', 'bi-bell', translate('Today')] : null,
        Route::has('bus-partner.sell') && $role !== BusStaff::BOARDING ? ['bus-partner.sell', 'bi-ticket-perforated', translate('Quick sell')] : null,
        Route::has('bus-partner.collect') && $role !== BusStaff::BOARDING && \Modules\BusManagement\Lib\BusSettings::rules($busAgency->rules)['tickets'] === 'collect'
            ? ['bus-partner.collect', 'bi-printer', translate('Collect')] : null,
        Route::has('bus-partner.boarding') ? ['bus-partner.boarding', 'bi-qr-code-scan', translate('Boarding')] : null,
        ['bus-partner.departures', 'bi-calendar-week', translate('Departures')],
        $role === BusStaff::MANAGER ? ['bus-partner.destinations', 'bi-signpost-split', translate('Destinations')] : null,
        $role === BusStaff::MANAGER ? ['bus-partner.fleet', 'bi-bus-front', translate('Fleet')] : null,
        $role === BusStaff::MANAGER ? ['bus-partner.payments', 'bi-wallet2', translate('Payments')] : null,
        $role === BusStaff::MANAGER ? ['bus-partner.staff', 'bi-people', translate('Staff')] : null,
        $role !== BusStaff::BOARDING ? ['bus-partner.share', 'bi-share', translate('Share')] : null,
    ]));
    $statusLabel = match ($busAgency->status) {
        BusAgency::LIVE => [translate('Taking bookings'), 'is-live'],
        BusAgency::PAUSED => [translate('Online booking paused'), 'is-paused'],
        default => [translate('Setting up'), 'is-setup'],
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
    <title>@yield('title') · {{ $busAgency->name }}</title>
    <link rel="icon" href="{{ dynamicAsset('public/assets/admin-module/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap-icons.min.css') }}">
    <style>
        :root { --red: #e02020; --ink: #1d2d2b; --muted: #5f6b6a; --line: #e5e9e8; --bg: #f4f6f6; --ok: #15803d; --warn: #9a4a06; }
        * { -webkit-tap-highlight-color: transparent; }
        html, body { background: var(--bg); color: var(--ink); }
        body { font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .b-top { background: #111114; color: #fff; position: sticky; top: 0; z-index: 30; }
        .b-top__in { max-width: 1280px; margin: 0 auto; padding: 0 16px; }
        .b-bar { display: flex; align-items: center; gap: 12px; min-height: 58px; padding-top: env(safe-area-inset-top); }
        .b-logo { width: 34px; height: 34px; border-radius: 9px; background: #1e3a8a; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; flex: none; overflow: hidden; }
        .b-logo img { width: 100%; height: 100%; object-fit: cover; }
        .b-name { font-weight: 800; font-size: 15px; line-height: 1.15; min-width: 0; }
        .b-name small { display: block; font-weight: 500; font-size: 12px; color: #aeb9b8; }
        .b-pill { display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 13px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .b-pill.is-live { background: rgba(21, 128, 61, .22); color: #86efac; }
        .b-pill.is-setup { background: rgba(245, 158, 11, .2); color: #fcd34d; }
        .b-pill.is-paused { background: rgba(224, 32, 32, .22); color: #fca5a5; }
        .b-nav { display: flex; gap: 22px; overflow-x: auto; scrollbar-width: none; }
        .b-nav::-webkit-scrollbar { display: none; }
        .b-nav a { color: #c9d1d0; font-size: 13.5px; font-weight: 600; padding: 12px 2px; border-bottom: 3px solid transparent; white-space: nowrap; text-decoration: none; display: inline-flex; gap: 7px; align-items: center; position: relative; }
        .b-nav a.active { color: #fff; border-bottom-color: var(--red); }
        .b-nav .b-badge { background: var(--red); color: #fff; border-radius: 999px; font-size: 11px; min-width: 18px; height: 18px; padding: 0 5px; display: none; align-items: center; justify-content: center; font-weight: 800; }
        .b-main { max-width: 1280px; margin: 0 auto; padding: 18px 16px 40px; }
        .b-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; }
        .b-h { font-weight: 800; font-size: 16px; margin: 0; }
        .b-hint { font-size: 12.5px; color: var(--muted); line-height: 1.45; }
        .btn { border-radius: 10px; font-weight: 700; }
        .btn-primary { background: var(--red); border-color: var(--red); }
        .btn-primary:hover, .btn-primary:focus { background: #c41a1a; border-color: #c41a1a; }
        .btn-primary:disabled, .btn-primary.disabled { background: var(--red); border-color: var(--red); opacity: .45; }
        .btn-dark { background: #1d2d2b; border-color: #1d2d2b; }
        .btn-soft { background: #fff; border-color: #dde3e2; color: var(--ink); }
        .b-card .btn-soft { background: #f4f6f6; border-color: #f4f6f6; }
        .form-control, .form-select { border-radius: 10px; min-height: 42px; }
        .b-tag { display: inline-flex; align-items: center; gap: 5px; height: 22px; padding: 0 8px; border-radius: 11px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
        .b-tag.ok { background: #e9f7ef; color: var(--ok); }
        .b-tag.warn { background: #fff4e5; color: var(--warn); }
        .b-tag.bad { background: #fdecec; color: #b91c1c; }
        .b-tag.mute { background: #f1f3f3; color: #48615e; }
        .b-tag.blue { background: #e8eefc; color: #1d4ed8; }
        .b-toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); background: #111114; color: #fff; padding: 10px 16px; border-radius: 12px; font-weight: 600; z-index: 2000; max-width: calc(100% - 32px); box-shadow: 0 10px 30px rgba(0,0,0,.25); }
        .seg { display: inline-flex; border: 1px solid #dde3e2; border-radius: 10px; overflow: hidden; flex-wrap: wrap; }
        .seg input { position: absolute; opacity: 0; pointer-events: none; }
        .seg label { padding: 0 12px; height: 34px; display: flex; align-items: center; font-size: 13px; font-weight: 600; cursor: pointer; margin: 0; background: #fff; }
        .seg input:checked + label { background: #1d2d2b; color: #fff; }
        .seg input:focus-visible + label { outline: 2px solid var(--red); outline-offset: -2px; }
        /* Seat plans (shared by Fleet, Quick sell and Boarding) */
        .sp { display: inline-flex; flex-direction: column; gap: 4px; border: 2px solid #e3e8e7; border-radius: 26px 26px 12px 12px; padding: 10px; background: #fbfcfc; }
        .sp-row { display: flex; gap: 4px; justify-content: center; }
        .sp-cell { width: 34px; height: 32px; border-radius: 7px 7px 5px 5px; display: flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 700; flex: none; border: 0; padding: 0; }
        .sp-seat { background: #22c55e; color: #fff; }
        .sp-aisle { width: 14px; }
        .sp-driver { background: #48615e; color: #fff; border-radius: 50%; font-size: 9.5px; }
        .sp-door { background: #dbeafe; color: #1d4ed8; font-size: 10px; }
        .sp-cabin { display: flex; justify-content: space-between; background: #eef1f0; border-radius: 10px; padding: 6px 8px; margin-bottom: 4px; font-size: 11.5px; font-weight: 700; color: #48615e; }
        .sp-cabin b { color: #1d4ed8; }
        .sp-deck { font-size: 11.5px; font-weight: 700; color: var(--muted); text-align: center; margin: 4px 0; }
        .sp-seat.st-held { background: #f59e0b; }
        .sp-seat.st-app { background: #2563eb; }
        .sp-seat.st-counter { background: #1d2d2b; }
        .sp-seat.st-boarded { background: #9ca3af; }
        .sp-seat.st-kept { box-shadow: inset 0 0 0 3px #1d2d2b; }
        .sp-seat.st-picked { background: var(--red); transform: scale(1.08); }
        button.sp-seat:disabled { cursor: default; opacity: 1; }
        button.sp-seat:not(:disabled) { cursor: pointer; }
        button.sp-seat:focus-visible { outline: 3px solid #111; outline-offset: 1px; }
        .sp-legend { display: flex; flex-wrap: wrap; gap: 10px; font-size: 12px; color: var(--muted); }
        .sp-legend i { display: inline-block; width: 14px; height: 14px; border-radius: 4px; vertical-align: -2px; margin-right: 4px; }
        @media (max-width: 575px) { .sp-cell { width: 30px; height: 28px; } }
    </style>
    @stack('head')
</head>
<body>
<header class="b-top">
    <div class="b-top__in">
        <div class="b-bar">
            <div class="b-logo">@if($busAgency->logo)<img src="{{ dynamicStorage('storage/app/public/bus/logo/' . $busAgency->logo) }}" alt="">@else{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(preg_replace('/[^A-Za-z]/', '', $busAgency->name), 0, 2)) }}@endif</div>
            <div class="b-name text-truncate">{{ $busAgency->name }}<small>{{ translate('Partner portal') }} · {{ $busAgency->origin_city }}</small></div>
            <span class="b-pill {{ $statusLabel[1] }} ms-auto">{{ $statusLabel[0] }}</span>
            <span class="d-none d-md-inline small text-white-50">{{ auth()->user()->first_name }} · {{ translate(ucfirst($busStaff->role)) }}</span>
            <form action="{{ route('bus-partner.logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm text-white-50 px-1" title="{{ translate('Sign_out') }}" aria-label="{{ translate('Sign_out') }}"><i class="bi bi-box-arrow-right fs-5"></i></button>
            </form>
        </div>
        <nav class="b-nav" aria-label="{{ translate('Partner_menu') }}">
            @foreach($nav as [$routeName, $icon, $label])
                <a href="{{ route($routeName) }}" class="{{ request()->routeIs($routeName . '*') ? 'active' : '' }}"><i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}@if($routeName === 'bus-partner.today')<span class="b-badge" data-requests-badge></span>@endif</a>
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
    @yield('content')
</main>

<script src="{{ dynamicAsset('public/assets/admin-module/js/bootstrap.bundle.min.js') }}"></script>
<script>
    window.busToast = function (text) {
        var el = document.createElement('div');
        el.className = 'b-toast'; el.setAttribute('role', 'status'); el.textContent = text;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 3600);
    };
    window.busPost = function (url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'Content-Type': 'application/json'},
            body: JSON.stringify(body || {})
        }).then(function (r) {
            if (r.status === 401 || r.status === 419) { window.location.reload(); throw new Error('session'); }
            return r.json().then(function (d) { d._status = r.status; return d; });
        });
    };
</script>
@include('busmanagement::partner._seatplan_js')
@if(Route::has('bus-partner.today') && $busStaff->role !== \Modules\BusManagement\Entities\BusStaff::BOARDING)
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
