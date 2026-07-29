<!DOCTYPE html>
<html lang="en" dir="{{ session()->get('direction') ?? 'ltr' }}">

<head>
    @php($logo = getSession('header_logo'))
    @php($favicon = getSession('favicon'))
    @php($preloader = getSession('preloader'))
    <!-- Page Title -->
    <title>{{ translate('admin_login') }}</title>
    <!-- Meta Data -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="description" content=""/>
    <meta name="keywords" content=""/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ $favicon ? dynamicStorage('storage/app/public/business/' . $favicon) : '' }}"/>

    <!-- Web Fonts -->
    <!-- Web Fonts -->
    <link href="{{ dynamicAsset('public/assets/admin-module/css/fonts/google.css') }}" rel="stylesheet">

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

</head>

<body>
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
            <div class="login-left d-flex justify-content-center align-items-center bg-center bg-img"
                 data-bg-img="{{dynamicAsset('public/assets/admin-module/img/media/login-new-bg.png')}}">
                <div
                    class="tf-box max-w-100 h-100 rounded-0 d-flex flex-column gap-3 align-items-center justify-content-center p-4 py-5 p-md-5">
                    <img class="login-logo" src="{{ onErrorImage(
                                        $logo,
                                        dynamicStorage('storage/app/public/business') . '/' . $logo,
                                        dynamicAsset('public/assets/admin-module/img/logo.png'),
                                        'business/',
                                    ) }}" alt="Logo">
                    <h2 class="text-center absolute-white fs-30 fs-20-mobile">{{translate("Share the")}}
                        <strong>{{translate("Ride")}}</strong> {{translate("Share the")}} <br>
                        <strong>{{translate("Journey")}}</strong></h2>
                </div>
            </div>
            <div class="login-right-wrap">
                <div class="d-flex justify-content-end mt-2 me-2">
                        <span class="badge badge-success fz-12 opacity-75">
                            {{ translate('Software_Version') }} : {{ env('SOFTWARE_VERSION') }}
                        </span>
                </div>
                <div class="login-right w-100 m-auto px-0 pb-{{ env('APP_MODE') == 'demo' ? '3' : '5' }}">
                    <div class="inner-div px-4">
                        <div class="text-center mb-30">
                            <h2 class="text-uppercase mb-3">{{ businessConfig('business_name')->value ?? null }}</h2>
                            <h3 class="mb-2">{{ translate('Sign_In') }}</h3>
                            <p class="opacity-75">{{ translate('sign_in_to_stay_connected') }}
                            </p>
                        </div>
                        <div class="mb-4">
                            <div class="mb-4">
                                <div class="">
                                    <label for="email" class="mb-2">{{ translate('email') }}</label>
                                    <input type="email" name="email" class="form-control"
                                           placeholder="{{ translate('email') }}" required=""
                                           id="email"
                                           value="{{ request()->cookie('remember_email') }}"
                                    >
                                </div>
                            </div>
                            <div class="mb-4 input-group_tooltip">
                                <label for="password"
                                       class="mb-2">{{ translate('password') }}</label>
                                <input type="password" name="password" id="password"
                                       class="form-control"
                                       placeholder="{{ translate('ex') }}: ********"
                                       autocomplete="current-password"
                                       required>
                                <i id="password-eye"
                                   class="mt-3 bi bi-eye-slash-fill text-primary tooltip-icon"></i>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div class="d-flex gap-1 align-items-center">
                                    <input type="checkbox" name="remember" id="remember" {{ request()->cookie('remember_checked') ? 'checked' : '' }}>
                                    <label class="lh-1"
                                           for="remember">{{ translate('remember_me') }}</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div>
                                @php($recaptcha = businessConfig('recaptcha')?->value)
                                @if(isset($recaptcha) && $recaptcha['status'] == 1)
                                    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                                    <input type="hidden" name="set_default_captcha" id="set_default_captcha_value"
                                           value="0">

                                    <div class="row d-none" id="reload-captcha">
                                        <div class="col-6 pr-0">
                                            <input type="text" class="form-control form-control-lg border-none"
                                                   name="default_captcha_value" value=""
                                                   placeholder="{{translate('Enter captcha')}}" autocomplete="off">
                                        </div>
                                        <div class="col-6 input-icons bg-white rounded cursor-pointer"
                                             data-toggle="tooltip" data-placement="right"
                                             title="{{translate('Click to refresh')}}">
                                            <a class="refresh-recaptcha">
                                                <img src="{{ URL('/admin/auth/code/captcha/1') }}"
                                                     class="input-field h-75 rounded-10 border-bottom-0 width-90-percent"
                                                     id="default_recaptcha_id" alt="{{ translate('recaptcha') }}">
                                                <i class="tio-refresh icon"></i>
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <div class="row p-2">
                                        <div class="col-6 pr-0">
                                            <input type="text" class="form-control form-control-lg border-none"
                                                   name="default_captcha_value" value=""
                                                   placeholder="{{translate('Enter captcha')}}" autocomplete="off">
                                        </div>
                                        <div class="col-6 input-icons bg-white rounded cursor-pointer"
                                             data-toggle="tooltip" data-placement="right"
                                             title="{{translate('Click to refresh')}}">
                                            <a class="refresh-recaptcha">
                                                <img src="{{ URL('/admin/auth/code/captcha/1') }}"
                                                     class="input-field h-75 rounded-10 border-bottom-0 width-90-percent"
                                                     id="default_recaptcha_id" alt="{{ translate('recaptcha') }}">
                                                <i class="tio-refresh icon"></i>
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <button
                            class="btn btn-primary radius-50 text-capitalize fw-semibold w-100 justify-content-center h-45 align-items-center"
                            id="signInBtn"
                            type="submit">{{ translate('sign_in') }}</button>

                    </div>

                </div>

                @if (env('APP_MODE') == 'demo')
                    <div
                        class="login-footer mt-auto d-flex align-items-center justify-content-between mt-3 px-xxl-5 py-xl-3">
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
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

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
        toastr.error('{{ $error }}', Error, {
            CloseButton: true,
            ProgressBar: true
        });
        @endforeach
    </script>
@endif
@if(isset($recaptcha) && $recaptcha['status'] == 1)
    <script src="https://www.google.com/recaptcha/api.js?render={{$recaptcha['site_key']}}"></script>
    <script>
        $(document).ready(function () {
            $('#signInBtn').click(function (e) {

                if ($('#set_default_captcha_value').val() == 1) {
                    $('#login-form').submit();
                    return true;
                }

                e.preventDefault();

                if (typeof grecaptcha === 'undefined') {
                    toastr.error('Invalid recaptcha key provided. Please check the recaptcha configuration.');

                    $('#reload-captcha').removeClass('d-none');
                    $('#set_default_captcha_value').val('1');

                    return;
                }

                grecaptcha.ready(function () {
                    grecaptcha.execute('{{$recaptcha['site_key']}}', {action: 'submit'}).then(function (token) {
                        $('#g-recaptcha-response').value = token;
                        $('#login-form').submit();
                    });
                });

                window.onerror = function (message) {
                    var errorMessage = 'An unexpected error occurred. Please check the recaptcha configuration';
                    if (message.includes('Invalid site key')) {
                        errorMessage = 'Invalid site key provided. Please check the recaptcha configuration.';
                    } else if (message.includes('not loaded in api.js')) {
                        errorMessage = 'reCAPTCHA API could not be loaded. Please check the recaptcha API configuration.';
                    }

                    $('#reload-captcha').removeClass('d-none');
                    $('#set_default_captcha_value').val('1');

                    toastr.error(errorMessage)
                    return true;
                };
            });
        });

    </script>

@endif
<script type="text/javascript">
    $('.refresh-recaptcha').on('click', function () {
        let url = "{{ route('admin.auth.default-captcha',':tmp') }}";
        document.getElementById('default_recaptcha_id').src = url.replace(':tmp', Math.random());
    });
</script>

</body>

</html>
