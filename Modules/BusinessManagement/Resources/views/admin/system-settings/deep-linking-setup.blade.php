@extends('adminmodule::layouts.master')

@section('title', translate('deep_linking_setup'))

@section('content')
    <!-- Main Content -->
    <div class="content container-fluid">
        <!-- Page Title -->
        <h2 class="fs-22 mb-4 text-capitalize">{{ translate('system_settings') }}</h2>
        <!-- End Page Title -->

        <!-- Inlile Menu -->
        <div class="mb-4">
            @include('businessmanagement::admin.system-settings.partials._system-settings-inline')
        </div>
        <!-- End Inlile Menu -->

        <form action="{{route('admin.business.deep-linking.update')}}" class="submit-by-ajax" method="post" id="appVersion">
            @csrf
            <span class="my-3 bg-warning rounded bg-warning-10 d-flex align-items-centre gap-2 py-2 px-3">
                 <i class="bi bi-info-circle-fill text-warning mt-1"></i>
                {{ translate('On this page you can configure app deep linking and store redirect settings for android and ios.') }}
                {{ translate('Please provide correct app identifiers and store links for proper redirection. ') }}
            </span>
            @foreach(['customer_app', 'driver_app'] as $value)
                    <?php
                        $data = businessConfig(key: $value . '_deep_linking_data', settingsType: DEEP_LINKING_SETTINGS)?->value;
                    ?>
                <div class="card border-0 mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-column gap-3">
                            <div class="mb-2">
                                <h5 class="fw-semibold text-capitalize mb-2">
                                    {{ translate('Deep Linking Setup for ' . $value) }}</h5>
                                <div class="fs-12">
                                    {{ translate('Configure your android and ios app identifiers and store redirect urls here.') }}
                                </div>
                            </div>
                            <div class="card border-0">
                                <div class="card-body">
                                    <h5 class="fw-semibold d-flex align-items-center gap-2 mb-4">
                                        <img src="{{ dynamicAsset('public/assets/admin-module/img/svg/android.svg') }}" class="svg"
                                             alt="{{ translate('Android logo') }}">
                                        {{ translate('For Android') }}
                                    </h5>
                                    <div class="row gap-md-0 gap-4">
                                        <div class="col-md-6">
                                            <div class="">
                                                <h6 class="fw-semibold text-capitalize mb-2">
                                                    {{ translate('package_name') }}
                                                    <span class="text-danger">*</span>
                                                </h6>
                                                <div class="fs-12 mb-2">
                                                    {{ translate("Enter your android package name to identify and open the correct app via deep links") }}
                                                </div>
                                                <input type="text" name="{{ $value }}[package_name]"
                                                       value="{{ env('APP_MODE')!='demo'? old( $value . '[package_name]', $data['package_name'] ?? '') : '-------'}}"
                                                       id="{{ $value }}-package_name"
                                                       class="form-control" placeholder="{{ translate('Ex: package.name') }}"
                                                       tabindex="1"
                                                    {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}
                                                >
                                                <span class="error-text" data-error="{{ $value }}.package_name"></span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="">
                                                <h6 class="fw-semibold text-capitalize mb-2">
                                                    {{ translate('sha256_fingerprint') }}
                                                    <span class="text-danger">*</span>
                                                </h6>
                                                <div class="fs-12 mb-2">
                                                    {{ translate('Provide the android app sha256 fingerprint to verify and secure deep link access') }}
                                                </div>
                                                <input type="text" name="{{ $value }}[sha256_fingerprint]"
                                                       value="{{ env('APP_MODE')!='demo'? old( $value . '[sha256_fingerprint]', $data['sha256_fingerprint'] ?? '') : '-------'}}"
                                                       id="{{ $value }}-sha256_fingerprint"
                                                       class="form-control"
                                                       placeholder="{{ translate('Enter sha256 fingerprint') }}"  tabindex="2"
                                                    {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}
                                                >
                                                <span class="error-text" data-error="{{ $value }}.sha256_fingerprint"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card border-0">
                                <div class="card-body">
                                    <h5 class="fw-semibold d-flex align-items-center gap-2 mb-4">
                                        <img src="{{ dynamicAsset('public/assets/admin-module/img/svg/apple.svg') }}" class="svg"
                                             alt="{{ translate('iOS logo') }}">
                                        {{ translate('For iOS') }}
                                    </h5>
                                    <div class="row gap-md-0 gap-4">
                                        <div class="col-md-6">
                                            <div class="">
                                                <h6 class="fw-semibold text-capitalize mb-2">
                                                    {{ translate('bundle_id') }}
                                                    <span class="text-danger">*</span>
                                                </h6>
                                                <div class="fs-12 mb-2">
                                                    {{ translate("Enter your ios bundle id to allow deep links to open the app correctly on ios devices.") }}
                                                </div>
                                                <input type="text" name="{{ $value }}[bundle_id]"
                                                       value="{{ env('APP_MODE')!='demo'? old( $value . '[bundle_id]', $data['bundle_id'] ?? '') : '-------'}}"
                                                       id="{{ $value }}-bundle_id"
                                                       class="form-control" placeholder="{{ translate('Ex: bundle.id') }}"
                                                       tabindex="3"
                                                    {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}
                                                >
                                                <span class="error-text" data-error="{{ $value }}.bundle_id"></span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="">
                                                <h6 class="fw-semibold text-capitalize mb-2">
                                                    {{ translate('team_id') }}
                                                    <span class="text-danger">*</span>
                                                </h6>
                                                <div class="fs-12 mb-2">
                                                    {{ translate('Provide your apple developer team id to verify app ownership and enable ios deep linking.') }}
                                                </div>
                                                <input type="text" name="{{ $value }}[team_id]"
                                                       value="{{ env('APP_MODE')!='demo'? old( $value . '[team_id]', $data['team_id'] ?? '') : '-------'}}"
                                                       id="{{ $value }}-team_id"
                                                       class="form-control"
                                                       placeholder="{{ translate('Enter team Id') }}"  tabindex="4"
                                                    {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}
                                                >
                                                <span class="error-text" data-error="{{ $value }}.team_id"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="d-flex justify-content-end mt-4">
                <button type="{{env('APP_MODE')!='demo'?'submit':'button'}}"
                        class="btn btn-primary text-uppercase btn-lg call-demo" tabindex="9">{{ translate('save') }}</button>
            </div>
        </form>
    </div>
    <!-- End Main Content -->
@endsection
