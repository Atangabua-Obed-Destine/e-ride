<!DOCTYPE html>
<html lang="en">
@php($preloader = getSession('preloader'))
@php($favicon = getSession('favicon'))
<head>
    <meta charset="UTF-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>@yield('title')</title>
    @stack('seo')

    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/landing-page/assets/css/bootstrap-icons.min.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/landing-page/assets/css/bootstrap.min.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/landing-page/assets/css/animate.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/landing-page/assets/css/line-awesome.min.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/landing-page/assets/css/odometer.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/landing-page/assets/css/owl.min.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/landing-page/assets/css/main.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/toastr.css') }}"/>
    @include('landing-page.layouts.css')
    <link rel="shortcut icon"
          href="{{ $favicon ? dynamicStorage(path: "storage/app/public/business/".$favicon) : dynamicAsset(path: 'public/landing-page/assets/img/favicon.png') }}"
          type="image/x-icon"/>
</head>

<body>

<div class="preloader" id="preloader">
    @if ($preloader)
        <img class="preloader-img" width="160" loading="eager"
             src="{{ $preloader ? dynamicStorage(path: 'storage/app/public/business/' . $preloader) : '' }}" alt="">
    @else
        <div class="spinner-grow" role="status">
            <span class="visually-hidden">{{ translate('Loading...') }}</span>
        </div>
    @endif
</div>

@include('landing-page.partials._header')

@yield('content')

<!-- Footer Section Start -->
@include('landing-page.partials._footer')
<!-- Footer Section End -->
<script src="{{ dynamicAsset(path: 'public/landing-page/assets/js/jquery-3.6.0.min.js') }}"></script>
<script src="{{ dynamicAsset(path: 'public/landing-page/assets/js/bootstrap.min.js') }}"></script>
<script src="{{ dynamicAsset(path: 'public/landing-page/assets/js/viewport.jquery.js') }}"></script>
<script src="{{ dynamicAsset(path: 'public/landing-page/assets/js/wow.min.js') }}"></script>
<script src="{{ dynamicAsset(path: 'public/landing-page/assets/js/owl.min.js') }}"></script>
<script src="{{ dynamicAsset(path: 'public/landing-page/assets/js/main.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/toastr.js') }}"></script>

{!! Toastr::message() !!}
@if ($errors->any())
    <script>
        "use strict";
        @foreach ($errors->all() as $error)
        toastr.error('{{ $error }}', {
            CloseButton: true,
            ProgressBar: true,
        });
        @endforeach
    </script>
@endif
@stack('script')
</body>

</html>
