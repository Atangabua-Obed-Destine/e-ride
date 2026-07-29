@extends('landing-page.layouts.master')
@section('title', 'Blog Category')

@section('content')
    <div class="blog-root-container">
        <div class="container pt-3">
            <!-- Page Header Start -->
            @include('blog.partials._header')
            <!-- Page Header End -->
            <section class="blog-section">
                @include('blog.partials._nav')
                <div class="row g-4 mt-0">
                    <div class="col-lg-8">
                        <div class="row g-4">
                            @forelse($blogs as $blog)
                                <div class="col-md-6">
                                    @include('blog.partials._blog-card', ['blog' => $blog])
                                </div>
                            @empty
                                <div class="col-12">
                                    @include('blog.partials._no-blog-found')
                                </div>
                            @endforelse
                        </div>
                        @if ($blogs->hasPages())
                            <div class="d-flex justify-content-center mt-3">
                                {{ $blogs->links() }}
                            </div>
                        @endif
                    </div>
                    <div class="col-lg-4">
                        <div class="sticky-top-wrapper top-170px">
                            @if($recentBlogs->count() > 0)
                                <div class="card card-body rounded-10 card-shadow border-0 mb-20 p-3 p-sm-4">
                                    <h4 class="fs-20 fw-medium mb-20">{{ translate('Recent posts') }}</h4>
                                    <div class="recent-post-wrapper">
                                        @foreach($recentBlogs as $blog)
                                            <div class="recent-post">
                                                <div class="d-flex gap-3">
                                                    <img class="h-80px aspect-1 object-cover rounded-10"
                                                         src="{{ $blog->thumbnail ? dynamicStorage('storage/app/public/blog/' . $blog->thumbnail) : '' }}"
                                                         alt="{{ $blog->title }}">
                                                    <div class="d-flex flex-column gap-3">
                                                        <h6 class="fs-14 fs-12-mobile fw-medium line-clamp-2 mb-0">
                                                            <a
                                                                href="{{ route('blog.details', $blog?->slug) }}"
                                                                class="line-clamp-2 text-dark text-hover-primary">
                                                                {{ $blog->title }}
                                                            </a>
                                                        </h6>
                                                        <p class="fs-14 fs-12-mobile mb-0">{{ $blog->published_at->format('F d, Y') }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if($isAppDownloadSetupEnabled)
                                <div class="d-flex flex-column gap-20">
                                    @if(!empty($driverAndroidAppLink) || !empty($driverIosAppLink))
                                        <div
                                            class="bg-white rounded-10 card-shadow p-3 p-sm-4 d-flex flex-column align-items-center text-center gap-4">
                                            <div>
                                                <h4 class="mb-2 fs-16-mobile">{{ $driverAppContent['title'] ?? translate('Download the Driver App') }}</h4>
                                                <p class="fs-12-mobile mb-0">{{ $driverAppContent['subtitle'] ?? translate('Take control of your rides anywhere.') }}</p>
                                            </div>
                                            <div
                                                class="bg-fafafa rounded-10 p-3 d-flex justify-content-center align-items-center flex-column gap-3 h-100">
                                                <div class="border rounded-10 p-2">
                                                    {!! QrCode::size(64)->generate(route('blog.driver-app-download')) !!}
                                                </div>
                                                <p class="fs-12-mobile mb-0">{{ translate('Scan to DownLoad') }}</p>
                                            </div>
                                            <div class="d-flex gap-3 flex-column flex-sm-row w-100">
                                                @if(!empty($driverAndroidAppLink['app_url']))
                                                    <a target="_blank"
                                                       class="btn btn-dark fw-semibold px-4 py-3 rounded d-flex justify-content-center align-items-center gap-2 w-100"
                                                       type="button"
                                                       href="{{ $driverAndroidAppLink['app_url'] ?? '#' }}">
                                                        <img
                                                            src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-icon.svg') }}"
                                                            class="svg" alt="">
                                                        {{ translate('Google Play') }}
                                                    </a>
                                                @endif
                                                @if(!empty($driverIosAppLink['app_url']))
                                                    <a target="_blank"
                                                       class="btn btn-dark fw-semibold px-4 py-3 rounded d-flex justify-content-center align-items-center gap-2 w-100"
                                                       type="button"
                                                       href="{{ $driverIosAppLink['app_url'] ?? '#' }}">
                                                        <img
                                                            src="{{ dynamicAsset(path: 'public/landing-page/assets/img/apple-icon.svg') }}"
                                                            class="svg" alt="">
                                                        {{ translate('App Store') }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                    @if(!empty($customerAndroidAppLink) || !empty($customerIosAppLink))
                                        <div class="newsletter--wrapper bg__img"
                                             data-img="{{ isset($customerAppContent) && $customerAppContent['image'] ? dynamicStorage('storage/app/public/blog/setting/app/' . $customerAppContent['image']) : '' }}">
                                            <div
                                                class="position-relative p-3 p-sm-4 d-flex flex-column align-items-center text-center gap-4">
                                                <div class="">
                                                    <h4 class="mb-2 fs-16-mobile text-white">{{ $customerAppContent['title'] ?? translate('Download the User App') }}</h4>
                                                    <p class="fs-12-mobile mb-0 text-white">{{ $customerAppContent['subtitle'] ?? translate('Book and manage your rides anytime, anywhere.') }}</p>
                                                </div>
                                                <div
                                                    class="bg-white rounded-10 p-3 d-flex justify-content-center align-items-center flex-column gap-3 h-100">
                                                    <div class="border rounded-10 p-2">
                                                        {!! QrCode::size(64)->generate(route('blog.customer-app-download')) !!}
                                                    </div>
                                                    <p class="fs-12-mobile mb-0">{{ translate('Scan to DownLoad') }}</p>
                                                </div>
                                                <div class="d-flex gap-3 flex-column flex-sm-row w-100">
                                                    @if(!empty($customerAndroidAppLink['app_url']))
                                                        <a target="_blank"
                                                           class="btn btn-white text-dark fw-semibold px-4 py-3 rounded d-flex justify-content-center align-items-center gap-2 w-100"
                                                           type="button"
                                                           href="{{ $customerAndroidAppLink['app_url'] }}">
                                                            <img
                                                                src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-icon.svg') }}"
                                                                class="svg" alt="">
                                                            {{ translate('Google Play') }}
                                                        </a>
                                                    @endif
                                                    @if($customerIosAppLink['app_url'])
                                                        <a target="_blank"
                                                           class="btn btn-white text-dark fw-semibold px-4 py-3 rounded d-flex justify-content-center align-items-center gap-2 w-100"
                                                           type="button"
                                                           href="{{ $customerIosAppLink['app_url'] }}">
                                                            <img
                                                                src="{{ dynamicAsset(path: 'public/landing-page/assets/img/apple-icon.svg') }}"
                                                                class="svg" alt="">
                                                            {{ translate('App Store') }}
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/landing-page/assets/js/blog.js') }}"></script>
@endpush
