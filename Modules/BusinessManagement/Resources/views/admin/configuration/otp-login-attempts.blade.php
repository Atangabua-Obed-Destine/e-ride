@extends('adminmodule::layouts.master')
@section('title', translate('OTP & Login Attempts'))

@section('content')
    <!-- login setup Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h4 class="mb-4 fs-20 pb-xxl-1">{{ translate('Login Settings') }}</h4>
            @include('businessmanagement::admin.configuration.partials._login-settings-inline-menu')
            <form action="{{ route('admin.business.configuration.login-settings.otp-login-attempts-store') }}" class="submit-by-ajax">
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
                                            <div class="col-lg-4">
                                                <div>
                                                    <label class="text-capitalize mb-2">
                                                        {{translate('Maximum_OTP_Submit_Attempts')}} <span class="text-danger">*</span>
                                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                                           title="{{translate('defines_how_many_times_a_user_can_enter_an_incorrect_otp_before_being_temporarily_blocked.')}}"></i>
                                                    </label>
                                                    <input type="text" name="maximum_otp_hit" class="form-control"
                                                           id="maximum_otp_hit" placeholder="{{translate('Ex: 10')}}" tabindex="9"
                                                           value="{{$settings->firstWhere('key_name', 'maximum_otp_hit')->value ?? ''}}" data-decimal="0">
                                                    <span class="error-text" data-error="maximum_otp_hit"></span>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div>
                                                    <label class="text-capitalize mb-2">
                                                        {{translate('OTP_Resend_Time')}} ({{ translate('In_Seconds') }}) <span class="text-danger">*</span>
                                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                                           title="{{translate('set_the_waiting_time_(in_seconds)_before_the_user_can_request_to_resend_the_otp_again.')}}"></i>
                                                    </label>
                                                    <input type="text" name="otp_resend_time" class="form-control" tabindex="10"
                                                           id="otp_resend_time" placeholder="{{translate('Ex: 60')}}"
                                                           value="{{$settings->firstWhere('key_name', 'otp_resend_time')->value ?? ''}}" data-decimal="0">
                                                    <span class="error-text" data-error="otp_resend_time"></span>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div>
                                                    <label class="text-capitalize mb-2">
                                                        {{translate('Temporary_OTP_Block_Time')}} ({{ translate('In_Seconds') }}) <span class="text-danger">*</span>
                                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                                           title="{{translate('set_the_time_(in_seconds)_that_a_user_is_restricted_from_otp_verification_after_exceeding_the_maximum_attempt_limit.')}}"></i>
                                                    </label>
                                                    <input type="text" name="temporary_block_time" class="form-control" tabindex="11"
                                                           id="temporary_block_time" placeholder="{{translate('Ex: 600')}}" data-decimal="0"
                                                           value="{{$settings->firstWhere('key_name', 'temporary_block_time')->value ?? ''}}">
                                                    <span class="error-text" data-error="temporary_block_time"></span>
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
                                            <div class="col-lg-4">
                                                <label class="text-capitalize mb-2">
                                                    {{translate('Maximum_Login_Attempts')}} <span class="text-danger">*</span>
                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                                       title="{{translate('set_the_maximum_number_of_incorrect_login_attempts_allowed_within_a_period.')}}"></i>
                                                </label>
                                                <input type="text" name="maximum_login_hit" class="form-control" tabindex="7"
                                                       id="maximum_login_hit" placeholder="{{translate('Ex: 10')}}"
                                                       value="{{$settings->firstWhere('key_name', 'maximum_login_hit')->value ?? ''}}" data-decimal="0">
                                                <span class="error-text" data-error="maximum_login_hit"></span>
                                            </div>
                                            <div class="col-lg-4">
                                                <div>
                                                    <label class="text-capitalize mb-2">
                                                        {{translate('Temporary_Login_Block_Time')}} ({{ translate('In_Seconds') }}) <span class="text-danger">*</span>
                                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                                           title="{{translate('duration_(in_seconds)_for_which_a_user_will_be_blocked_after_exceeding_the_maximum_login_attempts.')}}"></i>
                                                    </label>
                                                    <input type="text" name="temporary_login_block_time"
                                                           class="form-control" id="temporary_login_block_time"
                                                           placeholder="{{translate('Ex: 10')}}" tabindex="8"
                                                           value="{{$settings->firstWhere('key_name', 'temporary_login_block_time')->value ?? ''}}" data-decimal="0">
                                                    <span class="error-text" data-error="temporary_login_block_time"></span>
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
                        <button type="{{ env('APP_MODE') == 'demo' ? 'button' : 'submit' }}" class="btn btn-primary min-w-120 cmn_focus call-demo">{{ translate('Save Information') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
