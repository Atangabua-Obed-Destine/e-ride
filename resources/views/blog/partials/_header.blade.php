<section class="page-header bg__img px-3 py-4 py-sm-5 mb-3 mb-sm-30"
         data-img="{{ dynamicAsset(path: 'public/landing-page/assets/img/blog/blog-bg.png') }}">
    <h1 class="text-white fs-24 fs-16-mobile">{{ $blogPageTitle ?? translate('DriveMond Blog') }}</h1>
        <p class="mt-2 fs-12-mobile lh-base mb-0">
            {{ $blogPageSubtitle ?? translate('Discover how DriveMond is redefining transportation and delivery through technology, efficiency, and user-centric design') }}
        </p>
</section>
