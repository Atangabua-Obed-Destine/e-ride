@php
    use Modules\BusManagement\Entities\BusCompanyStaff;
    // The menus, grouped (Watch, Run, Money; Modules/BusManagement/Lib/PortalMenu.php): the phone's
    // row of links and, on a wide screen, the menus, icon rail and "Go to…" of the desktop workspace
    // (resources/views/layouts/partials/desktop.blade.php, posinnova's).
    $menus = \Modules\BusManagement\Lib\PortalMenu::company($busCompanyStaff->role);
    $nav = \Modules\BusManagement\Lib\PortalMenu::flat($menus);
    $deskCfg = [
        'home' => route('bus-company.overview'),
        'brand' => $busCompany->name,
        'who' => auth()->id() . ':' . $busCompany->id,
        'suffix' => ' · ' . $busCompany->name,
        'place' => ['bi-building', $busCompany->name . ' · ' . translate('Head office')],
        'user' => trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) . ' · ' . BusCompanyStaff::roleLabel($busCompanyStaff->role),
        // Every accounting screen belongs to the one "Accounting" tab.
        'alias' => ['partner/accounting' => 'partner/bus-company/accounting'],
        'side' => array_map(fn ($m) => ['heading' => $m[1], 'links' => array_map(fn ($l) => [route($l[0]), $l[1], $l[2], request()->routeIs($l[0] . '*'), null], $m[2])], $menus),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#111114">
    <title>@yield('title') · {{ $busCompany->name }}</title>
    <link rel="icon" href="{{ dynamicAsset('public/assets/admin-module/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap-icons.min.css') }}">
    @include('busmanagement::partner._portal_style')
    <style>
        .ho-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .ho-filters .form-select, .ho-filters .form-control { min-height: 38px; width: auto; max-width: 100%; }
        .kpi { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; }
        .kpi b { display: block; font-size: 22px; font-weight: 800; line-height: 1.2; }
        .kpi span { font-size: 12.5px; color: var(--muted); }
        .kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
        .ho-table { width: 100%; font-size: 13.5px; }
        .ho-table th { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .02em; white-space: nowrap; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        .ho-table td { padding: 9px 10px; border-bottom: 1px solid #f0f2f2; vertical-align: top; }
        .ho-table td.n, .ho-table th.n { text-align: right; white-space: nowrap; }
        .ho-scroll { overflow-x: auto; }
    </style>
    @include('layouts.partials.desktop', ['part' => 'head'])
    {{-- busToast, busPost, busDesk: in the head, for the scripts of the page itself. --}}
    @include('busmanagement::partner._portal_js')
    @stack('head')
</head>
<body>
@include('layouts.partials.desktop', ['part' => 'titlebar', 'desk' => $deskCfg])
<div class="thetop">
@include('layouts.partials.desktop', ['part' => 'side', 'desk' => $deskCfg, 'piClass' => 'pi-desk-only', 'piLabel' => translate('Head office menu')])
<main>
<header class="b-top" id="pi-header">
    <div class="b-top__in">
        <div class="b-bar">
            @include('layouts.partials.desktop', ['part' => 'toolbar'])
            <div class="b-logo">@if($busCompany->logo)<img src="{{ $busCompany->logoUrl() }}" alt="">@else{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(preg_replace('/[^A-Za-z]/', '', $busCompany->name), 0, 2)) }}@endif</div>
            <div class="b-name text-truncate">{{ $busCompany->name }}<small>{{ translate('Head office') }} · {{ translate(key: '{n} branch(es)', replace: ['n' => $busBranches->count()]) }}</small></div>
            <span class="d-none d-md-inline small text-white-50 ms-md-auto">{{ auth()->user()->first_name }} · {{ BusCompanyStaff::roleLabel($busCompanyStaff->role) }}</span>
            @include('layouts.partials.desktop', ['part' => 'go', 'piClass' => 'text-white-50 ms-auto ms-md-0'])
            <form action="{{ route('bus-company.logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm text-white-50 px-1" title="{{ translate('Sign_out') }}" aria-label="{{ translate('Sign_out') }}"><i class="bi bi-box-arrow-right fs-5"></i></button>
            </form>
        </div>
        <nav class="b-nav" aria-label="{{ translate('Head office menu') }}">
            @foreach($nav as [$routeName, $icon, $label])
                <a href="{{ route($routeName) }}" class="{{ request()->routeIs($routeName . '*') ? 'active' : '' }}"><i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}</a>
            @endforeach
        </nav>
    </div>
</header>
@include('layouts.partials.desktop', ['part' => 'tabs'])

<div class="b-main" id="scrollable-container">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</div>
</main>
</div>

<script src="{{ dynamicAsset('public/assets/admin-module/js/bootstrap.bundle.min.js') }}"></script>
<script>
    window.hoMoney = function (n) { return Math.round(n || 0).toLocaleString() + ' FCFA'; };
</script>
@stack('script')
@if (session('toast'))
    <script>busToast(@json(session('toast')));</script>
@endif
@include('layouts.partials.desktop', ['part' => 'status', 'desk' => $deskCfg])
</body>
</html>
