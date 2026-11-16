@php
    use Modules\HotelManagement\Entities\Hotel;
    use Modules\HotelManagement\Entities\HotelStaff;
    $isManager = $hotelStaff->role === HotelStaff::MANAGER;
    $nav = array_values(array_filter([
        ['hotel-partner.rooms', 'bi-grid-3x3-gap', translate('Rooms')],
        ['hotel-partner.inbox', 'bi-chat-dots', translate('Inbox')],
        ['hotel-partner.nights', 'bi-calendar-week', translate('Coming nights')],
        $isManager ? ['hotel-partner.setup', 'bi-door-open', translate('Setup')] : null,
        $isManager ? ['hotel-partner.staff', 'bi-people', translate('Staff')] : null,
    ]));
    $statusLabel = match ($hotel->status) {
        Hotel::LIVE => [translate('Guests can ask you'), 'is-live'],
        Hotel::PAUSED => [translate('Paused by E-Ride'), 'is-paused'],
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
    <title>@yield('title') · {{ $hotel->name }}</title>
    <link rel="icon" href="{{ dynamicAsset('public/assets/admin-module/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap-icons.min.css') }}">
    <style>
        :root { --red: #e02020; --ink: #1d2d2b; --muted: #5f6b6a; --line: #e5e9e8; --bg: #f4f6f6; --ok: #15803d; --warn: #8a4f00; }
        * { -webkit-tap-highlight-color: transparent; }
        html, body { background: var(--bg); color: var(--ink); }
        body { font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .h-top { background: #111114; color: #fff; position: sticky; top: 0; z-index: 30; }
        .h-top__in { max-width: 1280px; margin: 0 auto; padding: 0 16px; }
        .h-bar { display: flex; align-items: center; gap: 12px; min-height: 58px; padding-top: env(safe-area-inset-top); }
        .h-logo { width: 34px; height: 34px; border-radius: 9px; background: var(--red); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; flex: none; }
        .h-name { font-weight: 800; font-size: 15px; line-height: 1.15; min-width: 0; }
        .h-name small { display: block; font-weight: 500; font-size: 12px; color: #aeb9b8; }
        .h-pill { display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 13px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .h-pill.is-live { background: rgba(21, 128, 61, .22); color: #86efac; }
        .h-pill.is-setup { background: rgba(245, 158, 11, .2); color: #fcd34d; }
        .h-pill.is-paused { background: rgba(224, 32, 32, .22); color: #fca5a5; }
        .h-nav { display: flex; gap: 22px; overflow-x: auto; scrollbar-width: none; }
        .h-nav::-webkit-scrollbar { display: none; }
        .h-nav a { color: #c9d1d0; font-size: 13.5px; font-weight: 600; padding: 12px 2px; border-bottom: 3px solid transparent; white-space: nowrap; text-decoration: none; display: inline-flex; gap: 7px; align-items: center; }
        .h-nav a.active { color: #fff; border-bottom-color: var(--red); }
        .h-badge { background: var(--red); color: #fff; border-radius: 999px; font-size: 11px; min-width: 18px; height: 18px; padding: 0 5px; display: none; align-items: center; justify-content: center; font-weight: 800; }
        .h-main { max-width: 1280px; margin: 0 auto; padding: 18px 16px 40px; }
        .h-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; }
        .h-h { font-weight: 800; font-size: 16px; margin: 0; }
        .h-hint { font-size: 12.5px; color: var(--muted); line-height: 1.45; }
        .btn { border-radius: 10px; font-weight: 700; }
        .btn-primary { background: var(--red); border-color: var(--red); }
        .btn-primary:hover, .btn-primary:focus { background: #c41a1a; border-color: #c41a1a; }
        .btn-primary:disabled, .btn-primary.disabled { background: var(--red); border-color: var(--red); opacity: .45; }
        .btn-soft { background: #f4f6f6; border-color: #e3e8e7; color: var(--ink); }
        .form-control, .form-select { border-radius: 10px; min-height: 42px; }
        .h-tag { display: inline-flex; align-items: center; gap: 5px; height: 22px; padding: 0 8px; border-radius: 11px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
        .h-tag.ok { background: #e9f7ef; color: var(--ok); }
        .h-tag.warn { background: #fff4e5; color: var(--warn); }
        .h-tag.bad { background: #fdecec; color: #b91c1c; }
        .h-tag.mute { background: #eef1f1; color: #48615e; }
        .h-tag.blue { background: #e8eefc; color: #1d4ed8; }
        .h-tag.purple { background: #f1ebfd; color: #6d28d9; }
        .h-toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); background: #111114; color: #fff; padding: 10px 16px; border-radius: 12px; font-weight: 600; z-index: 2000; max-width: calc(100% - 32px); box-shadow: 0 10px 30px rgba(0,0,0,.25); }
        .chip-opt { display: inline-flex; }
        .chip-opt input { position: absolute; opacity: 0; pointer-events: none; }
        .chip-opt label { min-height: 38px; padding: 0 12px; border-radius: 19px; border: 1.5px solid #d5dcdb; background: #fff; display: inline-flex; align-items: center; font-size: 13px; font-weight: 600; cursor: pointer; margin: 0; }
        .chip-opt input:checked + label { border-color: var(--red); background: #fdecec; color: #b91c1c; }
        .chip-opt input:focus-visible + label { outline: 2px solid var(--ink); outline-offset: 1px; }
    </style>
    @stack('head')
</head>
<body>
<header class="h-top">
    <div class="h-top__in">
        <div class="h-bar">
            <div class="h-logo" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(preg_replace('/[^A-Za-z]/', '', $hotel->name), 0, 2)) }}</div>
            <div class="h-name text-truncate">{{ $hotel->name }}<small>{{ translate('Hotel partner portal') }}</small></div>
            <span class="h-pill {{ $statusLabel[1] }} ms-auto">{{ $statusLabel[0] }}</span>
            <button type="button" class="btn btn-sm text-white-50 px-1" id="ringBtn" aria-pressed="false" title="{{ translate('Ring for new messages') }}" aria-label="{{ translate('Ring for new messages') }}"><i class="bi bi-bell-slash fs-5"></i></button>
            <span class="d-none d-md-inline small text-white-50">{{ auth()->user()->first_name }} · {{ $isManager ? translate('Manager') : translate('Reception') }}</span>
            <form action="{{ route('hotel-partner.logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm text-white-50 px-1" title="{{ translate('Sign_out') }}" aria-label="{{ translate('Sign_out') }}"><i class="bi bi-box-arrow-right fs-5"></i></button>
            </form>
        </div>
        <nav class="h-nav" aria-label="{{ translate('Partner_menu') }}">
            @foreach($nav as [$routeName, $icon, $label])
                <a href="{{ route($routeName) }}" class="{{ request()->routeIs($routeName . '*') ? 'active' : '' }}"><i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}@if($routeName === 'hotel-partner.inbox')<span class="h-badge" data-inbox-badge></span>@endif</a>
            @endforeach
        </nav>
    </div>
</header>

<main class="h-main">
    @if($hotel->status === Hotel::PAUSED)
        <div class="alert alert-danger">{{ translate('E-Ride paused your hotel: guests cannot ask you in the app right now. Your rooms board keeps working.') }}
            @if($hotel->pause_note)<b>{{ $hotel->pause_note }}</b>@endif</div>
    @elseif($hotel->status === Hotel::SETUP)
        <div class="alert alert-warning">{{ translate('Not shown to guests yet. Finish the Setup checklist: room types with a photo, their room numbers and the reception phone.') }}
            @if($isManager)<a href="{{ route('hotel-partner.setup') }}" class="alert-link">{{ translate('Open Setup') }}</a>@endif</div>
    @endif
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>

<script src="{{ dynamicAsset('public/assets/admin-module/js/bootstrap.bundle.min.js') }}"></script>
<script>
    window.hotelToast = function (text) {
        var el = document.createElement('div');
        el.className = 'h-toast'; el.setAttribute('role', 'status'); el.textContent = text;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 3600);
    };
    window.hotelPost = function (url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'Content-Type': 'application/json'},
            body: JSON.stringify(body || {})
        }).then(function (r) {
            if (r.status === 401 || r.status === 419) { window.location.reload(); throw new Error('session'); }
            return r.json().then(function (d) { d._status = r.status; return d; });
        });
    };
    // The ring: a short chime when a guest writes (browsers only play sound after a tap, hence the bell).
    (function () {
        var audio = null, on = localStorage.getItem('hotelRing') === '1', btn = document.getElementById('ringBtn');
        var paint = function () { btn.setAttribute('aria-pressed', on ? 'true' : 'false'); btn.querySelector('i').className = 'bi fs-5 ' + (on ? 'bi-bell-fill text-warning' : 'bi-bell-slash'); };
        window.hotelChime = function () {
            if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
            if (!on) return;
            try {
                audio = audio || new (window.AudioContext || window.webkitAudioContext)();
                [880, 1320].forEach(function (f, i) {
                    var o = audio.createOscillator(), g = audio.createGain(), t = audio.currentTime + i * 0.18;
                    o.frequency.value = f; o.connect(g); g.connect(audio.destination);
                    g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.3, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.35);
                    o.start(t); o.stop(t + 0.4);
                });
            } catch (e) {}
        };
        btn.addEventListener('click', function () { on = !on; localStorage.setItem('hotelRing', on ? '1' : '0'); paint(); if (on) hotelChime(); });
        paint();
        var last = localStorage.getItem('hotelLatest') || '';
        window.hotelSetBadge = function (n) {
            document.querySelectorAll('[data-inbox-badge]').forEach(function (b) { b.textContent = n; b.style.display = n > 0 ? 'inline-flex' : 'none'; });
            document.title = (n > 0 ? '(' + n + ') ' : '') + document.title.replace(/^\(\d+\) /, '');
        };
        (function poll() {
            fetch(@json(route('hotel-partner.feed')), {headers: {'Accept': 'application/json'}})
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (!d) return;
                    hotelSetBadge(d.count);
                    if (d.latest && d.latest !== last) {
                        if (last && d.count > 0) { hotelChime(); if (window.hotelOnNew) window.hotelOnNew(); }
                        last = d.latest; localStorage.setItem('hotelLatest', last);
                    }
                }).catch(function () {})
                .finally(function () { setTimeout(poll, 15000); });
        })();
    })();
</script>
@stack('script')
@if (session('toast'))
    <script>hotelToast(@json(session('toast')));</script>
@endif
</body>
</html>
