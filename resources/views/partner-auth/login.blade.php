{{--
    The sign-in page shared by every E-Ride partner portal (Food & Shops, Hotels, Bus agencies,
    Bus companies). Each portal's own login view includes it with its own words and form target.

    Params: $portal (store | hotel | bus | company), $action (form URL), $field ('email' or 'login'),
    $fieldLabel, $heading, $sub, $features (list of short lines), $help.
--}}
@php
    $portals = [
        'store' => ['route' => 'partner.login', 'label' => translate('Food & Shops'),
            'icon' => '<path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>'],
        'hotel' => ['route' => 'hotel-partner.login', 'label' => translate('Hotels and guest houses'),
            'icon' => '<path d="M3 18V7"/><path d="M3 14h18v4"/><path d="M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="2"/>'],
        'bus' => ['route' => 'bus-partner.login', 'label' => translate('Bus agencies'),
            'icon' => '<rect x="4" y="3" width="16" height="14" rx="2"/><path d="M4 10h16M8 17v3M16 17v3"/><circle cx="8" cy="13.5" r=".8"/><circle cx="16" cy="13.5" r=".8"/>'],
        'company' => ['route' => 'bus-company.login', 'label' => translate('Bus companies (head office)'),
            'icon' => '<path d="M4 20V5l8-2v17"/><path d="M12 9h8v11"/><path d="M7 8h2M7 12h2M7 16h2M15 13h2M15 16h2"/><path d="M2 20h20"/>'],
    ];
    $me = $portals[$portal] ?? $portals['store'];
    $others = array_filter($portals, fn($p, $k) => $k !== $portal && \Illuminate\Support\Facades\Route::has($p['route']), ARRAY_FILTER_USE_BOTH);
    $field ??= 'login';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#141417">
    <title>{{ translate('Sign in') }} · {{ $heading }}</title>
    <link rel="icon" href="{{ dynamicAsset('public/assets/admin-module/img/favicon.png') }}">
    <style>
        :root { --red: #e02020; --red-dark: #b91818; --ink: #17191c; --muted: #5d6670; --line: #dfe3e6; --bg: #f4f5f6; --panel: #141417; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: var(--ink); background: var(--bg); }
        a { color: inherit; }
        .shell { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); }

        /* Brand side */
        .brand { background: var(--panel); color: #fff; padding: 40px 48px; display: flex; flex-direction: column; position: relative; overflow: hidden; }
        .brand::after { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--red); }
        .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; }
        .logo svg { color: #fff; }
        .logo b { font-size: 18px; letter-spacing: .08em; font-weight: 800; }
        .logo small { display: block; font-size: 11.5px; letter-spacing: .14em; text-transform: uppercase; color: #9aa1a9; font-weight: 600; margin-top: -2px; }
        .brand-main { margin: auto 0; padding: 48px 0 32px; max-width: 460px; }
        .portal-tag { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #ff8a8a; margin-bottom: 14px; }
        .brand h2 { font-size: 34px; line-height: 1.15; font-weight: 800; letter-spacing: -.02em; margin: 0 0 12px; }
        .brand p { color: #b5bcc3; margin: 0 0 28px; font-size: 15.5px; }
        .features { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
        .features li { display: grid; grid-template-columns: 26px 1fr; gap: 10px; align-items: start; color: #e6e9ec; font-size: 14.5px; }
        .features .tick { width: 22px; height: 22px; border-radius: 6px; background: rgba(224, 32, 32, .16); border: 1px solid rgba(224, 32, 32, .45); display: flex; align-items: center; justify-content: center; margin-top: 1px; }
        .others { border-top: 1px solid #2a2c31; padding-top: 18px; }
        .others h3 { font-size: 11.5px; letter-spacing: .12em; text-transform: uppercase; color: #8c939b; margin: 0 0 10px; font-weight: 700; }
        .others nav { display: flex; flex-wrap: wrap; gap: 8px; }
        .others a { display: inline-flex; align-items: center; gap: 7px; text-decoration: none; color: #d4d8dc; font-size: 13px; font-weight: 600; padding: 7px 10px; border: 1px solid #2f3237; border-radius: 6px; }
        .others a:hover, .others a:focus-visible { border-color: #5b6067; color: #fff; }

        /* Form side */
        .form-side { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px 24px; }
        .card { width: 100%; max-width: 420px; background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 32px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        .card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 22px; }
        .card-icon { width: 42px; height: 42px; border-radius: 6px; background: var(--ink); color: #fff; display: flex; align-items: center; justify-content: center; flex: none; }
        .card h1 { font-size: 22px; font-weight: 800; margin: 0; letter-spacing: -.01em; }
        .card .for { font-size: 13px; color: var(--muted); margin: 0; }
        .field { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #2c3238; }
        .input { width: 100%; height: 46px; border: 1px solid #cfd5da; border-radius: 6px; padding: 0 12px; font: inherit; color: var(--ink); background: #fff; }
        .input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 3px rgba(23, 25, 28, .12); }
        .input[aria-invalid="true"] { border-color: var(--red); }
        .pw { position: relative; }
        .pw .input { padding-right: 76px; }
        .pw button { position: absolute; right: 6px; top: 6px; height: 34px; padding: 0 10px; border: 0; border-radius: 6px; background: #f0f2f4; color: #2c3238; font: inherit; font-size: 13px; font-weight: 700; cursor: pointer; }
        .pw button:focus-visible { outline: 2px solid var(--ink); outline-offset: 1px; }
        .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 4px 0 20px; }
        .check { display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; color: #3a4148; cursor: pointer; }
        .check input { width: 16px; height: 16px; accent-color: var(--ink); margin: 0; }
        .btn { width: 100%; height: 48px; border: 0; border-radius: 6px; background: var(--red); color: #fff; font: inherit; font-weight: 800; font-size: 15.5px; cursor: pointer; }
        .btn:hover { background: var(--red-dark); }
        .btn:focus-visible { outline: 3px solid rgba(224, 32, 32, .35); outline-offset: 2px; }
        .btn[disabled] { opacity: .7; cursor: progress; }
        .err { display: flex; gap: 10px; align-items: flex-start; background: #fdf0f0; border: 1px solid #f3c7c7; color: #8f1414; border-radius: 6px; padding: 10px 12px; font-size: 14px; margin-bottom: 18px; }
        .help { display: flex; gap: 10px; align-items: flex-start; margin-top: 20px; padding-top: 18px; border-top: 1px solid #edf0f2; font-size: 13px; color: var(--muted); }
        .help svg { flex: none; margin-top: 2px; }
        .foot { margin-top: 20px; font-size: 12.5px; color: #7a828a; text-align: center; }
        .foot a { color: #4a525a; }
        .mobile-brand { display: none; }

        @media (max-width: 991px) {
            .shell { grid-template-columns: 1fr; }
            .brand { display: none; }
            .mobile-brand { display: flex; align-items: center; justify-content: space-between; gap: 12px; background: var(--panel); color: #fff; padding: 14px 18px; border-bottom: 3px solid var(--red); }
            .mobile-brand .portal-tag { margin: 0; color: #ff8a8a; font-size: 11.5px; }
            .form-side { justify-content: flex-start; padding: 24px 16px 32px; }
            .card { padding: 24px 20px; }
            .m-others { width: 100%; max-width: 420px; margin-top: 18px; }
            .m-others h3 { font-size: 11.5px; letter-spacing: .1em; text-transform: uppercase; color: #7a828a; margin: 0 0 8px; }
            .m-others nav { display: flex; flex-wrap: wrap; gap: 8px; }
            .m-others a { font-size: 13px; font-weight: 600; text-decoration: none; padding: 7px 10px; border: 1px solid var(--line); border-radius: 6px; background: #fff; color: #2c3238; }
        }
        @media (min-width: 992px) { .m-others { display: none; } }
    </style>
</head>
<body>
@php
    $logo = '<svg width="34" height="34" viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="21" fill="none" stroke="currentColor" stroke-width="5"/><path d="M34 15H21a7 7 0 0 0-7 7v4a7 7 0 0 0 7 7h13" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/><path d="M20 24h11" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/></svg>';
    $icon = fn($paths, $size = 22) => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
@endphp
<header class="mobile-brand">
    <a class="logo" href="{{ url('/') }}">{!! $logo !!}<span><b>E-RIDE</b><small>{{ translate('Partner portal') }}</small></span></a>
    <span class="portal-tag">{{ $me['label'] }}</span>
</header>
<div class="shell">
    <aside class="brand">
        <a class="logo" href="{{ url('/') }}">{!! $logo !!}<span><b>E-RIDE</b><small>{{ translate('Partner portal') }}</small></span></a>
        <div class="brand-main">
            <div class="portal-tag">{!! $icon($me['icon'], 16) !!} {{ $me['label'] }}</div>
            <h2>{{ $heading }}</h2>
            <p>{{ $sub }}</p>
            <ul class="features">
                @foreach($features as $f)
                    <li><span class="tick"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ff8a8a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span><span>{{ $f }}</span></li>
                @endforeach
            </ul>
        </div>
        @if($others)
            <div class="others">
                <h3>{{ translate('Other partner portals') }}</h3>
                <nav aria-label="{{ translate('Other partner portals') }}">
                    @foreach($others as $p)<a href="{{ route($p['route']) }}">{!! $icon($p['icon'], 15) !!}{{ $p['label'] }}</a>@endforeach
                </nav>
            </div>
        @endif
    </aside>

    <main class="form-side">
        <div class="card">
            <div class="card-head">
                <div class="card-icon">{!! $icon($me['icon']) !!}</div>
                <div><h1>{{ translate('Sign in') }}</h1><p class="for">{{ $heading }}</p></div>
            </div>
            @if ($errors->any())
                <div class="err" role="alert"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/></svg><span>{{ $errors->first() }}</span></div>
            @endif
            <form action="{{ $action }}" method="POST" id="signin" novalidate>
                @csrf
                <div class="field">
                    <label for="{{ $field }}">{{ $fieldLabel }}</label>
                    <input id="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" name="{{ $field }}" class="input" required
                           autocomplete="username" autocapitalize="none" spellcheck="false" value="{{ old($field) }}" autofocus
                           @if($errors->any()) aria-invalid="true" @endif>
                </div>
                <div class="field">
                    <label for="password">{{ translate('Password') }}</label>
                    <div class="pw">
                        <input id="password" type="password" name="password" class="input" required autocomplete="current-password" @if($errors->any()) aria-invalid="true" @endif>
                        <button type="button" id="pwToggle" aria-controls="password" aria-pressed="false">{{ translate('Show') }}</button>
                    </div>
                </div>
                <div class="row">
                    <input type="hidden" name="remember" value="0">
                    <label class="check"><input type="checkbox" name="remember" value="1" checked> {{ translate('Keep me signed in on this device') }}</label>
                </div>
                <button type="submit" class="btn" id="signinBtn">{{ translate('Sign in') }}</button>
            </form>
            <div class="help"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><span>{{ $help }}</span></div>
        </div>
        @if($others)
            <div class="m-others">
                <h3>{{ translate('Other partner portals') }}</h3>
                <nav>@foreach($others as $p)<a href="{{ route($p['route']) }}">{{ $p['label'] }}</a>@endforeach</nav>
            </div>
        @endif
        <p class="foot"><a href="{{ url('/') }}">{{ translate('E-Ride website') }}</a> · {{ translate('Only for E-Ride partners and their staff.') }}</p>
    </main>
</div>
<script>
    (function () {
        var t = document.getElementById('pwToggle'), p = document.getElementById('password');
        var show = @json(translate('Show')), hide = @json(translate('Hide'));
        t.addEventListener('click', function () {
            var on = p.type === 'password';
            p.type = on ? 'text' : 'password';
            t.textContent = on ? hide : show;
            t.setAttribute('aria-pressed', on ? 'true' : 'false');
            p.focus();
        });
        var f = document.getElementById('signin'), b = document.getElementById('signinBtn'), busy = @json(translate('Signing in'));
        f.addEventListener('submit', function (e) {
            if (!f.checkValidity()) { e.preventDefault(); f.reportValidity(); return; }
            b.disabled = true; b.textContent = busy + '…';
        });
        // Back from the next page: the button works again.
        window.addEventListener('pageshow', function () { b.disabled = false; b.textContent = @json(translate('Sign in')); });
    })();
</script>
</body>
</html>
