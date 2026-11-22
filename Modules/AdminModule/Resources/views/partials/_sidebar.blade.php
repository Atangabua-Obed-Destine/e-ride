{{--
    The admin sidebar, built from Modules\AdminModule\Lib\AdminNav: every page grouped by
    business area, with the admin's permissions applied. Sections fold (the one you are in
    stays open); the search finds pages inside sub-menus too. The desktop menu bar, "Go to…"
    and the phone menu read the same map.
--}}
@php($navSections = \Modules\AdminModule\Lib\AdminNav::sections())
<aside class="aside">
    <div class="aside-header">
        <a href="{{ route('admin.dashboard') }}" class="logo d-flex gap-2">
            <img width="115"
                 src="{{ $logo ? dynamicStorage("storage/app/public/business/".$logo) : dynamicAsset('public/assets/admin-module/img/logo.png') }}"
                 alt="" class="main-logo">
        </a>
        <button class="toggle-menu-button aside-toggle bg-white p-0 dark-color" aria-label="{{ translate('Fold the menu') }}">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <div class="position-relative flex-grow-1">
        <div class="aside-body" data-trigger="scrollbar">
            <div class="user-profile media gap-3 align-items-center my-3">
                <div class="avatar rounded-circle">
                    <img class="fit-object dark-support rounded-circle"
                         src="{{ onErrorImage(
                        auth()->user()?->profile_image,
                        dynamicStorage('storage/app/public/employee/profile') . '/' . auth()->user()->profile_image,
                        dynamicAsset('public/assets/admin-module/img/user.png'),
                        'employee/profile/',
                    ) }}" alt="">
                </div>
                <div class="media-body">
                    <div class="card-title word-break fw-bold">{{ trim(auth()->user()?->first_name . ' ' . auth()->user()?->last_name) ?: auth()->user()?->email }}</div>
                    <span class="card-text">{{ auth()->user()?->user_type === 'super-admin' ? translate('Super admin') : translate('Staff') }}</span>
                </div>
            </div>

            <div class="aside-search mb-2">
                <div class="input-group search-form__input_group">
                    <span class="search-form__icon"><i class="bi bi-search"></i></span>
                    <input type="search" id="admin-nav-search" class="theme-input-style search-form__input" autocomplete="off"
                           placeholder="{{ translate('Find a page') }}" aria-label="{{ translate('Find a page') }}"/>
                </div>
                <div class="ed-nav-hint">{{ translate('Tip: Ctrl + K finds any page from anywhere') }}</div>
            </div>

            <ul class="main-nav nav" id="admin-nav">
                @foreach($navSections as $section)
                    @php($sectionOpen = $section['is_active'] || $section['key'] === 'overview')
                    <li class="nav-category ed-cat {{ $sectionOpen ? 'ed-open' : '' }}" data-ed-section="{{ $section['key'] }}"
                        title="{{ translate($section['title']) }}" role="button" tabindex="0" aria-expanded="{{ $sectionOpen ? 'true' : 'false' }}">
                        <i class="bi {{ $section['icon'] }} ed-cat-icon"></i>
                        <span>{{ translate($section['title']) }}</span>
                        <i class="bi bi-chevron-down ed-cat-caret"></i>
                    </li>
                    @foreach($section['items'] as $item)
                        @if(!empty($item['children']))
                            <li class="{{ $item['is_active'] ? 'active sub-menu-opened' : '' }}" data-ed-in="{{ $section['key'] }}">
                                <a href="#" data-ed-words="{{ strtolower(translate($item['title']) . ' ' . ($item['words'] ?? '')) }}">
                                    <i class="bi {{ $item['icon'] ?? $section['icon'] }}"></i>
                                    <span class="link-title">{{ translate($item['title']) }}</span>
                                </a>
                                <ul class="nav flex-column sub-menu">
                                    @foreach($item['children'] as $child)
                                        <li class="{{ $child['is_active'] ? 'active open' : '' }}">
                                            <a href="{{ $child['url'] }}" data-ed-words="{{ strtolower(translate($child['title']) . ' ' . ($child['words'] ?? '')) }}">
                                                <i class="bi bi-dot"></i>
                                                <span class="link-title">{{ translate($child['title']) }}</span>
                                                @if(($child['badge_count'] ?? 0) > 0)
                                                    <span class="badge {{ str_starts_with($child['badge'] ?? '', 'trip:') ? 'badge-primary' : 'bg-danger' }} ms-auto">{{ $child['badge_count'] }}</span>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li class="{{ $item['is_active'] ? 'active open' : '' }}" data-ed-in="{{ $section['key'] }}">
                                <a href="{{ $item['url'] }}" data-ed-words="{{ strtolower(translate($item['title']) . ' ' . ($item['words'] ?? '')) }}">
                                    <i class="bi {{ $item['icon'] ?? $section['icon'] }}"></i>
                                    <span class="link-title">{{ translate($item['title']) }}</span>
                                    @if(($item['badge_count'] ?? 0) > 0)
                                        <span class="badge bg-danger ms-auto">{{ $item['badge_count'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endif
                    @endforeach
                @endforeach
                <li class="ed-nav-empty d-none"><span>{{ translate('No page matches. Try other words, or Ctrl + K.') }}</span></li>
            </ul>
        </div>
    </div>
</aside>

@push('script')
<script>
    // Sidebar sections fold (remembered); the one you are in stays open; the search opens what it finds.
    (function () {
        var nav = document.getElementById('admin-nav');
        if (!nav) return;
        var KEY = 'ed.nav.open', saved = {};
        try { saved = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) {}
        var cats = Array.prototype.slice.call(nav.querySelectorAll('.ed-cat'));
        function items(key) { return Array.prototype.slice.call(nav.querySelectorAll('[data-ed-in="' + key + '"]')); }
        function hasActive(key) { return items(key).some(function (li) { return li.classList.contains('active'); }); }
        function show(cat, open) {
            cat.classList.toggle('ed-open', open);
            cat.setAttribute('aria-expanded', open ? 'true' : 'false');
            items(cat.dataset.edSection).forEach(function (li) { li.classList.toggle('ed-hidden', !open); });
        }
        function wanted(cat) {
            var key = cat.dataset.edSection;
            if (hasActive(key)) return true;
            if (saved[key] === true || saved[key] === false) return saved[key];
            return key === 'overview';
        }
        cats.forEach(function (cat) {
            var key = cat.dataset.edSection;
            show(cat, wanted(cat));
            var toggle = function () {
                var now = !cat.classList.contains('ed-open');
                show(cat, now);
                saved[key] = now;
                try { localStorage.setItem(KEY, JSON.stringify(saved)); } catch (e) {}
            };
            cat.addEventListener('click', toggle);
            cat.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
        });

        var input = document.getElementById('admin-nav-search'), empty = nav.querySelector('.ed-nav-empty');
        if (!input) return;
        function textOf(a) { return a ? ((a.dataset.edWords || '') + ' ' + a.textContent.toLowerCase()) : ''; }
        input.addEventListener('input', function () {
            var words = input.value.toLowerCase().trim().split(/\s+/).filter(Boolean), any = false;
            var hit = function (a) { var t = textOf(a); return words.every(function (w) { return t.indexOf(w) !== -1; }); };
            cats.forEach(function (cat) {
                var key = cat.dataset.edSection;
                if (!words.length) {
                    cat.classList.remove('ed-hidden');
                    items(key).forEach(function (li) { li.querySelectorAll('.sub-menu > li').forEach(function (s) { s.classList.remove('ed-hidden'); }); });
                    show(cat, wanted(cat));
                    return;
                }
                var secHit = false;
                items(key).forEach(function (li) {
                    var subs = li.querySelectorAll('.sub-menu > li'), liHit;
                    if (subs.length) {
                        var parentHit = hit(li.querySelector(':scope > a')), n = 0;
                        subs.forEach(function (s) { var h = parentHit || hit(s.querySelector('a')); s.classList.toggle('ed-hidden', !h); if (h) n++; });
                        liHit = n > 0;
                        var ul = li.querySelector('.sub-menu');
                        if (liHit && ul) { ul.style.display = 'block'; ul.classList.add('open'); li.classList.add('sub-menu-opened'); }
                    } else {
                        liHit = hit(li.querySelector('a'));
                    }
                    li.classList.toggle('ed-hidden', !liHit);
                    if (liHit) secHit = true;
                });
                cat.classList.toggle('ed-hidden', !secHit);
                cat.classList.toggle('ed-open', secHit);
                if (secHit) any = true;
            });
            empty.classList.toggle('d-none', !words.length || any);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                var first = nav.querySelector('li:not(.ed-hidden):not(.nav-category) li:not(.ed-hidden) > a[href]:not([href="#"]), li:not(.ed-hidden):not(.nav-category) > a[href]:not([href="#"])');
                if (first) { e.preventDefault(); first.click(); }
            }
            if (e.key === 'Escape') { input.value = ''; input.dispatchEvent(new Event('input')); }
        });
    })();
</script>
@endpush
