{{--
    The desktop workspace: posinnova's (resources/views/layouts/partials/desktop.blade.php
    there), shared by our systems so staff who use both find the same window. Included by
    the bus branch portal, the head office portal (Modules/BusManagement/Resources/views/
    partner|company/layout.blade.php) and the accounting frame (layouts/app.blade.php).
    Drawn here so nothing jumps while the page loads; public/assets/desk/desktop.js fills
    the menus and runs the tabs, public/assets/desk/pi-desktop.css lays them out.
    On a phone or a narrow window only the search button ("Go to…") and the menu it reads
    (the sidebar, hidden there) are used.

    $part: head | titlebar | toolbar | go | side | tabs | status
    $desk (every part but head):
      home     the Home tab's address        brand   the name in the title bar
      who      whose tabs these are          suffix  the end of the page titles
      place    [bootstrap icon, text]        user    the person, for the status bar
      alias, strip (optional) see desktop.js side    the sidebar: a list of sections
               ['heading', 'quiet' (heading only read by the menus), 'class',
                'drop' (null or [icon, label]: the section folds, one icon on the rail),
                'links' => [[href, icon, label, active, badge]]]
      first    (optional) a link above the sections: [href, icon, label, active]
--}}
@if ($part === 'head')
    {{-- ?v= carries each file's change time, as on posinnova. --}}
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/desk/pi-desktop.css') }}?v={{ @filemtime(public_path('assets/desk/pi-desktop.css')) }}">
    {{-- Before the page draws: a screen shown inside a workspace tab draws
         without the window's parts (pi-framed); a wide window gets the
         workspace (pi-desk). --}}
    <script>
        (function () {
            var d = document.documentElement;
            try { if (window.parent !== window && window.parent.PI_DESK) d.classList.add('pi-framed'); } catch (e) {}
            if (!d.classList.contains('pi-framed') && window.innerWidth >= 1024) d.classList.add('pi-desk');
            try { if (localStorage.getItem('pi.desk.sideFull') === '1') d.classList.add('pi-side-full'); } catch (e) {}
        })();
    </script>
@elseif ($part === 'titlebar')
    <div id="pi-titlebar" class="pi-desk-only no-print">
        <a href="{{ $desk['home'] }}" class="pi-tb-brand" title="{{ __('desktop.home') }}">
            <span class="pi-tb-dot"></span>
            <span class="pi-tb-name">{{ $desk['brand'] }}</span>
        </a>
        <nav id="pi-menubar" role="menubar" aria-label="{{ __('desktop.menu') }}"></nav>
        <span class="pi-tb-title">@yield('title')</span>
    </div>
@elseif ($part === 'toolbar')
    {{-- The header's sidebar button and toolbar (wide screens only). --}}
    <button type="button" class="side-bar-collapse pi-tb-side pi-desk-only" title="{{ __('desktop.sidebar') }}" aria-label="{{ __('desktop.sidebar') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM9 5v14"/></svg>
    </button>
    <div class="pi-tb pi-desk-only" role="toolbar" aria-label="{{ __('desktop.toolbar') }}">
        <button type="button" data-pi="back" title="{{ __('desktop.back') }} (Alt+←)" aria-label="{{ __('desktop.back') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
        <button type="button" data-pi="forward" title="{{ __('desktop.forward') }} (Alt+→)" aria-label="{{ __('desktop.forward') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
        <button type="button" data-pi="reload" title="{{ __('desktop.refresh') }} (F5)" aria-label="{{ __('desktop.refresh') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.6m14.8 2A8 8 0 004.6 9M4.6 9H9m11 11v-5h-.6m0 0a8 8 0 01-15.4-2m15.4 2H15"/></svg></button>
        <button type="button" data-pi="print" title="{{ __('desktop.print') }} (Ctrl+P)" aria-label="{{ __('desktop.print') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg></button>
        <button type="button" data-pi="palette" class="pi-tb-wide" title="{{ __('desktop.go_to_title') }} (Ctrl+K)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg><span>{{ __('desktop.go_to') }}</span></button>
    </div>
@elseif ($part === 'go')
    {{-- "Go to…" on phones and narrow windows (posinnova fc3eba1); wide screens have it in the toolbar. --}}
    <button type="button" data-pi="go" class="pi-go pi-narrow-only {{ $piClass ?? '' }}" title="{{ __('desktop.go_to_title') }}" aria-label="{{ __('desktop.go_to_title') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
    </button>
@elseif ($part === 'side')
    <aside class="side-bar pi-side {{ $piClass ?? '' }}" @isset($piId) id="{{ $piId }}" @endisset aria-label="{{ $piLabel ?? __('desktop.menu') }}">
        <div id="side-bar">
            @if (!empty($desk['first']))
                @php [$href, $icon, $text, $on] = $desk['first']; @endphp
                <a href="{{ $href }}" class="pi-side-first {{ $on ? 'active' : '' }}" @if($on) aria-current="page" @endif><i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $text }}</span></a>
            @endif
            @foreach ($desk['side'] as $sec)
                <div class="side-bar-area-heading {{ !empty($sec['quiet']) ? 'pi-quiet' : '' }} {{ $sec['class'] ?? '' }}">{{ $sec['heading'] }}</div>
                @if (!empty($sec['drop']))
                    @php $open = collect($sec['links'])->contains(fn ($l) => $l[3]); @endphp
                    <div class="pi-side-group {{ $open ? 'open' : '' }} {{ $sec['class'] ?? '' }}">
                        <a href="#" class="drop_down {{ $open ? 'pi-has-current' : '' }}" aria-expanded="{{ $open ? 'true' : 'false' }}"><i class="bi {{ $sec['drop'][0] }}" aria-hidden="true"></i><span>{{ $sec['drop'][1] }}</span><svg class="svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></a>
                        <div class="chiled" @unless($open) style="display: none" @endunless>
                            @foreach ($sec['links'] as [$href, $icon, $text, $on])
                                <a href="{{ $href }}" class="{{ $on ? 'active' : '' }}" @if($on) aria-current="page" @endif><span>{{ $text }}</span></a>
                            @endforeach
                        </div>
                    </div>
                @else
                    @foreach ($sec['links'] as $l)
                        <a href="{{ $l[0] }}" class="{{ $l[3] ? 'active' : '' }} {{ $sec['class'] ?? '' }}" @if($l[3]) aria-current="page" @endif @if(!empty($l[4])) data-badge="{{ $l[4] }}" @endif><i class="bi {{ $l[1] }}" aria-hidden="true"></i><span>{{ $l[2] }}</span></a>
                    @endforeach
                @endif
            @endforeach
        </div>
    </aside>
    <script>
        // A group of the menu folds and unfolds (on the rail, desktop.js first widens the menu).
        document.addEventListener('click', function (e) {
            var d = e.target.closest && e.target.closest('#side-bar a.drop_down');
            if (!d) return;
            e.preventDefault();
            var sub = d.nextElementSibling, open = sub.style.display === 'none';
            sub.style.display = open ? '' : 'none';
            d.parentNode.classList.toggle('open', open);
            d.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    </script>
@elseif ($part === 'tabs')
    <nav id="pi-tabs" class="pi-desk-only no-print" role="tablist" aria-label="{{ __('desktop.open_screens') }}"></nav>
@elseif ($part === 'status')
    <footer id="pi-status" class="pi-desk-only no-print">
        <span id="pi-conn" data-state="online" title="{{ __('desktop.conn_title') }}"><span class="pi-dot"></span><span data-conn-text>{{ __('desktop.conn_online') }}</span></span>
        <span class="pi-sb-item"><i class="bi {{ $desk['place'][0] }}"></i> {{ $desk['place'][1] }}</span>
        <span class="pi-sb-item"><i class="bi bi-person"></i> {{ $desk['user'] }}</span>
        <span class="pi-sb-gap"></span>
        <button type="button" class="pi-sb-btn" data-pi="palette" title="{{ __('desktop.go_to_title') }}"><kbd>Ctrl K</kbd> {{ __('desktop.go_to') }}</button>
        <span class="pi-sb-item pi-sb-keys">{{ __('desktop.key_tabs') }}</span>
        <span id="pi-clock" class="pi-sb-item"></span>
    </footer>
    @php
        $__pi_desk = [
            'home' => $desk['home'],
            'base' => url('/'),
            'who' => $desk['who'],
            'business' => $desk['brand'],
            'suffix' => $desk['suffix'],
            'homeIcon' => 'bi bi-house-door',
            'alias' => $desk['alias'] ?? null,
            'strip' => $desk['strip'] ?? [],
            'text' => __('desktop'),
        ];
    @endphp
    <script>
        window.PI_DESK_CFG = {!! json_encode($__pi_desk, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
    </script>
    <script src="{{ dynamicAsset('public/assets/desk/desktop.js') }}?v={{ @filemtime(public_path('assets/desk/desktop.js')) }}"></script>
@endif
