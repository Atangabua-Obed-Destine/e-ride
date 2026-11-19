<!doctype html>
<html lang="{{ $t->lang }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', $t('brand')) · E-Ride</title>
<meta name="description" content="@yield('description', $t('meta_home'))">
<meta property="og:site_name" content="E-Ride">
<meta property="og:type" content="website">
<meta property="og:title" content="@yield('title', $t('brand'))">
<meta property="og:description" content="@yield('description', $t('meta_home'))">
<meta property="og:url" content="{{ url()->current() }}">
<meta name="theme-color" content="#E02020">
<link rel="canonical" href="{{ url()->current() }}">
<style>
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:#F4F6F6;color:#1D2D2B;font:15px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
a{color:#E02020}
button,input,select{font:inherit;color:inherit}
h1{font-size:24px;line-height:1.2;margin:0;font-weight:800;letter-spacing:-.01em}
h2{font-size:17px;margin:0;font-weight:800}
.bar{background:#fff;border-bottom:1px solid #E8ECEB;position:sticky;top:0;z-index:5}
.bar-in{max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 16px;min-height:56px}
.brand{font-weight:900;color:#E02020;text-decoration:none;font-size:17px;white-space:nowrap}
.brand span{font-weight:600;color:#1D2D2B}
.nav{display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.nav a.link{color:#1D2D2B;font-weight:600;font-size:14px;text-decoration:none;padding:8px}
.lang{font-size:12px;font-weight:700;color:#5F6B6A;text-decoration:none;padding:8px 6px}
.lang b{color:#1D2D2B}
.pill{display:inline-flex;align-items:center;justify-content:center;height:38px;padding:0 16px;border-radius:19px;background:#E02020;color:#fff;font-weight:600;font-size:14px;text-decoration:none;border:0;cursor:pointer}
.pill.light{background:#fff;color:#1D2D2B}
.wrap{max-width:1100px;margin:0 auto;padding:0 0 32px}
.page{max-width:560px;margin:0 auto}
.sec{padding:16px}
.card{background:#fff;border:1px solid #E3E8E7;border-radius:14px;padding:14px}
.muted{color:#5F6B6A}
.sm{font-size:13px}.xs{font-size:12px}.b{font-weight:700}
.row{display:flex;align-items:center;gap:10px}
.col{display:flex;flex-direction:column;gap:6px}
.btn{display:flex;align-items:center;justify-content:center;gap:8px;min-height:52px;width:100%;border-radius:12px;background:#E02020;color:#fff;font-weight:600;font-size:16px;text-decoration:none;border:0;cursor:pointer;padding:0 18px}
.btn:disabled{background:#C9D1D0;cursor:not-allowed}
.btn.dark{background:#1D2D2B}
.btn.ghost{background:#F4F6F6;color:#1D2D2B}
.btn.line{background:#fff;color:#1D2D2B;border:1px solid #D5DCDB}
.lbl{font-size:13px;font-weight:600;display:block;margin-bottom:6px}
.in{width:100%;height:50px;border:1.5px solid #D5DCDB;border-radius:12px;padding:0 14px;font-size:16px;background:#fff}
.in:focus{outline:2px solid #1D2D2B;outline-offset:1px}
.prefix{display:flex;align-items:center;border:1.5px solid #D5DCDB;border-radius:12px;overflow:hidden;background:#fff;height:50px}
.prefix span{padding:0 12px;height:100%;display:flex;align-items:center;background:#F4F6F6;border-right:1.5px solid #D5DCDB;font-weight:700}
.prefix input{border:0;height:100%;flex:1;padding:0 12px;font-size:16px;min-width:0}
.prefix:focus-within{outline:2px solid #1D2D2B;outline-offset:1px}
.err{background:#FDECEC;border:1px solid #F5B5B5;color:#7F1D1D;border-radius:12px;padding:10px 12px;font-size:14px;margin:12px 16px 0}
.ok{background:#ECFDF3;border:1px solid #BBF7D0;color:#14532D;border-radius:12px;padding:10px 12px;font-size:14px}
.warn{background:#FFF4E5;color:#7A3E0B;border-radius:12px;padding:12px 14px;font-size:14px}
.tag{display:inline-flex;align-items:center;gap:4px;min-height:22px;padding:0 8px;border-radius:6px;font-size:12px;font-weight:700}
.t-green{background:#ECFDF3;color:#166534}.t-amber{background:#FFF4E5;color:#7A3E0B}.t-grey{background:#F4F6F6;color:#5F6B6A}.t-ind{background:#EEF2FF;color:#3730A3}.t-dark{background:#1D2D2B;color:#fff}
.verified{display:inline-flex;align-items:center;gap:3px;color:#1D4ED8;font-size:12px;font-weight:600}
.chips{display:flex;gap:8px;flex-wrap:wrap}
.chip{display:inline-flex;align-items:center;height:36px;padding:0 14px;border-radius:18px;border:1.5px solid #D5DCDB;background:#fff;font-size:13px;font-weight:600;color:#1D2D2B;text-decoration:none;white-space:nowrap}
.chip.on{background:#1D2D2B;border-color:#1D2D2B;color:#fff}
.appband{border-radius:14px;background:#1D2D2B;color:#fff;padding:14px;display:flex;align-items:center;gap:12px}
.appband .sub{color:#C9D1D0}
.foot{max-width:1100px;margin:0 auto;padding:18px 16px 36px;color:#5F6B6A;font-size:12px;display:flex;gap:12px;flex-wrap:wrap;justify-content:space-between}
.foot a{color:#5F6B6A}
.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
@media (min-width:900px){h1{font-size:30px}}
@media print{.bar,.foot,.noprint{display:none!important}body{background:#fff}}
</style>
@stack('head')
</head>
<body>
<header class="bar">
    <div class="bar-in">
        <a class="brand" href="{{ route('bus.web.home') }}">E-Ride <span>Bus</span></a>
        <nav class="nav" aria-label="E-Ride Bus">
            @php($other = $t->lang === 'fr' ? 'en' : 'fr')
            <a class="lang" href="{{ request()->fullUrlWithQuery(['lang' => $other]) }}" hreflang="{{ $other }}" aria-label="{{ $other === 'fr' ? 'Français' : 'English' }}">@if($t->lang === 'en')<b>EN</b> · FR @else EN · <b>FR</b>@endif</a>
            @if($me)
                <a class="link" href="{{ route('bus.web.trips') }}">{{ $t('my_trips') }}</a>
            @elseif(!request()->routeIs('bus.web.login', 'bus.web.register', 'bus.web.register.code'))
                <a class="pill" href="{{ route('bus.web.login', ['next' => '/' . ltrim(request()->path(), '/')]) }}">{{ $t('sign_in') }}</a>
            @endif
        </nav>
    </div>
</header>
<main class="wrap">
    @yield('body')
</main>
<footer class="foot">
    <span>{{ $t('footer') }}</span>
    <span class="row" style="gap:14px">
        <a href="{{ route('bus.web.app') }}">{{ $t('get_app') }}</a>
        @if($me)
            <form method="POST" action="{{ route('bus.web.logout') }}" style="display:inline">@csrf<button type="submit" style="background:none;border:0;padding:0;color:#5F6B6A;text-decoration:underline;cursor:pointer;font-size:12px">{{ $t('sign_out') }}</button></form>
        @endif
    </span>
</footer>
@stack('scripts')
</body>
</html>
