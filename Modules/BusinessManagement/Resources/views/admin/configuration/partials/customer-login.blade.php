@php
    $smsSetupLink = '<a href="' . route('admin.business.configuration.third-party.sms-gateway.index') . '" class="fw-semibold text-info text-decoration-underline" target="_blank">' . translate('Configure SMS Setup') . '</a>';
@endphp
<div class="row g-3">
    <div class="col-12">
        <div class="card border-0">
            <div class="card-body">
                <div class="mb-20">
                    <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('Login Setup') }}</h4>
                    <p class="fs-14 mb-0">{{ translate('The option you select customer will have the option to login customer app') }}</p>
                </div>
                <div class="p-xxl-20 p-3 bg-F6F6F6 rounded">
                    <div class="mb-xxl-20 mb-3">
                        <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('Choose How to Login') }}</h4>
                        <p class="fs-14 mb-0">{{ translate('Based on your selection, customers will have the options to login customer app') }}</p>
                    </div>
                    <div class="bg-warning bg-opacity-10 d-flex align-items-center gap-2 px-2 py-2 rounded mb-20">
                        <i class="bi bi-info-circle-fill text-warning"></i>
                        <p class="media-body mb-0 fs-12">
                            {{ translate('At least one login option must remain active for Verification. Otherwise you will be unable to select & Save.') }}
                        </p>
                    </div>
                    <div class="bg-white rounded p-xl-3 p-2">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="custom-checkbox d-flex align-items-start gap-2">
                                    <input type="checkbox" name="customer[manual_login]"
                                           id="customer-manual_login"
                                           class="input-size-20" {{ !isset($loginOptions) || ($loginOptions['manual_login'] ?? 0) == 1 ? 'checked' : '' }}>
                                    <label for="customer-manual_login" class="mb-0">
                                        <h5 class="fs-14 mb-1">{{ translate('Manual Login') }}</h5>
                                        <p class="fs-12 m-0 opacity-75">
                                            {{ translate('By enabling manual login, customers will get the option to create an account and log in using the necessary credentials & password in the app & website') }}
                                        </p>
                                    </label>
                                </div>
                                <span class="error-text justify-content-start" data-error="customer"></span>
                            </div>
                            <div class="col-md-6">
                                <div class="custom-checkbox d-flex align-items-start gap-2">
                                    <input type="checkbox" name="customer[otp_login]"
                                           id="customer-otp_login"
                                           class="input-size-20" {{ $isOtpEnabled ? '' : 'disabled' }} {{ ($loginOptions['otp_login'] ?? 0) == 1 ? 'checked' : '' }}>
                                    <label for="customer-otp_login" class="mb-0">
                                        <h5 class="fs-14 mb-1">
                                            {{ translate('OTP Login') }}
                                            @if(!$isOtpEnabled)
                                                <img src="{{ asset('public/assets/admin-module/img/info-warning-icon.png') }}"
                                                     class="cursor-pointer"
                                                     data-bs-toggle="tooltip"
                                                     data-bs-placement="right"
                                                     data-bs-title="{{ translate('Configure your SMS settings to function OTP login properly') }}">
                                            @endif
                                        </h5>
                                        <p class="fs-12 m-0 opacity-75">
                                            {{ translate('With OTP Login, customers can log in using their phone number without password.') }}
                                            @if(!$isOtpEnabled)
                                                {!! translate(key: 'To enable this feature {smsSetup} Here.', replace: ['smsSetup' => $smsSetupLink]) !!}
                                            @endif
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
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('User Number Verification') }}</h4>
                        <p class="fs-14 mb-0">{{ translate('User must verify their number after signup manually.') }}</p>
                    </div>
                    <label class="switcher rounded-pill mb-0">
                        <input class="switcher_input" type="checkbox" name="customer_verification" id="customer_verification" {{ $isOtpEnabled ? '' : 'disabled' }} {{ $isCustomerVerificationEnabled ? 'checked' : '' }}>
                        <span class="switcher_control"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
