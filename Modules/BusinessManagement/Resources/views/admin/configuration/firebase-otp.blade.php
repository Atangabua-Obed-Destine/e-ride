@extends('adminmodule::layouts.master')

@section('title', translate('Firebase OTP Setup'))

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('3rd_party') }}</h2>
            @include('businessmanagement::admin.configuration.partials._third_party_inline_menu')
            <div class="main-content">
                <!-- Tab Content -->
                <div class="text--primary-2 mx-4 d-flex flex-wrap justify-content-end align-items-center mb-3"
                     type="button"
                     data-bs-toggle="modal" data-bs-target="#instructionsModal">
                    <strong class="d-flex gap-1 align-items-baseline">{{ translate('How it Works') }}
                        <i class="tio-info-outined"></i></strong>
                </div>
                <div class="card">
                    <div class="card-body">
                        <form action="{{route('admin.business.configuration.third-party.firebase-otp.update')}}"
                              method="post">
                            @csrf
                            @method('PUT')
                            <div class="row align-items-end">
                                <div class="col-md-6">
                                    <div
                                        class="form-control gap-2 align-items-center d-flex justify-content-between mb-4 cmn_focus">
                                        <span class="pr-1 d-flex gap-2 fw-medium align-items-center">
                                            <span class="line--limit-1">
                                                {{ translate('Firebase_OTP_Verification_Status') }}
                                            </span>
                                            <span class="form-label-secondary d-flex align-items-center gap-1"
                                                  data-bs-toggle="tooltip" data-placement="right"
                                                  data-bs-title="{{ translate('If_this_field_is_active_customers_get_the_OTP_through_Firebase.') }}">
                                                  <img
                                                      src="{{ dynamicAsset('public/assets/admin-module/img/svg/info-circle.svg') }}"
                                                      class="svg"
                                                      alt="{{ translate('firebase_otp_verification') }}"> <span
                                                    class="text-danger">*</span>
                                            </span>
                                        </span>
                                        <div class="position-relative">
                                            <div class="position-relative">
                                                <label class="switcher">
                                                    <input type="checkbox" tabindex="1"
                                                           data-id="firebase_otp_verification"
                                                           data-type="toggle"
                                                           data-button-on="{{ translate("Turn On") }}"
                                                           data-button-off="{{ translate("Turn Off") }}"
                                                           data-image-on="{{ dynamicAsset('public/assets/admin-module/img/order-delivery-verification-on.png') }}"
                                                           data-image-off="{{ dynamicAsset('public/assets/admin-module/img/order-delivery-verification-off.png') }}"
                                                           data-title-on="<strong>{{translate('Want to enable Firebase OTP Verification?')}}</strong>"
                                                           data-title-off="<strong>{{translate('Want to disable Firebase OTP Verification?')}}</strong> "
                                                           data-text-on="<p>{{ translate('With Firebase OTP enabled, verification codes will be sent through Firebase.') .' </p>' .'  <p>   <strong>
                                            Note: ' . translate('Enable Firebase OTP means users will not receive verification codes through Email or SMS Although those methods are activated.') .'</strong>'}}</p>"
                                                           data-text-off="<p>{{ translate('If you disable Firebase OTP, users will no longer receive verification codes via Firebase. You must activate Email or SMS verification as an alternative') }}</p>"
                                                           class="switcher_input dynamic-checkbox-toggle"
                                                           {{--                                                           value="1"--}}
                                                           name="firebase_otp_verification_status"
                                                           id="firebase_otp_verification"
                                                        {{$firebaseOtpValues->firstWhere('key_name','firebase_otp_verification_status')?->value == 1 ? "checked" : ""}}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label for="firebase_web_api_key"
                                               class="mb-2">{{ translate('Web_API_key') }}</label>
                                        <input type="text" name="firebase_otp_web_api_key" tabindex="2"
                                               value="{{env('APP_MODE') == 'demo' ? '------' : $firebaseOtpValues->firstWhere('key_name','firebase_otp_web_api_key')?->value ??''}}"
                                               {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}
                                               class="form-control" id="firebase_web_api_key" placeholder="API Key">
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-3 justify-content-end mt-3">
                                <button type="reset" class="btn btn-light" tabindex="3">{{ translate('reset') }}</button>
                                <button type="{{ env('APP_MODE') != 'demo' ? 'submit' : 'button' }}" tabindex="4"
                                        class="btn btn-primary call-demo">{{ translate('save_information') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- End Tab Content -->
            </div>

        </div>
    </div>
    <!-- End Main Content -->

    <div class="modal fade" id="instructionsModal" tabindex="-1" aria-labelledby="instructionsModalLabel" role="dialog"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-end">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center my-5">
                        <img src="{{ dynamicAsset('public/assets/admin-module/img/bell.png') }}">
                    </div>
                    <h5 class="modal-title my-3" id="instructionsModalLabel">{{ translate('Instructions') }}</h5>
                    <p>{{ translate('For configuring OTP in the Firebase, you must create a Firebase project first. If you haven’t created any project for your application yet, please create a project first.') }}
                    </p>
                    <p>{{ translate('Now go to the') }} <a class="primary-color"
                                                           href="https://console.firebase.google.com/"
                                                           target="_blank">{{translate("Firebase console")}}</a> {{ translate('and follow the instructions below') }}
                        -</p>
                    <ol class="d-flex flex-column gap-2">
                        <li>{{ translate('Go to your Firebase project.') }}</li>
                        <li>{{ translate('Navigate to the Build menu from the left sidebar and select Authentication.') }}
                        </li>
                        <li>{{ translate('Get started with the project and go to the Sign-in method tab.') }}</li>
                        <li>{{ translate('From the Sign-in providers section, select the Phone option.') }}</li>
                        <li>{{ translate('Ensure to enable the Phone method and press save.') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

@endsection
