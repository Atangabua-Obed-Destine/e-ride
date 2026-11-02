<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#111114">
    <title>@yield('title') · {{ translate('E-Ride Partner') }}</title>
    <link rel="icon" href="{{ dynamicAsset('public/assets/admin-module/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap-icons.min.css') }}">
    <style>
        :root { --red: #e02020; --red-soft: rgba(224, 32, 32, .08); --ink: #141416; --muted: #6b6b76; --line: #e7e7ec; --bg: #f4f4f6; --ok: #12a150; --warn: #c27a00; }
        * { -webkit-tap-highlight-color: transparent; }
        html, body { background: var(--bg); color: var(--ink); }
        body { font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding-bottom: calc(76px + env(safe-area-inset-bottom)); }
        .p-top { position: sticky; top: 0; z-index: 30; background: #111114; color: #fff; padding: 10px 16px; padding-top: calc(10px + env(safe-area-inset-top)); display: flex; align-items: center; gap: 12px; }
        .p-top__logo { width: 36px; height: 36px; border-radius: 10px; object-fit: cover; background: #26262c; flex: none; }
        .p-top__name { font-weight: 800; font-size: 16px; line-height: 1.15; min-width: 0; }
        .p-top__name small { display: block; font-weight: 500; font-size: 12px; color: #a3a3ad; }
        .p-open { margin-left: auto; display: inline-flex; align-items: center; gap: 8px; border: 0; border-radius: 999px; padding: 8px 14px; font-weight: 800; font-size: 14px; cursor: pointer; }
        .p-open.is-open { background: var(--ok); color: #fff; }
        .p-open.is-closed { background: #3a3a42; color: #d5d5dc; }
        .p-open i { font-size: 10px; }
        .p-main { max-width: 1100px; margin: 0 auto; padding: 16px; }
        .p-nav { position: fixed; left: 0; right: 0; bottom: 0; z-index: 30; background: #fff; border-top: 1px solid var(--line); display: flex; padding-bottom: env(safe-area-inset-bottom); }
        .p-nav a { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 9px 4px 10px; font-size: 12px; font-weight: 600; color: var(--muted); text-decoration: none; position: relative; }
        .p-nav a i { font-size: 20px; }
        .p-nav a.active { color: var(--red); }
        .p-nav .p-badge { position: absolute; top: 4px; left: calc(50% + 6px); background: var(--red); color: #fff; border-radius: 999px; font-size: 11px; min-width: 18px; height: 18px; padding: 0 5px; display: none; align-items: center; justify-content: center; font-weight: 800; }
        .p-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; }
        .btn { border-radius: 12px; font-weight: 700; }
        .btn-primary { background: var(--red); border-color: var(--red); }
        .btn-primary:hover, .btn-primary:focus { background: #c41a1a; border-color: #c41a1a; }
        .btn-outline-primary { color: var(--red); border-color: var(--red); }
        .btn-outline-primary:hover { background: var(--red); border-color: var(--red); }
        .form-control, .form-select { border-radius: 12px; min-height: 44px; }
        .text-primary { color: var(--red) !important; }
        .switcher { position: relative; display: inline-block; width: 46px; height: 26px; }
        .switcher_input { opacity: 0; width: 0; height: 0; position: absolute; }
        .switcher_control { position: absolute; inset: 0; background: #cfcfd6; border-radius: 999px; transition: .2s; cursor: pointer; }
        .switcher_control::after { content: ''; position: absolute; width: 20px; height: 20px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s; }
        .switcher_input:checked + .switcher_control { background: var(--ok); }
        .switcher_input:checked + .switcher_control::after { transform: translateX(20px); }
        .p-toast { position: fixed; left: 50%; bottom: calc(90px + env(safe-area-inset-bottom)); transform: translateX(-50%); background: #111114; color: #fff; padding: 10px 16px; border-radius: 12px; font-weight: 600; z-index: 60; max-width: calc(100% - 32px); box-shadow: 0 10px 30px rgba(0,0,0,.25); }
        @media (min-width: 900px) {
            body { padding-bottom: 0; }
            .p-nav { position: sticky; top: 56px; bottom: auto; border-top: 0; border-bottom: 1px solid var(--line); justify-content: center; gap: 8px; }
            .p-nav a { flex: none; flex-direction: row; gap: 8px; padding: 12px 18px; font-size: 14px; }
            .p-nav a i { font-size: 17px; }
            .p-nav .p-badge { position: static; }
            .p-toast { bottom: 24px; }
        }
    </style>
    @stack('head')
</head>
<body>
<header class="p-top">
    <img class="p-top__logo" src="{{ $partnerStore->logoUrl() ?? dynamicAsset('public/assets/admin-module/img/favicon.png') }}" alt="">
    <div class="p-top__name text-truncate">
        {{ $partnerStore->name }}
        <small>{{ translate('E-Ride Partner') }}</small>
    </div>
    <button type="button" class="p-open {{ $partnerStore->is_open ? 'is-open' : 'is-closed' }}" data-open-toggle data-url="{{ route('partner.store.open') }}" data-open="{{ $partnerStore->is_open ? 1 : 0 }}" aria-pressed="{{ $partnerStore->is_open ? 'true' : 'false' }}">
        <i class="bi bi-circle-fill"></i><span data-open-label>{{ $partnerStore->is_open ? translate('Open') : translate('Closed') }}</span>
    </button>
    <form action="{{ route('partner.logout') }}" method="POST" class="m-0">
        @csrf
        <button type="submit" class="btn btn-sm text-white-50 px-1" title="{{ translate('Sign_out') }}" aria-label="{{ translate('Sign_out') }}"><i class="bi bi-box-arrow-right fs-5"></i></button>
    </form>
</header>

<nav class="p-nav" aria-label="{{ translate('Partner_menu') }}">
    <a href="{{ route('partner.orders') }}" class="{{ request()->routeIs('partner.orders') ? 'active' : '' }}"><i class="bi bi-receipt"></i>{{ translate('Orders') }}<span class="p-badge" data-new-badge></span></a>
    <a href="{{ route('partner.menu') }}" class="{{ request()->routeIs('partner.menu') ? 'active' : '' }}"><i class="bi bi-card-list"></i>{{ translate('Menu') }}</a>
    <a href="{{ route('partner.store') }}" class="{{ request()->routeIs('partner.store') ? 'active' : '' }}"><i class="bi bi-shop"></i>{{ translate('Store') }}</a>
    <a href="{{ route('partner.earnings') }}" class="{{ request()->routeIs('partner.earnings') || request()->routeIs('partner.orders.history') ? 'active' : '' }}"><i class="bi bi-wallet2"></i>{{ translate('Earnings') }}</a>
</nav>

<main class="p-main">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @yield('content')
</main>

<script src="{{ dynamicAsset('public/assets/admin-module/js/bootstrap.bundle.min.js') }}"></script>
<script>
    window.partnerToast = function (text) {
        var el = document.createElement('div');
        el.className = 'p-toast'; el.setAttribute('role', 'status'); el.textContent = text;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 3200);
    };
    window.partnerPost = function (url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'Content-Type': 'application/json'},
            body: JSON.stringify(body || {})
        }).then(function (r) {
            if (r.status === 401 || r.status === 419) { window.location.reload(); throw new Error('session'); }
            return r.json().then(function (d) { d._status = r.status; return d; });
        });
    };
    (function () {
        var btn = document.querySelector('[data-open-toggle]');
        btn.addEventListener('click', function () {
            var next = btn.dataset.open === '1' ? 0 : 1;
            if (!next && !confirm(@json(translate('Close the store? Customers will not be able to order until you open it again.')))) return;
            partnerPost(btn.dataset.url, {is_open: next}).then(function (d) {
                btn.dataset.open = d.is_open ? '1' : '0';
                btn.classList.toggle('is-open', !!d.is_open);
                btn.classList.toggle('is-closed', !d.is_open);
                btn.setAttribute('aria-pressed', d.is_open ? 'true' : 'false');
                btn.querySelector('[data-open-label]').textContent = d.is_open ? @json(translate('Open')) : @json(translate('Closed'));
                partnerToast(d.is_open
                    ? (d.open_now ? @json(translate('Open: customers can order now.')) : @json(translate('Switched open, but outside your opening hours.')))
                    : @json(translate('Closed: no new orders.')));
            }).catch(function () { partnerToast(@json(translate('Could not save. Check your connection.'))); });
        });
    })();
</script>
@stack('script')
@if (session('toast'))
    <script>partnerToast(@json(session('toast')));</script>
@endif
</body>
</html>
