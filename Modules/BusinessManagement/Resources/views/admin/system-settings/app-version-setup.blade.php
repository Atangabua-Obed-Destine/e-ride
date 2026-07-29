@extends('adminmodule::layouts.master')

@section('title', translate('app_version_Setup'))

@push('css_or_js')
@endpush

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

        <form action="{{route('admin.business.app-version-setup.update')}}" method="post" id="appVersion">
            @csrf
            <div class="card border-0 mb-4">
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        <div class="mb-2">
                            <h5 class="fw-semibold text-capitalize mb-2">
                                {{ translate('User_App_Version_Control') }}</h5>
                            <div class="fs-12">
                                {{ translate('Setup the minimum App versions in which the system will be compatible') }}
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
                                                {{ translate('Minimum_Customer_App_Version') }}
                                            </h6>
                                            <div class="fs-12 mb-2">
                                                {{ translate("Set minimum Android app version. If user don't have, they'll be requested a force update.") }}
                                            </div>
                                            <input type="number" name="minimum_customer_app_version_for_android"
                                                   step="0.001"
                                                   value="{{ env('APP_MODE')!='demo'?(($customerAppVersionControlForAndroid && array_key_exists('minimum_app_version',$customerAppVersionControlForAndroid)) ? $customerAppVersionControlForAndroid['minimum_app_version'] : old('minimum_customer_app_version_for_android')) : ''}}"
                                                   id="minimum_customer_app_version_for_android"
                                                   class="form-control" placeholder="{{ translate('Ex: 14.2') }}"
                                                   required tabindex="1">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="">
                                            <h6 class="fw-semibold text-capitalize mb-2">
                                                {{ translate('Download_URL_For_Customer_App') }}
                                            </h6>
                                            <div class="fs-12 mb-2">
                                                {{ translate('Enter Android app download URL that will redirect user when they agree to update.') }}
                                            </div>
                                            <input type="url" name="customer_app_url_for_android"
                                                   value="{{ env('APP_MODE')!='demo'?(($customerAppVersionControlForAndroid && array_key_exists('app_url',$customerAppVersionControlForAndroid)) ? $customerAppVersionControlForAndroid['app_url'] : old('customer_app_url_for_android')) : ''}}"
                                                   id="customer_app_url_for_android"
                                                   class="form-control"
                                                   placeholder="{{ translate('Enter_download_link') }}" required tabindex="2">
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
                                                {{ translate('Minimum_Customer_App_Version') }}
                                            </h6>
                                            <div class="fs-12 mb-2">
                                                {{ translate("Set minimum iOS app version. If user don't have, they'll be requested a force update.") }}
                                            </div>
                                            <input type="number" name="minimum_customer_app_version_for_ios" step=".001"
                                                   value="{{ env('APP_MODE')!='demo'?(($customerAppVersionControlForIos && array_key_exists('minimum_app_version',$customerAppVersionControlForIos)) ? $customerAppVersionControlForIos['minimum_app_version'] : old('minimum_customer_app_version_for_ios')) : ''}}"
                                                   id="minimum_customer_app_version_for_ios"
                                                   class="form-control" placeholder="{{ translate('Ex: 14.2') }}"
                                                   required tabindex="3">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="">
                                            <h6 class="fw-semibold text-capitalize mb-2">
                                                {{ translate('Download_URL_For_Customer_App') }}
                                            </h6>
                                            <div class="fs-12 mb-2">
                                                {{ translate('Enter iOS app download URL that will redirect user when they agree to update.') }}
                                            </div>
                                            <input type="url" name="customer_app_url_for_ios"
                                                   value="{{ env('APP_MODE')!='demo'?(($customerAppVersionControlForIos && array_key_exists('app_url',$customerAppVersionControlForIos)) ? $customerAppVersionControlForIos['app_url'] : old('customer_app_url_for_ios')) : ''}}"
                                                   id="customer_app_url_for_ios"
                                                   class="form-control"
                                                   placeholder="{{ translate('Enter_download_link') }}" required tabindex="4">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        <div class="mb-2">
                            <h5 class="fw-semibold text-capitalize mb-2">
                                {{ translate('Driver_App_Version_Control') }}</h5>
                            <div class="fs-12">
                                {{ translate('Setup the minimum App versions in which the system will be compatible') }}
                            </div>
                        </div>
                        <div class="card border-0">
                            <div class="card-body">
                                <h5 class="fw-semibold d-flex align-items-center gap-2 mb-4">
                                    <img src="{{ dynamicAsset('public/assets/admin-module/img/svg/android.svg') }}" class="svg"
                                         alt="{{ translate('Android logo') }}">
                                    {{ translate('For_Android') }}
                                </h5>
                                <div class="row gap-md-0 gap-4">
                                    <div class="col-md-6">
                                        <div class="">
                                            <h6 class="fw-semibold text-capitalize mb-2">
                                                {{ translate('Minimum_Driver_App_Version') }}
                                            </h6>
                                            <div class="fs-12 mb-2">
                                                {{ translate("Set minimum Android app version. If user don't have, they'll be requested a force update.") }}
                                            </div>
                                            <input type="number" name="minimum_driver_app_version_for_android"
                                                   step=".001"
                                                   value="{{ env('APP_MODE')!='demo'?(($driverAppVersionControlForAndroid && array_key_exists('minimum_app_version',$driverAppVersionControlForAndroid)) ? $driverAppVersionControlForAndroid['minimum_app_version'] : old('minimum_driver_app_version_for_android')) : ''}}"
                                                   id="minimum_driver_app_version_for_android"
                                                   class="form-control" placeholder="{{ translate('Ex: 14.2') }}"
                                                   required tabindex="5">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="">
                                            <h6 class="fw-semibold text-capitalize mb-2">
                                                {{ translate('Download_URL_For_Driver_App') }}
                                            </h6>
                                            <div class="fs-12 mb-2">
                                                {{ translate('Enter Android app download URL that will redirect user when they agree to update.') }}
                                            </div>
                                            <input type="url" name="driver_app_url_for_android"
                                                   value="{{ env('APP_MODE')!='demo'?(($driverAppVersionControlForAndroid && array_key_exists('app_url',$driverAppVersionControlForAndroid)) ? $driverAppVersionControlForAndroid['app_url'] : old('driver_app_url_for_android')) : ''}}"
                                                   id="driver_app_url_for_android"
                                                   class="form-control"
                                                   placeholder="{{ translate('Enter_download_link') }}" required tabindex="6">
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
                                                {{ translate('Minimum_Driver_App_Version') }}
                                            </h6>
                                            <div class="fs-12 mb-2">
                                                {{ translate("Set minimum iOS app version. If user don't have, they'll be requested a force update.") }}
                                            </div>
                                            <input type="number" name="minimum_driver_app_version_for_ios" step=".001"
                                                   value="{{ env('APP_MODE')!='demo'?(($driverAppVersionControlForIos && array_key_exists('minimum_app_version',$driverAppVersionControlForIos)) ? $driverAppVersionControlForIos['minimum_app_version'] : old('minimum_driver_app_version_for_ios')) : ''}}"
                                                   id="minimum_driver_app_version_for_ios"
                                                   class="form-control" placeholder="{{ translate('Ex: 14.2') }}"
                                                   required tabindex="7">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="">
                                            <h6 class="fw-semibold text-capitalize mb-2">
                                                {{ translate('Download_URL_For_Driver_App') }}
                                            </h6>
                                            <div class="fs-12 mb-2">
                                                {{ translate('Enter iOS app download URL that will redirect user when they agree to update.') }}
                                            </div>
                                            <input type="url" name="driver_app_url_for_ios"
                                                   value="{{ env('APP_MODE')!='demo'?(($driverAppVersionControlForIos && array_key_exists('app_url',$driverAppVersionControlForIos)) ? $driverAppVersionControlForIos['app_url'] : old('driver_app_url_for_ios')) : ''}}"
                                                   id="driverAppUrlForIos"
                                                   class="form-control"
                                                   placeholder="{{ translate('Enter_download_link') }}" required tabindex="8">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="{{env('APP_MODE')!='demo'?'submit':'button'}}"
                        class="btn btn-primary text-uppercase btn-lg call-demo" tabindex="9">{{ translate('save') }}</button>
            </div>
        </form>
    </div>
    <!-- End Main Content -->

@endsection

@push('script')
@endpush
