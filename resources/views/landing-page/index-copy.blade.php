@extends('landing-page.layouts.master')
@section('title', 'Home')


@section('content')
    <!-- Banner Section Start -->
    <section class="banner-section">
        <div class="container">
            <div class="banner-wrapper justify-content-between bg__img wow animate__fadeInDown"
                 data-img="{{ $introSection?->value && $introSection?->value['background_image'] ? dynamicStorage(path: 'storage/app/public/business/landing-pages/intro-section/'.$introSectionImage?->value['background_image']) : dynamicAsset(path: 'public/landing-page/assets/img/banner/banner-bg.png') }}">
                <div class="banner-content text-center text-sm-start">
                    <h1 class="title fs-20-mobile max-w-100">{{ $introSection?->value && $introSection?->value['title'] ? translate($introSection?->value['title']) : translate("It’s Time to Change The Riding Experience") }}</h1>
                    <p class="txt fs-12-mobile">{{ $introSection?->value && $introSection?->value['sub_title'] ? translate($introSection?->value['sub_title']) : translate("Embrace the future today and explore the amazing features that make "). (($business_name && $business_name['value']) ? $business_name['value'] : "DriveMond") .translate("the smart, sustainable, and efficient ride sharing & delivery solution.") }}
                    </p>
                    <div class="app--btns d-flex flex-wrap flex-column flex-sm-row">
                        <div class="dropdown">
                            <a href="#" class="cmn--btn h-50 d-flex gap-2 lh-1" data-bs-toggle="dropdown">{{translate('Download User App')}} <i class="bi bi-chevron-down"></i></a>
                            <div class="dropdown-menu dropdown-button-menu">
                                <ul>
                                    <li class="border-bottom">
                                        <a href="{{ $cta?->value && $cta?->value['play_store']['user_download_link'] ? $cta?->value['play_store']['user_download_link'] : "" }}">
                                            <img width="20" class="w-20px" src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-fav.png') }}" alt="">
                                            <span>{{translate('Play Store')}}</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ $cta?->value && $cta?->value['app_store']['user_download_link'] ? $cta?->value['app_store']['user_download_link'] : "" }}">
                                            <img width="20" class="w-20px" src="{{ dynamicAsset(path: 'public/landing-page/assets/img/apple.png') }}" alt="">
                                            <span>{{translate('App Store')}}</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <a href="#about" class="cmn--btn btn-white text-nowrap overflow-hidden text-truncate h-50">
                            {{translate('Earn_From')}} {{ (($business_name && $business_name['value']) ? $business_name['value'] : "DriveMond") }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Banner Section End -->

    <!-- Basic Info Section Start -->
    <section class="basic-info-section">
        <div class="container position-relative">
            <div class="basic-info-wrapper wow animate__fadeInUp">
                <div class="basic-info-item">
                    <img
                        src="{{ $businessStatistics?->value && $businessStatistics?->value['total_download']['image'] ? dynamicStorage(path: 'storage/app/public/business/landing-pages/business-statistics/total-download/'.$businessStatistics?->value['total_download']['image']) : dynamicAsset(path: 'public/landing-page/assets/img/icons/1.png') }}"
                        alt="">
                    <div class="content text-center text-lg-start">
                        <h2 class="h5 fw-bold mb-3 fs-16-mobile">{{ $businessStatistics?->value && $businessStatistics?->value['total_download']['count'] ? $businessStatistics?->value['total_download']['count'] : "1M+" }}</h2>
                        <p class="fs-16 fs-14-mobile">{{ $businessStatistics?->value && $businessStatistics?->value['total_download']['content'] ? translate($businessStatistics?->value['total_download']['content']) : translate("download") }}</p>
                    </div>
                </div>
                <div class="basic-info-item">
                    <img
                        src="{{ $businessStatistics?->value && $businessStatistics?->value['complete_ride']['image'] ? dynamicStorage(path: 'storage/app/public/business/landing-pages/business-statistics/complete-ride/'.$businessStatistics?->value['complete_ride']['image']) : dynamicAsset(path: 'public/landing-page/assets/img/icons/2.png') }}"
                        alt="">
                    <div class="content text-center text-lg-start">
                        <h2 class="h5 fw-bold mb-3 fs-16-mobile">{{ $businessStatistics?->value && $businessStatistics?->value['complete_ride']['count'] ? $businessStatistics?->value['complete_ride']['count'] : "1M+" }}</h2>
                        <p class="fs-16 fs-14-mobile">{{ $businessStatistics?->value && $businessStatistics?->value['complete_ride']['content'] ? translate($businessStatistics?->value['complete_ride']['content']) : translate("Complete Ride") }}</p>
                    </div>
                </div>
                <div class="basic-info-item">
                    <img
                        src="{{ $businessStatistics?->value && $businessStatistics?->value['happy_customer']['image'] ? dynamicStorage(path: 'storage/app/public/business/landing-pages/business-statistics/happy-customer/'.$businessStatistics?->value['happy_customer']['image']) : dynamicAsset(path: 'public/landing-page/assets/img/icons/3.png') }}"
                        alt="">
                    <div class="content text-center text-lg-start">
                        <h2 class="h5 fw-bold mb-3 fs-16-mobile">{{ $businessStatistics?->value && $businessStatistics?->value['happy_customer']['count'] ? $businessStatistics?->value['happy_customer']['count'] : "1M+" }}</h2>
                        <p class="fs-16 fs-14-mobile">{{ $businessStatistics?->value && $businessStatistics?->value['happy_customer']['content'] ? translate($businessStatistics?->value['happy_customer']['content']) : translate("Happy Customer") }}</p>
                    </div>
                </div>
                <div class="basic-info-item">
                    <img
                        src="{{ $businessStatistics?->value && $businessStatistics?->value['support']['image'] ? dynamicStorage(path: 'storage/app/public/business/landing-pages/business-statistics/support/'.$businessStatistics?->value['support']['image']) : dynamicAsset(path: 'public/landing-page/assets/img/icons/4.png')}}"
                        alt="">
                    <div class="content text-center text-lg-start">
                        <h2 class="h5 fw-bold mb-3 fs-16-mobile">{{ $businessStatistics?->value && $businessStatistics?->value['support']['title'] ? $businessStatistics?->value['support']['title'] : "24/7 hr" }}</h2>
                        <p class="fs-16 fs-14-mobile">{{ $businessStatistics?->value && $businessStatistics?->value['support']['content'] ? translate($businessStatistics?->value['support']['content']) : translate("Support") }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Basic Info Section End -->

    <!-- Our Solution Section Start -->
    <section class="platform-section py-4 py-sm-60">
        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/platform/platform-bg.png') }}"
             class="shape d-none d-lg-block" alt="">
        <div class="container">
            <div class="mb-3 mb-sm-4 text-center">
                <h2 class="section-title mb-2 mb-sm-3 fs-16-mobile wow animate__fadeInUp">{{ translate('Our') }} <span class="text--base">{{ translate('Solutions') }}</span></h2>
                <p class="fs-18 mb-0 fs-12-mobile">{{ $ourSolutionSection?->value && $ourSolutionSection?->value['sub_title'] ? translate($ourSolutionSection?->value['sub_title']) : translate("Explore our dynamic day-to-day solution for everyday life") }}</p>
            </div>
            <div class="position-relative wow animate__fadeInDown">
                <div class="ourSolution-slider owl-theme owl-carousel sliderItem-sameHeight">
                    @if($ourSolutionSectionListCount > 0)
                        @foreach($ourSolutionSectionList as $ourSolutionSingle)
                            @if($ourSolutionSingle?->value['status'] == 1)
                                <!-- ourSolution Slider Single Slide -->
                                <div class="ourSolution__item sliderItem">
                                    <div class="w-200 w-150-mobile aspect-1 mx-auto mb-3">
                                        <img src="{{ onErrorImage(
                                            $ourSolutionSingle?->value['image'],
                                            dynamicStorage(path: 'storage/app/public/business/landing-pages/our-solutions/'.$ourSolutionSingle?->value['image']),
                                            dynamicAsset(path: 'public/landing-page/assets/img/platform/'.rand(1,2).'.png'),
                                            'business/landing-pages/our-solutions/',
                                        ) }}" alt="" class="object-cover h-100 rounded">
                                    </div>
                                    <div>
                                        <h4 class="fs-20 fs-16-mobile mb-3">{{ $ourSolutionSingle?->value['title'] ?? '' }}</h4>
                                        <p class="fs-12-mobile">{{ $ourSolutionSingle?->value['description'] ?? '' }}</p>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @else
                        <div class="ourSolution__item sliderItem">
                            <div class="w-200 w-150-mobile aspect-1 mx-auto mb-3">
                                <img class="object-cover h-100 rounded" src="{{dynamicAsset(path: 'public/landing-page/assets/img/platform/1.png')}}" alt="client">
                            </div>
                            <div>
                                <h4 class="fs-20 fs-16-mobile mb-3">{{ translate('Parcel Delivery') }}</h4>
                                <p class="fs-12-mobile">{{ translate('Send important parcels to the right place with custom fare setup option.') }}</p>
                            </div>
                        </div>
                        <div class="ourSolution__item sliderItem">
                            <div class="w-200 w-150-mobile aspect-1 mx-auto mb-3">
                                <img class="object-cover h-100 rounded" src="{{dynamicAsset(path: 'public/landing-page/assets/img/platform/2.png')}}" alt="client">
                            </div>
                            <div>
                                <h4 class="fs-20 fs-16-mobile mb-3">{{ translate('Ride Sharing') }}</h4>
                                <p class="fs-12-mobile">{{ translate('Book a ride to your desired destination and set a custom fare from the app.') }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- ourSolution Slider Middle Nav Icons -->
                <div class="slider-middle d-flex justify-content-center">
                    <div class="owl-btn prev-btn ourSolution-owl-prev btn btn-circle btn-light text-dark border">
                        <i class="bi bi-chevron-left"></i>
                    </div>
                    <div class="mx-2 mx-sm-3"></div>
                    <div class="owl-btn next-btn ourSolution-owl-next btn btn-circle btn-light text-dark border">
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Our Solution Section End -->

    <!-- Platform Section Start -->
    {{-- <section class="platform-section pt-60 pb-60">
        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/platform/platform-bg.png') }}"
             class="shape d-none d-lg-block" alt="">
        <div class="container position-relative">
            <div class="text-center wow animate__fadeInUp pb-4">
                <h2 class="section-title mb-0">
                    {{ $ourSolutionSection?->value && $ourSolutionSection?->value['title'] ? translate($ourSolutionSection?->value['title']) : translate("Our_Solutions") }}
                    <!--{{ translate("Our")}} <span class="text--base">{{ translate("Solutions")}}</span>-->
                </h2>
                <p class="section-text mt-0 pt-2">
                    {{ $ourSolutionSection?->value && $ourSolutionSection?->value['sub_title'] ? translate($ourSolutionSection?->value['sub_title']) : translate("Explore our dynamic day-to-day solution for everyday life") }}
                </p>
            </div>

            <div class="row justify-content-center gap-4 mt-3">
                @if($ourSolutionSectionListCount > 0)
                    @foreach($ourSolutionSectionList as $ourSolutionSingle)
                        @if($ourSolutionSingle?->value['status'] == 1)
                            <div class="col-sm-6 col-md-5 mb-3">
                                <div class="platform-item wow animate__fadeInUp">
                                    <img src="{{ onErrorImage(
                                            $ourSolutionSingle?->value['image'],
                                            dynamicStorage(path: 'storage/app/public/business/landing-pages/our-solutions/'.$ourSolutionSingle?->value['image']),
                                            dynamicAsset(path: 'public/landing-page/assets/img/platform/'.rand(1,2).'.png'),
                                            'business/landing-pages/our-solutions/',
                                        ) }}" alt="" class="img-fluid square-uploaded-img">
                                    <h3 class="title mt-3">
                                        {{ $ourSolutionSingle?->value['title'] ?? '' }}
                                    </h3>
                                    <p class="txt ">
                                        {{ $ourSolutionSingle?->value['description'] ?? '' }}
                                    </p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @else
                    <div class="col-sm-6 col-md-5 mb-3">
                        <div class="platform-item wow animate__fadeInUp">
                            <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/platform/1.png') }}" alt="">
                            <h3 class="title">{{ translate('Ride Sharing') }}</h3>
                            <p class="txt">{{ translate('Book a ride to your desired destination and set a custom fare from the app') }}</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-5 mb-3">
                        <div class="platform-item wow animate__fadeInUp">
                            <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/platform/2.png') }}" alt="">
                            <h3 class="title">{{ translate('Parcel Delivery') }}</h3>
                            <p class="txt">{{ translate('Send important parcels to the right place with custom fare setup option') }}</p>
                        </div>
                    </div>
                @endif

            </div>

        </div>
    </section> --}}
    <!-- Platform Section End -->

    <!-- Our Services Section Start -->
    <section class="service-section bg-light py-4 py-sm-60">
        <div class="container">
            <div class="mb-3 mb-sm-5 text-center">
                <h3 class="section-title mb-2 mb-sm-3 fs-16-mobile wow animate__fadeInUp">{{ translate('Our') }} <span class="text--base">{{ translate('Services') }}</span></h3>
                <p class="fs-18 mb-0 fs-12-mobile">{{ translate('Discover our innovative solutions designed to enhance daily operations.') }}</p>
            </div>
            <ul class="nav nav-tabs nav--tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="first-tab" data-bs-toggle="tab" data-bs-target="#tab1" type="button" role="tab" aria-controls="tab1" aria-selected="true">
                        {{ translate('Regular Trip') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="second-tab" data-bs-toggle="tab" data-bs-target="#tab2" type="button" role="tab" aria-controls="tab2" aria-selected="false">
                        {{ translate('Schedule Trip') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="third-tab" data-bs-toggle="tab" data-bs-target="#tab3" type="button" role="tab" aria-controls="tab3" aria-selected="false">
                        {{ translate('Parcel delivery') }}
                    </button>
                </li>
            </ul>
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="tab1" role="tabpanel" aria-labelledby="first-tab">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="mt-3 mt-sm-5">
                                <h4 class="mb-3 fs-16-mobile">
                                    {{ translate('Plan your next adventure with DriveMonds trip') }}
                                    <span class="text--base">{{ translate('scheduling') }}</span>
                                    {{ translate('features.') }}
                                </h4>
                                <div class="editor-content">
                                    <p>{{ translate('Discover endless opportunities to schedule trips that align with your skills and interests, transforming your time into a profitable venture.') }}</p>
                                    <ul>
                                        <li>Select your preferred mode of transport for your journey – whether it's a car, scooter, or bicycle.</li>
                                        <li>Enjoy the freedom of scheduling trips that suit your personal timetable.</li>
                                        <li>Receive immediate payments and take advantage of exciting bonus offers.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="w-475 w-220-mobile aspect-1 mx-auto overflow-hidden rounded">
                                <img
                                    class="img-fluid w-100 h-100 object-cover"
                                    src="{{ dynamicAsset(path: 'public/landing-page/assets/img/service/demo.png') }}"
                                    alt=""
                                >
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab2" role="tabpanel" aria-labelledby="second-tab">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="mt-3 mt-sm-5">
                                <h4 class="mb-3 fs-16-mobile">
                                    {{ translate('Plan your next adventure with DriveMonds trip') }}
                                    <span class="text--base">{{ translate('scheduling') }}</span>
                                    {{ translate('features.') }}
                                </h4>
                                <div class="editor-content">
                                    <p>{{ translate('Discover endless opportunities to schedule trips that align with your skills and interests, transforming your time into a profitable venture.') }}</p>
                                    <ul>
                                        <li>Select your preferred mode of transport for your journey – whether it's a car, scooter, or bicycle.</li>
                                        <li>Enjoy the freedom of scheduling trips that suit your personal timetable.</li>
                                        <li>Receive immediate payments and take advantage of exciting bonus offers.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="w-475 aspect-1 mx-auto">
                                <img class="img-fluid object-cover rounded" src="{{ dynamicAsset(path: 'public/landing-page/assets/img/service/demo.png') }}" alt="">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab3" role="tabpanel" aria-labelledby="third-tab">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="mt-3 mt-sm-5">
                                <h4 class="mb-3 fs-16-mobile">
                                    {{ translate('Plan your next adventure with DriveMonds trip') }}
                                    <span class="text--base">{{ translate('scheduling') }}</span>
                                    {{ translate('features.') }}
                                </h4>
                                <div class="editor-content">
                                    <p>{{ translate('Discover endless opportunities to schedule trips that align with your skills and interests, transforming your time into a profitable venture.') }}</p>
                                    <ul>
                                        <li>Select your preferred mode of transport for your journey – whether it's a car, scooter, or bicycle.</li>
                                        <li>Enjoy the freedom of scheduling trips that suit your personal timetable.</li>
                                        <li>Receive immediate payments and take advantage of exciting bonus offers.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="w-475 aspect-1 mx-auto">
                                <img class="img-fluid object-cover rounded" src="{{ dynamicAsset(path: 'public/landing-page/assets/img/service/demo.png') }}" alt="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Our Services Section End -->

    <!-- Gallery Section Start -->
    <section class="gallery-section py-4 py-sm-60">
        <div class="container">
            <div class="row g-4 pb-50">
                <div class="col-lg-6">
                    <div class="h-100">
                        <div class="w-100 h-345 h-200-mobile mx-auto overflow-hidden rounded-20 wow animate__fadeInDown">
                            <img
                                class="img-fluid w-100 h-100 object-cover"
                                src="{{ dynamicAsset(path: 'public/landing-page/assets/img/service/pop1.png') }}"
                                alt=""
                            >
                        </div>
                        <div class="mt-4 mt-sm-30">
                            <h3 class="mb-3 mb-sm-4 fs-16-mobile wow animate__fadeInUp">
                                {{ translate('Ride Completed') }} <span class="text--base">{{ translate('Hassle-Free') }}</span>
                            </h3>
                            <p class="fs-16 mb-0 fs-12-mobile wow animate__fadeInUp">
                                {{ translate('Experience comfort, safety, and satisfaction with every trip. End your journey with a smile — every time with DriveMond.') }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="h-100">
                        <div class="mb-4 mb-sm-30">
                            <h3 class="mb-3 mb-sm-4 fs-16-mobile wow animate__fadeInUp">
                                {{ translate('Easily') }} <span class="text--base">{{ translate('Share') }}</span> {{ translate('Your Ride') }}
                            </h3>
                            <p class="fs-16 mb-0 fs-12-mobile wow animate__fadeInUp">
                                {{ translate('With every turn of the wheel, discover something new — because each ride opens the door to infinite possibilities.') }}
                            </p>
                        </div>
                        <div class="w-100 h-345 h-200-mobile mx-auto overflow-hidden rounded-20 wow animate__fadeInDown">
                            <img
                                class="img-fluid w-100 h-100 object-cover"
                                src="{{ dynamicAsset(path: 'public/landing-page/assets/img/service/pop2.png') }}"
                                alt=""
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Gallery Section End -->

    <!-- App Download Section Start -->
    <section class="app-download-section pb-4 pb-sm-60">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="h-100 wow animate__fadeInDown">
                        <div class="mb-4 mb-sm-30">
                            <h3 class="mb-3 mb-sm-4 fs-16-mobile">
                                {{ translate('Your') }} <span class="text--base">{{ translate('Share') }}</span> {{ translate('Your Ride') }}
                            </h3>
                            <p class="fs-16 mb-0 fs-12-mobile">
                                {{ translate('Experience hassle-free transportation with DriveMond. Reliable rides anytime, anywhere.') }}
                            </p>
                        </div>
                        <div class="bg-fafafa border rounded-20 p-3 p-sm-4 d-flex justify-content-between align-items-end gap-3 gap-sm-4 w-auto">
                            <div>
                                <h5 class="mb-2 fs-16-mobile">{{ translate('Download the User App') }}</h5>
                                <p class="mb-0">{{ translate('Start your Journey here') }}</p>
                                <div class="d-flex gap-3 mt-3">
                                    <a target="_blank" class="no-gutter" type="button"
                                       href="{{ $cta?->value && $cta?->value['play_store']['user_download_link'] ? $cta?->value['play_store']['user_download_link'] : "" }}">
                                        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-store.png') }}"
                                             class="w-125px" alt="">
                                    </a>
                                    <a target="_blank" class="no-gutter" type="button"
                                       href="{{ $cta?->value && $cta?->value['app_store']['user_download_link'] ? $cta?->value['app_store']['user_download_link'] : "" }}">
                                        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/app-store.png') }}"
                                             class="w-125px" alt="">
                                    </a>
                                </div>
                            </div>
                            <div class="bg-white rounded-10 p-3 d-flex justify-content-center align-items-center flex-column gap-2 h-100">
                                <div class="border rounded-10 p-2">
                                    <img src="{{dynamicAsset('public/assets/admin-module/img/qr-code/qr-code.png')}}" width="80" height="80" alt="">
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
                            src="{{ dynamicAsset(path: 'public/landing-page/assets/img/service/demo.png') }}"
                            alt=""
                        >
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- App Download Section End -->

    <!-- Earn Money Section Start -->
    <section class="earn-money-section bg-light py-4 py-sm-60">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="w-475 w-220-mobile aspect-1 mx-auto overflow-hidden rounded wow animate__fadeInUp">
                        <img
                            class="img-fluid w-100 h-100 object-cover"
                            src="{{ dynamicAsset(path: 'public/landing-page/assets/img/service/demo.png') }}"
                            alt=""
                        >
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="h-100 wow animate__fadeInDown">
                        <div class="mb-4 mb-sm-30">
                            <h3 class="mb-3 mb-sm-4 fs-16-mobile">
                                {{ translate('Earn Money with') }} <span class="text--base">{{ translate('DriveMond') }}</span>
                            </h3>
                            <p class="fs-16 mb-0 fs-12-mobile">
                                {{ translate('Explore limitless possibilities with our platform — turning your skills, time, and passion into a rewarding source of income.') }}
                            </p>
                        </div>
                        <div class="bg-fafafa border rounded-20 p-3 p-sm-4 d-flex justify-content-between align-items-end gap-3 gap-sm-4 w-auto">
                            <div>
                                <h5 class="mb-2 fs-16-mobile">{{ translate('Download the Delivery / Driver App') }}</h5>
                                <p class="mb-0">{{ translate('Start your earning Journey here') }}</p>
                                <div class="d-flex gap-3 mt-3">
                                    <a target="_blank" class="no-gutter" type="button"
                                       href="{{ $cta?->value && $cta?->value['app_store']['driver_download_link'] ? $cta?->value['app_store']['driver_download_link'] : "" }}">
                                        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/app-store.png') }}"
                                             class="w-125px" alt="">
                                    </a>
                                    <a target="_blank" class="no-gutter" type="button"
                                       href="{{ $cta?->value && $cta?->value['play_store']['driver_download_link'] ? $cta?->value['play_store']['driver_download_link'] : "" }}">
                                        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-store.png') }}"
                                             class="w-125px" alt="">
                                    </a>
                                </div>
                            </div>
                            <div class="bg-white rounded-10 p-3 d-flex justify-content-center align-items-center flex-column gap-2 h-100">
                                <div class="border rounded-10 p-2">
                                    <img src="{{dynamicAsset('public/assets/admin-module/img/qr-code/qr-code.png')}}" width="80" height="80" alt="">
                                </div>
                                <p class="fs-12-mobile mb-0">{{ translate('Scan to DownLoad') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Earn Money Section End -->

    {{-- <!-- earn money Section Start -->
    <section class="about-section bg-2 py-25">
        <div class="scroll-elem" id="about"></div>
        <div class="container">
            <div class="about__wrapper">
                <div class="about__wrapper-thumb wow animate__fadeInUp">
                    <img class="main-img"
                         src="{{ $earnMoneyImage?->value['image'] ? dynamicStorage(path: 'storage/app/public/business/landing-pages/earn-money/'.$earnMoneyImage?->value['image']): dynamicAsset(path: 'public/landing-page/assets/img/about1.png') }}"
                         alt="img">
                </div>

                <div class="about__wrapper-content bg-transparent wow animate__fadeInDown">
                    <h2 class="section-title text-start ms-0">{{ $earnMoney?->value && $earnMoney?->value['title'] ? translate($earnMoney?->value['title']) : translate("Earn Money with") }}
                        <span class="text--base">{{ (($business_name && $business_name['value']) ? $business_name['value'] : "DriveMond")}}</span></h2>
                    <p>
                        {{ $earnMoney?->value && $earnMoney?->value['sub_title'] ? translate($earnMoney?->value['sub_title']) : translate("With flexible schedules and a user-friendly platform, you can earn money with every ride. Become a ").(($business_name && $business_name['value']) ? $business_name['value'] : "DriveMond"). translate("today!") }}
                    </p>
                    <br>
                    <div class="dropdown d-inline-block">
                        <a class="cmn--btn btn-black px-4 h-50" href="#" data-bs-toggle="dropdown">
                            {{translate('Be a Delivery man / Driver')}}
                        </a>
                        <div class="dropdown-menu dropdown-button-menu">
                            <ul>
                                <li>
                                    <a href="{{ $cta?->value && $cta?->value['play_store']['driver_download_link'] ? $cta?->value['play_store']['driver_download_link'] : "" }}">
                                        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-fav.png') }}" alt="">
                                        <span>{{translate('Play Store')}}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ $cta?->value && $cta?->value['app_store']['driver_download_link'] ? $cta?->value['app_store']['driver_download_link'] : "" }}">
                                        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/apple-fav.png') }}" alt="">
                                        <span>{{translate('App Store')}}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- About Section End --> --}}

    <!-- Testimonial Section Start -->
    <section class="testimonial-section pt-4 pt-sm-60">
        <div class="container-fluid">
            <h2 class="section-title mb-0 mb-sm-3 fs-16-mobile wow animate__fadeInUp"><span class="text--base">2000+</span> {{ translate('People Share Their Love') }}</h2>
            <div class="wow animate__fadeInDown">
                <div class="testimonial-slider owl-theme owl-carousel sliderItem-sameHeight">
                    @if($testimonialListCount>0)
                        @foreach($testimonials as $testimonial)
                            @if($testimonial?->value['status'] == 1)
                                <!-- Testimonial Slider Single Slide -->
                                <div class="testimonial__item sliderItem p-3 p-sm-4">
                                    <div class="position-absolute left-0 top-0 mt-4">
                                        <img width="40" src="{{dynamicAsset(path: 'public/landing-page/assets/img/quatation.svg')}}" alt="" class="svg">
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
                                        <p class="mb-2 name text-dark fs-14"><span>{{ $testimonial?->value && $testimonial?->value['reviewer_name'] ? $testimonial?->value['reviewer_name']: "" }}</span></p>
                                        <p class="text--base mb-2 fs-12">{{ $testimonial?->value && $testimonial?->value['designation'] ? $testimonial?->value['designation']: "" }}</p>
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
                                <img src="{{dynamicAsset(path: 'public/landing-page/assets/img/client/user.png')}}" alt="client">
                            </div>
                            <div class="testimonial__item-cont">
                                <p class="mb-2 name text-dark"><strong>{{ "Roofus K." }}</strong><p>
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
                    {{-- <div class="slider-counter mx-3"></div> --}}
                    <div class="mx-2 mx-sm-3"></div>
                    <div class="owl-btn testimonial-owl-next btn btn-circle bg--base text-white">
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Testimonial Section End -->

    <!-- CTA Section Start -->
    {{-- <section class="cta-section py-60px-90px">
        <div class="container">
            <div class="cta--wrapper bg__img"
                 data-img="{{ $ctaImage?->value['background_image'] ? dynamicStorage(path: 'storage/app/public/business/landing-pages/cta/'.$ctaImage?->value['background_image']) : dynamicAsset(path: 'public/landing-page/assets/img/cta-bg.png') }}">
                <div class="cta-inner">
                    <div class="content wow animate__fadeInDown">
                        <h2 class="title text-capitalize">{{ $cta?->value && $cta?->value['title'] ? translate($cta?->value['title']) : translate("Download Our App") }}</h2>
                        <p class="mb-3 pb-1">
                            {{ $cta?->value && $cta?->value['sub_title'] ? translate($cta?->value['sub_title']) : translate("For both Android and IOS") }}
                        </p>
                        <div class="d-flex flex-wrap flex-lg-nowrap gap-4 gap-lg-5">
                            <div class="me-xl-4 d-flex align-items-center gap-3">
                                <img src="{{dynamicAsset('public/assets/admin-module/img/qr-code/user.png')}}" width="88" alt="">
                                <div class="d-flex gap-3 flex-column">
                                    <a target="_blank" class="no-gutter" type="button"
                                       href="{{ $cta?->value && $cta?->value['app_store']['user_download_link'] ? $cta?->value['app_store']['user_download_link'] : "" }}">
                                        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/app-store.png') }}"
                                             class="w-125px" alt="">
                                    </a>
                                    <a target="_blank" class="no-gutter" type="button"
                                       href="{{ $cta?->value && $cta?->value['play_store']['user_download_link'] ? $cta?->value['play_store']['user_download_link'] : "" }}">
                                        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-store.png') }}"
                                             class="w-125px" alt="">
                                    </a>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <img src="{{dynamicAsset('public/assets/admin-module/img/qr-code/driver.png')}}" width="88" alt="">
                                <div class="d-flex gap-3 flex-column">
                                    <a target="_blank" class="no-gutter" type="button"
                                       href="{{ $cta?->value && $cta?->value['app_store']['driver_download_link'] ? $cta?->value['app_store']['driver_download_link'] : "" }}">
                                        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/app-store.png') }}"
                                             class="w-125px" alt="">
                                    </a>
                                    <a target="_blank" class="no-gutter" type="button"
                                       href="{{ $cta?->value && $cta?->value['play_store']['driver_download_link'] ? $cta?->value['play_store']['driver_download_link'] : "" }}">
                                        <img src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-store.png') }}"
                                             class="w-125px" alt="">
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="position-relative w-100 max-355 wow animate__fadeInUp">
                        <img class="mw-100"
                             src="{{ $ctaImage?->value['image'] ? dynamicStorage(path: 'storage/app/public/business/landing-pages/cta/'.$ctaImage?->value['image']) : dynamicAsset(path: 'public/landing-page/assets/img/cta.png') }}"
                             alt="">
                    </div>
                </div>
            </div>
        </div>
    </section> --}}
    <!-- CTA Section End -->
@endsection
