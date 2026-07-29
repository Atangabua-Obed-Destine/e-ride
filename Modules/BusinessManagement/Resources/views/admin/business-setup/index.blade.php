@extends('adminmodule::layouts.master')

@section('title', translate('Business_Info'))

@push('css_or_js')
    @php($map_key = businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null)
    <script src="https://maps.googleapis.com/maps/api/js?key={{$map_key}}&libraries=places"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/js/maps/markerclusterer.js')}}"></script>
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <form action="{{ route('admin.business.setup.info.store') }}" id="business_form" method="post"
              enctype="multipart/form-data">
            @csrf
            <div class="container-fluid">
                <h2 class="fs-20 fw-bold mb-3 text-capitalize">{{translate('business_management')}}</h2>
                <div class="mb-3">
                    <div class="">
                        @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        @can('business_edit')
                            <div class="card mb-3">
                                    <?php
                                    $config = businessConfig('maintenance_mode')?->value == 1 ? 1 : 0;
                                    $selectedMaintenanceSystem = businessConfig('maintenance_system_setup')?->value ?? [];
                                    if ($config && count($selectedMaintenanceSystem) > 0) {
                                        $selectedMaintenanceDuration = businessConfig('maintenance_duration_setup')?->value;
                                        $startDate = new DateTime($selectedMaintenanceDuration['start_date']);
                                        $endDate = new DateTime($selectedMaintenanceDuration['end_date']);
                                    }
                                    ?>
                                <div class="card-body p-0">
                                    <div
                                            class="d-flex flex-md-nowrap flex-wrap align-items-center justify-content-between gap-3 p-4">
                                        <div class="w-0 flex-grow-1">
                                            <h4 class="text-capitalize mb-2">{{ translate('System Maintenance Mode') }}</h4>
                                            <div class="fs-14">
                                                {{ translate('Use the System Maintenance feature to work on the system and disable user access .') }}
                                            </div>
                                        </div>
                                        <label
                                                class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                                            <label for="maintenance-mode-input"
                                                   class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Maintenance mode') }}</label>
                                            <label class="switcher cmn_focus rounded-pill">
                                                <input data-url="{{ route('admin.business.setup.info.maintenance') }}"
                                                       type="checkbox" id="maintenance-mode-input"
                                                       class="switcher_input toggle-switch-input collapsible-card-switcher {{ $config == 0 ? 'maintenance-mode-show' : 'maintenance-off' }}"
                                                       tabindex="1"
                                                        {{ $config ? 'checked' : '' }}
                                                        {{ env('APP_MODE') == 'demo' ? 'disabled' : '' }}>
                                                <span class="switcher_control"></span>
                                            </label>
                                        </label>
                                    </div>
                                    <div class="collapsible-card-content pt-0">
                                        @if ($config && count($selectedMaintenanceSystem) > 0)
                                            <hr class="m-0">
                                            <div class="d-flex flex-wrap gap-3 align-items-center p-4 pb-0">
                                                <p class="mb-0">
                                                    @if ($selectedMaintenanceDuration['maintenance_duration'] == 'until_change')
                                                        {{ translate('Your maintenance mode is activated.') }}
                                                    @else
                                                        {{ translate('Your maintenance mode is activated from') }}
                                                        <span
                                                                class="text-body fw-semibold">{{ $startDate->format('m/d/Y, h:i A') }}</span>
                                                        {{ translate('to') }}
                                                        <span
                                                                class="text-body fw-semibold">{{ $endDate->format('m/d/Y, h:i A') }}</span>
                                                        .
                                                    @endif

                                                </p>
                                                <a class="c1 edit maintenance-mode-show" href="#"><i
                                                            class="tio-edit"></i></a>
                                            </div>
                                        @else
                                            {{-- <p>
                                            *{{ translate('By turning on maintenance mode Control your all system & function') }}
                                        </p> --}}
                                        @endif

                                        @if ($config && count($selectedMaintenanceSystem) > 0)
                                            <div class="d-flex flex-wrap gap-3 mt-3 align-items-center p-4 pt-0">
                                                <h6 class="mb-0">
                                                    {{ translate('Selected Systems') }}
                                                </h6>
                                                <div class="bg-F6F6F6 px-4 py-2 mb-0 rounded">
                                                    <ul class="selected-systems d-flex gap-4 flex-wrap m-0 p-0 ps-3">
                                                        @foreach ($selectedMaintenanceSystem as $system)
                                                            <li>{{ ucwords(str_replace('_', ' ', $system)) }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            </div>
                                        @endif

                                    </div>
                                </div>
                            </div>
                        @endcan
                    </div>
                    <div class="col-12">
                        <div class="card h-100">
                            <div class="card-header">
                                <h4 class="mb-2">
                                    {{translate('Basic_Information')}}
                                </h4>
                                <p class="mb-0">{{ translate('here_you_setup_your_all_business_information.') }}</p>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-xl-8">
                                        <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                            <div class="row g-4">
                                                <div class="col-lg-6">
                                                    <div>
                                                        <label for="business_name" class="mb-2">
                                                            {{ translate('business_name') }} <span
                                                                    class="text-danger">*</span>
                                                        </label>
                                                        <input type="text" name="business_name"
                                                               value="{{ $settings->firstWhere('key_name', 'business_name')?->value ?? old('business_name') }}"
                                                               id="business_name"
                                                               class="form-control {{ $settings->firstWhere('key_name', 'business_name')?->value ?? old('business_name') ? 'dark-border' : '' }}"
                                                               placeholder="{{ translate('Ex: ABC Company') }}"
                                                               tabindex="2"
                                                               required
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div>
                                                        <label for="business_contact_num" class="mb-2">
                                                            {{ translate('contact_Phone') }} <span
                                                                    class="text-danger">*</span>
                                                        </label>
                                                        <input type="tel" pattern="[0-9]{1,14}" name=""
                                                               value="{{ $settings->firstWhere('key_name', 'business_contact_phone')?->value ?? old('business_contact_phone') }}"
                                                               id="business_contact_num"
                                                               class="form-control w-100 text-dir-start {{ $settings->firstWhere('key_name', 'business_contact_phone')?->value ?? old('business_contact_phone') ? 'dark-border' : '' }}"
                                                               placeholder="{{ translate('Ex: +9XXX-XXX-XXXX') }}"
                                                               tabindex="3"
                                                               oninvalid="this.setCustomValidity('Please enter a valid phone number (only digits, max 14)')"
                                                               oninput="this.setCustomValidity('')"
                                                        >
                                                        <input type="hidden" id="business_contact_num-hidden-element"
                                                               name="business_contact_phone" tabindex="4">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div>
                                                        <label for="business_email" class="mb-2">
                                                            {{ translate('contact_email') }} <span
                                                                    class="text-danger">*</span>
                                                        </label>
                                                        <input type="email" name="business_contact_email"
                                                               value="{{ $settings->firstWhere('key_name', 'business_contact_email')->value ?? old('business_contact_email') }}"
                                                               id="business_email"
                                                               class="form-control {{ $settings->firstWhere('key_name', 'business_contact_email')?->value ?? old('business_contact_email') ? 'dark-border' : '' }}"
                                                               placeholder="{{ translate('Ex: company@email.com') }}"
                                                               tabindex="5"
                                                               required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div>
                                                        <label for="business_support_number" class="mb-2">
                                                            {{ translate('Support_Phone') }} <span
                                                                    class="text-danger">*</span>
                                                        </label>
                                                        <input type="tel" pattern="[0-9]{1,14}" name=""
                                                               value="{{ $settings->firstWhere('key_name', 'business_support_phone')?->value ?? old('business_support_phone') }}"
                                                               id="business_support_number"
                                                               class="form-control w-100 text-dir-start {{ $settings->firstWhere('key_name', 'business_contact_phone')?->value ?? old('business_contact_phone') ? 'dark-border' : '' }}"
                                                               placeholder="{{ translate('Ex: 9XXX-XXX-XXXX') }}"
                                                               tabindex="6"
                                                               oninvalid="this.setCustomValidity('Please enter a valid phone number (only digits, max 14)')"
                                                               oninput="this.setCustomValidity('')"
                                                        >
                                                        <input type="hidden" id="business_support_number-hidden-element"
                                                               name="business_support_phone" tabindex="7">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div>
                                                        <label for="business_support_email" class="mb-2">
                                                            {{ translate('Support_email') }} <span
                                                                    class="text-danger">*</span>
                                                        </label>
                                                        <input type="text" name="business_support_email"
                                                               value="{{ $settings->firstWhere('key_name', 'business_support_email')?->value ?? old('business_support_email') }}"
                                                               id="business_support_email"
                                                               class="form-control {{ $settings->firstWhere('key_name', 'business_support_email')?->value ?? old('business_support_email') ? 'dark-border' : '' }}"
                                                               placeholder="{{ translate('Ex: support@email.com') }}"
                                                               tabindex="8" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div>
                                                        <label for="country" class="mb-2">
                                                            {{ translate('Country') }} <span
                                                                    class="text-danger">*</span>
                                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                               data-bs-toggle="tooltip"
                                                               data-bs-title="{{ translate('select_your_business_country_location.') }}"></i>
                                                        </label>
                                                        <select name="country_code" id="country"
                                                                class="form-control js-select cmn_focus" required
                                                                tabindex="9">
                                                            <option value="" disabled selected>
                                                                {{ translate('Select_your_country') }}</option>
                                                            @foreach (COUNTRIES as $country)
                                                                <option value="{{ $country['code'] }}"
                                                                        {{ ($settings->where('key_name', 'country_code')->first()->value ?? '') == $country['code'] ? 'selected' : '' }}>
                                                                    {{ $country['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <label for="business_address"
                                                           class="mb-2">{{ translate('business_address') }} <span
                                                                class="text-danger">*</span></label>
                                                    <div class="character-count">
                                                        <textarea name="business_address" id="business_address"
                                                                  cols="30" rows="4"
                                                                  class="form-control character-count-field {{ $settings->firstWhere('key_name', 'business_address')?->value ?? old('business_address') ? 'dark-border' : '' }}"
                                                                  placeholder="{{ translate('Type Here ...') }}"
                                                                  maxlength="100" data-max-character="100"
                                                                  tabindex="10"
                                                                  required>{{ $settings->firstWhere('key_name', 'business_address')?->value ?? old('business_address') }}</textarea>
                                                        <span
                                                                class="d-flex justify-content-end">{{ translate('0/100') }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-4">
                                        <div class="map--container h-100">
                                            <input class="form-control map-search-input" type="text"
                                                   placeholder="{{ translate('Search_here') }}" tabindex="11">
                                            <div id="map-bind-with-address" class="rounded map h-100"
                                                 data-title="{{ $settings->firstWhere('key_name', 'business_address')?->value }}"
                                            >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card h-100">
                            <div class="card-header">
                                <h4 class="mb-2">
                                    {{translate('General_Setup')}}
                                </h4>
                                <p class="mb-0">{{ translate('Configure_Essential_Business_Details.') }}</p>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="mb-20">
                                            <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                                {{translate('Time_Setup')}}
                                            </h5>
                                            <p class="mb-0">{{ translate('setup_your_business_time_zone_and_format_from_here') }}</p>
                                        </div>
                                        <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                            <div class="row g-4">
                                                <div class="col-lg-4">
                                                    <div>
                                                        <label for="time_zone" class="mb-2">
                                                            {{ translate('Time_Zone') }} <span
                                                                    class="text-danger">*</span>
                                                        </label>
                                                        <select name="time_zone" id="time_zone"
                                                                class="form-control js-select cmn_focus" tabindex="12"
                                                                required>
                                                            <option value="" disabled selected>
                                                                {{ translate('select_your_time_zone') }}</option>
                                                            @foreach (TIME_ZONES as $zone)
                                                                <option value="{{ $zone['tzCode'] }}"
                                                                        {{ ($settings->where('key_name', 'time_zone')->first()->value ?? '') == $zone['tzCode'] ? 'selected' : '' }}>
                                                                    (GMT{{ $zone['utc'] }})
                                                                    {{ $zone['tzCode'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div>
                                                        <label for="time_format" class="mb-2">
                                                            {{ translate('Time_Format') }} <span
                                                                    class="text-danger">*</span>
                                                        </label>
                                                        <div
                                                                class="d-flex align-items-center form-control cmn_focus rounded">
                                                            <div class="flex-grow-1">
                                                                <input type="radio" name="time_format" value="h:i:s A"
                                                                       id="time_format_left" tabindex="13"
                                                                       {{ ($settings->firstWhere('key_name', 'time_format')?->value ?? '') == 'h:i:s A' ? 'checked' : '' }}
                                                                       required
                                                                >
                                                                <label for="time_format_left"
                                                                       class="media gap-2 align-items-center">
                                                                    <span
                                                                            class="media-body">{{ translate('12_hours') }}</span>
                                                                </label>
                                                            </div>

                                                            <div class="flex-grow-1">
                                                                <input type="radio" name="time_format" value="H:i:s"
                                                                       id="time_format_right" tabindex="14"
                                                                        {{ ($settings->where('key_name', 'time_format')->first()->value ?? '') == 'H:i:s' ? 'checked' : '' }}>
                                                                <label for="time_format_right"
                                                                       class="media gap-2 align-items-center">
                                                                    <span
                                                                            class="media-body">{{ translate('24_hours') }}</span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="border-top"></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mb-20">
                                            <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                                {{translate('Currency_Setup')}}
                                            </h5>
                                            <p class="mb-0">{{ translate('Set the default currency used across your platform for pricing and transactions.') }}</p>
                                        </div>
                                        <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                            <div class="row g-4">
                                                <div class="col-lg-4">
                                                    @php($cc = $settings->where('key_name', 'currency_code')->first()?->value)
                                                    <div>
                                                        <label for="time_zone" class="mb-2">
                                                            {{ translate('Currency') }} ($) <span
                                                                    class="text-danger">*</span>
                                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                               data-bs-toggle="tooltip"
                                                               title="{{translate('select_the_currency_of_your_business.')}}"></i>
                                                        </label>
                                                        <select name="currency_code" id="currency"
                                                                class="form-control js-select cmn_focus" tabindex="15"
                                                                required>
                                                            <option disabled
                                                                    selected>{{ translate('select_currency') }}</option>
                                                            @foreach (CURRENCIES as $currency)
                                                                <option value="{{ $currency['code'] }}"
                                                                        {{ $cc == $currency['code'] ? 'selected' : '' }}>
                                                                    {{ $currency['name'] }}
                                                                    ({{ $currency['symbol'] }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div>
                                                        <label for="currency_position" class="mb-2">
                                                            {{ translate('Currency_symbol_position') }} <span
                                                                    class="text-danger">*</span>
                                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                               data-bs-toggle="tooltip"
                                                               title="{{translate('select_the_currency_symbol_position_(left:_$99,_right:_99$).')}}"></i>
                                                        </label>
                                                        <select name="currency_symbol_position" id="currency_position"
                                                                class="form-control js-select cmn_focus" tabindex="16"
                                                                required>
                                                            <option disabled
                                                                    selected>{{ translate('select_currency_symbol_position') }}</option>
                                                            <option
                                                                    value="left" {{ ($settings->firstWhere('key_name', 'currency_symbol_position')?->value ?? '') == 'left' ? 'selected' : '' }}>
                                                                ($) {{ translate('left') }}
                                                            </option>
                                                            <option
                                                                    value="right" {{ ($settings->where('key_name', 'currency_symbol_position')->first()->value ?? '') == 'right' ? 'selected' : '' }}>
                                                                {{ translate('right') }} ($)
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div>
                                                        <label for="time_format" class="mb-2">
                                                            {{ translate('Digit_after_decimal_point') }} <span
                                                                    class="text-danger">*</span>
                                                        </label>
                                                        <input type="number" name="currency_decimal_point"
                                                               value="{{ $settings->firstWhere('key_name', 'currency_decimal_point')?->value ?? old('currency_decimal_point') }}"
                                                               id="currency_decimal" class="form-control"
                                                               placeholder="{{ translate('Ex: 2') }}" tabindex="17"
                                                               required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="border-top"></div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="d-flex flex-column h-100">
                                            <div class="mb-20">
                                                <h5 class="mb-2">
                                                    {{ translate('Trade_License_Setup') }}
                                                </h5>
                                                <p class="mb-0">
                                                    {{ translate('setup_your_shop_trade_license_setup__from_here') }}
                                                </p>
                                            </div>
                                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 flex-grow-1">
                                                <label for="trade_licence_number" class="mb-2">
                                                    {{ translate('Trade_license_Number') }}
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" name="trade_licence_number"
                                                       value="{{ $settings->firstWhere('key_name', 'trade_licence_number')?->value ?? old('trade_licence_number') }}"
                                                       id="trade_licence_number"
                                                       class="form-control {{ $settings->firstWhere('key_name', 'trade_licence_number')?->value ?? old('trade_licence_number') ? 'dark-border' : '' }}"
                                                       placeholder="{{ translate('Ex: 9.43896534') }}" tabindex="18"
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="d-flex flex-column h-100">
                                            <div class="mb-20">
                                                <h5 class="mb-2">
                                                    {{ translate('Company_Copyright') }}
                                                </h5>
                                                <p class="mb-0">
                                                    {{ translate('setup_your_company_copyright_content_from_here') }}
                                                </p>
                                            </div>
                                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 flex-grow-1">
                                                <label for="copyright_text" class="mb-2">
                                                    {{ translate('Copyright_Content') }}
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <div class="character-count">
                                                    <textarea name="copyright_text" id="copyright_text" cols="30"
                                                              rows="1"
                                                              class="form-control character-count-field {{ $settings->firstWhere('key_name', 'copyright_text')?->value ?? old('copyright_text') ? 'dark-border' : '' }}"
                                                              placeholder="{{ translate('Copyright@email.com') }}"
                                                              maxlength="100" data-max-character="100"
                                                              tabindex="19"
                                                              required>{{ $settings->firstWhere('key_name', 'copyright_text')?->value ?? old('copyright_text') }}</textarea>
                                                    <span
                                                            class="d-flex justify-content-end">{{ translate('0/100') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="border-top"></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mb-20">
                                            <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                                {{translate('Admin_Panel_Color')}}
                                            </h5>
                                            <p class="mb-0">{{ translate('setup_your_admin_panel_color_from_here') }}</p>
                                        </div>
                                        <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                            <div class="row g-3">
                                                <div class="col-sm-6 col-md-4 col-xxl-2">
                                                    <div
                                                            class="bg-white border rounded p-2 d-flex justify-content-between align-items-center gap-2 h-100 form-group mb-0">
                                                        <div class="fs-12 text-dark">
                                                            <div class="mb-1">{{ translate('Primary') }}</div>
                                                            <div class="color_code text-uppercase">
                                                                {{ $settings->firstWhere('key_name', 'website_color')->value['primary'] ?? null }}
                                                            </div>
                                                        </div>
                                                        <div class="color-code-wrapper border rounded">
                                                            <input type="color" name="website_color[primary]"
                                                                   class="form-control form-control_color" tabindex="20"
                                                                   value="{{ $settings->firstWhere('key_name', 'website_color')->value['primary'] ?? null }}">
                                                            <div class="hover-div text-white">
                                                                <div
                                                                        class="d-flex justify-content-center align-items-center h-100">
                                                                    <i class="bi bi-pencil"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-md-4 col-xxl-2">
                                                    <div
                                                            class="bg-white border rounded p-2 d-flex justify-content-between align-items-center gap-2 h-100 form-group mb-0">
                                                        <div class="fs-12 text-dark">
                                                            <div class="mb-1">{{ translate('secondary') }}</div>
                                                            <div class="color_code text-uppercase">
                                                                {{ $settings->firstWhere('key_name', 'website_color')->value['secondary'] ?? null }}
                                                            </div>
                                                        </div>
                                                        <div class="color-code-wrapper border rounded">
                                                            <input type="color" name="website_color[secondary]"
                                                                   class="form-control form-control_color" tabindex="21"
                                                                   value="{{ $settings->firstWhere('key_name', 'website_color')->value['secondary'] ?? null }}">
                                                            <div class="hover-div text-white">
                                                                <div
                                                                        class="d-flex justify-content-center align-items-center h-100">
                                                                    <i class="bi bi-pencil"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-md-4 col-xxl-2">
                                                    <div
                                                            class="bg-white border rounded p-2 d-flex justify-content-between align-items-center gap-2 h-100 form-group mb-0">
                                                        <div class="fs-12 text-dark">
                                                            <div class="mb-1">{{ translate('Background') }}</div>
                                                            <div class="color_code text-uppercase">
                                                                {{ $settings->firstWhere('key_name', 'website_color')->value['background'] ?? null }}
                                                            </div>
                                                        </div>
                                                        <div class="color-code-wrapper border rounded">
                                                            <input type="color" name="website_color[background]"
                                                                   class="form-control form-control_color" tabindex="22"
                                                                   value="{{ $settings->firstWhere('key_name', 'website_color')->value['background'] ?? null }}">
                                                            <div class="hover-div text-white">
                                                                <div
                                                                        class="d-flex justify-content-center align-items-center h-100">
                                                                    <i class="bi bi-pencil"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-md-4 col-xxl-2">
                                                    <div
                                                            class="bg-white border rounded p-2 d-flex justify-content-between align-items-center gap-2 h-100 form-group mb-0">
                                                        <div class="fs-12 text-dark">
                                                            <div class="mb-1">{{ translate('Text_Dark') }}</div>
                                                            <div class="color_code text-uppercase">
                                                                {{ $settings->firstWhere('key_name', 'text_color')->value['primary'] ?? null }}
                                                            </div>
                                                        </div>
                                                        <div class="color-code-wrapper border rounded">
                                                            <input type="color" name="text_color[primary]"
                                                                   class="form-control form-control_color" tabindex="23"
                                                                   value="{{ $settings->firstWhere('key_name', 'text_color')->value['primary'] ?? null }}">
                                                            <div class="hover-div text-white">
                                                                <div
                                                                        class="d-flex justify-content-center align-items-center h-100">
                                                                    <i class="bi bi-pencil"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-md-4 col-xxl-2">
                                                    <div
                                                            class="bg-white border rounded p-2 d-flex justify-content-between align-items-center gap-2 h-100 form-group mb-0">
                                                        <div class="fs-12 text-dark">
                                                            <div class="mb-1">{{ translate('Text_Medium') }}</div>
                                                            <div class="color_code text-uppercase">
                                                                {{ $settings->firstWhere('key_name', 'text_color')->value['secondary'] ?? null }}
                                                            </div>
                                                        </div>
                                                        <div class="color-code-wrapper border rounded">
                                                            <input type="color" name="text_color[secondary]"
                                                                   class="form-control form-control_color" tabindex="24"
                                                                   value="{{ $settings->firstWhere('key_name', 'text_color')->value['secondary'] ?? null }}">
                                                            <div class="hover-div text-white">
                                                                <div
                                                                        class="d-flex justify-content-center align-items-center h-100">
                                                                    <i class="bi bi-pencil"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-md-4 col-xxl-2">
                                                    <div
                                                            class="bg-white border rounded p-2 d-flex justify-content-between align-items-center gap-2 h-100 form-group mb-0">
                                                        <div class="fs-12 text-dark">
                                                            <div class="mb-1">{{ translate('Text_Light') }}</div>
                                                            <div class="color_code text-uppercase">
                                                                {{ $settings->firstWhere('key_name', 'text_color')->value['light'] ?? null }}
                                                            </div>
                                                        </div>
                                                        <div class="color-code-wrapper border rounded">
                                                            <input type="color" name="text_color[light]"
                                                                   class="form-control form-control_color" tabindex="25"
                                                                   value="{{ $settings->firstWhere('key_name', 'text_color')->value['light'] ?? null }}">
                                                            <div class="hover-div text-white">
                                                                <div
                                                                        class="d-flex justify-content-center align-items-center h-100">
                                                                    <i class="bi bi-pencil"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="border-top"></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mb-20">
                                            <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                                {{translate('Logos_&_icons')}}
                                            </h5>
                                            <p class="mb-0">{{ translate('Supported Formats: .png') }}
                                                ({{ translate('for loading .gif only') }}
                                                ). {{ translate(key: 'Max_Size: {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]) }}</p>
                                        </div>
                                        <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                            <div class="row gy-4">
                                                <div class="col-lg-6 col-xl-3">
                                                    <div
                                                            class="image-upload-card bg-white card card-body px-4 py-3 h-100"
                                                            id="header-logo-card">
                                                        <div class="text-center">
                                                            <div class="mb-4">
                                                                <label for="" class="fw-medium mb-2">
                                                                    {{ translate('Business_Logo') }}
                                                                    <span class="text-danger">*</span>
                                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                       data-bs-toggle="tooltip"
                                                                       title="{{translate('upload_your_main_brand_logo._this_will_appear_in_the_website_header,_dashboard,_and_key_brand_areas_across_the_platform.')}}"></i>
                                                                </label>
                                                                <p class="fs-12 mb-0">
                                                                    {{ translate('Upload_your_main_brand_logo.') }}
                                                                </p>
                                                            </div>
                                                            <div class="d-flex justify-content-center">
                                                                <div
                                                                        class="upload-file auto cmn_focus rounded-10 max-w-100">
                                                                    <input type="file" name="header_logo"
                                                                           class="upload-file__input"
                                                                           accept=".png" tabindex="26"
                                                                           data-max-upload-size="{{ readableUploadMaxFileSize('image') }}"
                                                                            {{ $settings?->firstWhere('key_name', 'header_logo')?->value ? '' : 'required' }}
                                                                    >
                                                                    <span
                                                                            class="edit-btn {{ $settings?->firstWhere('key_name', 'header_logo')?->value ? 'show' : '' }}">
                                                                        <img
                                                                                src="{{ dynamicAsset('public/assets/admin-module/img/svg/edit-circle.svg') }}"
                                                                                alt="" class="svg">
                                                                    </span>
                                                                    <div
                                                                            class="upload-file__img d-flex justify-content-center align-items-center ratio-3-1">
                                                                        <div class="upload-file__textbox text-center">
                                                                            <img width="34" height="34"
                                                                                 src="{{ onErrorImage(
                                                                                    $settings?->firstWhere('key_name', 'header_logo')?->value,
                                                                                    dynamicStorage('storage/app/public/business') . '/' . $settings?->firstWhere('key_name', 'header_logo')?->value,
                                                                                    dynamicAsset('public/assets/admin-module/img/svg/document-upload.svg'),
                                                                                    'business/',
                                                                                ) }}"
                                                                                 alt="" class="svg">
                                                                            <div
                                                                                    class="mt-2 fw-medium fs-10 text-info {{ $settings?->firstWhere('key_name', 'header_logo')?->value ? 'd-none' : '' }}">
                                                                                {{ translate('Add_Image') }}
                                                                            </div>
                                                                        </div>
                                                                        <img class="upload-file__img__img"
                                                                             loading="lazy"
                                                                             style="display: none;" alt="">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="fs-12 mt-3">
                                                                <span>{{ translate('Ratio') }} : <strong>3:1</strong></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div class="col-lg-6 col-xl-3">
                                                    <div
                                                            class="image-upload-card bg-white card card-body px-4 py-3 h-100"
                                                            id="favicon-card">
                                                        <div class="text-center">
                                                            <div class="mb-4">
                                                                <label for="" class="fw-medium mb-2">
                                                                    {{ translate('Website_Favicon') }}
                                                                    <span class="text-danger">*</span>
                                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                       data-bs-toggle="tooltip"
                                                                       title="{{translate('upload_a_small_icon_(usually_16×16_or_32×32_px)_that_appears_on_browser_tabs_and_bookmarks_for_brand_recognition.')}}"></i>
                                                                </label>
                                                                <p class="fs-12 mb-0">
                                                                    {{ translate('displayed_in_the_browser_tab.') }}
                                                                </p>
                                                            </div>

                                                            <div class="d-flex justify-content-center">
                                                                <div
                                                                        class="upload-file auto cmn_focus rounded-10 max-w-100">
                                                                    <input type="file" name="favicon"
                                                                           class="upload-file__input"
                                                                           accept=".png" tabindex="27"
                                                                           data-max-upload-size="{{ readableUploadMaxFileSize('image') }}"
                                                                            {{ $settings?->firstWhere('key_name', 'favicon')?->value ? '' : 'required' }}>
                                                                    <span
                                                                            class="edit-btn {{ $settings?->firstWhere('key_name', 'favicon')?->value ? 'show' : '' }}">
                                                                        <img
                                                                                src="{{ dynamicAsset('public/assets/admin-module/img/svg/edit-circle.svg') }}"
                                                                                alt="" class="svg">
                                                                    </span>
                                                                    <div
                                                                            class="upload-file__img d-flex justify-content-center align-items-center">
                                                                        <div class="upload-file__textbox text-center">
                                                                            <img width="34" height="34"
                                                                                 src="{{ onErrorImage(
                                                                                    $settings?->firstWhere('key_name', 'favicon')?->value,
                                                                                    dynamicStorage('storage/app/public/business') . '/' . $settings?->firstWhere('key_name', 'favicon')?->value,
                                                                                    dynamicAsset('public/assets/admin-module/img/svg/document-upload.svg'),
                                                                                    'business/',
                                                                                ) }}"
                                                                                 alt="" class="svg">
                                                                            <div
                                                                                    class="mt-2 fw-medium fs-10 text-info {{ $settings?->firstWhere('key_name', 'favicon')?->value ? 'd-none' : '' }}">
                                                                                {{ translate('Add_Image') }}
                                                                            </div>
                                                                        </div>
                                                                        <img class="upload-file__img__img"
                                                                             loading="lazy"
                                                                             style="display: none;" alt="">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="fs-12 mt-3">
                                                                <span>{{ translate('Ratio') }} : <strong>1:1</strong></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div class="col-lg-6 col-xl-3">
                                                    <div
                                                            class="image-upload-card bg-white card card-body px-4 py-3 h-100"
                                                            id="footer-logo-card">
                                                        <div class="text-center">
                                                            <div class="mb-4">
                                                                <label for="" class="fw-medium mb-2">
                                                                    {{ translate('Website_Footer_logo') }}
                                                                    <span class="text-danger">*</span>
                                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                       data-bs-toggle="tooltip"
                                                                       title="{{translate('upload_the_logo_displayed_in_the_footer_area_of_your_website._usually_a_simplified_or_monochrome_version_of_your_main_logo.')}}"></i>
                                                                </label>
                                                                <p class="fs-12 mb-0">
                                                                    {{ translate('Shown_at_website_bottom.') }}
                                                                </p>
                                                            </div>

                                                            <div class="d-flex justify-content-center">
                                                                <div
                                                                        class="upload-file auto cmn_focus rounded-10 max-w-100">
                                                                    <input type="file" name="footer_logo"
                                                                           class="upload-file__input"
                                                                           accept=".png" tabindex="28"
                                                                           data-max-upload-size="{{ readableUploadMaxFileSize('image') }}"
                                                                            {{ $settings?->firstWhere('key_name', 'footer_logo')?->value ? '' : 'required' }}
                                                                    >
                                                                    <span
                                                                            class="edit-btn {{ $settings?->firstWhere('key_name', 'footer_logo')?->value ? 'show' : '' }}">
                                                                        <img
                                                                                src="{{ dynamicAsset('public/assets/admin-module/img/svg/edit-circle.svg') }}"
                                                                                alt="" class="svg">
                                                                    </span>

                                                                    <div
                                                                            class="upload-file__img d-flex justify-content-center align-items-center ratio-3-1">
                                                                        <div class="upload-file__textbox text-center">
                                                                            <img width="34" height="34"
                                                                                 src="{{ onErrorImage(
                                                                                    $settings?->firstWhere('key_name', 'footer_logo')?->value,
                                                                                    dynamicStorage('storage/app/public/business') . '/' . $settings?->firstWhere('key_name', 'footer_logo')?->value,
                                                                                    dynamicAsset('public/assets/admin-module/img/svg/document-upload.svg'),
                                                                                    'business/',
                                                                                ) }}"
                                                                                 alt="" class="svg">
                                                                            <div
                                                                                    class="mt-2 fw-medium fs-10 text-info {{ $settings?->firstWhere('key_name', 'footer_logo')?->value ? 'd-none' : '' }}">
                                                                                {{ translate('Add_Image') }}
                                                                            </div>
                                                                        </div>

                                                                        <img class="upload-file__img__img"
                                                                             loading="lazy"
                                                                             style="display: none;" alt="">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="fs-12 mt-3">
                                                                <span>{{ translate('Ratio') }} : <strong>3:1</strong></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div class="col-lg-6 col-xl-3">
                                                    <div
                                                            class="image-upload-card bg-white card card-body px-4 py-3 h-100"
                                                            id="preloader-card">
                                                        <div class="text-center">
                                                            <div class="mb-4">
                                                                <label for="" class="fw-medium mb-2">
                                                                    {{ translate('Loading_gif') }}
                                                                    <span class="text-danger">*</span>
                                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                       data-bs-toggle="tooltip"
                                                                       title="{{translate('upload_a_loading_animation_(gif_format)_that_will_appear_while_pages_or_ride_details_are_loading,_enhancing_user_experience.')}}"></i>
                                                                </label>
                                                                <p class="fs-12 mb-0">
                                                                    {{ translate('During_loading_or_transitions.') }}
                                                                </p>
                                                            </div>

                                                            <div class="d-flex justify-content-center">
                                                                <div
                                                                        class="upload-file auto cmn_focus rounded-10 max-w-100">
                                                                    <input type="file" name="preloader"
                                                                           class="upload-file__input" accept=".gif"
                                                                           tabindex="29"
                                                                           data-max-upload-size="{{ readableUploadMaxFileSize('image') }}"
                                                                            {{ $settings?->firstWhere('key_name', 'preloader')?->value ? '' : 'required' }}
                                                                    >
                                                                    <span
                                                                            class="edit-btn {{ $settings?->firstWhere('key_name', 'preloader')?->value ? 'show' : '' }}">
                                                                        <img
                                                                                src="{{ dynamicAsset('public/assets/admin-module/img/svg/edit-circle.svg') }}"
                                                                                alt="" class="svg">
                                                                    </span>
                                                                    <div
                                                                            class="upload-file__img d-flex justify-content-center align-items-center">
                                                                        <div class="upload-file__textbox text-center">
                                                                            <img width="34" height="34"
                                                                                 src="{{ onErrorImage(
                                                                                    $settings?->firstWhere('key_name', 'preloader')?->value,
                                                                                    dynamicStorage('storage/app/public/business') . '/' . $settings?->firstWhere('key_name', 'preloader')?->value,
                                                                                    dynamicAsset('public/assets/admin-module/img/svg/document-upload.svg'),
                                                                                    'business/',
                                                                                ) }}"
                                                                                 alt="" class="svg">
                                                                            <div
                                                                                    class="mt-2 fw-medium fs-10 text-info {{ $settings?->firstWhere('key_name', 'preloader')?->value ? 'd-none' : '' }}">
                                                                                {{ translate('Add_Image') }}
                                                                            </div>
                                                                        </div>
                                                                        <img class="upload-file__img__img"
                                                                             loading="lazy"
                                                                             style="display: none;" alt="">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="fs-12 mt-3">
                                                                <span>{{ translate('Ratio') }} : <strong>1:1</strong></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="footer-sticky">
                <div class="container-fluid">
                    <div class="btn--container justify-content-end py-4">
                        <button type="submit" class="btn btn-primary text-capitalize cmn_focus"
                                tabindex="30">{{ translate('save_information') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- End Main Content -->


    {{--    Maintencemode modal --}}
    <div class="modal fade" id="maintenance-mode-modal" tabindex="-1" aria-labelledby="exampleModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="mb-0">
                        <i class="tio-notifications-alert mr-1"></i>
                        {{ translate('System Maintenance') }}
                    </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="{{ route('admin.business.setup.info.maintenance') }}">
                        <?php
                        $maintenanceMode = businessConfig('maintenance_mode')?->value == 1 ? 1 : 0;
                        $selectedMaintenanceSystem = businessConfig('maintenance_system_setup')?->value ?? [];
                        $selectedMaintenanceDuration = businessConfig('maintenance_duration_setup')?->value;
                        $selectedMaintenanceMessage = businessConfig('maintenance_message_setup')?->value;
                        ?>
                    <div class="modal-body">
                        @csrf
                        <div class="d-flex flex-column gap-4">
                            <div class="border-bottom px-4 py-3">
                                <div class="row g-3 align-items-center">
                                    <div class="col-sm-6 col-md-8">
                                        *{{ translate('By turning on maintenance mode Control your all system & function') }}
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <div
                                                class="d-flex justify-content-between align-items-center border rounded px-3 py-2">
                                            <div class="text-body">{{ translate('Maintenance Mode') }}</div>
                                            <label class="switcher ml-auto mb-0">
                                                <input type="checkbox" class="switcher_input" name="maintenance_mode"
                                                       id="maintenance-mode-checkbox"
                                                        {{ $maintenanceMode ? 'checked' : '' }}>
                                                <span class="switcher_control"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="px-4">
                                <div class="row mb-4">
                                    <div class="d-flex align-items-center">
                                        <div class="col-xl-4">
                                            <h5 class="mb-2">{{ translate('Select System') }}</h5>
                                            <p>{{ translate('Select the systems you want to temporarily deactivate for maintenance') }}
                                            </p>
                                        </div>
                                        <div class="col-xl-8">
                                            <div class="border p-3">
                                                <div class="d-flex flex-wrap gap-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input system-checkbox"
                                                               name="all_system"
                                                               type="checkbox"
                                                               {{ in_array('user_app', $selectedMaintenanceSystem) && in_array('driver_app', $selectedMaintenanceSystem)
                                                                   ? 'checked'
                                                                   : '' }}
                                                               id="allSystem">
                                                        <label class="form-check-label"
                                                               for="allSystem">{{ translate('All System') }}</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input system-checkbox" name="user_app"
                                                               type="checkbox"
                                                               {{ in_array('user_app', $selectedMaintenanceSystem) ? 'checked' : '' }}
                                                               id="userApp">
                                                        <label class="form-check-label"
                                                               for="userApp">{{ translate('User App') }}</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input system-checkbox"
                                                               name="driver_app"
                                                               type="checkbox"
                                                               {{ in_array('driver_app', $selectedMaintenanceSystem) ? 'checked' : '' }}
                                                               id="driverApp">
                                                        <label class="form-check-label"
                                                               for="driverApp">{{ translate('Driver App') }}</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="d-flex align-items-center">
                                        <div class="col-xl-4">
                                            <h5 class="mb-2">{{ translate('Maintenance Date') }}
                                                & {{ translate('Time') }}</h5>
                                            <p>{{ translate('Choose the maintenance mode duration for your selected system.') }}
                                            </p>
                                        </div>
                                        <div class="col-xl-8">
                                            <div class="border p-3">
                                                <div class="d-flex flex-wrap gap-5 mb-3">
                                                    <div>
                                                        <input type="radio" name="maintenance_duration"
                                                               {{ $selectedMaintenanceDuration == '' || (isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'one_day') ? 'checked' : '' }}
                                                               value="one_day" id="one_day">
                                                        <label class="form-check-label"
                                                               for="one_day">{{ translate('For 24 Hours') }}</label>
                                                    </div>
                                                    <div>
                                                        <input type="radio" name="maintenance_duration"
                                                               {{ isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'one_week' ? 'checked' : '' }}
                                                               value="one_week" id="one_week">
                                                        <label class="form-check-label"
                                                               for="one_week">{{ translate('For 1 Week') }}</label>
                                                    </div>
                                                    <div>
                                                        <input type="radio" name="maintenance_duration"
                                                               {{ isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'until_change' ? 'checked' : '' }}
                                                               value="until_change" id="until_change">
                                                        <label class="form-check-label"
                                                               for="until_change">{{ translate('Until I change') }}</label>
                                                    </div>
                                                    <div>
                                                        <input type="radio" name="maintenance_duration"
                                                               {{ isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'customize' ? 'checked' : '' }}
                                                               value="customize" id="customize">
                                                        <label class="form-check-label"
                                                               for="customize">{{ translate('Customize') }}</label>
                                                    </div>
                                                </div>
                                                <div class="row start-and-end-date g-3 mt-0">
                                                    <div class="col-md-6">
                                                        <label class="form-label">{{ translate('Start Date') }}</label>
                                                        <input type="datetime-local" class="form-control"
                                                               name="start_date" id="startDate"
                                                               value="{{ old('start_date', $selectedMaintenanceDuration['start_date'] ?? '') }}"
                                                               required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">{{ translate('End Date') }}</label>
                                                        <input type="datetime-local" class="form-control"
                                                               name="end_date"
                                                               id="endDate"
                                                               value="{{ old('end_date', $selectedMaintenanceDuration['end_date'] ?? '') }}"
                                                               required>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <small id="dateError" class="form-text text-danger"
                                                               style="display: none;">{{ translate('Start date cannot be greater than end date.') }}</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="px-4">
                            <div id="advanceFeatureButtonDiv">
                                <div class="d-flex justify-content-center">
                                    <a href="#" id="advanceFeatureToggle"
                                       class="d-block mb-3 maintenance-advance-feature-button text-primary text-underline fw-bold">{{ translate('Advance Feature') }}</a>
                                </div>
                            </div>

                            <div class="row" id="advanceFeatureSection" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <div class="col-xl-4">
                                        <h5 class="mb-2">{{ translate('Maintenance Massage') }}</h5>
                                        <p>{{ translate('Select & type what massage you want to see your selected system when maintenance mode is active.') }}
                                        </p>
                                    </div>
                                    <div class="col-xl-8">
                                        <div class="border p-3">
                                            <div class="mb-4">
                                                <label class="mb-2">{{ translate('Show Contact Info') }}</label>
                                                <div class="d-flex flex-wrap gap-5 mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="business_number"
                                                               {{ isset($selectedMaintenanceMessage) && $selectedMaintenanceMessage['business_number'] == 1 ? 'checked' : '' }}
                                                               id="businessNumber">
                                                        <label class="form-check-label"
                                                               for="businessNumber">{{ translate('Business Number') }}</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="business_email"
                                                               {{ isset($selectedMaintenanceMessage) && $selectedMaintenanceMessage['business_email'] == 1 ? 'checked' : '' }}
                                                               id="businessEmail">
                                                        <label class="form-check-label"
                                                               for="businessEmail">{{ translate('Business Email') }}</label>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="mb-4">
                                                <label class="mb-2">{{ translate('Maintenance Message') }}
                                                    <i class="tio-info-outined" data-bs-toggle="tooltip"
                                                       title="{{ translate('The maximum character limit is 100') }}">
                                                    </i>
                                                </label>
                                                <input type="text" class="form-control" name="maintenance_message"
                                                       placeholder="We're Working On Something Special!" maxlength="100"
                                                       value="{{ $selectedMaintenanceMessage['maintenance_message'] ?? '' }}">
                                            </div>
                                            <div>
                                                <label class="mb-2">{{ translate('Message Body') }}
                                                    <i class="tio-info-outined" data-bs-toggle="tooltip"
                                                       title="{{ translate('The maximum character limit is 255') }}">
                                                    </i>
                                                </label>
                                                <textarea class="form-control" name="message_body" maxlength="255"
                                                          rows="3"
                                                          placeholder="{{ translate('Our system is currently undergoing maintenance to bring you an even tastier experience.') }}">{{ isset($selectedMaintenanceMessage) && $selectedMaintenanceMessage['message_body'] ? $selectedMaintenanceMessage['message_body'] : '' }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="showLessButton" style="display: none;">
                                <div class="d-flex justify-content-center mt-4">
                                    <a href="#" id="seeLessToggle"
                                       class="d-block mb-3 maintenance-advance-feature-button text-primary text-underline fw-bold">{{ translate('See Less') }}</a>
                                </div>
                            </div>
                        </div>
                        <div class="px-4">
                            <div class="btn--container justify-content-end">
                                <button type="button" class="btn btn-secondary cmn_reset" data-dismiss="modal"
                                        id="cancelButton">{{ translate('Cancel') }}</button>
                                <button type="{{ env('APP_MODE') != 'demo' ? 'submit' : 'button' }}"
                                        class="btn btn-primary call-demo">{{ translate('Save') }}</button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>

@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/map-bind-with-address.js') }}"></script>

    <script>
        "use strict";

        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#business_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_donot_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
    </script>
    <script>
        $('.maintenance-mode-show').click(function () {
            $('#maintenance-mode-modal').modal('show');
        });

        $(document).ready(function () {
            var initialMaintenanceMode = $('#maintenance-mode-input').is(':checked');

            $('#maintenance-mode-modal').on('show.bs.modal', function () {
                var initialMaintenanceModeModel = $('#maintenance-mode-input').is(':checked');
                $('#maintenance-mode-checkbox').prop('checked', initialMaintenanceModeModel);
            });

            $('#maintenance-mode-modal').on('hidden.bs.modal', function () {
                $('#maintenance-mode-input').prop('checked', initialMaintenanceMode);
                updateDataState();
            });

            $('#cancelButton').click(function () {
                $('#maintenance-mode-input').prop('checked', initialMaintenanceMode);
                $('#maintenance-mode-modal').modal('hide');
                updateDataState();
            });

            $('#maintenance-mode-checkbox').change(function () {
                $('#maintenance-mode-input').prop('checked', $(this).is(':checked'));
                updateDataState();
            });

            // Update the data-state attribute for the maintenance mode text
            function updateDataState() {
                const isChecked = $('#maintenance-mode-input').is(':checked');
                $('#switcher-text').attr('data-state', isChecked ? 'On' : 'Off');
            }

            // Call this function on page load to set the initial state
            updateDataState();
        });

        $(document).ready(function () {
            $('#advanceFeatureToggle').click(function (event) {
                event.preventDefault();
                $('#advanceFeatureSection').show();
                $('#showLessButton').show();
                $('#advanceFeatureButtonDiv').hide();
            });

            $('#seeLessToggle').click(function (event) {
                event.preventDefault();
                $('#advanceFeatureSection').hide();
                $('#showLessButton').hide();
                $('#advanceFeatureButtonDiv').show();
            });

            $('#allSystem').change(function () {
                var isChecked = $(this).is(':checked');
                $('.system-checkbox').prop('checked', isChecked);
            });

            // If any other checkbox is unchecked, also uncheck "All System"
            $('.system-checkbox').not('#allSystem').change(function () {
                if (!$(this).is(':checked')) {
                    $('#allSystem').prop('checked', false);
                } else {
                    // Check if all system-related checkboxes are checked
                    if ($('.system-checkbox').not('#allSystem').length === $('.system-checkbox:checked')
                        .not('#allSystem').length) {
                        $('#allSystem').prop('checked', true);
                    }
                }
            });

            $(document).ready(function () {
                var startDate = $('#startDate');
                var endDate = $('#endDate');
                var dateError = $('#dateError');

                function updateDatesBasedOnDuration(selectedOption) {
                    if (selectedOption === 'one_day' || selectedOption === 'one_week') {
                        var now = new Date();
                        var timezoneOffset = now.getTimezoneOffset() * 60000;
                        var formattedNow = new Date(now.getTime() - timezoneOffset).toISOString().slice(0,
                            16);

                        if (selectedOption === 'one_day') {
                            var end = new Date(now);
                            end.setDate(end.getDate() + 1);
                        } else if (selectedOption === 'one_week') {
                            var end = new Date(now);
                            end.setDate(end.getDate() + 7);
                        }

                        var formattedEnd = new Date(end.getTime() - timezoneOffset).toISOString().slice(0,
                            16);

                        startDate.val(formattedNow).prop('readonly', false).prop('required', true);
                        endDate.val(formattedEnd).prop('readonly', false).prop('required', true);
                        $('.start-and-end-date').removeClass('opacity');
                        dateError.hide();
                    } else if (selectedOption === 'until_change') {
                        startDate.val('').prop('readonly', true).prop('required', false);
                        endDate.val('').prop('readonly', true).prop('required', false);
                        $('.start-and-end-date').addClass('opacity');
                        dateError.hide();
                    } else if (selectedOption === 'customize') {
                        startDate.prop('readonly', false).prop('required', true);
                        endDate.prop('readonly', false).prop('required', true);
                        $('.start-and-end-date').removeClass('opacity');
                        dateError.hide();
                    }
                }

                function validateDates() {
                    var start = new Date(startDate.val());
                    var end = new Date(endDate.val());
                    if (start > end) {
                        dateError.show();
                        startDate.val('');
                        endDate.val('');
                    } else {
                        dateError.hide();
                    }
                }

                // Initial load
                var selectedOption = $('input[name="maintenance_duration"]:checked').val();
                updateDatesBasedOnDuration(selectedOption);

                // When maintenance duration changes
                $('input[name="maintenance_duration"]').change(function () {
                    var selectedOption = $(this).val();
                    updateDatesBasedOnDuration(selectedOption);
                });

                // When start date or end date changes
                $('#startDate, #endDate').change(function () {
                    $('input[name="maintenance_duration"][value="customize"]').prop('checked',
                        true);
                    startDate.prop('readonly', false).prop('required', true);
                    endDate.prop('readonly', false).prop('required', true);
                    validateDates();
                });
            });

        });
    </script>
    <script>
        // Get all upload-file input elements
        document.querySelectorAll('.upload-file__input').forEach(function (input) {
            input.addEventListener('change', function (event) {
                var file = event.target.files[0];
                var card = event.target.closest('.upload-file');
                var textbox = card.querySelector('.upload-file__textbox');
                var imgElement = card.querySelector('.upload-file__img__img');

                if (file) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        textbox.style.display = 'none';
                        imgElement.src = e.target.result;
                        imgElement.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
    <script>
        "use strict";
        $(document).ready(function () {
            //----- sticky footer
            $(window).on('scroll', function () {
                const $footer = $('.footer-sticky');
                const scrollPosition = $(window).scrollTop() + $(window).height();
                const documentHeight = $(document).height();

                if (scrollPosition >= documentHeight - 5) {
                    $footer.addClass('no-shadow');
                } else {
                    $footer.removeClass('no-shadow');
                }
            });
        });
    </script>

    <script>
        "use strict";
        initializePhoneInput("#business_contact_num", "#business_contact_num-hidden-element");
        initializePhoneInput("#business_support_number", "#business_support_number-hidden-element");
    </script>
@endpush
