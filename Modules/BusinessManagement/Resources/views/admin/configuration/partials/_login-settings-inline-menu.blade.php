@php
    $isLoginIndex = request()->routeIs('admin.business.configuration.login-settings.index');
    $isOtpAttemptsIndex = request()->routeIs('admin.business.configuration.login-settings.otp-login-attempts-index');
    $activeLoginTab = $activeLoginTab ?? (request('tab') === 'driver' ? 'driver' : 'customer');
@endphp
<div class="position-relative nav--tab-wrapper mb-20">
    <ul class="nav d-flex gap-3 flex-nowrap nav--tabs bg-transparent overflow-x-auto text-nowrap" role="tablist">
        <li class="nav-item text-capitalize" role="presentation">
            @if($isLoginIndex)
                <button type="button"
                        class="text-capitalize nav-link rounded-20 fs-14 {{ $activeLoginTab === 'customer' ? 'active' : '' }}"
                        data-bs-toggle="tab"
                        data-bs-target="#customer-login-pane"
                        role="tab"
                        aria-controls="customer-login-pane"
                        aria-selected="{{ $activeLoginTab === 'customer' ? 'true' : 'false' }}">
                    {{ translate('Customer Login') }}
                </button>
            @else
                <a href="{{ route('admin.business.configuration.login-settings.index', ['tab' => 'customer']) }}"
                   class="text-capitalize nav-link rounded-20 fs-14">{{ translate('Customer Login') }}</a>
            @endif
        </li>
        <li class="nav-item text-capitalize" role="presentation">
            @if($isLoginIndex)
                <button type="button"
                        class="text-capitalize nav-link rounded-20 fs-14 {{ $activeLoginTab === 'driver' ? 'active' : '' }}"
                        data-bs-toggle="tab"
                        data-bs-target="#driver-login-pane"
                        role="tab"
                        aria-controls="driver-login-pane"
                        aria-selected="{{ $activeLoginTab === 'driver' ? 'true' : 'false' }}">
                    {{ translate('Driver Login') }}
                </button>
            @else
                <a href="{{ route('admin.business.configuration.login-settings.index', ['tab' => 'driver']) }}"
                   class="text-capitalize nav-link rounded-20 fs-14">{{ translate('Driver Login') }}</a>
            @endif
        </li>
        <li class="nav-item text-capitalize" role="presentation">
            <a href="{{ route('admin.business.configuration.login-settings.otp-login-attempts-index') }}"
               class="text-capitalize nav-link rounded-20 fs-14 {{ $isOtpAttemptsIndex ? 'active' : '' }}">{{ translate('OTP & Login Attempts') }}</a>
        </li>
    </ul>
    <div class="nav--tab__prev">
        <button type="button" class="btn btn-circle fs-16">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>
    <div class="nav--tab__next">
        <button type="button" class="btn btn-circle fs-16">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
</div>
