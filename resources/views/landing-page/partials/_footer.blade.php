<!-- Newsletters Section Start -->
@if(landingPageConfig(key: 'is_newsletter_enabled',settingsType: NEWSLETTER)?->value == 1 )
    @php($newsLetter = landingPageConfig(key: INTRO_CONTENTS,settingsType: NEWSLETTER)?->value ?? null)
    <section class="newsletter-section p-0 mt-4 mt-sm-60">
        <div class="container">
            <div class="newsletter--wrapper bg__img"
                 data-img="{{ $newsLetter && $newsLetter['background_image'] ? dynamicStorage('storage/app/public/business/landing-pages/newsletter/'.$newsLetter['background_image']) :dynamicAsset(path: 'public/landing-page/assets/img/newsletter-new-bg.png') }}">
                <div class="position-relative p-4 p-sm-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-8">
                            <div class="wow animate__fadeInDown">
                                <h4 class="text-white text-uppercase mb-2 fs-16-mobile">{!! $newsLetter && $newsLetter['title'] ? change_text_color_or_bg($newsLetter['title']) :  translate('GET ALL UPDATES & EXCITING NEWS') !!}</h4>
                                <p class="text-white opacity-75 lh-base fs-12-mobile">{!! $newsLetter && $newsLetter['subtitle'] ? change_text_color_or_bg($newsLetter['subtitle']) :translate('Subscribe to out newsletters to receive all the latest activity we provide for you') !!}</p>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="wow animate__fadeInUp">
                                <div class="newsletter-right">
                                    <form action="{{ route('newsletter-subscription.store') }}" method="POST" class="newsletter-form">
                                        @csrf
                                        <input type="email" class="form-control"
                                               placeholder="{{ translate('Type email...') }}" name="email" autocomplete="off" required>
                                        <button type="submit"
                                                class="btn cmn--btn">{{ translate('Subscribe ') }}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
<!-- Newsletters Section End -->

<footer class="mt-4 mt-sm-60">
    @php($logo = getSession('header_logo'))
    @php($footerLogo = getSession('footer_logo'))
    @php($email = getSession('business_contact_email'))
    @php($contactNumber = getSession('business_contact_phone'))
    @php($businessAddress = getSession('business_address'))
    @php($businessName = getSession('business_name'))
    @php($footerContent = landingPageConfig(key: 'footer_contents', settingsType: FOOTER)?->value ?? null)
    @php($links = \Modules\BusinessManagement\Entities\SocialLink::where(['is_active'=>1])->orderBy('name','asc')->get())
    @php($driverAppVersionControlForAndroid = businessConfig(key: DRIVER_APP_VERSION_CONTROL_FOR_ANDROID, settingsType: APP_VERSION)?->value ?? null)
    @php($driverAppVersionControlForIos = businessConfig(key: DRIVER_APP_VERSION_CONTROL_FOR_IOS, settingsType: APP_VERSION)?->value ?? null)
    @php($customerAppVersionControlForAndroid = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_ANDROID, settingsType: APP_VERSION)?->value ?? null)
    @php($customerAppVersionControlForIos = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_IOS, settingsType: APP_VERSION)?->value ?? null)
    <div class="footer-top">
        <div class="container">
            <div class="footer__wrapper">
                <div class="footer__wrapper-widget">
                    <div class="cont">
                        <a href="{{ route('index') }}" class="logo">
                            <img
                                src="{{ $footerLogo ? dynamicStorage(path: "storage/app/public/business/".$footerLogo) : dynamicAsset(path: 'public/landing-page/assets/img/logo.png') }}"
                                alt="logo">
                        </a>
                        <p>
                            {!! $footerContent && $footerContent['title'] ? change_text_color_or_bg($footerContent['title']) : translate('Connect with our social media and other sites to keep up to date')!!}
                        </p>
                        <ul class="social-icons">
                            @foreach($links as $link)
                                @if($link->name == "facebook")
                                    <li>
                                        <a href="{{$link->link}}" target="_blank">
                                            <img
                                                src="{{ dynamicAsset(path: 'public/landing-page/assets/img/footer/facebook.png') }}"
                                                alt="img">
                                        </a>
                                    </li>
                                @elseif($link->name == "instagram")
                                    <li>
                                        <a href="{{$link->link}}" target="_blank">
                                            <img
                                                src="{{ dynamicAsset(path: 'public/landing-page/assets/img/footer/instagram.png') }}"
                                                alt="img">
                                        </a>
                                    </li>
                                @elseif($link->name == "twitter")
                                    <li>
                                        <a href="{{$link->link}}" target="_blank">
                                            <img
                                                src="{{ dynamicAsset(path: 'public/landing-page/assets/img/footer/twitter.png') }}"
                                                alt="img">
                                        </a>
                                    </li>
                                @elseif($link->name == "linkedin")
                                    <li>
                                        <a href="{{$link->link}}" target="_blank">
                                            <img
                                                src="{{ dynamicAsset(path: 'public/landing-page/assets/img/footer/linkedin.png') }}"
                                                alt="img">
                                        </a>
                                    </li>
                                @endif
                            @endforeach

                        </ul>
                        <div class="app-btns">
                            @if($customerAppVersionControlForAndroid || $customerAppVersionControlForIos)
                                <div class="me-xl-4">
                                    <h6 class="text-white mb-3 font-regular">User App</h6>
                                    <div class="d-flex gap-3 flex-column">
                                        @if($customerAppVersionControlForAndroid)
                                            <a target="_blank" type="button"
                                               href="{{ $customerAppVersionControlForAndroid['app_url'] }}">
                                                <img
                                                    src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-store.png') }}"
                                                    class="w-115px" alt="">
                                            </a>
                                        @endif
                                        @if($customerAppVersionControlForIos)
                                            <a target="_blank" type="button"
                                               href="{{ $customerAppVersionControlForIos['app_url'] }}">
                                                <img
                                                    src="{{ dynamicAsset(path: 'public/landing-page/assets/img/app-store.png') }}"
                                                    class="w-115px" alt="">
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            @if($driverAppVersionControlForAndroid || $driverAppVersionControlForIos)
                                <div>
                                    <h6 class="text-white mb-3 font-regular">Driver App</h6>
                                    <div class="d-flex gap-3 flex-column">
                                        @if($driverAppVersionControlForAndroid)
                                            <a target="_blank" type="button"
                                               href="{{ $driverAppVersionControlForAndroid['app_url'] }}">
                                                <img
                                                    src="{{ dynamicAsset(path: 'public/landing-page/assets/img/app-store.png') }}"
                                                    class="w-115px" alt="">
                                            </a>
                                        @endif
                                        @if($driverAppVersionControlForIos)
                                            <a target="_blank" type="button"
                                               href="{{ $driverAppVersionControlForIos['app_url'] }}">
                                                <img
                                                    src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-store.png') }}"
                                                    class="w-115px" alt="">
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="footer__wrapper-widget">
                    <ul class="footer__wrapper-link">
                        <li>
                            <a href="{{ route('index') }}">{{ translate('Home') }}</a>
                        </li>
                        <li>
                            <a href="{{route('about-us')}}">{{ translate('About Us') }}</a>
                        </li>
                        <li>
                            <a href="{{ route('contact-us') }}">{{ translate('Contact Us') }}</a>
                        </li>
                        <li>
                            <a href="{{ route('privacy') }}">{{ translate('Privacy Policy') }}</a>
                        </li>
                        <li>
                            <a href="{{ route('terms') }}">{{ translate('Terms & Condition') }}</a>
                        </li>
                    </ul>
                </div>
                <div class="footer__wrapper-widget">
                    <div class="footer__wrapper-contact">
                        <img class="icon"
                             src="{{ dynamicAsset(path: 'public/landing-page/assets/img/footer/mail.png') }}"
                             alt="footer">
                        <h6>
                            {{ translate('Send us Mail') }}
                        </h6>
                        <a href="Mailto:{{  $email ?? "contact@example.com" }}">{{  $email ?? "contact@example.com" }}</a>
                    </div>
                </div>
                <div class="footer__wrapper-widget">
                    <div class="footer__wrapper-contact">
                        <img class="icon"
                             src="{{ dynamicAsset(path: 'public/landing-page/assets/img/footer/tel.png') }}"
                             alt="footer">
                        <h6>
                            {{ translate('Contact Us') }}
                        </h6>
                        <div>
                            <a href="Tel:{{ $contactNumber ?? "+90-327-539" }}">{{ $contactNumber ?? "+90-327-539" }}</a>
                        </div>
                        <a href={{ "Mailto:".$email ?? "Mailto:support@6amtech.com"}}>{{ $email ?? "support@6amtech.com"}}</a>
                    </div>
                </div>
                <div class="footer__wrapper-widget">
                    <div class="footer__wrapper-contact">
                        <img class="icon"
                             src="{{ dynamicAsset(path: 'public/landing-page/assets/img/footer/pin.png') }}"
                             alt="footer">
                        <h6>
                            {{ translate('Send us Mail') }}
                        </h6>
                        <div>
                            {{ $businessAddress ? $businessAddress : "510 Kampong Bahru Rd Singapore 099446" }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom text-center py-3">
        {{getSession('copyright_text')}}
    </div>
</footer>
