{{--
    Landing-page header.

    Separate from partials/_header.blade.php on purpose: this nav points at
    sections WITHIN the landing page (#services, #how, …), which would be dead
    links on /about-us, /privacy and the rest. Those pages keep the original
    shared header.

    It deliberately reuses the vendor class names .navbar-bottom, .menu,
    .nav-toggle and .cmn--btn so the existing hamburger handler in
    public/landing-page/assets/js/main.js keeps working with no new JS.

    Section links only render when that section is actually switched on in
    admin, so the nav can never point at something that is not on the page.
--}}
@php
    /* The uploaded header_logo is drawn for a LIGHT background (dark mark, white
       wordmark), so it does not read on this dark header. We use the wordmark
       lockup instead. Upload a light/white version of the logo and switch the
       block below back to the <img> if you prefer the image. */

    /* "City Chemist, Bamenda" -> "Bamenda". Follows the address set in
       Business Setup > Business Information, so it updates itself. */
    $locality = '';
    if (!empty($businessAddr)) {
        $parts = array_filter(array_map('trim', explode(',', (string) $businessAddr)));
        $locality = end($parts) ?: '';
    }

    $navItems = array_filter([
        $showOurSolutionsSection ?? false ? ['href' => '#services', 'label' => translate('Services')]      : null,
        $waNumber                         ? ['href' => '#how',      'label' => translate('How to book')]   : null,
        ($isGalleryEnabled ?? false)      ? ['href' => '#rides',    'label' => translate('Choose a ride')] : null,
        ($isEarnMoneyEnabled ?? false)    ? ['href' => '#drive',    'label' => translate('Drive with us')] : null,
        ($showTestimonialSection ?? false)? ['href' => '#reviews',  'label' => translate('Reviews')]       : null,
    ]);
@endphp

<header>
    <div class="navbar-bottom er-header">
        <div class="container">
            <div class="navbar-bottom-wrapper">

                <a href="{{ route('index') }}" class="logo er-logo"
                   aria-label="{{ $businessName ?? 'E-Ride' }}{{ $locality ? ', '.$locality : '' }}">
                    <svg width="42" height="42" viewBox="0 0 48 48" aria-hidden="true" class="er-logo-mark">
                        <circle cx="24" cy="24" r="21" fill="none" stroke="currentColor" stroke-width="5"></circle>
                        <path d="M34 15H21a7 7 0 0 0-7 7v4a7 7 0 0 0 7 7h13" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"></path>
                        <path d="M20 24h11" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"></path>
                    </svg>
                    <span class="er-logo-text" aria-hidden="true">
                        <span class="er-logo-name">{{ $businessName ?? 'ERIDE' }}</span>
                        @if($locality)
                            <span class="er-logo-sub">{{ $locality }}</span>
                        @endif
                    </span>
                </a>

                <ul class="menu me-lg-4 er-menu">
                    @foreach($navItems as $item)
                        <li><a href="{{ $item['href'] }}"><span>{{ $item['label'] }}</span></a></li>
                    @endforeach

                    {{-- shown inside the mobile drawer only --}}
                    @if($waNumber)
                        <li class="d-sm-none">
                            <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" @if(!empty($erBookingOn)) data-er-book @endif
                               class="cmn--btn px-4 w-unset text-white d-inline-flex">
                                <span>{{ translate('Book a ride') }}</span>
                            </a>
                        </li>
                    @endif
                </ul>

                <div class="nav-toggle d-lg-none ms-auto me-2 me-sm-4">
                    <span></span><span></span><span></span>
                </div>

                @if($waNumber)
                    <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" @if(!empty($erBookingOn)) data-er-book @endif
                       class="cmn--btn d-none d-sm-flex er-header-cta">
                        @include('landing-page.partials._whatsapp-icon', ['size' => 17])
                        {{ translate('Book a ride') }}
                    </a>
                @endif

            </div>
        </div>
    </div>
</header>
