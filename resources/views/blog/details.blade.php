@php use Illuminate\Support\Str; @endphp
@extends('landing-page.layouts.master')
@php
    $metaTitle = $blog->meta_title ?? $blog->title;
    $metaDescription = $blog->meta_description ?? Str::limit(strip_tags($blog->description), 160);
    $metaImage = $blog->meta_image ? dynamicStorage('storage/app/public/blog/meta-image/' . $blog->meta_image) : dynamicStorage('storage/app/public/blog/' . $blog->thumbnail)
@endphp
@if(request()->filled('preview'))
    @section('title', $metaTitle)
@else
    @section('title', $metaTitle)
    @push('seo')
        <meta name="description" content="{{ $metaDescription }}">
        <meta property="og:type" content="article">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:image" content="{{ $metaImage }}">
        <meta property="og:url" content="{{ url()->current() }}">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <meta name="twitter:image" content="{{ $metaImage }}">

        <link rel="canonical" href="{{ url()->current() }}">

    @endpush
@endif

@section('content')
    <div class="blog-root-container">
        <div class="container pt-3">
            <section class="blog-details-section">
                @if(request()->filled('preview') && request()->get('preview') == 'drafted')
                    <div class="d-flex justify-content-center">
                        <span
                            class="btn btn-outline-danger border border-danger py-1 rounded text-center max-w-500">
                            <i class="bi bi-question-circle-fill"></i>
                            <span>{{ translate('This is a draft copy.') }} {{ translate('It has not been published yet.') }}</span>
                        </span>
                    </div>
                @endif
                <div class="row justify-content-center mt-2">
                    @if(count($blogDescriptionAndSection['sections']) > 0)
                        <div class="col-lg-3">
                            <div class="position-relative mt-0 mt-lg-5">
                                <div class="article-nav-wrapper_collapse ">
                                    <i class="bi bi-list open-icon fw-bold"></i>
                                    <i class="bi bi-chevron-left close-icon fw-bold fs-14 d-none"></i>
                                </div>
                            </div>

                            <div
                                class="article-nav-wrapper sticky-top-wrapper p-3 pt-4 pt-lg-3 px-lg-0 mt-1 mt-lg-0 d-none d-lg-block">
                                <h4 class="fs-16-mobile fw-semibold mb-4 mb-lg-3 ml-5 ml-lg-0">{{ translate('In this article:') }}</h4>
                                <ul class="m-0 p-0 scrollspy-blog-details-menu">
                                    @foreach($blogDescriptionAndSection['sections'] ?? [] as  $section)
                                        <li class="">
                                            <a href="#{{ $section['id'] }}"
                                               class="line-clamp-1">{{ $section['title'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                    <div class=" {{ count($blogDescriptionAndSection['sections']) > 0 ? 'col-lg-6' : 'col-lg-9' }}">
                        <div class="mb-3">
                            @if(!is_null($blog?->category?->name) && $blog?->category->status)
                                <div class="text-center mb-3">
                                    <a href="{{ route('blog.category', $blog?->category?->slug) }}"  title="{{ $blog?->category?->name }}"
                                         class="text--base bg--base bg-opacity-10 rounded px-3 py-1 lh-base mb-0 fs-12 line-clamp-1 w-fit-content mx-auto">
                                        <span>{{ ucwords($blog?->category?->name) }}</span>
                                    </a>
                                </div>
                            @endif


                            <h1 class="fs-24 fw-bold mb-4 text-center fs-16-mobile">
                                {{ $blog->title }}
                            </h1>
                            <div
                                class="fs-14 fs-12-mobile lh-1 d-flex justify-content-between justify-content-sm-center align-items-center gap-3 mb-0 mb-lg-5">
                                @php($authorName = $blog?->author?->name)
                                @php($authorSlug = $blog?->author?->slug)
                                @if($authorName)
                                    <span class="border-inline-end pe-3">
                                    {{ translate('By') }}
                                    <a href="{{ route('blog.author', $authorSlug) }}" class="text-info text-decoration-underline">
                                        {{ ucwords($authorName) }}
                                    </a>
                                </span>
                                @endif
                                @if(!request()->filled('preview'))
                                    <span
                                        class="border-inline-end pe-3">{{ translate('{count} Views', ['count' => abbreviateNumber($blog->click_count ?? 0)]) }}</span>
                                @endif
                                <span>{{ $blog->published_at->format('M d, Y') }}</span>
                            </div>
                        </div>

                        <div class="scrollspy-blog-details mx-lg-4">
                            <img class="max-h-420px w-100 aspect-2 rounded-10 pb-4 pb-sm-5"
                                 src="{{ $blog->thumbnail ? dynamicStorage('storage/app/public/blog/' . $blog->thumbnail) : '' }}"
                                 alt="{{ $blog->title }}">

                            <div class="rich-editor-html-content">
                                {!! $blogDescriptionAndSection['html'] !!}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="sticky-top-wrapper top-80px mt-3 mt-lg-0">
                            <div class="d-flex flex-column gap-3">
                                <div class="text-center mb-30">
                                    <a href="#" class="mb-3 text-info fw-medium">{{ translate('Share Now') }}</a>
                                    <div class="d-flex justify-content-center align-items-center gap-3 mb-20">
                                        <a href="{{ isset($blog->slug) && !request()->filled('preview') ? "https://www.facebook.com/sharer/sharer.php?u=" . urlencode(route('blog.details', $blog->slug)) : '' }}"
                                           target="_blank"
                                           class="flex-shrink-0">
                                            <img width="30" height="30"
                                                 src="{{ dynamicAsset(path: 'public/landing-page/assets/img/social/facebook.png') }}"
                                                 alt="">
                                        </a>
                                        <a href="{{ isset($blog->slug) && !request()->filled('preview') ? "https://twitter.com/intent/tweet?text=" . urlencode($blog->title) . "&url=" . urlencode(route('blog.details', $blog->slug)) : ''}}"
                                           target="_blank"
                                           class="flex-shrink-0">
                                            <img width="30" height="30"
                                                 src="{{ dynamicAsset(path: 'public/landing-page/assets/img/social/twitter.png') }}"
                                                 alt="">
                                        </a>

                                        <a href="{{ isset($blog->slug) && !request()->filled('preview') ? "https://api.whatsapp.com/send?text=" . urlencode($blog->title . ' ' . route('blog.details', $blog->slug)) : '' }}"
                                           target="_blank"
                                           class="flex-shrink-0">
                                            <img width="30" height="30"
                                                 src="{{ dynamicAsset(path: 'public/landing-page/assets/img/social/whatsapp.svg') }}"
                                                 alt="">
                                        </a>
                                        <a href="{{ isset($blog->slug) && !request()->filled('preview') ? "https://www.linkedin.com/shareArticle?mini=true&url=" . urlencode(route('blog.details', $blog->slug)) . "&title=" .  urlencode($blog->title) . "&summary=" . urlencode($blog->description) : '' }}"
                                           target="_blank"
                                           class="flex-shrink-0">
                                            <img width="30" height="30"
                                                 src="{{ dynamicAsset(path: 'public/landing-page/assets/img/social/linkedin.png') }}"
                                                 alt="">
                                        </a>
                                    </div>
                                    <hr class="my-0">
                                </div>
                                @if($isAppDownloadSetupEnabled)
                                    @if(!empty($driverAndroidAppLink) || !empty($driverIosAppLink))
                                        <div
                                            class="bg-white rounded-10 card-shadow p-3 d-flex flex-column align-items-center text-center gap-4">
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
                                            <div class="d-flex gap-3 flex-wrap flex-xxl-nowrap w-100">
                                                @if(!empty($driverAndroidAppLink['app_url']))
                                                    <a target="_blank"
                                                       class="btn btn-dark fw-semibold rounded d-flex justify-content-center align-items-center gap-2 w-100"
                                                       type="button"
                                                       href="{{ $driverAndroidAppLink['app_url'] ?? '#' }}">
                                                        <img
                                                            src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-icon.svg') }}"
                                                            class="svg" alt="">
                                                        <span class="text-nowrap">{{ translate('Google Play') }}</span>
                                                    </a>
                                                @endif
                                                @if(!empty($driverIosAppLink['app_url']))
                                                    <a target="_blank"
                                                       class="btn btn-dark fw-semibold rounded d-flex justify-content-center align-items-center gap-2 w-100"
                                                       type="button"
                                                       href="{{ $driverIosAppLink['app_url'] ?? '#' }}">
                                                        <img
                                                            src="{{ dynamicAsset(path: 'public/landing-page/assets/img/apple-icon.svg') }}"
                                                            class="svg" alt="">
                                                        <span class="text-nowrap">{{ translate('App Store') }}</span>
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                    @if(!empty($customerAndroidAppLink) || !empty($customerIosAppLink))
                                        <div class="newsletter--wrapper bg__img"
                                             data-img="{{ isset($customerAppContent) && $customerAppContent['image'] ? dynamicStorage('storage/app/public/blog/setting/app/' . $customerAppContent['image']) : '' }}">
                                            <div
                                                class="position-relative p-3 d-flex flex-column align-items-center text-center gap-4">
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
                                                <div class="d-flex gap-3 flex-wrap flex-xxl-nowrap w-100">
                                                    @if(!empty($customerAndroidAppLink['app_url']))
                                                        <a target="_blank"
                                                           class="btn btn-white text-dark fw-semibold rounded d-flex justify-content-center align-items-center gap-2 w-100"
                                                           type="button"
                                                           href="{{ $customerAndroidAppLink['app_url'] }}">
                                                            <img
                                                                src="{{ dynamicAsset(path: 'public/landing-page/assets/img/play-icon.svg') }}"
                                                                class="svg" alt="">
                                                            <span
                                                                class="text-nowrap">{{ translate('Google Play') }}</span>
                                                        </a>
                                                    @endif
                                                    @if($customerIosAppLink['app_url'])
                                                        <a target="_blank"
                                                           class="btn btn-white text-dark fw-semibold rounded d-flex justify-content-center align-items-center gap-2 w-100"
                                                           type="button"
                                                           href="{{ $customerIosAppLink['app_url'] }}">
                                                            <img
                                                                src="{{ dynamicAsset(path: 'public/landing-page/assets/img/apple-icon.svg') }}"
                                                                class="svg" alt="">
                                                            <span
                                                                class="text-nowrap">{{ translate('App Store') }}</span>
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pt-30 pt-sm-5">
                    <div class="card-mobile text-center mb-30 mb-sm-5">
                        <h4 class="fs-18 fs-16-mobile fw-normal mb-3">{{ translate('Share this article') }}</h4>
                        <div class="d-flex justify-content-center align-items-center gap-3 border-top-before-after">
                            <a href="{{ isset($blog->slug) && !request()->filled('preview') ? "https://www.facebook.com/sharer/sharer.php?u=" . urlencode(route('blog.details', $blog->slug)) : '' }}"
                               target="_blank"
                               class="flex-shrink-0">
                                <img width="30" height="30"
                                     src="{{ dynamicAsset(path: 'public/landing-page/assets/img/social/facebook.png') }}"
                                     alt="">
                            </a>
                            <a href="{{ isset($blog->slug) && !request()->filled('preview') ? "https://twitter.com/intent/tweet?text=" . urlencode($blog->title) . "&url=" . urlencode(route('blog.details', $blog->slug)) : ''}}"
                               target="_blank"
                               class="flex-shrink-0">
                                <img width="30" height="30"
                                     src="{{ dynamicAsset(path: 'public/landing-page/assets/img/social/twitter.png') }}"
                                     alt="">
                            </a>

                            <a href="{{ isset($blog->slug) && !request()->filled('preview') ? "https://api.whatsapp.com/send?text=" . urlencode($blog->title . ' ' . route('blog.details', $blog->slug)) : '' }}"
                               target="_blank"
                               class="flex-shrink-0">
                                <img width="30" height="30"
                                     src="{{ dynamicAsset(path: 'public/landing-page/assets/img/social/whatsapp.svg') }}"
                                     alt="">
                            </a>
                            <a href="{{ isset($blog->slug) && !request()->filled('preview') ? "https://www.linkedin.com/shareArticle?mini=true&url=" . urlencode(route('blog.details', $blog->slug)) . "&title=" .  urlencode($blog->title) . "&summary=" . urlencode($blog->description) : '' }}"
                               target="_blank"
                               class="flex-shrink-0">
                                <img width="30" height="30"
                                     src="{{ dynamicAsset(path: 'public/landing-page/assets/img/social/linkedin.png') }}"
                                     alt="">
                            </a>
                        </div>
                    </div>
                    <div class="">
                        <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                            <h3 class="fw-bold mb-0 fs-24 fs-16-mobile">
                                {{ translate('Popular articles') }}
                            </h3>

                            <a href="{{ route('blog.popular-blogs') }}" class="fs-16 fs-12-mobile text--base">
                                {{ translate('See more') }} <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>

                        <div class="row g-4 mb-3">
                            @foreach($popularBlogs as $blog)
                                <div class="col-lg-4 col-md-6">
                                    @include('blog.partials._blog-card', ['blog' => $blog])
                                </div>
                            @endforeach
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
