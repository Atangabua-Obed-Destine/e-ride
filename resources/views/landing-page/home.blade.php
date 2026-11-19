@php
    $img = fn(string $f) => dynamicAsset(path: 'public/assets/landing/' . $f);
    $tiles = [
        ['ride.webp', 'Ride', '#FFFFFF'], ['food.webp', 'Food and shops', '#FFF4E6'], ['parcel.webp', 'Parcels', '#F6EFE6'], ['bus', 'Bus', '#E8F0FF'],
        ['stay.webp', 'Hotels', '#F3EEFF'], ['explore.webp', 'Explore', '#EAF7F3'], ['move.webp', 'Move', '#FFF0EE'], ['market', 'Market and jobs', '#EEF2F7'],
    ];
    $busIcon = fn(int $s, string $c, float $w = 1.8) => '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="' . $c . '" stroke-width="' . $w . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="15" rx="3"/><path d="M4 11h16M8 3v8M16 3v8"/><circle cx="8" cy="15" r="1"/><circle cx="16" cy="15" r="1"/><path d="M7 18v3M17 18v3"/></svg>';
    $shopIcon = fn(int $s, string $c) => '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="' . $c . '" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9l1.5-5h13L20 9"/><path d="M4 9h16v2a3 3 0 0 1-5.3 2 3 3 0 0 1-5.4 0A3 3 0 0 1 4 11V9z"/><path d="M5 14v6h14v-6"/><path d="M10 20v-4h4v4"/></svg>';
    $arrow = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
    $play = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 3.5v17l9.5-8.5z"/><path d="M15 9.5l3.6 2-3.6 2-1.8-2z" opacity=".7"/></svg>';
    $check = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>';
    $dayKinds = ['ride', 'food', 'parcel', 'bus', 'hotel'];
    $rolePaths = [
        'bus' => '<rect x="4" y="3" width="16" height="15" rx="3"/><path d="M4 11h16"/><path d="M7 18v3M17 18v3"/>',
        'app' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
        'driver' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/><path d="M12 3v6.5M4.5 15l5.3-2M19.5 15l-5.3-2"/>',
        'food' => '<path d="M5 3v8a3 3 0 006 0V3M8 3v18M16 3c-1.7 0-3 2-3 5s1.3 4 3 4v9"/>',
        'agency' => '<path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>',
        'hotel' => '<path d="M3 18v-6a2 2 0 012-2h14a2 2 0 012 2v6"/><path d="M3 14h18"/><path d="M7 10V7h4v3"/>',
    ];
    $roleIcon = fn(string $k) => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($rolePaths[$k] ?? '') . '</svg>';
    $catPaths = [
        'visit' => '<path d="M3 20l6-11 4 6 3-4 5 9z"/><circle cx="17" cy="6" r="2"/>',
        'homes' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/>',
        'pharmacy' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M12 8v8M8 12h8"/>',
        'health' => '<path d="M12 21s-7-4.4-7-10a4 4 0 017-2.6A4 4 0 0119 11c0 5.6-7 10-7 10z"/>',
        'bank' => '<path d="M3 10l9-6 9 6"/><path d="M5 10v8M9 10v8M15 10v8M19 10v8"/><path d="M3 20h18"/>',
        'bus' => '<rect x="4" y="3" width="16" height="15" rx="3"/><path d="M4 11h16"/><path d="M7 18v3M17 18v3"/>',
        'stay' => '<path d="M3 18v-6a2 2 0 012-2h14a2 2 0 012 2v6"/><path d="M3 14h18"/><path d="M7 10V7h4v3"/><path d="M3 18v2M21 18v2"/>',
    ];
    $catIcon = fn(string $key) => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($catPaths[$key] ?? '<path d="M12 21s-6-5.5-6-10a6 6 0 0112 0c0 4.5-6 10-6 10z"/><circle cx="12" cy="11" r="2"/>') . '</svg>';
    $dayLook = [
        'ride' => ['#E02020', '#FFFFFF', '#FFE1DE', 'ride.webp'], 'food' => ['#FFF1DE', '#2B1D0E', '#6B4A25', 'food.webp'], 'parcel' => ['#F6EFE6', '#2B1D0E', '#6B4A25', 'parcel.webp'],
        'bus' => ['#E8F0FF', '#0F1E4A', '#33437A', null], 'hotel' => ['#F3EEFF', '#26164A', '#4F3E7A', 'stay.webp'],
    ];
    $other = $t->lang === 'fr' ? 'en' : 'fr';
    $apple = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.4 12.6c0-2.4 2-3.6 2.1-3.7-1.1-1.7-2.9-1.9-3.5-1.9-1.5-.2-2.9.9-3.7.9-.8 0-1.9-.9-3.2-.8-1.6 0-3.1 1-4 2.4-1.7 3-.4 7.4 1.2 9.8.8 1.2 1.8 2.5 3 2.4 1.2 0 1.7-.8 3.2-.8 1.5 0 1.9.8 3.2.8 1.3 0 2.2-1.2 3-2.4.9-1.4 1.3-2.7 1.3-2.8 0 0-2.6-1-2.6-3.9zM14 5.5c.7-.8 1.1-1.9 1-3-1 0-2.1.7-2.8 1.5-.6.7-1.2 1.8-1 2.9 1 .1 2.1-.6 2.8-1.4z"/></svg>';
@endphp
<!doctype html>
<html lang="{{ $t->lang }}" data-theme="{{ $theme }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $t('title') }}</title>
<meta name="description" content="{{ $t('meta') }}">
<meta property="og:site_name" content="E-Ride">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $t('title') }}">
<meta property="og:description" content="{{ $t('meta') }}">
<meta property="og:url" content="{{ url('/') }}">
<meta property="og:image" content="{{ $img('app-home.webp') }}">
<meta name="theme-color" content="{{ $theme === 'light' ? '#FAF7F4' : '#141416' }}">
<link rel="canonical" href="{{ url('/') }}">
<link rel="alternate" hreflang="en" href="{{ url('/?lang=en') }}">
<link rel="alternate" hreflang="fr" href="{{ url('/?lang=fr') }}">
@if($favicon)<link rel="icon" href="{{ $favicon }}">@endif
<link rel="preload" as="image" href="{{ $img('app-home.webp') }}">
<style>
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%;scroll-behavior:smooth}
:root{
  --bg:#141416;--ink:#FFFFFF;--muted:#B9B9C2;--soft:#D7D7DE;--surface:#1F1F24;--line:rgba(255,255,255,.08);--eyebrow:#FF6B61;--stop:#141416;
  --ring1:rgba(255,255,255,.07);--ring2:rgba(255,255,255,.09);--tileshadow:0 14px 30px rgba(0,0,0,.4);--phoneshadow:0 30px 80px rgba(0,0,0,.6),0 0 0 1px rgba(255,255,255,.08);
  --chip:rgba(255,255,255,.06);--chipline:transparent;--outline:rgba(255,255,255,.22);--cardshadow:none;
  --er-bg:#2A1113;--er-line:rgba(224,32,32,.35);--er-note:#FFB4AE;
  --bw-bg:#13203F;--bw-line:rgba(96,140,255,.3);--bw-eyebrow:#93B4FF;--bw-text:#B5C3E6;--bw-btn:#FFFFFF;--bw-btn-ink:#13203F;--bw-small:#93A6D6;
  --icon:#FF6B61;--kicker:#B9B9C2;--cta:#FF6B61;--daybtn:rgba(255,255,255,.07);--dayon:#1F1F24;--toggle:rgba(255,255,255,.06);--toggle-on:#FFFFFF;--toggle-on-ink:#141416;
}
:root[data-theme=light]{
  --bg:#FAF7F4;--ink:#141416;--muted:#55565F;--soft:#3A3A44;--surface:#FFFFFF;--line:#E6E0DA;--eyebrow:#C41A1A;--stop:#FAF7F4;
  --ring1:rgba(20,20,22,.08);--ring2:rgba(20,20,22,.10);--tileshadow:0 14px 30px rgba(20,20,22,.12),0 0 0 1px rgba(20,20,22,.05);--phoneshadow:0 30px 70px rgba(20,20,22,.22);
  --chip:#FFFFFF;--chipline:#ECE6E0;--outline:rgba(20,20,22,.22);--cardshadow:0 1px 2px rgba(20,20,22,.04);
  --er-bg:#FFF1F0;--er-line:#F6C9C6;--er-note:#B91C1C;
  --bw-bg:#EAF0FF;--bw-line:#CCDAFF;--bw-eyebrow:#1D4ED8;--bw-text:#33437A;--bw-btn:#1D4ED8;--bw-btn-ink:#FFFFFF;--bw-small:#3B4E86;
  --icon:#C41A1A;--kicker:#6B6C75;--cta:#C41A1A;--daybtn:#E6E0DA;--dayon:#FFFFFF;--toggle:rgba(20,20,22,.06);--toggle-on:#141416;--toggle-on-ink:#FFFFFF;
}
@media (prefers-color-scheme:light){:root[data-theme=auto]{
  --bg:#FAF7F4;--ink:#141416;--muted:#55565F;--soft:#3A3A44;--surface:#FFFFFF;--line:#E6E0DA;--eyebrow:#C41A1A;--stop:#FAF7F4;
  --ring1:rgba(20,20,22,.08);--ring2:rgba(20,20,22,.10);--tileshadow:0 14px 30px rgba(20,20,22,.12),0 0 0 1px rgba(20,20,22,.05);--phoneshadow:0 30px 70px rgba(20,20,22,.22);
  --chip:#FFFFFF;--chipline:#ECE6E0;--outline:rgba(20,20,22,.22);--cardshadow:0 1px 2px rgba(20,20,22,.04);
  --er-bg:#FFF1F0;--er-line:#F6C9C6;--er-note:#B91C1C;
  --bw-bg:#EAF0FF;--bw-line:#CCDAFF;--bw-eyebrow:#1D4ED8;--bw-text:#33437A;--bw-btn:#1D4ED8;--bw-btn-ink:#FFFFFF;--bw-small:#3B4E86;
  --icon:#C41A1A;--kicker:#6B6C75;--cta:#C41A1A;--daybtn:#E6E0DA;--dayon:#FFFFFF;--toggle:rgba(20,20,22,.06);--toggle-on:#141416;--toggle-on-ink:#FFFFFF;
}}
.logo-light{display:none}
:root[data-theme=light] .logo-dark{display:none}:root[data-theme=light] .logo-light{display:block}
@media (prefers-color-scheme:light){:root[data-theme=auto] .logo-dark{display:none}:root[data-theme=auto] .logo-light{display:block}}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.5 Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;overflow-x:hidden}
a{color:inherit;text-decoration:none}
img{max-width:100%}
.wrap{max-width:1240px;margin:0 auto;padding:0 32px}
.eyebrow{margin:0;color:var(--eyebrow);font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.h2{margin:0;font-size:44px;line-height:1.08;font-weight:800;letter-spacing:-.025em}
.lead{margin:0;font-size:18px;line-height:1.55;color:var(--muted)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:54px;padding:0 24px;border-radius:6px;font-size:16px;font-weight:700;border:0;cursor:pointer}
.btn-red{background:#E02020;color:#FFFFFF}.btn-red:hover{background:#C81B1B}
.btn-line{border:1.5px solid var(--outline);color:var(--ink);background:transparent}
.btn-ink{background:#141416;color:#FFFFFF}
.chip{display:inline-flex;align-items:center;gap:8px;height:34px;padding:0 14px;border-radius:6px;background:var(--chip);border:1px solid var(--chipline);color:var(--soft);font-size:13px;font-weight:600}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block}
.card{background:var(--surface);border:1px solid var(--line);border-radius:6px;padding:28px;display:flex;flex-direction:column;gap:10px;position:relative;overflow:hidden;box-shadow:var(--cardshadow)}
.card.flat{border:0;box-shadow:none}
.cardt{margin:0;font-size:22px;font-weight:800;letter-spacing:-.01em;line-height:1.2}
.cardb{margin:0;font-size:15px;line-height:1.5;color:var(--muted)}
.pitch{margin:0;font-size:16px;font-weight:650;line-height:1.4}
.pts{list-style:none;margin:4px 0 0;padding:0;display:flex;flex-direction:column;gap:8px}
.pts li{display:flex;gap:10px;font-size:14.5px;line-height:1.4}
.pts li::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;margin-top:8px;flex-shrink:0}
.stop{width:44px;height:44px;border-radius:50%;background:var(--stop);border:2px solid #E02020;color:var(--ink);font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative;z-index:1}
.sechead{display:flex;gap:22px;align-items:flex-start}
.sechead > div{display:flex;flex-direction:column;gap:12px;max-width:760px}
.indent{padding-left:66px}
.sec{padding-top:130px;display:flex;flex-direction:column;gap:36px;position:relative}
/* Header */
.top{display:flex;align-items:center;justify-content:space-between;gap:20px;padding-top:20px;padding-bottom:20px}
.top nav{display:flex;align-items:center;gap:24px}
.top nav .lnk{font-size:15px;font-weight:600;color:var(--soft)}
.top nav .lnk:hover{color:var(--ink)}
.lang{display:flex;border-radius:6px;background:var(--toggle);padding:3px}
.lang a{height:32px;min-width:40px;padding:0 10px;border-radius:4px;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;color:var(--muted)}
.lang a.on{background:var(--toggle-on);color:var(--toggle-on-ink)}
/* Hero */
.hero{overflow-x:clip;display:grid;grid-template-columns:minmax(0,1fr) 640px;gap:40px;align-items:center;padding-top:20px;padding-bottom:70px}
.hero h1{margin:0;font-size:68px;line-height:1.02;font-weight:900;letter-spacing:-.035em}
.orbit-box{position:relative;width:640px;height:640px}
.orbit-box svg.deco{position:absolute;left:0;top:0}
.ring{position:absolute;inset:0;animation:spin 80s linear infinite}
.tile{position:absolute;width:88px;height:88px;border-radius:8px;display:flex;align-items:center;justify-content:center;box-shadow:var(--tileshadow);animation:spin 80s linear infinite reverse}
.tile img{width:58px;height:58px;object-fit:contain}
.phone{position:absolute;left:205px;top:70px;width:230px;height:500px;border-radius:22px;background:#0B0B0C;padding:9px;box-shadow:var(--phoneshadow);animation:floaty 6s ease-in-out infinite}
.phone img{width:212px;height:482px;object-fit:cover;object-position:top;border-radius:15px;display:block}
.route{animation:dash 14s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes floaty{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
@keyframes dash{to{stroke-dashoffset:-220}}
@media (prefers-reduced-motion:reduce){.ring,.tile,.phone,.route{animation:none}}
.band{background:#E02020;color:#FFFFFF;padding:18px 0;transform:rotate(-1.2deg);margin:0 -20px}
.band div{display:flex;gap:22px;justify-content:center;flex-wrap:wrap;font-size:18px;font-weight:800;white-space:nowrap;padding:0 24px}
.band i{font-style:normal;opacity:.55}
/* The route down the page */
.trail{position:absolute;left:54px;top:150px;bottom:0;width:4px;background-image:linear-gradient(#E02020 30%,transparent 0);background-size:4px 12px;opacity:.45;border-radius:2px}
/* Services */
.grid4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
.span2{grid-column:span 2}.rows2{grid-row:span 2}
.ride{background:#E02020;color:#FFFFFF;min-height:520px}
.ride .cardt{font-size:40px;line-height:1.05}
.ride img{position:absolute;right:-10px;bottom:-6px;width:280px;height:auto}
.ride .pts{max-width:340px;color:#FFE9E7}
.food{background:#FFF1DE;color:#2B1D0E;min-height:250px}
.food .pitch,.food .pts{max-width:330px}
.food img{position:absolute;right:22px;bottom:0;height:150px;width:auto}
.svc-icon{width:60px;height:60px;object-fit:contain}
/* Header menu */
.top{position:sticky;top:0;z-index:20;background:var(--bg)}
.menu{position:relative}
.menu summary{list-style:none;gap:6px}
.menu summary::-webkit-details-marker{display:none}
.menu-pop{position:absolute;right:0;top:calc(100% + 8px);width:300px;background:var(--surface);border:1px solid var(--line);border-radius:6px;padding:8px;box-shadow:0 18px 40px rgba(0,0,0,.25);z-index:30}
.menu-item{display:flex;gap:12px;align-items:flex-start;padding:10px 12px;border-radius:6px;color:var(--ink)}
.menu-item:hover,.menu-item:focus-visible{background:var(--chip)}
.menu-item svg{flex-shrink:0;margin-top:2px;color:var(--eyebrow)}
.menu-item b{display:block;font-size:15px}.menu-item small{display:block;font-size:13px;color:var(--muted)}
/* Hero action box */
.box{background:var(--surface);border:1px solid var(--line);border-radius:6px;padding:8px 8px 18px;box-shadow:var(--cardshadow);max-width:560px}
.tabs{display:flex;gap:4px;padding:4px;background:var(--toggle);border-radius:6px;margin-bottom:14px}
.tab{flex:1;min-height:42px;border:0;border-radius:4px;background:transparent;color:var(--muted);font:inherit;font-weight:700;font-size:15px;cursor:pointer}
.tab[aria-selected=true]{background:var(--toggle-on);color:var(--toggle-on-ink)}
.pane{padding:0 10px}
.busform{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.busform label{display:flex;flex-direction:column;gap:4px;font-size:12px;font-weight:700;color:var(--muted)}
.busform select,.busform input{height:48px;border:1.5px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font:inherit;font-size:16px;padding:0 12px;width:100%}
.busform label:nth-child(3){grid-column:span 1}
.busform .btn{grid-column:span 2}
.ministeps{margin:2px 0 14px;padding-left:22px;display:flex;flex-direction:column;gap:6px;font-size:15px}
.pane-cta{display:flex;align-items:center;justify-content:space-between;gap:14px}
.pane-qr{display:block;width:74px;height:74px;background:#FFFFFF;border-radius:6px;padding:6px;flex-shrink:0}
.pane-qr svg{width:62px;height:62px;display:block}
.store{min-height:46px;font-size:14px;padding:0 16px}
/* How a ride works */
.how{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;position:relative}
.how li{display:flex;flex-direction:column;gap:6px;padding:18px;background:var(--surface);border:1px solid var(--line);border-radius:6px;box-shadow:var(--cardshadow)}
.how .n{width:32px;height:32px;border-radius:50%;background:#E02020;color:#FFFFFF;font-weight:900;display:flex;align-items:center;justify-content:center;margin-bottom:6px}
.how b{font-size:17px}.how li > span:last-child{font-size:14px;color:var(--muted);line-height:1.45}
.later{display:flex;align-items:center;gap:10px;margin:16px 0 0;font-size:15px;font-weight:600;color:var(--soft)}
.later svg{color:var(--eyebrow);flex-shrink:0}
/* Drive and earn */
.drive{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:18px}
.drive-card{background:#E02020;color:#FFFFFF;border:0;min-height:330px;padding-right:300px}
.drive-card .pts{color:#FFE9E7}
.drive-card .btn-line{border-color:rgba(255,255,255,.6);color:#FFFFFF}
.drive-card .btn-red{background:#FFFFFF;color:#141416}.drive-card .btn-red:hover{background:#F2F2F2}
.drive-art{position:absolute;right:-10px;bottom:-6px;width:290px;height:auto}
.drive-steps{list-style:none;margin:4px 0 0;padding:0;display:flex;flex-direction:column;gap:12px}
.drive-steps li{display:flex;align-items:center;gap:12px;font-size:16px;font-weight:600}
.drive-steps .n{width:30px;height:30px;border-radius:50%;border:2px solid #E02020;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:900;flex-shrink:0}
/* App cards */
.appcards{display:flex;flex-direction:column;gap:12px;justify-self:end;position:relative;width:100%;max-width:400px}
.appcard{display:flex;gap:14px;align-items:center;background:#FFFFFF;color:#141416;border-radius:6px;padding:12px}
.appcard .qr{width:96px;height:96px;padding:6px;flex-shrink:0}
.appcard .qr svg{width:84px;height:84px}
.appcard b{display:block;font-size:17px}.appcard small{display:block;font-size:13px;color:#55565F;line-height:1.35;margin:2px 0 6px}
.appcard em{font-style:normal;font-size:12px;font-weight:800;color:#C41A1A;letter-spacing:.04em;text-transform:uppercase}
/* Footer */
footer{display:block!important}
.foot-top{display:flex;justify-content:space-between;gap:30px;flex-wrap:wrap}
.foot-top .cols{display:grid;grid-template-columns:repeat(4,minmax(130px,auto));gap:30px}
.ft{font-weight:800;color:var(--ink)}
.badge-store{display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 12px;border-radius:6px;border:1px solid var(--line);color:var(--ink);font-size:13px;font-weight:700}
.foot-bottom{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:30px;padding-top:18px;border-top:1px solid var(--line);font-size:13px;color:var(--muted)}
.foot-bottom a{color:var(--muted)}.foot-bottom a:hover{color:var(--ink)}
.foot-links,.social{display:flex;gap:16px;flex-wrap:wrap}
/* Burger menu */
.burger{display:none;width:46px;height:46px;border-radius:6px;border:1.5px solid var(--outline);background:transparent;cursor:pointer;flex-direction:column;justify-content:center;align-items:center;gap:5px;padding:0}
.burger span{display:block;width:20px;height:2px;background:var(--ink);border-radius:2px}
.sheet{position:fixed;inset:0;z-index:60;background:var(--bg);overflow-y:auto;animation:sheetIn .35s cubic-bezier(.2,.7,.2,1)}
.sheet[hidden]{display:none}
@keyframes sheetIn{from{transform:translateY(-3%);opacity:0}to{transform:none;opacity:1}}
.sheet-in{padding-top:18px;padding-bottom:30px;display:flex;flex-direction:column;gap:26px;min-height:100%}
.sheet-head{display:flex;justify-content:space-between;align-items:center}
.sheet-close{width:46px;height:46px;border-radius:6px;border:1.5px solid var(--outline);background:transparent;color:var(--ink);cursor:pointer;display:flex;align-items:center;justify-content:center}
.sheet-links{display:flex;flex-direction:column}
.sheet-links a{display:flex;align-items:baseline;gap:16px;padding:12px 0;border-bottom:1px solid var(--line);font-size:28px;font-weight:800;letter-spacing:-.02em}
.sheet-links .n{font-size:13px;font-weight:800;color:var(--eyebrow);width:24px}
.sheet-roles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}
.sheet-roles .eyebrow{grid-column:1/-1;margin-bottom:4px}
.sheet-foot{display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-top:auto}
/* Slides: each section arrives like a new slide */
html{scroll-padding-top:84px}
@media (max-width:760px){html{scroll-padding-top:74px}}
@media (min-width:900px) and (prefers-reduced-motion:no-preference){html{scroll-snap-type:y proximity}}
.slide{scroll-snap-align:start}
@media (min-width:900px){.sec.slide{min-height:calc(100vh - 84px)}}
.js-motion .reveal{opacity:0;transform:translateY(56px);transition:opacity .8s cubic-bezier(.2,.7,.2,1),transform .8s cubic-bezier(.2,.7,.2,1)}
.js-motion .reveal.in{opacity:1;transform:none}
.js-motion .hero .rise{animation:rise .9s cubic-bezier(.2,.7,.2,1) both}
@keyframes rise{from{opacity:0;transform:translateY(40px)}to{opacity:1;transform:none}}
.dots{position:fixed;right:22px;top:50%;transform:translateY(-50%);z-index:15;display:flex;flex-direction:column;gap:12px}
.dots button{position:relative;width:12px;height:12px;border-radius:50%;border:2px solid var(--muted);background:transparent;padding:0;cursor:pointer;transition:transform .25s,background .25s,border-color .25s}
.dots button[aria-current=true]{background:#E02020;border-color:#E02020;transform:scale(1.35)}
.dots button span{position:absolute;right:22px;top:50%;transform:translateY(-50%);white-space:nowrap;font-size:12px;font-weight:700;background:var(--surface);color:var(--ink);border:1px solid var(--line);border-radius:4px;padding:4px 8px;opacity:0;pointer-events:none;transition:opacity .2s}
.dots button:hover span,.dots button:focus-visible span,.dots button[aria-current=true] span{opacity:1}
.dots button:not([aria-current=true]):not(:hover):not(:focus-visible) span{opacity:0}
@media (max-width:1180px){.dots{display:none}}
.trail-fill{position:absolute;left:0;top:0;width:4px;height:0;background:#E02020;border-radius:2px;opacity:1}
.trail{background-color:transparent}
/* City bar */
.citybar{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.citypick{height:44px;border:1.5px solid var(--line);border-radius:6px;background:var(--bg);color:var(--ink);font:inherit;font-weight:700;padding:0 12px;min-width:160px}
.locate{min-height:44px;font-size:14px;padding:0 14px}
.citystatus{margin:0;font-size:14px;font-weight:600;color:var(--eyebrow)}
/* City guide */
.guide{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr);gap:18px}
.cats{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
.cat{display:inline-flex;align-items:center;gap:8px;height:38px;padding:0 14px;border-radius:6px;background:#EAF7F3;color:#0E2E25;font-size:14px;font-weight:600}
.ask-card{background:#1D2D2B;border:0;color:#FFFFFF;min-height:300px}
.ask-card .cardb{color:#C9D1D0}.ask-card .eyebrow{color:#FCA5A5}
.bubbles{display:flex;flex-direction:column;align-items:flex-start;gap:8px;margin-top:6px}
.bubble{background:#FFFFFF;color:#1D2D2B;border-radius:6px 6px 6px 0;padding:9px 13px;font-size:14px;font-weight:600}
.bubble:nth-child(2){align-self:flex-end;border-radius:6px 6px 0 6px;background:#E02020;color:#FFFFFF}
.steps{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;counter-reset:s}
.steps li{display:flex;flex-direction:column;gap:6px;padding:18px;border-top:3px solid #E02020;background:var(--surface);border-radius:0 0 6px 6px;box-shadow:var(--cardshadow)}
.steps .n{font-size:13px;font-weight:900;color:var(--eyebrow)}
.steps b{font-size:16px}.steps li > span:last-child{font-size:14px;color:var(--muted);line-height:1.45}
/* Day */
.day{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:28px;align-items:stretch}
.daylist{display:flex;flex-direction:column;gap:10px}
.daybtn{display:flex;align-items:center;gap:18px;padding:16px 20px;border-radius:6px;font:inherit;color:var(--ink);cursor:pointer;text-align:left;background:transparent;border:1.5px solid var(--daybtn)}
.daybtn[aria-pressed=true]{background:var(--dayon);border-color:#E02020}
.daybtn .tm{font-size:22px;font-weight:900;font-variant-numeric:tabular-nums;width:76px}
.daybtn b{display:block;font-size:17px}.daybtn small{display:block;font-size:14px;color:var(--muted)}
.daypanel{min-height:420px}
.daypanel[hidden]{display:none}
.daypanel .cardt{font-size:34px;line-height:1.1;max-width:420px}
.daypanel .art{position:absolute;right:24px;bottom:18px;width:190px;height:auto}
/* Matters */
.er{background:var(--er-bg);border-color:var(--er-line);min-height:380px}
.er .badge{width:58px;height:58px;border-radius:6px;background:#E02020;display:flex;align-items:center;justify-content:center}
.bw{background:var(--bw-bg);border-color:var(--bw-line);min-height:380px;flex-direction:row;gap:20px;padding-right:0}
.bw .txt{display:flex;flex-direction:column;gap:10px;flex:1;min-width:0}
.bw .shot{width:190px;flex-shrink:0;align-self:flex-end;margin-bottom:-28px}
.bw .shot img{width:190px;height:226px;object-fit:cover;object-position:top;border-radius:6px 6px 0 0;display:block;border:6px solid #0B0B0C;border-bottom:0}
/* Get the app */
.get{border-radius:6px;background:#E02020;color:#FFFFFF;padding:56px;display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:30px;align-items:center;position:relative;overflow:hidden}
.get h2{margin:0;font-size:52px;line-height:1.02;font-weight:900;letter-spacing:-.03em}
.qr{width:172px;height:172px;border-radius:6px;background:#FFFFFF;padding:14px;display:flex;align-items:center;justify-content:center}
.qr svg{width:144px;height:144px}
footer{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:30px;padding-bottom:50px;margin-top:30px}
footer .cols{display:flex;gap:44px;flex-wrap:wrap;font-size:14px}
footer .cols div{display:flex;flex-direction:column;gap:10px}
footer .cols span,footer .cols a{color:var(--muted)}
footer .cols a:hover{color:var(--ink)}
.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
:focus-visible{outline:2px solid #E02020;outline-offset:3px}
/* Tablet and phone */
@media (max-width:1080px){
  .hero{grid-template-columns:1fr;gap:10px}
  .hero-art{display:flex;justify-content:center}
  .grid4{grid-template-columns:repeat(2,minmax(0,1fr))}
  .rows2{grid-row:auto}
  .safe4,.part4{grid-template-columns:repeat(2,minmax(0,1fr))!important}
  .top nav .lnk{display:none}
  .burger{display:flex}
  .steps{grid-template-columns:repeat(2,minmax(0,1fr))}
  .how{grid-template-columns:repeat(2,minmax(0,1fr))}
  .drive{grid-template-columns:1fr}
  .foot-top .cols{grid-template-columns:repeat(2,minmax(0,1fr))}
  .part4{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}
@media (max-width:760px){
  .wrap{padding:0 20px}
  .top nav .lnk{display:none}
  .top nav{gap:10px}
  .hero h1{font-size:40px;line-height:1.05}
  .lead{font-size:16px}
  .hero-art{position:relative;height:370px;overflow:hidden;margin:0 -20px}
  .orbit-box{position:absolute;left:50%;top:0;transform:translateX(-50%) scale(.56);transform-origin:top center}
  .band{transform:rotate(-2deg)}
  .band div{font-size:15px;gap:12px}
  .h2{font-size:30px}
  .sec{padding-top:72px;gap:22px}
  .sechead{gap:14px}
  .stop{width:36px;height:36px;font-size:13px}
  .indent{padding-left:50px}
  .trail{left:37px}
  .grid4{gap:12px}
  .card{padding:20px}
  .hero{padding-top:18px}
  .ride{min-height:0}.ride .cardt{font-size:28px}.ride .pts{max-width:none}
  .ride img{position:static;width:170px;align-self:flex-end;margin:-4px -14px -10px 0}
  .food{min-height:0}.food .pitch,.food .pts{max-width:none}
  .food img{position:static;height:96px;align-self:flex-end;margin:-6px 0 -20px 0}
  .grid4 > .card:not(.span2){grid-column:span 2}
  .day{grid-template-columns:1fr}
  .daylist{flex-direction:row;flex-wrap:wrap;gap:8px}
  .daybtn{padding:8px 14px;gap:0}.daybtn .tm{width:auto;font-size:15px}.daybtn span.who{display:none}
  .daypanel{min-height:260px}.daypanel .cardt{font-size:23px;max-width:200px}.daypanel .art{width:96px;right:12px;bottom:12px}
  .matters{grid-template-columns:1fr!important}
  .guide{grid-template-columns:1fr}
  .how{grid-template-columns:1fr}
  .drive-card{padding-right:20px;min-height:0}
  .drive-art{position:static;width:180px;align-self:flex-end;margin:-6px -14px -10px 0;order:9}
  .busform{grid-template-columns:1fr}
  .busform .btn{grid-column:auto}
  .pane-qr{display:none}
  .pane-cta .btn{width:100%}
  .top nav .menu summary{padding:0 12px}
  .top nav > .btn-red{display:none}
  .top nav > .menu,.top nav > .lang{display:none}
  .sheet-links a{font-size:24px}
  .sheet-roles{grid-template-columns:1fr}
  .top > a img{height:28px!important;min-width:44px}
  .menu-pop{position:fixed;left:12px;right:12px;top:70px;width:auto}
  .appcards{max-width:none}
  .foot-top .cols{grid-template-columns:1fr 1fr}
  .steps{grid-template-columns:1fr}
  .bw{flex-direction:column;padding-right:20px}
  .bw .shot{align-self:center;margin-bottom:-20px}
  .safe4{grid-template-columns:1fr!important}
  .part4{grid-template-columns:1fr!important}
  .get{grid-template-columns:1fr;padding:28px 22px}
  .get h2{font-size:32px}
  .get .qrcol{display:none}
  .together{font-size:30px!important}
  .hero .btns .btn{width:100%}
}
</style>
</head>
<body>
<div class="wrap top">
    <a href="{{ url('/') }}" aria-label="E-Ride">
        <img class="logo-dark" src="{{ $img('logo-on-dark.png') }}" alt="E-Ride" height="32" style="height:32px;width:auto">
        <img class="logo-light" src="{{ $img('logo-on-light.png') }}" alt="E-Ride" height="32" style="height:32px;width:auto">
    </a>
    <nav aria-label="E-Ride">
        <a class="lnk" href="#services">{{ $t('nav_services') }}</a>
        <a class="lnk" href="#guide">{{ $t('nav_guide') }}</a>
        <a class="lnk" href="#bus">{{ $t('nav_bus') }}</a>
        <a class="lnk" href="#safe">{{ $t('nav_safe') }}</a>
        <a class="lnk" href="#partners">{{ $t('nav_partners') }}</a>
        <a class="lnk" href="{{ route('contact-us') }}">{{ $t('help') }}</a>
        <details class="menu">
            <summary class="btn btn-line" style="min-height:44px;padding:0 16px;font-size:15px">{{ $t('signin') }}<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></summary>
            <div class="menu-pop" role="menu">
                <p class="eyebrow" style="padding:6px 12px 8px">{{ $t('signin_title') }}</p>
                @foreach($t('roles') as $key => [$who, $where])
                    @php($href = match ($key) { 'app' => '#get', 'driver' => $driverUrl, default => $portals[$key] ?? null })
                    @continue(!$href)
                    <a role="menuitem" href="{{ $href }}" class="menu-item">{!! $roleIcon($key) !!}<span><b>{{ $who }}</b><small>{{ $where }}</small></span></a>
                @endforeach
            </div>
        </details>
        <div class="lang" role="group" aria-label="Language">
            <a href="{{ url('/?lang=en') }}" class="{{ $t->lang === 'en' ? 'on' : '' }}" hreflang="en" @if($t->lang === 'en') aria-current="true" @endif>EN</a>
            <a href="{{ url('/?lang=fr') }}" class="{{ $t->lang === 'fr' ? 'on' : '' }}" hreflang="fr" @if($t->lang === 'fr') aria-current="true" @endif>FR</a>
        </div>
        <a class="btn btn-red" href="#get" style="min-height:44px;padding:0 18px;font-size:15px">{{ $t('get_app') }}</a>
        <button type="button" class="burger" aria-expanded="false" aria-controls="site-menu" aria-label="{{ $t('menu') }}"><span></span><span></span><span></span></button>
    </nav>
</div>
<div id="site-menu" class="sheet" hidden>
    <div class="sheet-in wrap">
        <div class="sheet-head">
            <img class="logo-dark" src="{{ $img('logo-on-dark.png') }}" alt="E-Ride" style="height:30px;width:auto">
            <img class="logo-light" src="{{ $img('logo-on-light.png') }}" alt="E-Ride" style="height:30px;width:auto">
            <button type="button" class="sheet-close" aria-label="{{ $t('close') }}"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
        </div>
        <nav class="sheet-links" aria-label="{{ $t('menu') }}">
            @foreach([['#services', 'nav_services'], ['#guide', 'nav_guide'], ['#how', 'nav_how'], ['#bus', 'nav_bus'], ['#safe', 'nav_safe'], ['#drive', 'nav_drive'], ['#partners', 'nav_partners']] as $i => [$href, $key])
                <a href="{{ $href }}"><span class="n">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>{{ $t($key) }}</a>
            @endforeach
        </nav>
        <div class="sheet-roles">
            <p class="eyebrow">{{ $t('signin_title') }}</p>
            @foreach($t('roles') as $key => [$who, $where])
                @php($href = match ($key) { 'app' => '#get', 'driver' => $driverUrl, default => $portals[$key] ?? null })
                @continue(!$href)
                <a href="{{ $href }}" class="menu-item">{!! $roleIcon($key) !!}<span><b>{{ $who }}</b><small>{{ $where }}</small></span></a>
            @endforeach
        </div>
        <div class="sheet-foot">
            <div class="lang" role="group" aria-label="Language">
                <a href="{{ url('/?lang=en') }}" class="{{ $t->lang === 'en' ? 'on' : '' }}" hreflang="en">EN</a>
                <a href="{{ url('/?lang=fr') }}" class="{{ $t->lang === 'fr' ? 'on' : '' }}" hreflang="fr">FR</a>
            </div>
            <a href="{{ route('contact-us') }}" style="font-weight:700">{{ $t('help') }}</a>
            <a class="btn btn-red" href="#get">{{ $t('get_app') }}</a>
        </div>
    </div>
</div>
<nav class="dots" aria-label="{{ $t('menu') }}"></nav>

<main>
<section class="wrap hero slide" data-label="{{ $t('slide_top') }}" aria-labelledby="hero-title">
    <div style="display:flex;flex-direction:column;gap:22px">
        <p class="eyebrow">{{ $t('hero_eyebrow') }}</p>
        <h1 id="hero-title">{{ $t('hero_title') }}</h1>
        <p class="lead" style="max-width:520px">{{ $t('hero_body') }}</p>
        @php($firstTab = 'ride')
        <div class="box">
            <div class="tabs" role="tablist" aria-label="E-Ride">
                @foreach($t('box_tabs') as $key => $label)
                    <button type="button" role="tab" class="tab" id="tab-{{ $key }}" aria-controls="pane-{{ $key }}" aria-selected="{{ $key === $firstTab ? 'true' : 'false' }}" tabindex="{{ $key === $firstTab ? '0' : '-1' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="pane" role="tabpanel" id="pane-bus" aria-labelledby="tab-bus" @if($firstTab !== 'bus') hidden @endif>
                @if($busWeb && $busSearch)
                    <form method="GET" action="{{ route('bus.web.search') }}" class="busform">
                        <label><span>{{ $t('box_from') }}</span>
                            <select name="from">@foreach($busSearch['from'] ?: ['Bamenda'] as $c)<option>{{ $c }}</option>@endforeach</select></label>
                        <label><span>{{ $t('box_to') }}</span>
                            <input name="to" list="landing-cities" placeholder="{{ $t('box_anywhere') }}" autocomplete="off"></label>
                        <datalist id="landing-cities">@foreach($busSearch['to'] as $c)<option value="{{ $c }}">@endforeach</datalist>
                        <label><span>{{ $t('box_date') }}</span>
                            <input type="date" name="date" value="{{ today()->toDateString() }}" min="{{ today()->toDateString() }}" max="{{ today()->addDays(59)->toDateString() }}"></label>
                        <button class="btn btn-red" type="submit">{{ $t('box_find') }}{!! $arrow !!}</button>
                    </form>
                @else
                    <ol class="ministeps">@foreach($t('box_bus_app') as $line)<li>{{ $line }}</li>@endforeach</ol>
                    <a class="btn btn-red" href="#get">{{ $t('box_in_app') }}{!! $arrow !!}</a>
                @endif
            </div>
            @foreach(['ride', 'food', 'parcel'] as $key)
                <div class="pane" role="tabpanel" id="pane-{{ $key }}" aria-labelledby="tab-{{ $key }}" @if($firstTab !== $key) hidden @endif>
                    <ol class="ministeps">@foreach($t('box_' . $key) as $line)<li>{{ $line }}</li>@endforeach</ol>
                    <div class="pane-cta">
                        <a class="btn btn-red" href="{{ route('get-app') }}">{{ $t('box_in_app') }}{!! $arrow !!}</a>
                        <span class="pane-qr" role="img" aria-label="{{ $t('qr_alt') }}">{!! $qr !!}</span>
                    </div>
                </div>
            @endforeach
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn btn-line store" href="{{ $playUrl }}" rel="noopener">{!! $play !!}{{ $t('play') }}</a>
            @if($appStoreUrl)<a class="btn btn-line store" href="{{ $appStoreUrl }}" rel="noopener">{!! $apple !!}{{ $t('appstore') }}</a>
            @else<span class="btn btn-line store" style="cursor:default;opacity:.75">{!! $apple !!}{{ $t('appstore_soon') }}</span>@endif
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
            <span class="chip"><span class="dot" style="background:#FFCC00"></span>MTN MoMo</span>
            <span class="chip"><span class="dot" style="background:#FF7900"></span>Orange Money</span>
            <span class="chip">{!! $check !!}{{ $t('chip_approved') }}</span>
            <span class="chip">{!! $check !!}{{ $t('chip_share') }}</span>
        </div>
    </div>
    <div class="hero-art">
        <div class="orbit-box">
            <svg class="deco" width="640" height="640" viewBox="0 0 640 640" fill="none" aria-hidden="true">
                <circle cx="320" cy="320" r="250" stroke="var(--ring1)"/>
                <circle cx="320" cy="320" r="170" stroke="var(--ring2)"/>
                <path class="route" d="M10 120 C 140 60, 230 210, 330 160 S 560 40, 630 150" stroke="#E02020" stroke-opacity="0.55" stroke-width="2.5" stroke-dasharray="2 10" stroke-linecap="round"/>
                <circle cx="10" cy="120" r="7" fill="#E02020"/>
            </svg>
            <div class="ring" aria-hidden="true">
                @foreach($tiles as $i => [$file, $alt, $bg])
                    @php($a = (M_PI * 2 * $i) / count($tiles) - M_PI / 2)
                    <div class="tile" style="left:{{ round(320 + 268 * cos($a) - 44) }}px;top:{{ round(320 + 268 * sin($a) - 44) }}px;background:{{ $bg }}">
                        @if($file === 'bus'){!! $busIcon(38, '#1D4ED8') !!}@elseif($file === 'market'){!! $shopIcon(36, '#334155') !!}@else<img src="{{ $img($file) }}" alt="" loading="lazy">@endif
                    </div>
                @endforeach
            </div>
            <div class="phone"><img src="{{ $img('app-home.webp') }}" alt="{{ $t('phone_alt') }}" width="212" height="482"></div>
        </div>
    </div>
</section>

<div style="overflow:hidden;padding:14px 0"><div class="band" aria-hidden="true"><div>@foreach($t('band') as $i => $w)@if($i)<i>·</i>@endif<span>{{ $w }}</span>@endforeach</div></div></div>

<div class="wrap" style="position:relative">
<div class="trail" aria-hidden="true"><i class="trail-fill"></i></div>

<section id="services" class="sec slide" data-label="{{ $t('nav_services') }}" aria-labelledby="s-title">
    <div class="sechead"><span class="stop">1</span><div>
        <p class="eyebrow">{{ $t('s_eyebrow') }}</p><h2 class="h2" id="s-title">{{ $t('s_title') }}</h2><p class="lead">{{ $t('s_lead') }}</p>
    </div></div>
    <div class="grid4 indent">
        <article class="card flat span2 rows2 ride">
            <p class="eyebrow" style="color:#FFE1DE">{{ $t('ride_eyebrow') }}</p>
            <h3 class="cardt">{{ $t('ride_title') }}</h3>
            <p class="pitch">{{ $t('ride_pitch') }}</p>
            <ul class="pts">@foreach($t('ride_pts') as $p)<li>{{ $p }}</li>@endforeach</ul>
            <img src="{{ $img('ride.webp') }}" alt="" loading="lazy">
        </article>
        <article class="card flat span2 food">
            <p class="eyebrow" style="color:#B45309">{{ $t('food_eyebrow') }}</p>
            <h3 class="cardt">{{ $t('food_title') }}</h3>
            <p class="pitch">{{ $t('food_pitch') }}</p>
            <ul class="pts" style="color:#6B4A25">@foreach($t('food_pts') as $p)<li>{{ $p }}</li>@endforeach</ul>
            <img src="{{ $img('food.webp') }}" alt="" loading="lazy">
        </article>
        @foreach([
            ['parcel', 'parcel.webp', '#F6EFE6', '#2B1D0E', '#6B4A25', null],
            ['move', 'move.webp', '#FFF0EE', '#3B1410', '#7A3A33', null],
            ['bus', 'bus', '#E8F0FF', '#0F1E4A', '#33437A', '#1D4ED8'],
            ['hotel', 'stay.webp', '#F3EEFF', '#26164A', '#4F3E7A', '#6D28D9'],
            ['explore', 'explore.webp', '#EAF7F3', '#0E2E25', '#2E5A4C', null],
            ['market', 'market', '#EEF2F7', '#0F172A', '#475569', null],
            ['jobs', 'jobs', '#FFF7E0', '#2E2306', '#6B5410', null],
            ['homes', 'homes', '#F1F5EC', '#1B2A10', '#3F5A22', null],
        ] as [$key, $art, $bg, $ink, $sub, $eyebrowColor])
            <article class="card flat {{ in_array($key, ['bus', 'hotel'], true) ? 'span2' : '' }}" style="background:{{ $bg }};color:{{ $ink }}" @if($key === 'bus') id="bus-card" @endif>
                @if($art === 'bus'){!! $busIcon(54, '#1D4ED8', 1.6) !!}
                @elseif($art === 'market'){!! $shopIcon(52, '#334155') !!}
                @elseif($art === 'jobs')<svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#A16207" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2.5"/><path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2"/><path d="M3 13h18"/></svg>
                @elseif($art === 'homes')<svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#4D7C0F" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/></svg>
                @else<img class="svc-icon" src="{{ $img($art) }}" alt="" loading="lazy">@endif
                @if($eyebrowColor)<p class="eyebrow" style="color:{{ $eyebrowColor }}">{{ $t($key . '_eyebrow') }}</p>@endif
                <h3 class="cardt" style="font-size:{{ $eyebrowColor ? 24 : 20 }}px">{{ $t($key . '_title') }}</h3>
                <p class="pitch">{{ $t($key . '_pitch') }}</p>
                <ul class="pts" style="color:{{ $sub }}">@foreach($t($key . '_pts') as $p)<li>{{ $p }}</li>@endforeach</ul>
            </article>
        @endforeach
    </div>
</section>


<section id="guide" class="sec slide" data-label="{{ $t('nav_guide') }}" aria-labelledby="g-title">
    <div class="sechead"><span class="stop">2</span><div>
        <p class="eyebrow">{{ $t('g_eyebrow') }}</p><h2 class="h2" id="g-title">{{ $t('g_title') }}</h2><p class="lead">{{ $t('g_lead') }}</p>
    </div></div>
    <div class="indent guide">
        <article class="card explore-card">
            <div style="display:flex;align-items:center;gap:14px">
                <img src="{{ $img('explore.webp') }}" alt="" width="56" height="56" style="width:56px;height:56px;object-fit:contain" loading="lazy">
                <h3 class="cardt" style="font-size:26px" data-explore-title data-one="{{ $t('g_explore') }}" data-any="{{ $t('g_explore_any') }}">{{ $cities ? str_replace('{city}', $cities[0], $t('g_explore')) : $t('g_explore_any') }}</h3>
            </div>
            <div class="citybar" data-cities='@json($cities)' data-in="{{ $t('g_in_service') }}" data-out="{{ $t('g_not_here') }}" data-denied="{{ $t('g_denied') }}" data-locating="{{ $t('g_locating') }}" data-url="{{ route('landing.city') }}">
                @if($cities)
                    <label class="sr" for="city-pick">{{ $t('g_city') }}</label>
                    <select id="city-pick" class="citypick">
                        @foreach($cities as $c)<option value="{{ $c }}" @selected($loop->first)>{{ $c }}</option>@endforeach
                    </select>
                    <button type="button" class="btn btn-line locate"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>{{ $t('g_locate') }}</button>
                @endif
            </div>
            <p class="citystatus" role="status" aria-live="polite">{{ $cities ? str_replace('{city}', $cities[0], $t('g_in_service')) : $t('g_soon') }}</p>
            <p class="cardb">{{ $t('g_explore_body') }}</p>
            <div class="cats">
                @foreach($explore as $c)
                    <span class="cat">{!! $catIcon($c['key']) !!}{{ $t->lang === 'fr' && $c['name_fr'] ? $c['name_fr'] : $c['name'] }}</span>
                @endforeach
            </div>
        </article>
        <article class="card ask-card">
            <p class="eyebrow">{{ $t('g_ask_eyebrow') }}</p>
            <h3 class="cardt" style="font-size:26px">{{ $t('g_ask_title') }}</h3>
            <div class="bubbles">
                @foreach($t('g_ask_examples') as $q)<span class="bubble">{{ $q }}</span>@endforeach
            </div>
            <p class="cardb" style="margin-top:auto">{{ $t('g_ask_body') }}</p>
        </article>
    </div>
    <div class="indent">
        <h3 class="cardt" style="font-size:20px;margin-bottom:14px">{{ $t('g_new_title') }}</h3>
        <ol class="steps">
            @foreach($t('g_steps') as $i => [$title, $body])
                <li><span class="n">{{ $i + 1 }}</span><b>{{ $title }}</b><span>{{ $body }}</span></li>
            @endforeach
        </ol>
    </div>
</section>

<section id="how" class="sec slide" data-label="{{ $t('nav_how') }}" aria-labelledby="h-title">
    <div class="sechead"><span class="stop">3</span><div>
        <p class="eyebrow">{{ $t('how_eyebrow') }}</p><h2 class="h2" id="h-title">{{ $t('how_title') }}</h2>
    </div></div>
    <div class="indent">
        <ol class="how">
            @foreach($t('how') as $i => [$title, $body])
                <li><span class="n">{{ $i + 1 }}</span><b>{{ $title }}</b><span>{{ $body }}</span></li>
            @endforeach
        </ol>
        <p class="later"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ $t('how_later') }}</p>
    </div>
</section>

<section id="day" class="sec slide" data-label="{{ $t('day_title') }}" aria-labelledby="d-title">
    <div class="sechead"><span class="stop">4</span><div>
        <p class="eyebrow">{{ $t('day_eyebrow') }}</p><h2 class="h2" id="d-title">{{ $t('day_title') }}</h2><p class="lead">{{ $t('day_lead') }}</p>
    </div></div>
    <div class="day indent">
        <div class="daylist" role="group" aria-label="{{ $t('day_title') }}">
            @foreach($t('day') as $i => $d)
                <button type="button" class="daybtn" data-day="{{ $i }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="day-{{ $i }}">
                    <span class="tm">{{ $d[0] }}</span><span class="who"><b>{{ $d[1] }}</b><small>{{ $d[2] }}</small></span>
                </button>
            @endforeach
        </div>
        @foreach($t('day') as $i => $d)
            @php([$bg, $ink, $sub, $art] = $dayLook[$dayKinds[$i]])
            <div class="card flat daypanel" id="day-{{ $i }}" style="background:{{ $bg }};color:{{ $ink }}" @if($i) hidden @endif aria-live="polite">
                <p class="eyebrow" style="color:{{ $sub }}">{{ $d[3] }} · {{ $d[2] }}</p>
                <h3 class="cardt">{{ $d[4] }}</h3>
                <p class="cardb" style="color:{{ $sub }};max-width:340px">{{ $d[5] }}</p>
                @if($art)<img class="art" src="{{ $img($art) }}" alt="" loading="lazy">@else<span class="art" style="width:150px">{!! $busIcon(150, '#1D4ED8', 1.2) !!}</span>@endif
            </div>
        @endforeach
    </div>
</section>

<section id="bus" class="sec slide" data-label="{{ $t('nav_bus') }}" aria-labelledby="m-title">
    <div class="sechead"><span class="stop">5</span><div>
        <p class="eyebrow">{{ $t('m_eyebrow') }}</p><h2 class="h2" id="m-title">{{ $t('m_title') }}</h2>
    </div></div>
    <div class="indent matters" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px">
        <article class="card er">
            <span class="badge"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></span>
            <p class="eyebrow" style="margin-top:8px">{{ $t('er_eyebrow') }}</p>
            <h3 class="cardt" style="font-size:30px;line-height:1.1">{{ $t('er_title') }}</h3>
            <p class="cardb">{{ $t('er_body') }}</p>
            <p class="cardb" style="margin-top:auto;color:var(--er-note);font-weight:600">{{ $t('er_note') }}</p>
        </article>
        <article class="card bw">
            <div class="txt">
                <p class="eyebrow" style="color:var(--bw-eyebrow)">{{ $t('bw_eyebrow') }}</p>
                <h3 class="cardt" style="font-size:30px;line-height:1.1">{{ $t('bw_title') }}</h3>
                <p class="cardb" style="color:var(--bw-text)">{{ $busWeb ? $t('bw_body') : $t('bw_body_app') }}</p>
                @if($busWeb)
                    <a class="btn" href="{{ $busWeb }}" style="background:var(--bw-btn);color:var(--bw-btn-ink);margin-top:auto;align-self:flex-start;min-height:50px">{{ $t('bw_cta') }}{!! $arrow !!}</a>
                    <span style="font-size:13px;color:var(--bw-small)">{{ preg_replace('#^https?://#', '', $busWeb) }}</span>
                @else
                    <a class="btn" href="#get" style="background:var(--bw-btn);color:var(--bw-btn-ink);margin-top:auto;align-self:flex-start;min-height:50px">{{ $t('get_app') }}{!! $arrow !!}</a>
                @endif
            </div>
            <div class="shot"><img src="{{ $img('app-bus.webp') }}" alt="{{ $t('bus_alt') }}" width="190" height="226"></div>
        </article>
    </div>
</section>

<section id="safe" class="sec slide" data-label="{{ $t('nav_safe') }}" aria-labelledby="safe-title">
    <div class="sechead"><span class="stop">6</span><div>
        <p class="eyebrow">{{ $t('safe_eyebrow') }}</p><h2 class="h2" id="safe-title">{{ $t('safe_title') }}</h2>
    </div></div>
    <div class="indent safe4" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px">
        @foreach($t('safe') as $i => [$title, $body])
            <article class="card">
                @if($i === 0)
                    <div style="display:flex;gap:8px"><span style="height:30px;padding:0 10px;border-radius:4px;background:#FFCC00;color:#1D2D2B;font-size:12px;font-weight:900;display:flex;align-items:center">MoMo</span><span style="height:30px;padding:0 10px;border-radius:4px;background:#000;color:#FF7900;font-size:12px;font-weight:900;display:flex;align-items:center">OM</span></div>
                @elseif($i === 1)
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--icon)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.4 3.4-5.5 6.5-5.5s5.7 2.1 6.5 5.5"/><path d="M16 7.5a3 3 0 010 6M19 20c-.3-1.9-1.2-3.4-2.6-4.4"/></svg>
                @elseif($i === 2)
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--icon)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>
                @else
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--icon)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L2.4 18a2 2 0 001.7 3h15.8a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
                @endif
                <h3 class="cardt" style="font-size:19px">{{ $title }}</h3>
                <p class="cardb">{{ $body }}</p>
            </article>
        @endforeach
    </div>
    <p class="indent together" style="margin:0;font-size:40px;line-height:1.1;font-weight:900;letter-spacing:-.02em">{{ $t('together') }}<span style="color:#E02020">!</span></p>
</section>

<section id="drive" class="sec slide" data-label="{{ $t('nav_drive') }}" aria-labelledby="dr-title">
    <div class="sechead"><span class="stop">7</span><div>
        <p class="eyebrow">{{ $t('drive_eyebrow') }}</p><h2 class="h2" id="dr-title">{{ $t('drive_title') }}</h2><p class="lead">{{ $t('drive_body') }}</p>
    </div></div>
    <div class="indent drive">
        <article class="card drive-card">
            <img src="{{ $img('ride.webp') }}" alt="" loading="lazy" class="drive-art">
            <ul class="pts" style="font-size:16px">@foreach($t('drive_pts') as $pt)<li>{{ $pt }}</li>@endforeach</ul>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:auto">
                <a class="btn btn-red" href="{{ $driverUrl }}" rel="noopener">{!! $play !!}{{ $t('drive_cta') }}</a>
                @if($driverAppStoreUrl)<a class="btn btn-line" href="{{ $driverAppStoreUrl }}" rel="noopener">{!! $apple !!}{{ $t('appstore') }}</a>
                @else<span class="btn btn-line" style="cursor:default;opacity:.75">{!! $apple !!}{{ $t('appstore_soon') }}</span>@endif
            </div>
        </article>
        <div class="card">
            <p class="eyebrow">{{ $t('drive_start') }}</p>
            <ol class="drive-steps">@foreach($t('drive_steps') as $i => $step)<li><span class="n">{{ $i + 1 }}</span>{{ $step }}</li>@endforeach</ol>
        </div>
    </div>
</section>

<section id="partners" class="sec slide" data-label="{{ $t('nav_partners') }}" aria-labelledby="p-title">
    <div class="sechead"><span class="stop">8</span><div>
        <p class="eyebrow">{{ $t('p_eyebrow') }}</p><h2 class="h2" id="p-title">{{ $t('p_title') }}</h2>
    </div></div>
    <div class="indent part4" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px">
        @foreach($t('partners') as [$kicker, $title, $body, $cta, $ask])
            @continue($ask === 'driver')
            @php($href = $partnerLink($ask))
            <article class="card" style="min-height:250px">
                <p class="eyebrow" style="color:var(--kicker)">{{ $kicker }}</p>
                <h3 class="cardt" style="font-size:20px">{{ $title }}</h3>
                <p class="cardb">{{ $body }}</p>
                @if($href)<a href="{{ $href }}" rel="noopener" style="margin-top:auto;font-size:15px;font-weight:700;color:var(--cta);display:inline-flex;align-items:center;gap:8px">{{ $cta }}{!! $arrow !!}</a>@endif
            </article>
        @endforeach
    </div>
</section>
</div>

<section id="get" class="wrap slide" data-label="{{ $t('slide_get') }}" style="padding-top:140px;padding-bottom:50px" aria-labelledby="get-title">
    <div class="get">
        <svg width="700" height="320" viewBox="0 0 700 320" fill="none" aria-hidden="true" style="position:absolute;right:-60px;top:-20px">
            <path d="M0 260 C 160 300, 240 90, 380 140 S 600 300, 700 40" stroke="#FFFFFF" stroke-opacity="0.35" stroke-width="2.5" stroke-dasharray="2 10" stroke-linecap="round"/>
        </svg>
        <div style="display:flex;flex-direction:column;gap:16px;position:relative">
            <p class="eyebrow" style="color:#FFE1DE">{{ $t('get_eyebrow') }}</p>
            <h2 id="get-title">{{ $t('get_title') }}</h2>
            <p class="lead" style="color:#FFE1DE">{{ $t('get_body') }}</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px">
                <a class="btn btn-ink" href="{{ $playUrl }}" rel="noopener">{!! $play !!}{{ $t('play') }}</a>
                @if($appStoreUrl)<a class="btn btn-ink" href="{{ $appStoreUrl }}" rel="noopener">{!! $apple !!}{{ $t('appstore') }}</a>
                @else<span class="btn" style="border:1.5px solid rgba(255,255,255,.6);color:#FFFFFF;cursor:default">{!! $apple !!}{{ $t('appstore_soon') }}</span>@endif
                @if($busWeb)<a class="btn" href="{{ $busWeb }}" style="border:1.5px solid rgba(255,255,255,.6);color:#FFFFFF">{{ $t('bus_cta') }}</a>@endif
            </div>
        </div>
        <div class="appcards" aria-label="{{ $t('apps_title') }}">
            @foreach([[$t('app_rider'), $qr, route('get-app')], [$t('app_driver'), $qrDriver, route('get-app', ['app' => 'driver'])]] as [[$name, $what], $code, $link])
                <a class="appcard" href="{{ $link }}">
                    <span class="qr" role="img" aria-label="{{ $t('qr_alt') }}: {{ $name }}">{!! $code !!}</span>
                    <span class="appcard-txt"><b>{{ $name }}</b><small>{{ $what }}</small><em>{{ $t('scan_one') }}</em></span>
                </a>
            @endforeach
        </div>
    </div>
</section>
</main>

<footer class="wrap">
    <div class="foot-top">
        <div style="display:flex;flex-direction:column;gap:10px;max-width:320px">
            <img class="logo-dark" src="{{ $img('logo-on-dark.png') }}" alt="E-Ride" style="height:30px;width:auto;align-self:flex-start">
            <img class="logo-light" src="{{ $img('logo-on-light.png') }}" alt="E-Ride" style="height:30px;width:auto;align-self:flex-start">
            <span style="font-size:14px;color:var(--muted)">{{ $t('foot_tag') }}</span>
            <span style="font-size:14px;color:var(--muted)">{{ $t('powered') }} <b style="color:var(--ink);letter-spacing:.02em">I-NNOVA</b></span>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
                <a class="badge-store" href="{{ $playUrl }}" rel="noopener">{!! $play !!}Google Play</a>
                @if($appStoreUrl)<a class="badge-store" href="{{ $appStoreUrl }}" rel="noopener">{!! $apple !!}App Store</a>
                @else<span class="badge-store" style="opacity:.7">{!! $apple !!}{{ $t('appstore_soon') }}</span>@endif
            </div>
        </div>
        <div class="cols">
            <div><span class="ft">{{ $t('f_services') }}</span>
                @foreach($t('svc_names') as $i => $name)
                    @if($i === 3 && $busWeb)<a href="{{ $busWeb }}">{{ $name }}</a>@else<a href="#services">{{ $name }}</a>@endif
                @endforeach
            </div>
            <div><span class="ft">{{ $t('f_partners') }}</span>
                <a href="#drive">{{ $t('f_drive') }}</a>
                <a href="{{ $portals['food'] }}">{{ $t('f_portal_food') }}</a>
                <a href="{{ $portals['agency'] }}">{{ $t('f_portal_bus') }}</a>
                <a href="{{ $portals['hotel'] }}">{{ $t('f_portal_hotel') }}</a>
            </div>
            <div><span class="ft">{{ $t('f_company') }}</span>
                <a href="{{ route('about-us') }}">{{ $t('about') }}</a>
                <a href="{{ route('contact-us') }}">{{ $t('contact_us') }}</a>
                <a href="#safe">{{ $t('f_safety') }}</a>
            </div>
            <div><span class="ft">{{ $t('contact') }}</span>
                @if($contact['phone'])<a href="tel:{{ preg_replace('/[^\d+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a>@endif
                @if($contact['email'])<a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>@endif
                @if($contact['address'])<span>{{ $contact['address'] }}</span>@endif
            </div>
        </div>
    </div>
    <div class="foot-bottom">
        <span>&copy; {{ date('Y') }} E-Ride. {{ $t('rights') }}</span>
        @if($social)
            <span class="social" aria-label="{{ $t('f_follow') }}">@foreach($social as $l)<a href="{{ $l['link'] }}" rel="noopener" target="_blank">{{ ucfirst($l['name']) }}</a>@endforeach</span>
        @endif
        <span class="foot-links">
            <a href="{{ route('privacy') }}">{{ $t('privacy') }}</a>
            <a href="{{ route('terms') }}">{{ $t('terms') }}</a>
            <a href="{{ url('/?lang=' . $other) }}" hreflang="{{ $other }}">{{ $t('switch_lang') }}</a>
        </span>
    </div>
</footer>
<script>
// The hero's tabs: Bus, Ride, Food, Parcel (arrow keys move between them).
(function () {
    var tabs = [].slice.call(document.querySelectorAll('.tab'));
    function show(tab) {
        tabs.forEach(function (x) {
            var on = x === tab;
            x.setAttribute('aria-selected', on ? 'true' : 'false');
            x.tabIndex = on ? 0 : -1;
            document.getElementById(x.getAttribute('aria-controls')).hidden = !on;
        });
    }
    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { show(tab); });
        tab.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            var next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
            show(next); next.focus(); e.preventDefault();
        });
    });
})();
// The sign-in menu closes on a tap outside it, or Escape.
(function () {
    var menu = document.querySelector('.menu');
    if (!menu) return;
    document.addEventListener('click', function (e) { if (menu.open && !menu.contains(e.target)) menu.open = false; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); } });
})();
// The menu sheet (phones and tablets).
(function () {
    var btn = document.querySelector('.burger'), sheet = document.getElementById('site-menu');
    if (!btn || !sheet) return;
    function set(open) {
        sheet.hidden = !open; btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.documentElement.style.overflow = open ? 'hidden' : '';
        (open ? sheet.querySelector('.sheet-close') : btn).focus();
    }
    btn.addEventListener('click', function () { set(true); });
    sheet.querySelector('.sheet-close').addEventListener('click', function () { set(false); });
    sheet.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { if (a.getAttribute('href').charAt(0) === '#') set(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !sheet.hidden) set(false); });
})();
// Slides: content rises in as each section arrives, side dots follow, the red route fills.
(function () {
    var root = document.documentElement;
    var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var slides = [].slice.call(document.querySelectorAll('.slide'));
    if (!still && 'IntersectionObserver' in window) {
        root.classList.add('js-motion');
        document.querySelectorAll('.hero > div:first-child > *, .hero .hero-art').forEach(function (el, i) { el.classList.add('rise'); el.style.animationDelay = (i * 0.08) + 's'; });
        slides.forEach(function (s) {
            if (s.classList.contains('hero')) return;
            var items = s.querySelectorAll('.sechead, .indent > :not(.daypanel), .grid4 > *, .how > li, .steps > li, .get > *');
            [].forEach.call(items, function (el, i) { el.classList.add('reveal'); el.style.transitionDelay = Math.min(i, 8) * 0.07 + 's'; });
        });
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
        }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
        document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
    }
    var dots = document.querySelector('.dots');
    var buttons = slides.map(function (s, i) {
        var b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('aria-label', s.dataset.label || ('' + (i + 1)));
        b.innerHTML = '<span></span>';
        b.firstChild.textContent = s.dataset.label || '';
        b.addEventListener('click', function () { s.scrollIntoView({ behavior: still ? 'auto' : 'smooth', block: 'start' }); });
        dots.appendChild(b);
        return b;
    });
    var trail = document.querySelector('.trail'), fill = document.querySelector('.trail-fill'), ticking = false;
    function onScroll() {
        ticking = false;
        var mid = window.innerHeight * 0.45, current = 0;
        slides.forEach(function (s, i) { if (s.getBoundingClientRect().top <= mid) current = i; });
        buttons.forEach(function (b, i) { b.setAttribute('aria-current', i === current ? 'true' : 'false'); });
        if (trail && fill) {
            var r = trail.getBoundingClientRect();
            fill.style.height = Math.max(0, Math.min(r.height, mid - r.top)) + 'px';
        }
    }
    window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
    window.addEventListener('resize', onScroll);
    onScroll();
})();
// City guide: the visitor's city, from the list or their location (only cities where E-Ride is set up).
(function () {
    var bar = document.querySelector('.citybar');
    if (!bar) return;
    var cities = JSON.parse(bar.dataset.cities || '[]'), pick = bar.querySelector('.citypick'), locate = bar.querySelector('.locate');
    var title = document.querySelector('[data-explore-title]'), status = document.querySelector('.citystatus');
    function useCity(city) {
        if (!city) return;
        title.textContent = title.dataset.one.replace('{city}', city);
        status.textContent = bar.dataset.in.replace('{city}', city);
        if (pick) pick.value = city;
        try { localStorage.setItem('eride_city', city); } catch (e) {}
    }
    try { var saved = localStorage.getItem('eride_city'); if (saved && cities.indexOf(saved) !== -1) useCity(saved); } catch (e) {}
    if (pick) pick.addEventListener('change', function () { useCity(pick.value); });
    if (locate) locate.addEventListener('click', function () {
        if (!navigator.geolocation) { status.textContent = bar.dataset.denied; return; }
        status.textContent = bar.dataset.locating;
        navigator.geolocation.getCurrentPosition(function (pos) {
            fetch(bar.dataset.url + '?lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.city) { useCity(d.city); }
                    else { title.textContent = title.dataset.any; status.textContent = bar.dataset.out.replace('{list}', cities.join(', ')); }
                })
                .catch(function () { status.textContent = bar.dataset.denied; });
        }, function () { status.textContent = bar.dataset.denied; }, { timeout: 10000, maximumAge: 600000 });
    });
})();
// "A day in Bamenda": one time at a time.
(function () {
    var buttons = document.querySelectorAll('.daybtn');
    buttons.forEach(function (b) {
        b.addEventListener('click', function () {
            buttons.forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
            document.querySelectorAll('.daypanel').forEach(function (p) { p.hidden = p.id !== 'day-' + b.dataset.day; });
        });
    });
})();
</script>
</body>
</html>
