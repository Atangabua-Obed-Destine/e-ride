@extends('adminmodule::layouts.master')

@section('title', translate('notification'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ dynamicAsset('public/landing-page/assets/css/owl.min.css') }}"/>
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('notifications') }}</h2>

            <div class="mb-4 overflow-x-auto">
                <ul class="nav nav--tabs_two">
                    <li class="nav-item">
                        <a href="{{route('admin.business.configuration.notification.index', ['type' => 'regular-trip'])}}"
                           class="nav-link text-capitalize {{Request::is('admin/business/configuration/notification/*') && !Request::is('admin/business/configuration/notification/firebase-configuration') ? "active":""}}">{{ translate('notification_message') }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{route('admin.business.configuration.notification.firebase-configuration')}}"
                           class="nav-link text-capitalize {{Request::is('admin/business/configuration/notification/firebase-configuration')? "active":""}}">{{ translate('firebase_configuration') }}</a>
                    </li>
                </ul>
            </div>

            <div class="card border-0 mb-3">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div class="">
                        <h5 class="text-capitalize mb-2">{{ translate('push_notification_setup') }}</h5>
                        <div
                            class="fs-12">{{ translate('Here you can set sending notification On/Off based on the case.') }}</div>
                    </div>

                    <button
                        class="btn border rounded-pill bg-primary-light border-primary-light btn-link text-decoration-none"
                        data-bs-toggle="modal" data-bs-target="#how_it_work">
                        <i class="bi bi-question-circle"></i>
                        {{ translate('how_it_work') }}
                    </button>
                </div>

                <div class="card-body p-30">
                    <form action="{{ route('admin.business.configuration.notification.store') }}" method="post"
                          id="server_key_form">
                        @csrf
                        <div class="row">
                            <div class="col-12 mb-4">
                                <label for="server_key" class="mb-3 fw-medium text-dark">
                                    {{ translate('service_account_content') }}
                                    <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('Select and copy all the service file content and add here') }}"></i>
                                </label>
                                <textarea name="server_key" id="server_key" placeholder="Type Here..."
                                          class="form-control color-border-focus {{ $settings?->firstWhere('key_name', SERVER_KEY)?->value ? 'color-border' : '' }}"
                                          cols="30" rows="10"
                                          required {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}>{{ env("APP_MODE") != "demo" ? $settings?->firstWhere('key_name', SERVER_KEY)?->value : "..................." }}</textarea>
                            </div>
                            <div class="col-md-12 mb-4">
                                <label for="firebase_apiKey" class="form-label">
                                    {{ translate('Api_Key') }}
                                </label>
                                <input type="text" name="api_key" id="firebase_apiKey" class="form-control"
                                       placeholder="{{ translate('Ex') }}: {{ ('AIzaSyAhMz6lR******Phf4KE9raM87') }}"
                                       value="{{ env("APP_MODE") != "demo" ? $settings?->firstWhere('key_name', 'api_key')?->value : "..................." }}" {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}>
                            </div>
                            <div class="col-md-4 mb-4">
                                <label for="firebase_authDomain" class="form-label">
                                    {{ translate('Auth_Domain') }}
                                </label>
                                <input type="text" name="auth_domain" id="firebase_authDomain" class="form-control"
                                       placeholder="{{ translate('Ex') }}: {{ ('your-domain-2050.firebaseapp.com') }}"
                                       value="{{ env("APP_MODE") != "demo" ? $settings?->firstWhere('key_name', 'auth_domain')?->value : "..................." }}" {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}>
                            </div>
                            <div class="col-md-4 mb-4">
                                <label for="firebase_project_id" class="form-label">
                                    {{ translate('Project_ID') }}
                                </label>
                                <input type="text" name="project_id" id="firebase_project_id" class="form-control"
                                       placeholder="{{ translate('Ex') }}: {{ ('my-app-12345') }}"
                                       value="{{ env("APP_MODE") != "demo" ? $settings?->firstWhere('key_name', 'project_id')?->value : "..................." }}" {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}>
                            </div>
                            <div class="col-md-4 mb-4">
                                <label for="firebase_storageBucket" class="form-label">
                                    {{ translate('Storage_Bucket') }}
                                </label>
                                <input type="text" name="storage_bucket" id="firebase_storageBucket" class="form-control"
                                       placeholder="{{ translate('Ex') }}: {{ ('****-2050.appspot.com') }}"
                                       value="{{ env("APP_MODE") != "demo" ? $settings?->firstWhere('key_name', 'storage_bucket')?->value : "..................." }}" {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}>
                            </div>
                            <div class="col-md-4 mb-4">
                                <label for="firebase_messagingSenderId" class="form-label">
                                    {{ translate('Messaging_Sender_ID') }}
                                </label>
                                <input type="text" name="messaging_sender_id" id="firebase_messagingSenderId"
                                       placeholder="{{ translate('Ex') }}: {{ ('54*****103') }}"
                                       class="form-control"
                                       value="{{ env("APP_MODE") != "demo" ? $settings?->firstWhere('key_name', 'messaging_sender_id')?->value : "..................." }}"
                                    {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}>
                            </div>
                            <div class="col-md-4 mb-4">
                                <label for="firebase_appId" class="form-label">
                                    {{ translate('App_ID') }}
                                </label>
                                <input type="text" name="app_id" id="firebase_appId" class="form-control"
                                       placeholder="{{ translate('Ex') }}: {{ ('1:54138740***********') }}"
                                       value="{{ env("APP_MODE") != "demo" ? $settings?->firstWhere('key_name', 'app_id')?->value : "..................." }}" {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}>
                            </div>
                            <div class="col-md-4 mb-4">
                                <label for="firebase_measurementId" class="form-label">
                                    {{ translate('Measurement_ID') }}
                                </label>
                                <input type="text" name="measurement_id" id="firebase_measurementId" class="form-control"
                                       placeholder="{{ translate('Ex') }}: {{ ('LX3X2M******') }}"
                                       value="{{ env("APP_MODE") != "demo" ? $settings?->firstWhere('key_name', 'measurement_id')?->value : "..................." }}" {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-3">
                                    <button class="btn btn-primary cmn_focus text-uppercase call-demo"
                                            type="{{ env('APP_MODE') != 'demo' ? 'submit' : 'button' }}">{{ translate('submit') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
    <!-- End Main Content -->
    <div class="modal fade" id="how_it_work">
        <div class="modal-dialog status-warning-modal">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-5 pt-0">
                    <div class="single-item-slider owl-carousel">
                        <div class="item">
                            <div class="mb-20">
                                <div class="text-center">
                                    <img src="{{dynamicAsset('public/assets/admin-module/img/firebase/slide-1.png')}}" alt="" class="mb-20">
                                    <h5 class="modal-title">{{translate('Go to Firebase Console')}}</h5>
                                </div>
                                <ul>
                                    <li>
                                        {{translate('Open your web browser and go to the Firebase Console')}}
                                        <a href="https://console.firebase.google.com" class="text-underline primary-color">
                                            https://console.firebase.google.com/
                                        </a>
                                    </li>
                                    <li>
                                        {{translate("Select the project for which you want to configure FCM from the Firebase Console dashboard.")}}
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="item">
                            <div class="mb-20">
                                <div class="text-center">
                                    <img src="{{dynamicAsset('public/assets/admin-module/img/firebase/slide-2.png')}}" alt="" class="mb-20">
                                    <h5 class="modal-title">{{translate('Navigate to Project Settings')}}</h5>
                                </div>
                                <ul>
                                    <li>
                                        {{translate('In the left-hand menu, click on the "Settings" gear icon, and then select "Project settings" from the dropdown.')}}
                                    </li>
                                    <li>
                                        {{translate('In the Project settings page, click on the "Cloud Messaging" tab from the top menu.')}}
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="item">
                            <div class="mb-20">
                                <div class="text-center">
                                    <img src="{{dynamicAsset('public/assets/admin-module/img/firebase/slide-3.png')}}" alt="" class="mb-20">
                                    <h5 class="modal-title">{{translate('Obtain All The Information Asked!')}}</h5>
                                </div>
                                <ul>
                                    <li>
                                        {{translate('In the Firebase Project settings page, click on the "General" tab from the top menu.')}}
                                    </li>
                                    <li>
                                        {{translate('Under the "Your apps" section, click on the "Web" app for which you want to configure FCM.')}}
                                    </li>
                                    <li>
                                        {{translate('Then Obtain API Key, FCM Project ID, Auth Domain, Storage Bucket, Messaging Sender ID.')}}
                                    </li>
                                </ul>
                                <p>
                                    {{translate('Note: Please make sure to use the obtained information securely and in accordance with Firebase and FCM documentation, terms of service, and any applicable laws and regulations.')}}
                                </p>
                                <div class="btn-wrap">
                                    <button type="submit" class="btn btn-primary justify-content-center py-2 w-100" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#how_it_work">{{translate('Got It')}}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-center">
                        <div class="slide-counter"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ dynamicAsset('public/landing-page/assets/js/owl.min.js') }}"></script>

    <script>
        function initializeSlider() {

            let owl = $(".single-item-slider");
            owl.owlCarousel({
                autoplay: false,
                items: 1,
                onInitialized: counter,
                onTranslated: counter,
                autoHeight: true,
                dots: true,
                // rtl: true,
            });

            function counter(event) {
                let element = event.target;
                let items = event.item.count;
                let item = event.item.index + 1;

                if (item > items) {
                    item = item - items;
                }
                $(".slide-counter").html(+item + "/" + items);
            }
        }

        initializeSlider();
    </script>

    <script>
        "use strict";

        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan


        $('#notification_setup_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });

        $('#server_key_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });

        $('.switcher_input').on('click', function () {
            updateSettings(this)
        })

        function updateSettings(obj) {
            $.ajax({
                url: '{{ route('admin.business.configuration.notification.notification-settings') }}',
                _method: 'PUT',
                data: {
                    id: $(obj).data('id'),
                    type: $(obj).data('type'),
                    status: ($(obj).prop("checked")) === true ? 1 : 0
                },
                beforeSend: function () {
                    $('.preloader').removeClass('d-none');
                },
                success: function (d) {
                    $('.preloader').addClass('d-none');
                    toastr.success("{{ translate('status_successfully_changed') }}");
                },
                error: function () {
                    $('.preloader').addClass('d-none');
                    toastr.error("{{ translate('status_change_failed') }}");

                }
            });
        }
    </script>
@endpush
