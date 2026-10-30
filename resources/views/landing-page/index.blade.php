@extends('landing-page.layouts.master')

@section('title', $businessName ?? translate('E-Ride'))
@section('body_class', 'eride-dark')

@php
    /* Contact details, editable in Business Management > Business Setup > Business Information */
    $contactNumber = getSession('business_contact_phone');
    $businessAddr  = getSession('business_address');

    /* wa.me needs digits only */
    $waNumber = preg_replace('/\D+/', '', (string) $contactNumber);

    /* Automated WhatsApp booking (Landing Page Setup > WhatsApp Booking):
       booking buttons open the booking sheet and send to this number. */
    $erBooking = \App\Http\Controllers\WhatsAppBookingController::settings();
    $erBookingOn = $erBooking['status'] && $erBooking['number'] !== '';
    if ($erBooking['number'] !== '') { $waNumber = $erBooking['number']; }

    /* The controller passes $businessStatistics but not the filtered collection, so build it here
       with the same rule it uses (non-empty value + status on). */
    $activeBusinessStatistics = collect($businessStatistics)->filter(
        fn($item) => !empty($item?->value) && ($item?->value['status'] ?? false)
    );

    /* Newsletter is not passed by the controller; read it straight from the CMS,
       the same way partials/_footer.blade.php does. */
    $newsEnabled = landingPageConfig(key: 'is_newsletter_enabled', settingsType: NEWSLETTER)?->value == 1;
    $newsContent = landingPageConfig(key: INTRO_CONTENTS, settingsType: NEWSLETTER)?->value ?? null;

    $storage = 'storage/app/public/business/landing-pages/';

    /* Hero background, uploaded under Landing Page Setup > Intro Section */
    $heroBg = $introSection && ($introSection['background_image'] ?? null)
        ? dynamicStorage(path: $storage . 'intro-section/' . $introSection['background_image'])
        : null;

    /* Typewriter words come from Our Solutions, so the admin controls them */
    $typeWords = collect($activeOurSolutions)
        ->map(fn($s) => $s?->value['title'] ?? null)
        ->filter()
        ->values();
@endphp

{{-- landing page uses its own header; other pages keep partials/_header.blade.php --}}
@section('header')
    @include('landing-page.partials._header-landing')
@endsection

@push('seo')
    <meta name="description"
          content="{{ \Illuminate\Support\Str::limit(strip_tags($introSection['sub_title'] ?? translate('Reliable rides, rapid delivery.')), 155) }}">
@endpush

@section('content')

    {{-- ========================================================= HERO ===== --}}
    @php
        $heroTitle = $introSection && ($introSection['title'] ?? null)
            ? $introSection['title']
            : translate('Your ride,') . ' **' . translate('your way.') . '**';
        /* Long admin titles get a smaller size so they never fill the screen */
        $heroTitleLong = mb_strlen(strip_tags(str_replace('**', '', $heroTitle))) > 48;
    @endphp
    <section class="er-hero{{ $heroBg ? ' er-hero--photo' : '' }}"
             @if($heroBg) style="--er-hero-photo: url('{{ $heroBg }}')" @endif>
        @include('landing-page.partials._hero-backdrop')

        <div class="er-wrap er-hero-grid">

            <div class="er-hero-copy">
                <span class="er-hero-eyebrow">
                    <span class="er-live"><span></span></span>
                    {{ translate('Rides & parcels, on demand') }}
                </span>

                <h1 class="er-h1 er-hero-title{{ $heroTitleLong ? ' er-hero-title--long' : '' }}">
                    {!! change_text_color_or_bg($heroTitle) !!}
                </h1>

                @if($typeWords->isNotEmpty())
                    <p class="er-rotator">
                        <span class="er-sr">{{ translate('We bring you') }} {{ $typeWords->implode(', ') }}</span>
                        <span aria-hidden="true">{{ translate('We bring you') }}</span>
                        <span class="er-rotator-window" aria-hidden="true">
                            <span class="er-rotator-track" data-er-rotator>
                                @foreach($typeWords as $word)
                                    <span>{{ $word }}</span>
                                @endforeach
                            </span>
                        </span>
                    </p>
                @endif

                <p class="er-sub er-hero-sub">
                    {!! $introSection && ($introSection['sub_title'] ?? null)
                        ? change_text_color_or_bg($introSection['sub_title'])
                        : translate('Smooth, reliable movement across town, booked in a single message.') !!}
                </p>

                @if($waNumber)
                    <div class="er-hero-actions">
                        <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" class="er-btn er-btn-primary" @if($erBookingOn) data-er-book @endif>
                            @include('landing-page.partials._whatsapp-icon', ['size' => 20])
                            {{ translate('Book on WhatsApp') }}
                        </a>
                        <a href="tel:{{ $contactNumber }}" class="er-btn er-btn-outline">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"></path></svg>
                            {{ $contactNumber }}
                        </a>
                    </div>
                @endif

                <ul class="er-trust">
                    <li>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                        {{ translate('Vetted drivers') }}
                    </li>
                    <li>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                        {{ translate('Live trip tracking') }}
                    </li>
                    <li>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M7 9.5v.01M17 14.5v.01"/></svg>
                        {{ translate('Upfront fares') }}
                    </li>
                </ul>
            </div>

            <div class="er-hero-visual">
                @if($heroBg)
                    <img class="er-hero-photo" src="{{ $heroBg }}" alt="{{ $businessName }}">
                @else
                    @include('landing-page.partials._hero-device')
                @endif
            </div>

        </div>
    </section>

    {{-- ================================== SERVICE STRIP (Our Solutions) ===== --}}
    @if($showOurSolutionsSection && $activeOurSolutions->isNotEmpty())
        <section class="er-strip">
            <div class="er-wrap er-strip-inner">
                @foreach($activeOurSolutions as $key => $solution)
                    @if($key > 0)<span class="er-dot">&bull;</span>@endif
                    <span>{{ $solution?->value['title'] ?? '' }}</span>
                @endforeach
            </div>
        </section>

        {{-- ======================================= WHAT WE DO (Our Solutions) ===== --}}
        <section id="services" class="er-pad">
            <div class="er-wrap">
                <div style="max-width: 640px">
                    <span class="er-eyebrow">{{ translate('What we do') }}</span>
                    <h2 class="er-title" style="margin-top: 18px">
                        {!! $ourSolutionsSectionContent && ($ourSolutionsSectionContent['title'] ?? null)
                            ? change_text_color_or_bg($ourSolutionsSectionContent['title'])
                            : translate('Our ').change_text_color_or_bg('**'.translate('Solutions').'**') !!}
                    </h2>
                    <p class="er-sub" style="margin-top: 14px">
                        {!! $ourSolutionsSectionContent && ($ourSolutionsSectionContent['sub_title'] ?? null)
                            ? change_text_color_or_bg($ourSolutionsSectionContent['sub_title'])
                            : translate('Explore our dynamic day-to-day solution for everyday life') !!}
                    </p>
                    <div class="er-rule" style="margin-top: 20px"></div>
                </div>

                <div class="er-grid-3" style="margin-top: 44px">
                    @foreach($activeOurSolutions as $solution)
                        <div class="er-surface er-pad-box" style="padding: 34px">
                            <span class="er-icon">
                                @if($solution?->value['image'] ?? null)
                                    <img src="{{ dynamicStorage(path: $storage.'our-solutions/'.$solution->value['image']) }}"
                                         alt="" width="30" height="30" style="object-fit: contain">
                                @else
                                    <svg width="29" height="29" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 17h14M6.5 17l-1.2-5.2A3 3 0 0 1 8.2 8h7.6a3 3 0 0 1 2.9 3.8L17.5 17"></path><circle cx="7.5" cy="18.5" r="1.6"></circle><circle cx="16.5" cy="18.5" r="1.6"></circle></svg>
                                @endif
                            </span>
                            <h3 style="font-size: 23px; font-weight: 700; margin-top: 24px">{{ $solution?->value['title'] ?? '' }}</h3>
                            <p class="er-muted" style="line-height: 1.65; margin-top: 11px; font-size: 16px">
                                {{ $solution?->value['description'] ?? '' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ========================================== HOW TO BOOK ON WHATSAPP ===== --}}
    @if($waNumber)
        <section id="how" class="er-pad er-band">
            <div class="er-wrap">
                <div style="text-align: center; max-width: 640px; margin: 0 auto">
                    <span class="er-eyebrow">{{ translate('Book your ride on WhatsApp') }}</span>
                    <h2 class="er-title" style="margin-top: 18px">{{ translate('Four steps, one message') }}</h2>
                    <p class="er-sub" style="margin-top: 14px">{{ translate('No app to install. Save our number and chat with us now.') }}</p>
                </div>

                @php
                    $steps = [
                        ['t' => translate('Quick and easy'),      'd' => translate('Message us in a click.'),                         'i' => 'chat'],
                        ['t' => translate('Share your location'), 'd' => translate('Tell us where you are and where you are going.'), 'i' => 'pin'],
                        ['t' => translate('Get a driver'),        'd' => translate('We confirm your driver and arrival time.'),       'i' => 'clock'],
                        ['t' => translate('Ride and enjoy'),      'd' => translate('Sit back and enjoy a smooth ride.'),              'i' => 'check'],
                    ];
                @endphp

                <div class="er-grid-4" style="margin-top: 48px">
                    @foreach($steps as $i => $step)
                        <div class="er-surface-2 er-pad-box" style="padding: 30px">
                            <span class="er-icon-round">
                                @if($step['i'] === 'chat')
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.8-.9L3 20.5l1.6-4.8A8.4 8.4 0 0 1 12 3.1a8.4 8.4 0 0 1 9 8.4Z"></path></svg>
                                @elseif($step['i'] === 'pin')
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                @elseif($step['i'] === 'clock')
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3.5 2"></path></svg>
                                @else
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m20 6-11 11-5-5"></path></svg>
                                @endif
                            </span>
                            <div class="er-step">{{ translate('STEP') }} {{ $i + 1 }}</div>
                            <h3 style="font-size: 19px; font-weight: 700; margin-top: 8px">{{ $step['t'] }}</h3>
                            <p class="er-muted" style="line-height: 1.6; margin-top: 9px; font-size: 15px">{{ $step['d'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div style="display: flex; justify-content: center; margin-top: 40px">
                    <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" class="er-btn er-btn-primary" @if($erBookingOn) data-er-book @endif>
                        {{ $erBookingOn ? translate('Book a ride now') : translate('Start a chat now') }}
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- ========================================= CHOOSE YOUR RIDE (Gallery) ===== --}}
    @if($isGalleryEnabled && ($cardOneGallery || $cardTwoGallery))
        <section id="rides" class="er-pad">
            <div class="er-wrap">
                <div style="text-align: center; max-width: 640px; margin: 0 auto">
                    <h2 class="er-title">
                        {!! translate('Choose the ride that ').change_text_color_or_bg('**'.translate('fits your needs').'**') !!}
                    </h2>
                </div>

                <div class="er-grid-2" style="margin-top: 46px">
                    {{-- the controller already unwraps ->value, so these are plain arrays --}}
                    @foreach([['card' => $cardOneGallery, 'primary' => false], ['card' => $cardTwoGallery, 'primary' => true]] as $tier)
                        @php($card = $tier['card'] ?? null)
                        @if($card)
                            <div class="er-surface er-tier">
                                <div class="er-tier-head">{{ strip_tags(change_text_color_or_bg($card['title'] ?? '')) }}</div>
                                <div class="er-pad-box" style="padding: 32px">
                                    <div class="er-tier-img">
                                        @if($card['image'] ?? null)
                                            <img src="{{ dynamicStorage(path: $storage.'gallery/'.$card['image']) }}" alt="">
                                        @else
                                            <span class="er-dim">[ {{ translate('RIDE PHOTO') }} ]</span>
                                        @endif
                                    </div>
                                    <p class="er-muted" style="line-height: 1.65; margin-top: 22px; font-size: 16px">
                                        {!! change_text_color_or_bg($card['subtitle'] ?? '') !!}
                                    </p>
                                    @if($waNumber)
                                        <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" @if($erBookingOn) data-er-book @endif
                                           class="er-btn er-btn-block {{ $tier['primary'] ? 'er-btn-primary' : 'er-btn-outline' }}"
                                           style="margin-top: 26px">{{ translate('Book this ride') }}</a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ==================================== WHY E-RIDE (Business Statistics) ===== --}}
    @if($showBusinessStatisticsSection && $activeBusinessStatistics->isNotEmpty())
        <section class="er-pad er-band">
            <div class="er-wrap">
                <div class="er-grid-4">
                    @foreach($activeBusinessStatistics as $stat)
                        <div>
                            <span class="er-icon-soft">
                                @if($stat?->value['image'] ?? null)
                                    <img src="{{ dynamicStorage(path: $storage.'business-statistics/'.$stat->value['image']) }}"
                                         alt="" width="26" height="26" style="object-fit: contain">
                                @else
                                    <svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="#ff6b6b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="m9 12 2 2 4-4"></path></svg>
                                @endif
                            </span>
                            <h3 style="font-size: 17px; font-weight: 800; margin-top: 18px; text-transform: uppercase; letter-spacing: .04em">
                                {{ $stat?->value['title'] ?? '' }}
                            </h3>
                            <p class="er-muted" style="line-height: 1.6; margin-top: 9px; font-size: 15px">
                                {{ $stat?->value['content'] ?? '' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ========================================================= TESTIMONIALS ===== --}}
    @if($showTestimonialSection && $activeTestimonials->isNotEmpty())
        <section id="reviews" class="er-pad">
            <div class="er-wrap">
                <div style="text-align: center; max-width: 640px; margin: 0 auto">
                    <h2 class="er-title">
                        {!! $testimonialSectionContent && ($testimonialSectionContent['title'] ?? null)
                            ? change_text_color_or_bg($testimonialSectionContent['title'])
                            : translate('People share their love') !!}
                    </h2>
                </div>

                <div class="er-grid-3" style="margin-top: 44px">
                    @foreach($activeTestimonials as $testimonial)
                        @php($review = $testimonial?->value ?? [])
                        @php($rating = (int) round((float) ($review['rating'] ?? 0)))
                        <figure class="er-surface er-pad-box" style="padding: 30px; margin: 0">
                            <div style="display: flex; gap: 3px" role="img"
                                 aria-label="{{ $rating }} {{ translate('out of 5 stars') }}">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="{{ $i <= $rating ? '#f5a623' : '#3a3a44' }}" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1L12 2Z"></path></svg>
                                @endfor
                            </div>
                            <blockquote style="margin: 18px 0 0; font-size: 17px; line-height: 1.65; color: #e3e3ea">
                                {{ $review['review'] ?? '' }}
                            </blockquote>
                            <figcaption style="display: flex; align-items: center; gap: 12px; margin-top: 22px; padding-top: 20px; border-top: 1px solid #27272e">
                                @if($review['reviewer_image'] ?? null)
                                    <img src="{{ dynamicStorage(path: $storage.'testimonial/'.$review['reviewer_image']) }}"
                                         alt="" width="44" height="44" style="border-radius: 50%; object-fit: cover; flex: none">
                                @else
                                    <span style="width: 44px; height: 44px; border-radius: 50%; background: #1f1f25; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; color: #ff6b6b; flex: none">
                                        {{ mb_substr($review['reviewer_name'] ?? 'E', 0, 1) }}
                                    </span>
                                @endif
                                <span>
                                    <span style="display: block; font-weight: 700; font-size: 15px; color: #fff">{{ $review['reviewer_name'] ?? '' }}</span>
                                    <span style="display: block; font-size: 14px; color: #8b8b96">{{ $review['designation'] ?? '' }}</span>
                                </span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ========================================= DRIVE AND EARN (Earn Money) ===== --}}
    @if($isEarnMoneyEnabled)
        <section id="drive" class="er-pad er-band">
            <div class="er-wrap" style="display: flex; flex-wrap: wrap; gap: 52px; align-items: center">

                <div style="flex: 999 1 480px; min-width: 0">
                    <span class="er-eyebrow">{{ translate('For drivers') }}</span>
                    <h2 class="er-title" style="margin-top: 18px">
                        {!! $earnMoneySectionContent && ($earnMoneySectionContent['title'] ?? null)
                            ? change_text_color_or_bg($earnMoneySectionContent['title'])
                            : translate('Earn Money with ').change_text_color_or_bg('**'.translate('E-Ride').'**') !!}
                    </h2>
                    <p class="er-sub" style="margin-top: 18px; max-width: 520px">
                        {!! $earnMoneySectionContent && ($earnMoneySectionContent['subtitle'] ?? null)
                            ? change_text_color_or_bg($earnMoneySectionContent['subtitle'])
                            : translate('Drive when it suits you, accept the trips you want, and get paid for every ride you complete.') !!}
                    </p>

                    <div style="display: flex; flex-wrap: wrap; gap: 14px; margin-top: 32px">
                        @include('landing-page.partials._app-buttons', [
                            'android' => $driverAppVersionControlForAndroid,
                            'ios'     => $driverAppVersionControlForIos,
                        ])
                        @if($waNumber)
                            <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" class="er-btn er-btn-primary">
                                {{ translate('Sign up to drive') }}
                            </a>
                            <a href="tel:{{ $contactNumber }}" class="er-btn er-btn-outline">{{ $contactNumber }}</a>
                        @endif
                    </div>
                </div>

                <div style="flex: 1 1 320px; min-width: 0">
                    @php($earnImg = $earnMoneySectionContent['image'] ?? null)
                    @if($earnImg)
                        <img src="{{ dynamicStorage(path: $storage.'earn-money/'.$earnImg) }}" alt=""
                             style="width: 100%; border-radius: 20px; display: block">
                    @else
                        <div class="er-ph" style="aspect-ratio: 4 / 3">
                            <span>[ {{ translate('DRIVER IMAGE') }} ]</span>
                            <span style="font-weight: 400; font-size: 13px">{{ translate('Landing Page Setup > Earn Money') }}</span>
                        </div>
                    @endif
                </div>

            </div>
        </section>
    @endif

    {{-- ==================================================== BIG WHATSAPP CTA ===== --}}
    @if($waNumber)
        <section style="padding: 92px 0">
            <div class="er-wrap">
                <div class="er-cta er-pad-box">
                    <div style="flex: 1 1 320px; min-width: 0; display: flex; align-items: center; gap: 20px">
                        <span style="width: 68px; height: 68px; border-radius: 50%; background: rgba(255,255,255,.16); display: inline-flex; align-items: center; justify-content: center; flex: none">
                            @include('landing-page.partials._whatsapp-icon', ['size' => 36])
                        </span>
                        <div>
                            <div style="font-size: 25px; font-weight: 800; color: #fff; line-height: 1.25">{{ translate('Book your ride on WhatsApp') }}</div>
                            <div style="font-size: 15px; color: #ffd9d9; margin-top: 6px">{{ translate('Save this number and chat with us now') }}</div>
                        </div>
                    </div>
                    <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" class="er-cta-num" @if($erBookingOn) data-er-book @endif>{{ $contactNumber }}</a>
                </div>
            </div>
        </section>
    @endif

    {{-- ================================= GET THE APP (Customer App Download) ===== --}}
    @if($isCustomerAppDownloadEnabled && ($customerAppVersionControlForAndroid || $customerAppVersionControlForIos))
        <section class="er-pad er-band">
            <div class="er-wrap" style="display: flex; flex-wrap: wrap; gap: 40px; align-items: center; justify-content: space-between">
                <div style="flex: 999 1 420px; min-width: 0">
                    <h2 class="er-title">
                        {!! $customerAppDownloadSectionContent && ($customerAppDownloadSectionContent['title'] ?? null)
                            ? change_text_color_or_bg($customerAppDownloadSectionContent['title'])
                            : translate('Get the ').change_text_color_or_bg('**'.translate('E-Ride').'**').' '.translate('app') !!}
                    </h2>
                    <p class="er-sub" style="margin-top: 14px; max-width: 480px">
                        {!! $customerAppDownloadSectionContent && ($customerAppDownloadSectionContent['subtitle'] ?? null)
                            ? change_text_color_or_bg($customerAppDownloadSectionContent['subtitle'])
                            : translate('Reliable rides anytime, anywhere.') !!}
                    </p>
                    <div style="display: flex; flex-wrap: wrap; gap: 14px; margin-top: 30px">
                        @include('landing-page.partials._app-buttons', [
                            'android' => $customerAppVersionControlForAndroid,
                            'ios'     => $customerAppVersionControlForIos,
                        ])
                    </div>
                </div>
                <div style="flex: 1 1 300px; min-width: 0">
                    @php($appImg = $customerAppDownloadSectionContent['image'] ?? null)
                    @if($appImg)
                        <img src="{{ dynamicStorage(path: $storage.'customer-app-download/'.$appImg) }}" alt=""
                             style="width: 100%; border-radius: 20px; display: block">
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- ========================================================== NEWSLETTER ===== --}}
    @if($newsEnabled)
        <section class="er-pad er-band">
            <div class="er-wrap" style="display: flex; flex-wrap: wrap; gap: 40px; align-items: center; justify-content: space-between">
                <div style="flex: 999 1 420px; min-width: 0">
                    <h2 style="font-size: 32px; font-weight: 800">
                        {!! $newsContent && ($newsContent['title'] ?? null)
                            ? change_text_color_or_bg($newsContent['title'])
                            : translate('GET ALL UPDATES & EXCITING NEWS') !!}
                    </h2>
                    <p class="er-sub" style="margin-top: 12px; max-width: 460px">
                        {!! $newsContent && ($newsContent['subtitle'] ?? null)
                            ? change_text_color_or_bg($newsContent['subtitle'])
                            : translate('Subscribe to our newsletter to receive all the latest activity we provide for you') !!}
                    </p>
                </div>
                <form action="{{ route('newsletter-subscription.store') }}" method="POST"
                      style="flex: 1 1 360px; min-width: 0; display: flex; flex-wrap: wrap; gap: 12px">
                    @csrf
                    <label for="er-newsletter-email" class="er-sr">{{ translate('Email address') }}</label>
                    <input id="er-newsletter-email" class="er-input" type="email" name="email"
                           placeholder="{{ translate('Type email...') }}" autocomplete="email" required>
                    <button type="submit" class="er-btn er-btn-primary" style="flex: 1 1 140px">{{ translate('Subscribe') }}</button>
                </form>
            </div>
        </section>
    @endif

    {{-- ============================================ WHATSAPP BOOKING SHEET ===== --}}
    @if($erBookingOn)
        @include('landing-page.partials._whatsapp-booking', ['booking' => $erBooking])
    @endif

    {{-- ================================================= FLOATING WHATSAPP ===== --}}
    @if($waNumber)
        <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" class="er-float"
           aria-label="{{ translate('Chat with us on WhatsApp') }}">
            @include('landing-page.partials._whatsapp-icon', ['size' => 31])
        </a>
    @endif

@endsection

@push('script')
    <script>
        "use strict";
        (function () {
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            /* "We bring you <word>": one word at a time, sliding up. */
            var track = document.querySelector('[data-er-rotator]');
            if (track && !reduce) {
                var items = track.children, i = 0;
                var size = function () {
                    var w = items[i].getBoundingClientRect().width;
                    track.parentNode.style.width = Math.ceil(w) + 'px';
                };
                size();
                window.addEventListener('resize', size);
                if (items.length > 1) {
                    setInterval(function () {
                        i = (i + 1) % items.length;
                        track.style.transform = 'translateY(' + (-i * 100 / items.length) + '%)';
                        size();
                    }, 2600);
                }
            }

            /* The phone in the hero: distance and minutes follow the car,
               which loops along the route every 16 s (see _hero-device). */
            var trip = document.querySelector('[data-er-trip]');
            if (!trip) { return; }
            var km = trip.querySelector('[data-er-km]'),
                min = trip.querySelector('[data-er-min]'),
                eta = trip.querySelector('[data-er-eta]'),
                bar = trip.querySelector('[data-er-progress]');
            var total = 2.4, minutes = 6, loop = 16000, drive = 0.88, start = performance.now();
            var ease = function (t) { return t < .5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2; };
            var tick = function () {
                var t = ((performance.now() - start) % loop) / loop;
                var p = reduce ? 0.35 : ease(Math.min(t / drive, 1));
                var left = total * (1 - p);
                km.textContent = left < 1 ? Math.max(Math.round(left * 1000 / 10) * 10, 0) + ' m' : left.toFixed(1) + ' km';
                min.textContent = Math.max(Math.ceil(minutes * (1 - p)), 1);
                eta.textContent = Math.max(Math.ceil(3 * (1 - p)), 1);
                bar.style.width = (p * 100).toFixed(1) + '%';
                trip.classList.toggle('is-arriving', p > .92);
            };
            tick();
            if (!reduce) { setInterval(tick, 250); }
        })();
    </script>
@endpush
