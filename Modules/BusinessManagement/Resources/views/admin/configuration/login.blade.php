@extends('adminmodule::layouts.master')
@section('title', translate('Login'))
@section('content')
    <!-- login setup Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h4 class="mb-4 fs-20 pb-xxl-1">{{ translate('Login Settings') }}</h4>
            @php
                $activeLoginTab = request('tab') === 'driver' ? 'driver' : 'customer';
                $customerLoginOptions = businessConfig(key: CUSTOMER . '_login_options', settingsType: LOGIN_SETTINGS)?->value;
                $driverLoginOptions = businessConfig(key: DRIVER . '_login_options', settingsType: LOGIN_SETTINGS)?->value;
            @endphp
            @include('businessmanagement::admin.configuration.partials._login-settings-inline-menu', ['activeLoginTab' => $activeLoginTab])
            <form action="{{ route('admin.business.configuration.login-settings.store') }}" class="submit-by-ajax login-settings-form">
                <input type="hidden" name="active_tab" value="{{ $activeLoginTab }}" class="active-tab-input">
                <div class="tab-content">
                    <div class="tab-pane fade {{ $activeLoginTab === 'customer' ? 'show active' : '' }}"
                         id="customer-login-pane" role="tabpanel" aria-labelledby="customer-login-tab">
                        @include('businessmanagement::admin.configuration.partials.customer-login', [
                            'loginOptions' => $customerLoginOptions,
                            'isOtpEnabled' => $isOtpEnabled,
                            'isCustomerVerificationEnabled' => $isCustomerVerificationEnabled,
                        ])
                    </div>
                    <div class="tab-pane fade {{ $activeLoginTab === 'driver' ? 'show active' : '' }}"
                         id="driver-login-pane" role="tabpanel" aria-labelledby="driver-login-tab">
                        @include('businessmanagement::admin.configuration.partials.driver-login', [
                            'loginOptions' => $driverLoginOptions,
                            'isOtpEnabled' => $isOtpEnabled,
                            'isDriverVerificationEnabled' => $isDriverVerificationEnabled,
                        ])
                    </div>
                </div>
                <div class="btn--container justify-content-end mt-4">
                    <button type="reset" class="btn btn-secondary min-w-120 cmn_focus">{{ translate('Reset') }}</button>
                    <button type="{{ env('APP_MODE') == 'demo' ? 'button' : 'submit' }}"
                            class="btn btn-primary min-w-120 cmn_focus call-demo">{{ translate('Save Information') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('script2')
    <script>
        (function () {
            window.validateLoginSettingsForm = function (form) {
                let $form = $(form);
                let $driverError = $form.find('.error-text[data-error="driver"]');
                let isBiometricLoginEnabled = $form.find('[name="driver[biometric_login]"]').is(':checked');
                let isManualLoginEnabled = $form.find('[name="driver[manual_login]"]').is(':checked');
                let isOtpLoginEnabled = $form.find('[name="driver[otp_login]"]').is(':checked:not(:disabled)');

                $driverError.text('');
                $form.find('[name^="driver["]').removeClass('is-invalid');

                if (isBiometricLoginEnabled && !isManualLoginEnabled && !isOtpLoginEnabled) {
                    toastr.error('{{ translate('Manual login or OTP login must be enabled when biometric login is enabled.') }}');
                    return false;
                }

                return true;
            };

            document.addEventListener('submit', function (event) {
                if (event.target.matches('.login-settings-form') && !window.validateLoginSettingsForm(event.target)) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }, true);

            $(document).on('shown.bs.tab', '.nav--tabs [data-bs-toggle="tab"]', function (e) {
                let target = $(e.target).data('bs-target');
                let tab = target === '#driver-login-pane' ? 'driver' : 'customer';
                let url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                window.history.replaceState({}, '', url);
                $('.login-settings-form .active-tab-input').val(tab);
            });

            $(document).on('click', '.login-settings-form [type="reset"]', function () {
                let $form = $(this).closest('form');
                setTimeout(function () {
                    $form.find('input[type="checkbox"]').each(function () {
                        this.checked = this.defaultChecked;
                    });
                }, 0);
            });
        })();
    </script>
@endpush
