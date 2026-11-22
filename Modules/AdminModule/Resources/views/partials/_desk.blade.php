{{--
    E-Ride admin workspace (public/assets/admin-module/js/eride-desk.js, css/eride-desk.css).

    Wide screens (1200px and more): a title bar with a menu per business area, tabs that keep
    each page alive (iframes), back / forward / reload / print, "Go to…" (Ctrl + K) and a status
    bar. Phones: a bottom bar (Overview, Attention, Go to, Menu). The pages themselves are the
    same; inside a tab they show without the header and sidebar (html.ed-framed).

    Parts: head (before paint), top (first thing in body), toolbar (in the header), bottom.
--}}
@switch($part)
    @case('head')
        <script>
            // Before the page draws: in a tab (framed), on the desktop workspace, or on a phone.
            (function () {
                var d = document.documentElement, framed = false;
                try { framed = window.self !== window.top && !!window.top.ED_DESK_SHELL; } catch (e) { framed = false; }
                if (framed) { d.classList.add('ed-framed'); window.__edFramed = true; return; }
                var off = false;
                try { off = localStorage.getItem('ed.desk') === 'off'; } catch (e) {}
                if (!off && window.innerWidth >= 1200) { d.classList.add('ed-desk'); window.ED_DESK_SHELL = true; }
                if (window.innerWidth < 1200) d.classList.add('ed-phone');
            })();
        </script>
        <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/eride-desk.css') }}?v={{ @filemtime(public_path('assets/admin-module/css/eride-desk.css')) }}"/>
        @break

    @case('top')
        @php $deskSections = \Modules\AdminModule\Lib\AdminNav::sections(); @endphp
        <div id="ed-titlebar" class="ed-desk-only" role="banner">
            <a class="ed-brand" href="{{ route('admin.dashboard') }}" data-ed-chrome>
                <span class="ed-brand-dot"></span>{{ businessConfig('business_name', 'business_information')?->value ?? 'E-Ride' }}
            </a>
            <nav id="ed-menubar" role="menubar" aria-label="{{ translate('Admin menu') }}">
                @foreach($deskSections as $section)
                    <div class="ed-mb-item">
                        <button type="button" class="ed-mb-btn {{ $section['is_active'] ? 'is-here' : '' }}" role="menuitem" aria-haspopup="true" aria-expanded="false">
                            {{ translate($section['title']) }}
                        </button>
                        <div class="ed-mb-menu {{ count($section['items']) + collect($section['items'])->sum(fn($i) => count($i['children'] ?? [])) > 14 ? 'is-wide' : '' }}" role="menu">
                            @foreach($section['items'] as $item)
                                @if(!empty($item['children']))
                                    <div class="ed-mb-group">
                                        <div class="ed-mb-head"><i class="bi {{ $item['icon'] ?? $section['icon'] }}"></i>{{ translate($item['title']) }}</div>
                                        @foreach($item['children'] as $child)
                                            <a href="{{ $child['url'] }}" role="menuitem" data-ed-chrome class="{{ $child['is_active'] ? 'is-here' : '' }}">
                                                <span>{{ translate($child['title']) }}</span>
                                                @if(!empty($child['setting']))<i class="bi bi-sliders ed-mb-set" title="{{ translate('Settings') }}"></i>@endif
                                                @if(($child['badge_count'] ?? 0) > 0 && !str_starts_with($child['badge'] ?? '', 'trip:'))<b class="ed-mb-badge">{{ $child['badge_count'] }}</b>@endif
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <a href="{{ $item['url'] }}" role="menuitem" data-ed-chrome class="ed-mb-top {{ $item['is_active'] ? 'is-here' : '' }}">
                                        <i class="bi {{ $item['icon'] ?? $section['icon'] }}"></i><span>{{ translate($item['title']) }}</span>
                                        @if(!empty($item['setting']))<i class="bi bi-sliders ed-mb-set" title="{{ translate('Settings') }}"></i>@endif
                                        @if(($item['badge_count'] ?? 0) > 0)<b class="ed-mb-badge">{{ $item['badge_count'] }}</b>@endif
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
            <button type="button" class="ed-goto-btn" data-ed="palette" title="{{ translate('Go to a page') }} (Ctrl+K)">
                <i class="bi bi-search"></i><span>{{ translate('Go to…') }}</span><kbd>Ctrl K</kbd>
            </button>
        </div>
        <nav id="ed-tabs" class="ed-desk-only" role="tablist" aria-label="{{ translate('Open pages') }}"></nav>
        <div id="ed-frames" class="ed-desk-only"></div>
        <div id="ed-busy" class="ed-desk-only"></div>
        @break

    @case('toolbar-left')
        <div class="ed-toolbar ed-desk-only" role="toolbar" aria-label="{{ translate('Page') }}">
            <button type="button" data-ed="back" title="{{ translate('Back') }} (Alt+←)" aria-label="{{ translate('Back') }}"><i class="bi bi-arrow-left"></i></button>
            <button type="button" data-ed="forward" title="{{ translate('Forward') }} (Alt+→)" aria-label="{{ translate('Forward') }}"><i class="bi bi-arrow-right"></i></button>
            <button type="button" data-ed="reload" title="{{ translate('Reload this tab') }} (F5)" aria-label="{{ translate('Reload this tab') }}"><i class="bi bi-arrow-clockwise"></i></button>
            <button type="button" data-ed="print" title="{{ translate('Print this tab') }} (Ctrl+P)" aria-label="{{ translate('Print this tab') }}"><i class="bi bi-printer"></i></button>
            <button type="button" data-ed="window" title="{{ translate('Open this tab in a new window') }}" aria-label="{{ translate('Open this tab in a new window') }}"><i class="bi bi-box-arrow-up-right"></i></button>
        </div>
        @break

    @case('search')
        <button type="button" class="ed-search-pill" data-ed="palette" title="{{ translate('Go to a page') }} (Ctrl+K)">
            <i class="bi bi-search"></i><span class="ed-search-text">{{ translate('Search pages and settings') }}</span><kbd>Ctrl K</kbd>
        </button>
        @break

    @case('bottom')
        @php $attention = \Modules\AdminModule\Lib\BusinessPulse::attentionTotal(); @endphp
        <footer id="ed-status" class="ed-desk-only" role="contentinfo">
            <span id="ed-conn" data-state="online" title="{{ translate('Connection') }}"><i></i><span>{{ translate('Online') }}</span></span>
            <span class="ed-st-who"><i class="bi bi-person-circle"></i>{{ trim(auth()->user()?->first_name . ' ' . auth()->user()?->last_name) ?: auth()->user()?->email }}</span>
            <a class="ed-st-attn {{ $attention > 0 ? 'has' : '' }}" href="{{ route('admin.dashboard') }}#attention" data-ed-chrome>
                <i class="bi bi-bell"></i>{{ $attention > 0 ? translate(key: '{count} to look at', replace: ['count' => $attention]) : translate('Nothing waiting') }}
            </a>
            <span class="ed-st-gap"></span>
            <span class="ed-st-keys">Ctrl K {{ translate('Go to') }} · Alt 1…9 {{ translate('tabs') }} · Alt W {{ translate('close tab') }}</span>
            <button type="button" class="ed-st-off" data-ed="desk-off" title="{{ translate('Use the classic layout (one page at a time)') }}"><i class="bi bi-window-stack"></i>{{ translate('Tabs on') }}</button>
            <span id="ed-clock"></span>
        </footer>
        <button type="button" class="ed-desk-on" data-ed="desk-on" title="{{ translate('Turn on tabs, menus and the status bar') }}"><i class="bi bi-window-stack"></i>{{ translate('Desktop mode') }}</button>

        <nav id="ed-phonebar" class="ed-phone-only" aria-label="{{ translate('Main') }}">
            <a href="{{ route('admin.dashboard') }}" class="{{ Request::is('admin') ? 'is-here' : '' }}"><i class="bi bi-grid-1x2"></i><span>{{ translate('Overview') }}</span></a>
            <a href="{{ route('admin.dashboard') }}#attention"><i class="bi bi-bell"></i><span>{{ translate('Attention') }}</span>@if($attention > 0)<b>{{ $attention > 99 ? '99+' : $attention }}</b>@endif</a>
            <button type="button" data-ed="palette"><i class="bi bi-search"></i><span>{{ translate('Go to') }}</span></button>
            <a href="{{ route('admin.settings-center') }}" class="{{ Request::is('admin/settings-center*') ? 'is-here' : '' }}"><i class="bi bi-sliders2"></i><span>{{ translate('Settings') }}</span></a>
            <button type="button" data-ed="menu"><i class="bi bi-list"></i><span>{{ translate('Menu') }}</span></button>
        </nav>

        <div id="ed-palette" class="ed-palette" hidden>
            <div class="ed-pal-box" role="dialog" aria-modal="true" aria-label="{{ translate('Go to a page') }}">
                <div class="ed-pal-input">
                    <i class="bi bi-search"></i>
                    <input type="text" role="combobox" aria-expanded="true" aria-controls="ed-pal-list" aria-autocomplete="list" autocomplete="off" spellcheck="false"
                           placeholder="{{ translate('Type a page, a setting, or a service: e.g. "bus", "fapshi", "withdraw"') }}">
                    <kbd>Esc</kbd>
                </div>
                <ul id="ed-pal-list" role="listbox"></ul>
                <div class="ed-pal-foot">
                    <span><kbd>↑</kbd><kbd>↓</kbd> {{ translate('choose') }}</span><span><kbd>Enter</kbd> {{ translate('open') }}</span>
                    <span class="ed-desk-only"><kbd>Shift</kbd>+<kbd>Enter</kbd> {{ translate('new tab') }}</span>
                </div>
            </div>
        </div>

        @php
            $deskCfg = [
                'home' => route('admin.dashboard'),
                'base' => url('/'),
                'admin' => url('/admin'),
                'who' => (string)auth()->id(),
                'pages' => \Modules\AdminModule\Lib\AdminNav::flat(),
                'text' => [
                    'opening' => translate('Opening…'),
                    'close' => translate('Close'),
                    'noResult' => translate('Nothing found. Try another word.'),
                    'recent' => translate('Recently opened'),
                    'settings' => translate('Setting'),
                    'online' => translate('Online'),
                    'offline' => translate('Offline'),
                    'closedTab' => translate('Closed the tab you used least, to make room:'),
                    'deskOff' => translate('Classic layout: one page at a time. Turn the desktop mode back on from the button at the bottom.'),
                ],
            ];
        @endphp
        <script>
            window.ED_DESK_CFG = {!! json_encode($deskCfg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!};
        </script>
        <script src="{{ dynamicAsset('public/assets/admin-module/js/eride-desk.js') }}?v={{ @filemtime(public_path('assets/admin-module/js/eride-desk.js')) }}"></script>
        @break
@endswitch
