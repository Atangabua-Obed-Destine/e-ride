<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#111114">
    <title>{{ translate('E-Ride Partner') }}</title>
    <link rel="icon" href="{{ dynamicAsset('public/assets/admin-module/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap.min.css') }}">
    <style>
        html, body { height: 100%; }
        body { margin: 0; background: #111114; color: #fff; font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .box { width: 100%; max-width: 380px; }
        .mark { width: 56px; height: 56px; border-radius: 16px; background: #e02020; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 26px; margin-bottom: 20px; }
        h1 { font-size: 26px; font-weight: 800; margin: 0 0 6px; letter-spacing: -.02em; }
        p.sub { color: #a3a3ad; margin: 0 0 24px; }
        label { font-size: 13px; font-weight: 700; color: #c9c9d2; margin-bottom: 6px; }
        .form-control { background: #1b1b20; border: 1px solid #2c2c33; color: #fff; min-height: 50px; border-radius: 14px; }
        .form-control:focus { background: #1b1b20; color: #fff; border-color: #e02020; box-shadow: 0 0 0 3px rgba(224, 32, 32, .25); }
        .btn-go { width: 100%; min-height: 52px; border-radius: 999px; background: #e02020; border: 0; color: #fff; font-weight: 800; font-size: 16px; margin-top: 8px; }
        .err { background: rgba(224, 32, 32, .12); border: 1px solid rgba(224, 32, 32, .4); color: #ffb4b4; border-radius: 12px; padding: 10px 12px; font-size: 14px; margin-bottom: 16px; }
        .help { color: #7c7c88; font-size: 13px; margin-top: 18px; }
    </style>
</head>
<body>
<main class="box">
    <div class="mark" aria-hidden="true">E</div>
    <h1>{{ translate('E-Ride Partner') }}</h1>
    <p class="sub">{{ translate('Receive orders, manage your menu and see your earnings.') }}</p>
    @if ($errors->any())
        <div class="err" role="alert">{{ $errors->first() }}</div>
    @endif
    <form action="{{ route('partner.login.submit') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="email">{{ translate('Email') }}</label>
            <input id="email" type="email" name="email" class="form-control" required autocomplete="username" value="{{ old('email') }}" autofocus>
        </div>
        <div class="mb-3">
            <label for="password">{{ translate('Password') }}</label>
            <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
        <input type="hidden" name="remember" value="1">
        <button type="submit" class="btn-go">{{ translate('Sign_in') }}</button>
    </form>
    <p class="help">{{ translate('Forgot your password? Ask the E-Ride team to reset it.') }}</p>
</main>
</body>
</html>
