@extends('adminmodule::layouts.master')

@section('content')
    <!-- login setup Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h4 class="mb-4 fs-20 pb-xxl-1">{{ translate('Login Settings') }}</h4>

            <!---- login setup ---->
            <div class="">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card border-0">
                            <div class="card-body">
                                <div class="mb-20">
                                    <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('Login Setup') }}</h4>
                                    <p class="fs-14 mb-0">{{ translate('The option you select customer will have the to option to login') }}</p>
                                </div>
                                <div class="p-xxl-20 p-3 bg-F6F6F6 rounded mb-20">
                                    <div class="mb-xxl-20 mb-3">
                                        <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('Customer Login Options') }}</h4>
                                        <p class="fs-14 mb-0">{{ translate('Based on your selection, customers will have the options to login customer app') }}</p>
                                    </div>
                                    <div class="bg-white rounded p-xl-3 p-2">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="custom-checkbox d-flex align-items-start gap-2">
                                                    <input type="checkbox" name="manual_login" id="manual_login" class="input-size-20" checked>
                                                    <label for="manual_login" class="mb-0">
                                                        <h5 class="fs-14 mb-1">
                                                            {{ translate('Manual Login') }}
                                                             <img src="{{ asset('public/assets/admin-module/img/info-warning-icon.png') }}" class="cursor-pointer"
                                                                data-bs-toggle="tooltip" data-bs-placement="right"
                                                                data-bs-title="{{ translate('Enter the amount you want to refund to the customer') }}">
                                                        </h5>
                                                        <p class="fs-12 m-0 opacity-75">
                                                            {{ translate('Customers will get the option
                                                            to create an account and log
                                                            in using the necessary
                                                            credentials & password in the
                                                            app') }}
                                                        </p>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="custom-checkbox d-flex align-items-start gap-2">
                                                    <input type="checkbox" name="otp_login" id="otp_login" class="input-size-20" checked>
                                                    <label for="otp_login" class="mb-0">
                                                        <h5 class="fs-14 mb-1">{{ translate('OTP Login') }}</h5>
                                                        <p class="fs-12 m-0 opacity-75">
                                                            {{ translate('With OTP Login, customers
                                                            can log in using their phone
                                                            number without password. To enable this feature') }}
                                                            <a href="#0" class="text-decoration-underline text-info">{{ translate('Configure SMS Setup') }}</a>
                                                            {{ translate('Here.') }}
                                                        </p>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-xxl-20 p-3 bg-F6F6F6 rounded">
                                    <div class="mb-xxl-20 mb-3">
                                        <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('Driver Login Options') }}</h4>
                                        <p class="fs-14 mb-0">{{ translate('Based on your selection, drivers will have the options to login customer app') }}</p>
                                    </div>
                                    <div class="bg-white rounded p-xl-3 p-2">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="custom-checkbox d-flex align-items-start gap-2">
                                                    <input type="checkbox" name="manual_login_driver" id="manual_login_driver" class="input-size-20" checked>
                                                    <label for="manual_login_driver" class="mb-0">
                                                        <h5 class="fs-14 mb-1">{{ translate('Manual Login') }}</h5>
                                                        <p class="fs-12 m-0 opacity-75">
                                                            {{ translate('Customers will get the option
                                                            to create an account and log
                                                            in using the necessary
                                                            credentials & password in the
                                                            app') }}
                                                        </p>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="custom-checkbox d-flex align-items-start gap-2">
                                                    <input type="checkbox" name="otp_login_driver" id="otp_login_driver" class="input-size-20" checked>
                                                    <label for="otp_login_driver" class="mb-0">
                                                        <h5 class="fs-14 mb-1">{{ translate('OTP Login') }}</h5>
                                                        <p class="fs-12 m-0 opacity-75">
                                                            {{ translate('With OTP Login, driver
                                                            can log in using their phone
                                                            number without password. To enable this feature') }}
                                                            <a href="#0" class="text-decoration-underline text-info">{{ translate('Configure SMS Setup') }}</a>
                                                            {{ translate('Here.') }}
                                                        </p>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card border-0">
                            <div class="card-body">
                                <div class="mb-20">
                                    <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('User Number Verification') }}</h4>
                                    <p class="fs-14 mb-0">{{ translate('User must verify their number after signup manually.') }}</p>
                                </div>
                                <div class="p-xxl-20 p-3 bg-F6F6F6 rounded">
                                    <div class="bg-white rounded p-xl-3 p-2">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="custom-checkbox d-flex gap-2">
                                                    <input type="checkbox" name="customer_active" id="customer_active" class="input-size-20" checked>
                                                    <label for="customer_active" class="mb-0">
                                                        <h5 class="fs-14 mb-0">{{ translate('Activate Verification for Customer') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="custom-checkbox d-flex gap-2">
                                                    <input type="checkbox" name="driver_active" id="driver_active" class="input-size-20" checked>
                                                    <label for="driver_active" class="mb-0">
                                                        <h5 class="fs-14 mb-0">{{ translate('Activate Verification for Driver') }}</h5>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="btn--container justify-content-end mt-4">
                    <button type="reset" class="btn btn-secondary min-w-120 cmn_focus">{{ translate('Reset') }}</button>
                    <button type="#0" class="btn btn-primary min-w-120 cmn_focus call-demo">{{ translate('Save Information') }}</button>
                </div>
            </div>
            <!---- OPT setup ---->
            <div class="">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card border-0">
                            <div class="card-body">
                                <div class="mb-20">
                                    <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('OTP Setup') }}</h4>
                                    <p class="fs-14 mb-0">{{ translate('Manage the settings for how many times a user can try to enter the otp.') }}</p>
                                </div>
                                <div class="p-xxl-20 p-3 bg-F6F6F6 rounded">
                                    <div class="row g-4">
                                        <div class="col-md-6 col-lg-4">
                                            <label for="max_opt" class="fs-14 text-dark mb-10px d-flex align-items-center gap-1">
                                                {{ translate('Maximum OTP hit') }}
                                                <i class="bi bi-info-circle-fill text-muted cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="right"
                                                            data-bs-title="{{ translate('Content need') }}"></i>
                                            </label>
                                            <div class="form-grop">
                                                <input type="text" class="form-control" id="max_opt" placeholder="EX: 5">
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-4">
                                            <label for="resend_opt" class="fs-14 text-dark mb-10px d-flex align-items-center gap-1">
                                                {{ translate('OTP resend time (Sec)') }}
                                                <i class="bi bi-info-circle-fill text-muted cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="right"
                                                            data-bs-title="{{ translate('Content need') }}"></i>
                                            </label>
                                            <div class="form-grop">
                                                <input type="text" class="form-control" id="resend_opt" placeholder="EX: 5">
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-4">
                                            <label for="temporary_opt" class="fs-14 text-dark mb-10px d-flex align-items-center gap-1">
                                                {{ translate('Temporary block time (Sec)') }}
                                                <i class="bi bi-info-circle-fill text-muted cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="right"
                                                            data-bs-title="{{ translate('Content need') }}"></i>
                                            </label>
                                            <div class="form-grop">
                                                <input type="text" class="form-control" id="temporary_opt" placeholder="EX: 5">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card border-0">
                            <div class="card-body">
                                <div class="mb-20">
                                    <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('Login Setup') }}</h4>
                                    <p class="fs-14 mb-0">{{ translate('Manage the settings for how many times a user can try to log in to the system.') }}</p>
                                </div>
                                <div class="p-xxl-20 p-3 bg-F6F6F6 rounded">
                                    <div class="row g-4">
                                        <div class="col-md-6 col-lg-4">
                                            <label for="max_opt_hit" class="fs-14 text-dark mb-10px d-flex align-items-center gap-1">
                                                {{ translate('Maximum Login hit') }}
                                                <i class="bi bi-info-circle-fill text-muted cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="right"
                                                            data-bs-title="{{ translate('Content need') }}"></i>
                                            </label>
                                            <div class="form-grop">
                                                <input type="text" class="form-control" id="max_opt_hit" placeholder="EX: 5">
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-4">
                                            <label for="temporary_opt_titme" class="fs-14 text-dark mb-10px d-flex align-items-center gap-1">
                                                {{ translate('Temporary login block time (Sec)') }}
                                                <i class="bi bi-info-circle-fill text-muted cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="right"
                                                            data-bs-title="{{ translate('Content need') }}"></i>
                                            </label>
                                            <div class="form-grop">
                                                <input type="text" class="form-control" id="temporary_opt_titme" placeholder="EX: 5">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="btn--container justify-content-end mt-4">
                    <button type="reset" class="btn btn-secondary min-w-120 cmn_focus">{{ translate('Reset') }}</button>
                    <button type="#0" class="btn btn-primary min-w-120 cmn_focus call-demo">{{ translate('Save Information') }}</button>
                </div>
            </div>
        </div>
    </div>
    
@endsection

@push('script')

@endpush
