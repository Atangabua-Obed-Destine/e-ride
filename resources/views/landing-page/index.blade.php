@php
    use Jenssegers\Agent\Agent;
     $userAgent = new Agent();
     $solutionCount = $activeOurSolutions->count() ?? 0;
@endphp
@extends('landing-page.layouts.master')
@section('title', 'Home')

@section('content')
    <!-- Intro Section Start -->
    <section class="banner-section">
        <div class="container">
            <div class="banner-wrapper justify-content-between bg__img wow animate__fadeInDown"
                 data-img="{{ $introSection && $introSection['background_image'] ? dynamicStorage(path: 'storage/app/public/business/landing-pages/intro-section/'.$introSection['background_image']) : dynamicAsset(path: 'public/landing-page/assets/img/banner/banner-bg.png') }}">
                <div class="banner-content text-center text-sm-start">
                    <h1 class="title fs-20-mobile max-w-100 text-uppercase">PUT YOUR CAR TO WORK WITH <span class="text-primary">E-RIDE</span></h1>
                    <p class="txt fs-12-mobile">
                        Earn Over <strong class="text-primary">200,000 FCFA</strong> Per Month<br><br>
                        <span class="d-block mb-1"><i class="bi bi-check-circle-fill text-primary me-2"></i> Flexible driving schedule</span>
                        <span class="d-block mb-1"><i class="bi bi-check-circle-fill text-primary me-2"></i> Safe and reliable ride requests</span>
                        <span class="d-block mb-1"><i class="bi bi-check-circle-fill text-primary me-2"></i> Work with your own car</span>
                        <span class="d-block mb-1"><i class="bi bi-check-circle-fill text-primary me-2"></i> Start earning immediately</span><br>
                        <span class="d-block mt-2 h5"><i class="bi bi-whatsapp text-success me-2"></i> <strong>+237 672 694 672</strong></span>
                    </p>
                    @if($driverAppVersionControlForAndroid || $driverAppVersionControlForIos || $customerAppVersionControlForAndroid || $customerAppVersionControlForIos)
                        <div class="app--btns d-flex flex-wrap flex-column flex-sm-row">

                            @if($customerAppVersionControlForAndroid && $customerAppVersionControlForIos)
                                <div class="dropdown py-0">
                                    <a href="#" class="cmn--btn h-50 d-flex gap-2 lh-1"
                                       data-bs-toggle="dropdown">{{translate('Download User App')}} <i
                                            class="bi bi-chevron-down"></i></a>
                                    <div class="dropdown-menu dropdown-button-menu">
                                        <ul>
                                            <li class="border-bottom">
                                                <a href="{{$customerAppVersionControlForAndroid['app_url']}}"
                                                   target="_blank">
                                                    <img width="20" class="w-20px"
                                                         src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-fav.png') }}"
                                                         alt="">
                                                    <span>{{translate('Play Store')}}</span>
                                                </a>
                                            </li>
                                            <li>
                                                <a href="{{$customerAppVersionControlForIos['app_url']}}"
                                                   target="_blank">
                                                    <img width="20" class="w-20px"
                                                         src="{{ dynamicAsset(path: 'public/landing-page/assets/img/apple.png') }}"
                                                         alt="">
                                                    <span>{{translate('App Store')}}</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            @elseif($customerAppVersionControlForAndroid)
                                <a href="{{$customerAppVersionControlForAndroid['app_url']}}" target="_blank"
                                   class="cmn--btn h-50 d-flex gap-2 lh-1">
                                    {{translate('Download User App')}}
                                </a>
                            @elseif($customerAppVersionControlForIos)
                                <a href="{{$customerAppVersionControlForIos['app_url']}}" target="_blank"
                                   class="cmn--btn h-50 d-flex gap-2 lh-1">
                                    {{translate('Download User App')}}
                                </a>
                            @endif



                            @if($driverAppVersionControlForAndroid && $driverAppVersionControlForIos)
                                <div class="dropdown py-0">
                                    <a href="#"
                                       class="cmn--btn btn-white text-nowrap overflow-hidden text-truncate h-50 d-flex gap-2 lh-1"
                                       data-bs-toggle="dropdown">Become an E-RIDE Driver Today
                                        <i
                                            class="bi bi-chevron-down"></i></a>
                                    <div class="dropdown-menu dropdown-button-menu">
                                        <ul>
                                            <li class="border-bottom">
                                                <a href="{{$driverAppVersionControlForAndroid['app_url']}}"
                                                   target="_blank">
                                                    <img width="20" class="w-20px"
                                                         src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-fav.png') }}"
                                                         alt="">
                                                    <span>{{translate('Play Store')}}</span>
                                                </a>
                                            </li>
                                            <li>
                                                <a href="{{$driverAppVersionControlForIos['app_url']}}"
                                                   target="_blank">
                                                    <img width="20" class="w-20px"
                                                         src="{{ dynamicAsset(path: 'public/landing-page/assets/img/apple.png') }}"
                                                         alt="">
                                                    <span>{{translate('App Store')}}</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            @elseif($driverAppVersionControlForAndroid)
                                <a href="{{$driverAppVersionControlForAndroid['app_url']}}" target="_blank"
                                   class="cmn--btn btn-white text-nowrap overflow-hidden text-truncate h-50">
                                    Become an E-RIDE Driver Today
                                </a>
                            @elseif($driverAppVersionControlForIos)
                                <a href="{{$driverAppVersionControlForIos['app_url']}}" target="_blank"
                                   class="cmn--btn btn-white text-nowrap overflow-hidden text-truncate h-50">
                                    Become an E-RIDE Driver Today
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
    <!-- Intro Section End -->

    <!-- Business Statistics Section Removed -->

    <!-- Our Solution Section Start -->
    @if($showOurSolutionsSection)
        <section class="platform-section p-0 mt-4 mt-sm-60">
            <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/platform/platform-bg.png') }}"
                 class="shape d-none d-lg-block" alt="">
            <div class="container">
                <div class="mb-3 mb-sm-4 text-center">
                    <h2 class="section-title mb-2 mb-sm-3 fs-16-mobile wow animate__fadeInUp">Safe • Reliable • Affordable</h2>
                    <p class="fs-18 mb-0 fs-12-mobile">Going out on a date? Need a comfortable ride? We bring you smooth, reliable movement.</p>
                </div>
                <div class="position-relative wow animate__fadeInDown">
                    <div class="ourSolution-slider owl-theme owl-carousel sliderItem-sameHeight">
                        <!-- Rides -->
                        <div class="ourSolution__item sliderItem">
                            <div class="w-200 w-150-mobile aspect-1 mx-auto mb-3">
                                <img class="object-cover h-100 rounded"
                                     src="{{dynamicAsset(path: 'public/landing-page/assets/img/platform/1.png')}}"
                                     alt="client">
                            </div>
                            <div>
                                <h4 class="fs-20 fs-16-mobile mb-3">Rides</h4>
                                <p class="fs-12-mobile">Comfortable and reliable rides.</p>
                            </div>
                        </div>
                        <!-- Food Delivery -->
                        <div class="ourSolution__item sliderItem">
                            <div class="w-200 w-150-mobile aspect-1 mx-auto mb-3">
                                <img class="object-cover h-100 rounded"
                                     src="{{dynamicAsset(path: 'public/landing-page/assets/img/platform/2.png')}}"
                                     alt="client">
                            </div>
                            <div>
                                <h4 class="fs-20 fs-16-mobile mb-3">Food Delivery</h4>
                                <p class="fs-12-mobile">Food delivery from your favorite spots.</p>
                            </div>
                        </div>
                        <!-- Errands -->
                        <div class="ourSolution__item sliderItem">
                            <div class="w-200 w-150-mobile aspect-1 mx-auto mb-3">
                                <img class="object-cover h-100 rounded"
                                     src="{{dynamicAsset(path: 'public/landing-page/assets/img/platform/1.png')}}"
                                     alt="client">
                            </div>
                            <div>
                                <h4 class="fs-20 fs-16-mobile mb-3">Errands</h4>
                                <p class="fs-12-mobile">Fast errands anywhere in town.</p>
                            </div>
                        </div>
                    </div>
                    @if($solutionCount > 3 ||  ($userAgent?->isMobile() && $solutionCount > 1) || ( $userAgent?->isTablet() && $solutionCount > 2))
                        <!-- ourSolution Slider Middle Nav Icons -->
                        <div class="slider-middle d-flex justify-content-center">
                            <div
                                class="owl-btn prev-btn ourSolution-owl-prev btn btn-circle btn-light text-dark border">
                                <i class="bi bi-chevron-left"></i>
                            </div>
                            <div class="mx-2 mx-sm-3"></div>
                            <div
                                class="owl-btn next-btn ourSolution-owl-next btn btn-circle btn-light text-dark border">
                                <i class="bi bi-chevron-right"></i>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif
    <!-- Our Solution Section End -->


    <!-- Our Services Section Start -->
    @if($showOurServicesSection)
        <section class="service-section bg-light py-4 py-sm-60 mt-4 mt-sm-60">
            <div class="container">
                <div class="mb-3 mb-sm-5 text-center">
                    <h3 class="section-title mb-2 mb-sm-3 fs-16-mobile wow animate__fadeInUp">
                        {!! $ourServicesSectionContent && $ourServicesSectionContent['title'] ? change_text_color_or_bg($ourServicesSectionContent['title']) :  translate('Our ') .change_text_color_or_bg(('**'. translate('Services') .'**')) !!}
                    </h3>
                    <p class="fs-18 mb-0 fs-12-mobile">{!! $ourServicesSectionContent && $ourServicesSectionContent['subtitle'] ? change_text_color_or_bg($ourServicesSectionContent['subtitle']) :  translate('Discover our innovative solutions designed to enhance daily operations.') !!}</p>
                </div>
                <ul class="nav nav-tabs nav--tabs" id="myTab" role="tablist">
                    @foreach($activeOurServices as $key => $ourService)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{$key == 0 ? "active" : ""}}" id="areaTab{{$key}}"
                                    data-bs-toggle="tab" data-bs-target="#tab{{$key}}" type="button" role="tab"
                                    aria-controls="tab{{$key}}" aria-selected="true">
                                {!! change_text_color_or_bg($ourService?->value['tab_name']) !!}
                            </button>
                        </li>
                    @endforeach
                </ul>
                <div class="tab-content" id="myTabContent">
                    @foreach($activeOurServices as $key => $ourService)
                        @if($ourService?->value['status']??0)
                            <div class="tab-pane fade {{$key == 0 ? "show active" : ""}}" id="tab{{$key}}"
                                 role="tabpanel" aria-labelledby="areaTab{{$key}}">
                                <div class="row g-4">
                                    <div class="col-lg-6">
                                        <div class="mt-3 mt-sm-5">
                                            <h4 class="mb-3 fs-16-mobile">
                                                {!! change_text_color_or_bg($ourService?->value['title']) !!}
                                            </h4>
                                            <div class="editor-content">
                                                {!! change_text_color_or_bg($ourService?->value['description']) !!}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="w-475 w-220-mobile aspect-1 mx-auto overflow-hidden rounded">
                                            <img
                                                class="img-fluid w-100 h-100 object-cover"
                                                src="{{ $ourService?->value['image'] ? dynamicStorage(path: 'storage/app/public/business/landing-pages/our-services/' .$ourService?->value['image']) : dynamicAsset(path: 'public/landing-page/assets/img/service/demo.png') }}"
                                                alt=""
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach

                </div>
            </div>
        </section>

    @endif
    <!-- Our Services Section End -->

    <!-- Gallery Section Start -->
    @if($isGalleryEnabled)
        <section class="gallery-section p-0 mt-4 mt-sm-60">
            <div class="container">
                <div class="row g-4 pb-50">
                    <div class="col-lg-6">
                        <div class="h-100">
                            <div
                                class="w-100 h-345 h-200-mobile mx-auto overflow-hidden rounded-20 wow animate__fadeInDown">
                                <img
                                    class="img-fluid w-100 h-100 object-cover"
                                    src="{{$cardOneGallery['image'] ? dynamicStorage('storage/app/public/business/landing-pages/gallery/' .$cardOneGallery['image']) : dynamicAsset(path: 'public/landing-page/assets/img/gallery/card-1.png') }}"
                                    alt=""
                                >
                            </div>
                            <div class="mt-4 mt-sm-30">
                                <h3 class="mb-3 mb-sm-4 fs-16-mobile wow animate__fadeInUp">
                                    {!! $cardOneGallery['title'] ?  change_text_color_or_bg($cardOneGallery['title']) : translate('Ride Completed ') .change_text_color_or_bg(('**'. translate('Hassle-Free') .'**'))  !!}
                                </h3>
                                <p class="fs-16 mb-0 fs-12-mobile wow animate__fadeInUp">
                                    {!! $cardOneGallery['subtitle'] ? change_text_color_or_bg($cardOneGallery['subtitle']) : translate('Experience comfort, safety, and satisfaction with every trip. End your journey with a smile — every time with E-Ride.') !!}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="h-100">
                            <div class="mb-4 mb-sm-30">
                                <h3 class="mb-3 mb-sm-4 fs-16-mobile wow animate__fadeInUp">
                                    {!! $cardTwoGallery['title'] ? change_text_color_or_bg($cardTwoGallery['title'])  : translate("Share Your Ride") !!}
                                </h3>
                                <p class="fs-16 mb-0 fs-12-mobile wow animate__fadeInUp">
                                    {!!$cardTwoGallery['subtitle'] ? change_text_color_or_bg($cardTwoGallery['subtitle']) : translate('With every turn of the wheel, discover something new — because each ride opens the door to infinite possibilities.') !!}
                                </p>
                            </div>
                            <div
                                class="w-100 h-345 h-200-mobile mx-auto overflow-hidden rounded-20 wow animate__fadeInDown">
                                <img
                                    class="img-fluid w-100 h-100 object-cover"
                                    src="{{ $cardTwoGallery['image'] ?  dynamicStorage('storage/app/public/business/landing-pages/gallery/' .$cardTwoGallery['image']) : dynamicAsset(path: 'public/landing-page/assets/img/gallery/card-2.png') }}"
                                    alt=""
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
    <!-- Gallery Section End -->

    <!-- App Download Section Start -->
    @if($isCustomerAppDownloadEnabled)
        <section class="app-download-section p-0 mt-4 mt-sm-60">
            <div class="container">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-6">
                        <div class="wow animate__fadeInDown">
                            <div class="mb-4 mb-sm-30">
                                <h3 class="mb-3 mb-sm-4 fs-16-mobile">
                                    {!! change_text_color_or_bg($customerAppDownloadSectionContent['title'])  !!}
                                </h3>
                                <p class="fs-16 mb-0 fs-12-mobile">
                                    {!! change_text_color_or_bg($customerAppDownloadSectionContent['subtitle']) !!}
                                </p>
                            </div>
                            <div
                                class="bg-fafafa border rounded-20 p-3 p-sm-4 d-flex justify-content-between align-items-center gap-3 gap-sm-4 w-auto">
                                <div>
                                    <h5 class="mb-2 fs-16-mobile">{!! change_text_color_or_bg($customerAppDownloadButtonContent['title']) !!}</h5>
                                    <p class="mb-0">{!! change_text_color_or_bg($customerAppDownloadButtonContent['subtitle']) !!}</p>
                                    <div class="d-flex gap-3 mt-3">
                                        @if($customerAppVersionControlForAndroid)
                                            <a target="_blank" class="no-gutter" type="button"
                                               href="{{ $customerAppVersionControlForAndroid['app_url'] }}">
                                                <img
                                                    src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-store.png') }}"
                                                    class="w-125px" alt="">
                                            </a>
                                        @endif
                                        @if($customerAppVersionControlForIos)
                                            <a target="_blank" class="no-gutter" type="button"
                                               href="{{ $customerAppVersionControlForIos['app_url'] }}">
                                                <img
                                                    src="{{ dynamicAsset(path: 'public/landing-page/assets/img/app-store.png') }}"
                                                    class="w-125px" alt="">
                                            </a>
                                        @endif
                                    </div>
                                </div>
                                <div
                                    class="bg-white rounded-10 p-3 d-flex justify-content-center align-items-center flex-column gap-2 h-100">
                                    <div class="border rounded-10 p-2">
                                        {!! \QrCode::size(64)->generate(route('blog.customer-app-download')) !!}
                                    </div>
                                    <p class="fs-12-mobile mb-0">{{ translate('Scan to DownLoad') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="w-475 w-220-mobile aspect-1 mx-auto overflow-hidden rounded wow animate__fadeInUp">
                            <img
                                class="img-fluid w-100 h-100 object-cover"
                                src="{{ $customerAppDownloadSectionContent['image'] ? dynamicStorage('storage/app/public/business/landing-pages/customer-app-download/' .$customerAppDownloadSectionContent['image']) : dynamicAsset(path: 'public/landing-page/assets/img/service/demo.png') }}"
                                alt=""
                            >
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
    <!-- App Download Section End -->

    <!-- Earn Money Section Start -->
    @if($isEarnMoneyEnabled)
        <section class="earn-money-section bg-light py-4 py-sm-60 mt-4 mt-sm-60">
            <div class="container">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-6">
                        <div class="w-475 w-220-mobile aspect-1 mx-auto overflow-hidden rounded wow animate__fadeInUp">
                            <img
                                class="img-fluid w-100 h-100 object-cover"
                                src="{{ isset($earnMoneySectionContent) && $earnMoneySectionContent['image'] ? dynamicStorage('storage/app/public/business/landing-pages/earn-money/'.$earnMoneySectionContent['image']) : dynamicAsset(path: 'public/landing-page/assets/img/service/demo.png') }}"
                                alt=""
                            >
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="h-100 wow animate__fadeInDown">
                            <div class="mb-4 mb-sm-30">
                                <h3 class="mb-3 mb-sm-4 fs-16-mobile">
                                    {!! change_text_color_or_bg(isset($earnMoneySectionContent) && $earnMoneySectionContent['title'] ? $earnMoneySectionContent['title'] : translate('Earn Money with **' . businessConfig(key: 'business_name', settingsType: BUSINESS_INFORMATION)?->value) . '**')  !!}
                                </h3>
                                <p class="fs-16 mb-0 fs-12-mobile">
                                    {!! change_text_color_or_bg(isset($earnMoneySectionContent) && $earnMoneySectionContent['subtitle'] ? $earnMoneySectionContent['subtitle'] : translate('Explore limitless possibilities with our platform — turning your skills, time, and passion into a rewarding source of income.'))  !!}
                                </p>
                            </div>
                            <div
                                class="bg-fafafa border rounded-20 p-3 p-sm-4 d-flex justify-content-between align-items-center gap-3 gap-sm-4 w-auto">
                                <div>
                                    <h5 class="mb-2 fs-16-mobile">{!! change_text_color_or_bg($earnMoneyButtonContent['title']) !!}</h5>
                                    <p class="mb-0">{!! $earnMoneyButtonContent['subtitle'] !!}</p>
                                    <div class="d-flex gap-3 mt-3">
                                        @if($driverAppVersionControlForAndroid)
                                            <a target="_blank" class="no-gutter" type="button"
                                               href="{{ $driverAppVersionControlForAndroid['app_url'] }}">
                                                <img
                                                    src="{{ dynamicAsset(path: 'public/landing-page/assets/img/app-store.png') }}"
                                                    class="w-125px" alt="">
                                            </a>
                                        @endif
                                        @if($driverAppVersionControlForIos)
                                            <a target="_blank" class="no-gutter" type="button"
                                               href="{{ $driverAppVersionControlForIos['app_url'] }}">
                                                <img
                                                    src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-store.png') }}"
                                                    class="w-125px" alt="">
                                            </a>
                                        @endif
                                    </div>
                                </div>
                                <div
                                    class="bg-white rounded-10 p-3 d-flex justify-content-center align-items-center flex-column gap-2 h-100">
                                    <div class="border rounded-10 p-2">
                                        {!! \QrCode::size(64)->generate(route('blog.driver-app-download')) !!}
                                    </div>
                                    <p class="fs-12-mobile mb-0">{{ translate('Scan to DownLoad') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
    <!-- Earn Money Section End -->


    <!-- Testimonial Section Start -->
    @if($showTestimonialSection)
        <section class="testimonial-section p-0 mt-4 mt-sm-60">
            <div class="container">
                <h2 class="section-title mb-0 mb-sm-3 fs-16-mobile wow animate__fadeInUp">
                    {!! change_text_color_or_bg($testimonialSectionContent['title'])  !!}
                </h2>
                <div class="wow animate__fadeInDown">
                    <div class="testimonial-slider owl-theme owl-carousel sliderItem-sameHeight">
                        @if($activeTestimonials->isNotEmpty())
                            @foreach($activeTestimonials as $testimonial)
                                @if($testimonial?->value['status'] == 1)
                                    <!-- Testimonial Slider Single Slide -->
                                    <div class="testimonial__item sliderItem p-3 p-sm-4">
                                        <div class="position-absolute left-0 top-0 mt-4">
                                            <img width="40"
                                                 src="{{dynamicAsset(path: 'public/landing-page/assets/img/quatation.svg')}}"
                                                 alt="" class="svg">
                                        </div>
                                        <div class="testimonial__item-img">
                                            <img src="{{ onErrorImage(
                                            $testimonial?->value && $testimonial?->value['reviewer_image'] ? $testimonial?->value['reviewer_image'] : '',
                                            $testimonial?->value && $testimonial?->value['reviewer_image'] ? dynamicStorage(path: 'storage/app/public/business/landing-pages/testimonial/'.$testimonial?->value['reviewer_image']) : '',
                                            dynamicAsset(path: 'public/landing-page/assets/img/client/user.png'),
                                            'business/landing-pages/testimonial/',
                                        ) }}" alt="client">

                                        </div>
                                        <div class="testimonial__item-cont">
                                            <p class="mb-2 name text-dark fs-14">
                                                <span>{!! $testimonial?->value && $testimonial?->value['reviewer_name'] ? change_text_color_or_bg($testimonial?->value['reviewer_name']): "" !!}</span>
                                            </p>
                                            <p class="text--base mb-2 fs-12">{!!  $testimonial?->value && $testimonial?->value['designation'] ? change_text_color_or_bg($testimonial?->value['designation']): "" !!}</p>
                                            <div class="rating mb-2">
                                                @php($count = $testimonial?->value && $testimonial?->value['rating'] ? $testimonial?->value['rating'] : 0)

                                                @for($inc=1;$inc<=5;$inc++)
                                                    @if ($inc <= (int)$count)
                                                        <i class="bi bi-star-fill text-warning"></i>
                                                    @elseif ($count != 0 && $inc <= (int)$count + 1.1 && $count > ((int)$count))
                                                        <i class="bi bi-star-half text-warning"></i>
                                                    @else
                                                        <i class="bi bi-star text-warning"></i>
                                                    @endif
                                                @endfor
                                            </div>
                                            <p class="text-dark">
                                            <blockquote>
                                                {{ $testimonial?->value && $testimonial?->value['review'] ? $testimonial?->value['review'] : "" }}
                                            </blockquote>
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <div class="testimonial__item sliderItem">
                                <div class="testimonial__item-img">
                                    <img src="{{dynamicAsset(path: 'public/landing-page/assets/img/client/user.png')}}"
                                         alt="client">
                                </div>
                                <div class="testimonial__item-cont">
                                    <p class="mb-2 name text-dark"><strong>{{ "Roofus K." }}</strong>
                                    <p>
                                    <p class="text--base mb-0">{{ "Customer" }}</p>
                                    <div class="rating mb-2">
                                        @php($count = 5)

                                        @for($inc=1;$inc<=5;$inc++)
                                            @if ($inc <= (int)$count)
                                                <i class="bi bi-star-fill text-warning"></i>
                                            @elseif ($count != 0 && $inc <= (int)$count + 1.1 && $count > ((int)$count))
                                                <i class="bi bi-star-half text-warning"></i>
                                            @else
                                                <i class="bi bi-star text-warning"></i>
                                            @endif
                                        @endfor
                                    </div>
                                    <p>
                                    <blockquote>
                                        {{ "Exceeded my expectations! Customer support is responsive and helpful. Fantastic experience!" }}
                                    </blockquote>
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Testimonial Slider Bottom Counter and Nav Icons -->
                    <div class="slider-bottom d-flex justify-content-center">
                        <div class="owl-btn testimonial-owl-prev btn btn-circle bg--base text-white">
                            <i class="bi bi-arrow-left"></i>
                        </div>
                        <div class="slider-counter mx-4 d-flex justify-content-center align-items-center"></div>
                        <div class="owl-btn testimonial-owl-next btn btn-circle bg--base text-white">
                            <i class="bi bi-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
    <!-- Testimonial Section End -->

@endsection
