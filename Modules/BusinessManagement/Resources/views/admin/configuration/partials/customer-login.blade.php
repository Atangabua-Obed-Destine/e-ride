@php
    $smsSetupLink = '<a href="' . route('admin.business.configuration.third-party.sms-gateway.index') . '" class="fw-semibold text-info text-decoration-underline" target="_blank">' . translate('Configure SMS Setup') . '</a>';
    $socialSetupLink = '<a href="' . route('admin.business.configuration.third-party.social-login.index') . '" class="fw-semibold text-info text-decoration-underline" target="_blank">' . translate('Configure Social Media Setup') . '</a>';
    $socialProviders = [
        ['key' => 'google', 'name' => translate('Google'), 'status' => 1, 'desc' => translate('Enabling Google Login, customers can log in to the site using their existing Gmail credentials.')],
        ['key' => 'facebook', 'name' => translate('Facebook'), 'status' => 1, 'desc' => translate('Enabling Facebook Login, customers can log in to the site using their existing Facebook credentials.')],
        ['key' => 'apple', 'name' => translate('Apple'), 'status' => $appleLoginStatus ?? 0, 'desc' => translate('Enabling Apple Login, customers can log in to the site using their existing Apple login credentials, Only for Apple devices.')],
    ];
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
                            <div class="col-md-6 col-xl-4">
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
                            <div class="col-md-6 col-xl-4">
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
                            <div class="col-md-6 col-xl-4">
                                <div class="custom-checkbox d-flex align-items-start gap-2">
                                    <input type="checkbox" name="customer[social_media_login]"
                                           id="customer-social_media_login"
                                           class="input-size-20" {{ ($loginOptions['social_media_login'] ?? 0) == 1 ? 'checked' : '' }}>
                                    <label for="customer-social_media_login" class="mb-0">
                                        <h5 class="fs-14 mb-1">{{ translate('Social Media Login') }}</h5>
                                        <p class="fs-12 m-0 opacity-75">
                                            {{ translate('With Social Media Login, customers can log in using social media accounts.') }}
                                            {!! translate(key: 'To enable this feature {smsSetup} Here.', replace: ['smsSetup' => $socialSetupLink]) !!}
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
    <div class="col-12 social-media-login-setup" style="{{ ($loginOptions['social_media_login'] ?? 0) == 1 ? '' : 'display: none;' }}">
        <div class="card border-0">
            <div class="card-body">
                <div class="p-xxl-20 p-3 bg-F6F6F6 rounded">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                        <div>
                            <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('Social Media Login Setup') }}</h4>
                            <p class="fs-14 mb-0">{{ translate('Select which social media you want for customer login') }}</p>
                        </div>
                        <a href="{{ route('admin.business.configuration.third-party.social-login.index') }}"
                           class="fw-semibold text-info text-decoration-underline" target="_blank">
                            {{ translate('Connect 3rd party login system from here') }}
                        </a>
                    </div>
                    <div class="bg-warning bg-opacity-10 d-flex align-items-center gap-2 px-2 py-2 rounded mb-20">
                        <i class="bi bi-info-circle-fill text-warning"></i>
                        <p class="media-body mb-0 fs-12">
                            {{ translate('At least one social media must remain active for login. Otherwise social media login cannot work.') }}
                        </p>
                    </div>
                    <div class="bg-white rounded p-xl-3 p-2">
                        <div class="row g-3">
                            @foreach ($socialProviders as $provider)
                                @php($isProviderActive = ($provider['status'] ?? 0) == 1)
                                @php($isProviderSelected = $isProviderActive && (($loginOptions['social_login'][$provider['key']] ?? 0) == 1))
                                <div class="col-md-6 col-xl-4">
                                    <div class="custom-checkbox d-flex align-items-start gap-2"
                                         @unless($isProviderActive)
                                             data-bs-toggle="tooltip" data-bs-placement="top"
                                             data-bs-title="{{ translate('{provider} is currently disabled from configure 3rd party social login options.', ['provider' => $provider['name']]) }}"
                                         @endunless>
                                        <input type="checkbox" name="customer[social_login][{{ $provider['key'] }}]"
                                               id="customer-social-{{ $provider['key'] }}"
                                               class="input-size-20" {{ $isProviderActive ? '' : 'disabled' }} {{ $isProviderSelected ? 'checked' : '' }}>
                                        <label for="customer-social-{{ $provider['key'] }}" class="mb-0 {{ $isProviderActive ? '' : 'pe-none' }}">
                                            <h5 class="fs-14 mb-1 {{ $isProviderActive ? '' : 'text-muted' }}">{{ $provider['name'] }}</h5>
                                            <p class="fs-12 m-0 {{ $isProviderActive ? 'opacity-75' : 'text-muted' }}">{{ $provider['desc'] }}</p>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
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
