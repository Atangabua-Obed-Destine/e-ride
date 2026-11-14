<!doctype html>
<html lang="{{ $t['lang'] }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') · {{ $t['market'] }}</title>
<meta name="description" content="@yield('description')">
{{-- Link previews (WhatsApp, Facebook): title, description and picture. --}}
<meta property="og:site_name" content="E-Ride Market">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:title" content="@yield('title')">
<meta property="og:description" content="@yield('description')">
@hasSection('og_url')<meta property="og:url" content="@yield('og_url')">@endif
@hasSection('og_image')
<meta property="og:image" content="@yield('og_image')">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="@yield('og_image')">
@endif
<meta name="theme-color" content="#E02020">
<style>
*{box-sizing:border-box}
body{margin:0;background:#F4F6F6;color:#1D2D2B;font:15px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
a{color:#E02020}
.wrap{max-width:560px;margin:0 auto;background:#fff;min-height:100vh}
.bar{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #E8ECEB;background:#fff;position:sticky;top:0;z-index:2}
.brand{font-weight:900;color:#E02020;text-decoration:none;font-size:15px}
.brand span{font-weight:500;color:#1D2D2B}
.pill{display:inline-flex;align-items:center;height:36px;padding:0 14px;border-radius:18px;background:#E02020;color:#fff;font-weight:600;font-size:13px;text-decoration:none}
.notice{margin:12px 16px;background:#FFF4E5;color:#7A3E0B;border-radius:12px;padding:10px 12px;font-size:13px}
.danger{margin:12px 16px;background:#FDECEC;border:1px solid #F5B5B5;color:#7F1D1D;border-radius:12px;padding:12px 14px;font-size:13px}
.danger b{color:#991B1B;display:block;margin-bottom:4px}
.btn{display:flex;align-items:center;justify-content:center;height:52px;border-radius:12px;background:#E02020;color:#fff;font-weight:600;font-size:16px;text-decoration:none;margin:0 16px}
.btn.dark{background:#1D2D2B}
.muted{color:#6B7675;font-size:13px}
.chips{display:flex;flex-wrap:wrap;gap:6px}
.chip{background:#F4F6F6;border-radius:10px;padding:5px 10px;font-size:12px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:0 16px 24px}
.card{background:#fff;border:1px solid #E8ECEB;border-radius:14px;overflow:hidden;text-decoration:none;color:#1D2D2B;display:flex;flex-direction:column}
.card img,.ph{width:100%;aspect-ratio:1/1;object-fit:cover;background:#EEF0F0;display:block}
.card .in{padding:8px 10px}
.card .t{font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.price{font-weight:800;color:#E02020}
.badge{display:inline-flex;align-items:center;gap:4px;color:#1D4ED8;font-size:12px;font-weight:600}
.foot{display:flex;justify-content:space-between;gap:8px;padding:10px 16px 28px}
.foot a{color:#6B7675;font-size:12px}
</style>
</head>
<body>
<div class="wrap">
@yield('body')
</div>
</body>
</html>
