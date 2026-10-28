<!DOCTYPE html>
<html lang="en" dir="{{ session()->get('direction') ?? 'ltr' }}">

<head>
    @php($favicon = getSession('favicon'))
    @php($preloader = getSession('preloader'))
    @php($businessName = businessConfig('business_name')?->value)
    @php($businessAddr = getSession('business_address'))
    @php($addrParts = $businessAddr ? array_filter(array_map('trim', explode(',', (string) $businessAddr))) : [])
    @php($locality = $addrParts ? end($addrParts) : '')
    <!-- Page Title -->
    <title>{{ translate('admin_login') }}</title>
    <!-- Meta Data -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="description" content=""/>
    <meta name="keywords" content=""/>
    <meta name="robots" content="noindex, nofollow"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ $favicon ? dynamicStorage('storage/app/public/business/' . $favicon) : '' }}"/>

    <!-- Web Fonts -->
    <link href="{{ dynamicAsset('public/assets/admin-module/css/fonts/google.css') }}" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
          rel="stylesheet"/>

    <!-- ======= BEGIN GLOBAL MANDATORY STYLES ======= -->
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap.min.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap-icons.min.css') }}"/>
    <link rel="stylesheet"
          href="{{ dynamicAsset('public/assets/admin-module/plugins/perfect-scrollbar/perfect-scrollbar.min.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/toastr.css') }}"/>
    <!-- ======= END BEGIN GLOBAL MANDATORY STYLES ======= -->

    <!-- ======= MAIN STYLES ======= -->
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/style.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/custom.css') }}"/>
    <!-- ======= END MAIN STYLES ======= -->
    @include('landing-page.layouts.css')
    {{-- E-Ride sign-in redesign. Loaded last so it wins; every rule is scoped to body.eride-login --}}
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/eride-login.css') }}"/>

</head>

<body class="eride-login">
<!-- Offcanval Overlay -->
<div class="offcanvas-overlay"></div>
<!-- Offcanval Overlay -->
<!-- Preloader -->
<div class="preloader" id="preloader">
    @if ($preloader)
        <img class="preloader-img" loading="eager" width="160"
             src="{{ dynamicStorage('storage/app/public/business/' . $preloader) }}" alt="">
    @else
        <div class="spinner-grow" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    @endif
</div>
<div class="resource-loader d-none" id="resource-loader">
    @if ($preloader)
        <img width="160" loading="eager" src="{{ dynamicStorage('storage/app/public/business/' . $preloader) }}"
             alt="">
    @else
        <div class="spinner-grow" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    @endif
</div>
<!-- End Preloader -->
<!-- Login Form -->
<div class="login-form d-block">
    <form action="{{ route('admin.auth.login') }}" enctype="multipart/form-data" method="POST"
          id="login-form">
        @csrf
        <div class="login-wrap">

            {{--
                Left: the brand panel. The uploaded header_logo is drawn for a light
                background, so it would disappear against this dark wash. We use the
                same wordmark lockup the landing-page header uses instead.
            --}}
            <div class="login-left d-flex bg-center bg-img"
                 data-bg-img="{{ dynamicAsset('public/assets/admin-module/img/media/login-new-bg.png') }}">
                <div class="d-flex flex-column justify-content-between gap-5 p-4 p-md-5 w-100">

                    <div class="er-brand">
                        <svg width="52" height="52" viewBox="0 0 48 48" aria-hidden="true" class="er-brand-mark">
                            <circle cx="24" cy="24" r="21" fill="none" stroke="currentColor" stroke-width="5"></circle>
                            <path d="M34 15H21a7 7 0 0 0-7 7v4a7 7 0 0 0 7 7h13" fill="none" stroke="currentColor"
                                  stroke-width="5" stroke-linecap="round"></path>
                            <path d="M20 24h11" fill="none" stroke="currentColor" stroke-width="5"
                                  stroke-linecap="round"></path>
                        </svg>
                        <span class="er-brand-text">
                            <span class="er-brand-name">{{ $businessName ?? 'E-Ride' }}</span>
                            @if($locality)
                                <span class="er-brand-sub">{{ $locality }}</span>
                            @endif
                        </span>
                    </div>

                    <div class="d-flex flex-column gap-4">
                        <h1 class="er-tagline">
                            {{ translate('Share the') }} <strong>{{ translate('Ride') }}</strong>,
                            {{ translate('share the') }} <strong>{{ translate('Journey') }}</strong>
                        </h1>
                        <ul class="er-points">
                            <li>
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="3"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                {{ translate('Rides, parcels and scheduled trips in one place') }}
                            </li>
                            <li>
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="3"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                {{ translate('Live trips, drivers and earnings at a glance') }}
                            </li>
                            <li>
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="3"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                {{ translate('Zones, fares and promotions you control') }}
                            </li>
                        </ul>
                    </div>

                    <p class="mb-0 small text-white-50">
                        &copy; {{ date('Y') }} {{ $businessName ?? 'E-Ride' }}. {{ translate('All rights reserved') }}.
                    </p>
                </div>
            </div>

            {{-- Right: the form --}}
            <div class="login-right-wrap">
                <div class="d-flex justify-content-end mt-3 me-3">
                    <span class="er-version">
                        {{ translate('Software_Version') }} : {{ env('SOFTWARE_VERSION') }}
                    </span>
                </div>
                <div class="login-right w-100 m-auto px-0 py-4">
                    <div class="inner-div px-4">

                        {{-- style.css hides .login-left below 576px, so phones get the lockup here instead --}}
                        <div class="er-brand er-brand-sm d-sm-none mb-4">
                            <svg width="40" height="40" viewBox="0 0 48 48" aria-hidden="true" class="er-brand-mark">
                                <circle cx="24" cy="24" r="21" fill="none" stroke="currentColor" stroke-width="5"></circle>
                                <path d="M34 15H21a7 7 0 0 0-7 7v4a7 7 0 0 0 7 7h13" fill="none" stroke="currentColor"
                                      stroke-width="5" stroke-linecap="round"></path>
                                <path d="M20 24h11" fill="none" stroke="currentColor" stroke-width="5"
                                      stroke-linecap="round"></path>
                            </svg>
                            <span class="er-brand-text">
                                <span class="er-brand-name">{{ $businessName ?? 'E-Ride' }}</span>
                                @if($locality)
                                    <span class="er-brand-sub">{{ $locality }}</span>
                                @endif
                            </span>
                        </div>

                        <span class="er-eyebrow">{{ translate('Admin panel') }}</span>
                        <h2 class="er-title">{{ translate('Sign_In') }}</h2>
                        <p class="er-sub mb-4">{{ translate('sign_in_to_stay_connected') }}</p>

                        <div class="er-field">
                            <label for="email">{{ translate('email') }}</label>
                            <input type="email" name="email" class="form-control"
                                   placeholder="{{ translate('email') }}" required
                                   id="email" autocomplete="username"
                                   value="{{ request()->cookie('remember_email') }}">
                        </div>

                        <div class="er-field input-group_tooltip">
                            <label for="password">{{ translate('password') }}</label>
                            <input type="password" name="password" id="password"
                                   class="form-control"
                                   placeholder="{{ translate('ex') }}: ********"
                                   autocomplete="current-password" required>
                            <i id="password-eye" class="bi bi-eye-slash-fill tooltip-icon"></i>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <label class="er-remember" for="remember">
                                <input type="checkbox" name="remember" id="remember"
                                       {{ request()->cookie('remember_checked') ? 'checked' : '' }}>
                                {{ translate('remember_me') }}
                            </label>
                        </div>

                        <button
                            class="btn btn-primary text-capitalize fw-semibold w-100 d-inline-flex justify-content-center align-items-center"
                            id="signInBtn"
                            type="submit">{{ translate('sign_in') }}</button>

                        <p class="er-note">
                            {{ translate('This is a restricted area. All sign-in attempts are recorded.') }}
                        </p>

                    </div>
                </div>

                @if (env('APP_MODE') == 'demo')
                    <div
                        class="login-footer mt-auto d-flex align-items-center justify-content-between px-xxl-5 py-xl-3">
                        <div>
                            <div>{{ translate('email') }} : admin@admin.com</div>
                            <div>{{ translate('password') }} : 12345678</div>
                        </div>
                        <button type="button" class="btn btn-primary login-copy"
                                onclick="copyCredentials()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                 class="bi bi-copy" viewBox="0 0 16 16">
                                <path fill-rule="evenodd"
                                      d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z"/>
                            </svg>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </form>
</div>
<!-- End Login Form -->

<!-- ======= BEGIN GLOBAL MANDATORY SCRIPTS ======= -->
<script src="{{ dynamicAsset('public/assets/admin-module/js/jquery-3.6.0.min.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/main.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/toastr.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/login.js') }}"></script>

<!-- ======= BEGIN GLOBAL MANDATORY SCRIPTS ======= -->

{!! Toastr::message() !!}

@if (env('APP_MODE') == 'demo')
    <script>
        "use strict";

        function copyCredentials() {
            document.getElementById('email').value = 'admin@admin.com';
            document.getElementById('password').value = '12345678'
            toastr.success('Copied successfully!', 'Success!', {
                CloseButton: true,
                ProgressBar: true
            });
        }
    </script>
@endif

@if ($errors->any())
    <script>
        "use strict";
        @foreach ($errors->all() as $error)
        toastr.error('{{ $error }}', '{{ translate('Error') }}', {
            CloseButton: true,
            ProgressBar: true
        });
        @endforeach
    </script>
@endif

</body>

</html>
