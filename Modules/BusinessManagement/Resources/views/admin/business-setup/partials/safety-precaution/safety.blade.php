<div class="tab-pane fade {{Request::is('admin/business/setup/safety-precaution/safety-alert') ? 'show active' : ''}}">

    <div class="card mb-3">
        <div class="card-body p-0 collapsible-card-body">
            <div class="p-20 d-flex align-items-center justify-content-between gap-2">
                <div class="w-0 flex-grow-1">
                    <h5 class="mb-2">{{ translate('Safety_Feature') }}</h5>
                    <p class="fs-14 mb-0"> {{ translate('Enabling this allows Customers & Drivers to send safety alerts directly to administrators and given Emergency numbers')}}
                        . </p>
                </div>
                <a href="javascript:"
                   class="text-info fw-semibold fs-12 text-nowrap d-flex safety_view-btn">
                    <span class="text-underline">{{ translate('View') }}</span>
                    <span><i class="tio-arrow-downward"></i> </span>
                </a>
                <label class="switcher rounded-pill cmn_focus">
                    <input class="switcher_input collapsible-card-switcher2 update-business-setting"
                           id="safetyFeatureStatus" tabindex="1"
                           type="checkbox"
                           name="safety_feature_status"
                           data-name="safety_feature_status"
                           data-type="{{SAFETY_FEATURE_SETTINGS}}"
                           data-url="{{route('admin.business.setup.update-business-setting')}}"
                           data-icon=" {{($settings->firstWhere('key_name', 'safety_feature_status')->value ?? 0) == 1 ? dynamicAsset('public/assets/admin-module/img/svg/turn-off-safety-feature.svg') : dynamicAsset('public/assets/admin-module/img/svg/turn-on-safety-feature.svg')}}"
                           data-title="{{translate('Are you sure')}}?"
                           data-sub-title="{{
                                         ($settings->firstWhere('key_name', 'safety_feature_status')->value?? 0) == 1 ?
                                         translate('Do you want to turn Off '). "<b>" . translate('Safety Feature') ."</b>? " . translate("When it is off") . ', ' . translate('the customer and the driver can not send safety alert & communicate with emergency number.') :
                                         translate('Do you want to turn ON '). "<b>" . translate('Safety Feature') ."</b>? " . translate("When it is on") . ', ' . translate('the customer and the driver can send safety alert & communicate with emergency number.')
                                         }}"
                           data-confirm-btn="{{($settings->firstWhere('key_name', 'safety_feature_status')->value?? 0) == 1 ? translate('Turn Off') : translate('Turn On')}}"
                           data-target-content=".safety_view-card"
                        {{($settings->firstWhere('key_name', 'safety_feature_status')->value?? 0) == 1? 'checked' : ''}}
                    >
                    <span class="switcher_control"></span>
                </label>
            </div>
            <form action="{{ route('admin.business.setup.safety-precaution.store') }}" method="post">
                @csrf
                <div class="safety_view-card collapsible-card-content border-top">
                    <div class="p-20 rounded">
                        <div class="row g-3 py-xl-3">
                            <div class="col-md-4">
                                <h6 class="mb-2 fs-16">{{ translate('For Trip Delay') }}</h6>
                                <p class="fs-14 mb-0">{{ translate('Here you can set how long to wait before sending Safety Alert notifications after a trip is delayed') }}</p>
                            </div>
                            <div class="col-md-8">
                                <div class="p-4 rounded bg-F6F6F6">
                                    <div>
                                        <label
                                            class="form-label text-dark">{{ translate('Minimum Delay Time') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group input--group">
                                            <input type="number" min="1" max="9999999"
                                                   class="form-control" name="minimum_delay_time"
                                                   placeholder="Ex : 20" tabindex="2"
                                                   value="{{ $settings->firstWhere('key_name', 'for_trip_delay')?->value['minimum_delay_time'] ?? null }}"
                                                   required>
                                            <select class="form-select" name="time_format" required tabindex="3">
                                                <option
                                                    value="minute" {{ $settings->firstWhere('key_name', 'for_trip_delay')?->value['time_format'] == 'minute' ? 'selected' : '' }}> {{ translate('minute') }}</option>
                                                <option
                                                    value="hour" {{ $settings->firstWhere('key_name', 'for_trip_delay')?->value['time_format'] == 'hour' ? 'selected' : '' }}> {{ translate('hour') }}</option>
                                                <option
                                                    value="second" {{ $settings->firstWhere('key_name', 'for_trip_delay')?->value['time_format'] == 'second' ? 'selected' : '' }}> {{ translate('second') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="border-top my-4"></div>

                        <div class="row g-3 py-xl-3">
                            <div class="col-md-4">
                                <h6 class="mb-2 fs-16">{{ translate('After Trip Complete') }}</h6>
                                <p class="fs-14 mb-0">{{ translate('If toggle ON, after complete a trip within a certain time Customer and Drive can use safety feature') }}
                                    .</p>
                            </div>
                            <div class="col-md-8">
                                <div class="p-4 rounded bg-F6F6F6" id="after_trip_complete">
                                    <div class="collapsible-card-body3">
                                        <label
                                            class="form-label text-dark">{{ translate('Safety Feature After Trip Completed') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <div
                                            class="form-control cmn_focus rounded gap-2 align-items-center d-flex justify-content-between">
                                            <div
                                                class="d-flex align-items-center fw-medium gap-2 text-capitalize">
                                                {{ translate('status') }}
                                            </div>
                                            <div class="position-relative">
                                                <label class="switcher">
                                                    <input type="checkbox" name="safety_feature_active_status"
                                                           class="switcher_input collapsible-card-switcher3" tabindex="4"
                                                        {{ $settings->firstWhere('key_name', 'after_trip_complete')?->value['safety_feature_active_status'] == '1' ? 'checked' : '' }}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="safety_view-card collapsible-card-content3">
                                            <div class="pt-3">
                                                <label
                                                    class="form-label text-dark">
                                                    {{ translate('Set Time') }}
                                                    <span class="text-danger">*</span>
                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                       data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('Set the time frame for sending safety alerts after a trip is completed.') }}">
                                                    </i>
                                                </label>
                                                <div class="input-group input--group">
                                                    <input type="number" min="1" max="9999999" tabindex="5"
                                                           class="form-control" name="set_time"
                                                           value="{{ $settings->firstWhere('key_name', 'after_trip_complete')?->value['set_time'] ?? null }}"
                                                           placeholder="{{ translate('Ex : 20 Minutes') }}">
                                                    <select class="form-select" name="after_trip_complete_time_format" tabindex="6"
                                                            required>
                                                        <option
                                                            value="minute" {{ $settings->firstWhere('key_name', 'after_trip_complete_time_format')?->value == 'minute' ? 'selected' : '' }}> {{ translate('minute') }}</option>
                                                        <option
                                                            value="hour" {{ $settings->firstWhere('key_name', 'after_trip_complete_time_format')?->value == 'hour' ? 'selected' : '' }}> {{ translate('hour') }}</option>
                                                        <option
                                                            value="second" {{ $settings->firstWhere('key_name', 'after_trip_complete_time_format')?->value == 'second' ? 'selected' : '' }}> {{ translate('second') }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-3 justify-content-end mt-4">
                            <button type="reset" class="btn btn-light min-w-120 justify-content-center fw-semibold cmn_focus">
                                Reset
                            </button>
                            <button type="submit"  class="btn btn-primary min-w-120 text-uppercase cmn_focus" tabindex="7">{{ translate('submit') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <div id="emergncyNumberCard" class="safety_view-card card overflow-visible mb-3">
        <div class="card-body p-0 collapsible-card-body">
            <div class="p-20 d-flex align-items-center justify-content-between gap-2">
                <div class="w-0 flex-grow-1">
                    <h5 class="mb-2">{{ translate('Emergency Number for Call') }}</h5>
                    <p class="fs-14 mb-0"> {{ translate('Enable the option to direct calls to the designated emergency contact number for users')}}
                        , </p>
                </div>
                <a href="javascript:"
                   class="text-info fw-semibold fs-12 text-nowrap d-flex view-btn">
                    <span class="text-underline">{{ translate('View') }}</span>
                    <span><i class="tio-arrow-downward"></i> </span>
                </a>
                <label class="switcher cmn_focus rounded-pill">
                    <input class="switcher_input collapsible-card-switcher update-business-setting"
                           id="emergencyNumberCallForStatus"
                           type="checkbox" tabindex="8"
                           name="emergency_number_for_call_status"
                           data-name="emergency_number_for_call_status"
                           data-type="{{SAFETY_FEATURE_SETTINGS}}"
                           data-url="{{route('admin.business.setup.update-business-setting')}}"
                           data-icon=" {{($settings->firstWhere('key_name', 'emergency_number_for_call_status')->value ?? 0) == 1 ? dynamicAsset('public/assets/admin-module/img/svg/turn-off-emergency-number.svg') : dynamicAsset('public/assets/admin-module/img/svg/turn-on-emergency-number.svg')}}"
                           data-title="{{translate('Are you sure')}}?"
                           data-sub-title="{{
                                                ($settings->firstWhere('key_name', 'emergency_number_for_call_status')->value?? 0) == 1 ?
                                                 translate('Do you want to turn OFF Emergency Number for Call option for driver and customer')."? ":
                                                 translate('Do you want to turn ON Emergency Number for Call option for driver and customer')."? "
                                                  }}"
                           data-confirm-btn="{{($settings->firstWhere('key_name', 'emergency_number_for_call_status')->value?? 0) == 1 ? translate('Turn Off') : translate('Turn On')}}"
                        {{($settings->firstWhere('key_name', 'emergency_number_for_call_status')->value?? 0) == 1? 'checked' : ''}}
                    >
                    <span class="switcher_control"></span>
                </label>
            </div>
            <div class="collapsible-card-content border-top">
                <form id="emergencyNumberForCallForm">
                    @csrf
                    <div class="p-20 rounded">
                        <div class="">
                            <div class="p-sm-4 p-3 rounded bg-F6F6F6">
                                <div class="row gy-4">
                                    <div class="col-sm-8 col-lg-6">
                                        <label class="form-label text-dark">
                                            {{ translate('Choose Number Type') }}
                                            <span class="text-danger">*</span>
                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                               data-bs-toggle="tooltip"
                                               data-bs-title="{{ translate('Select whether the emergency contact is a personal, company, or authority number.') }}"></i>
                                        </label>
                                        <div class="d-flex flex-sm-nowrap flex-wrap align-items-center form-control h-auto gap-sm-0 gap-1 cmn_focus rounded">
                                            @foreach(GOVT_EMERGENCY_NUMBER_TYPE as $key => $number_type)
                                                <div class="flex-grow-1">
                                                    <input type="radio" id="{{ $key }}" tabindex="9"
                                                           name="choose_number_type"
                                                           value="{{ $key }}" {{ ($settings->firstWhere('key_name', 'emergency_govt_number_type')?->value ?? 'phone') == $key ? 'checked' : '' }}>
                                                    <label for="{{ $key }}"
                                                           class="media gap-2 align-items-center">
                                                        <span class="media-body">{{ translate($number_type) }}</span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-sm-4 col-lg-6 number-box">
                                        <label class="form-label text-dark">
                                            <span>
                                                {{ translate($settings->firstWhere('key_name', 'emergency_govt_number_type')?->value ?? 'phone'). ' ' . translate('Number') }}
                                            </span>
                                            <span class="text-danger">*</span>
                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                               data-bs-toggle="tooltip"
                                               data-bs-title="{{ translate('Enter the emergency phone number customers or drivers can call directly from the app.') }}"></i>
                                        </label>

                                        <div
                                            class="number-field {{$settings->firstWhere('key_name', 'emergency_govt_number_type')?->value == null || $settings->firstWhere('key_name', 'emergency_govt_number_type')?->value  == 'phone'  || $settings->firstWhere('key_name', 'emergency_govt_number_type')?->value  == 'telephone' ? '' : 'd-none' }}">
                                            <input type="tel" pattern="[0-9]{1,14}" tabindex="10"
                                                   value="{{ $settings->firstWhere('key_name', 'emergency_govt_number_type')?->value != 'hotline' ? $settings->firstWhere('key_name', 'emergency_govt_number_for_call')?->value : '' }}"
                                                   id="base_phone_number" name="emergency_govt_number_for_call_init"
                                                   class="form-control w-100 text-dir-start"
                                                   placeholder="Ex: xxxxx xxxxxx">
                                            <input type="hidden" id="base-hidden-element"
                                                   value="{{ $settings->firstWhere('key_name', 'emergency_govt_number_type')?->value != 'hotline' ? $settings->firstWhere('key_name', 'emergency_govt_number_for_call')?->value : '' }}"
                                                   name="emergency_govt_number_for_call" tabindex="11">
                                        </div>

                                        <div
                                            class="number-field-hotline {{ $settings->firstWhere('key_name', 'emergency_govt_number_type')?->value  == 'hotline' ? '' : 'd-none' }}">
                                            <input type="number"
                                                   name="emergency_govt_number_for_call_hotline"
                                                   class="form-control w-100 text-dir-start"
                                                   value="{{ $settings->firstWhere('key_name', 'emergency_govt_number_type')?->value == 'hotline' ? $settings->firstWhere('key_name', 'emergency_govt_number_for_call')?->value : '' }}"
                                                   pattern="[0-9]{1,14}"
                                                   placeholder="Ex: xxxxx xxxxxx" tabindex="12"
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 border-top pt-4 view-advance-content position-relative">
                                <label class="form-label fw-bold fs-16 mb-3">
                                    {{ translate('Other Number') }}
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                       data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('Specify the number at which users can make calls or send sms of their information') }}"></i>
                                </label>

                                <div class="collapsible-card-body">
                                        <div
                                            class="order-number-row-container d-flex gap-3 flex-column">
                                            @if($settings->firstWhere('key_name', 'emergency_other_numbers_for_call')?->value)
                                                @foreach($emergencyNumbers as $key => $value)
                                                    <div class="order-number-row rounded bg-F6F6F6 position-relative p-xxl-4 p-3 d-flex flex-sm-nowrap flex-wrap gap-3 align-items-end">
                                                        <div class="flex-shrink-0 fs-16 fw-bold mb-3">{{ $key + 1 }}.
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <label class="form-label text-dark">
                                                                {{ translate('Title') }}
                                                            </label>
                                                            <input type="text" class="form-control" tabindex="13"
                                                                   name="emergency_other_number_title[]"
                                                                   value="{{ $value['title'] ?? '' }}"
                                                                   placeholder="{{ translate('Ex: Medical') }}">
                                                        </div>
                                                        <div class="flex-grow-1 number-field-wrapper">
                                                            <label class="form-label text-dark">
                                                                {{ translate('Phone Number') }}
                                                            </label>
                                                            <div class="">
                                                                <input type="tel" pattern="[0-9]{1,14}" tabindex="14"
                                                                       id="phone_number{{ $key }}"
                                                                       class="form-control w-100 text-dir-start"
                                                                       name="emergency_other_number_init[]"
                                                                       value="{{ $value['number'] ?? '' }}"
                                                                       placeholder="{{ translate('Ex: xxxxx xxxxxx') }}">
                                                                <input type="hidden"
                                                                       id="hidden-element{{ $key }}"
                                                                       name="emergency_other_number[]"
                                                                       value="{{ $value['number'] ?? '' }}">
                                                            </div>
                                                        </div>
                                                        <div
                                                            class="flex-shrink-0 mb-2 cursor-pointer order-number-close">
                                                            <i class="bi bi-x-circle-fill fs-3 text-danger"></i>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endif
                                            <div class="order-number-row rounded bg-F6F6F6 position-relative p-xxl-4 p-3 d-flex flex-sm-nowrap flex-wrap gap-3 align-items-end"
                                                 data-extra-row="true">
                                                <div class="flex-shrink-0 fs-16 fw-bold mb-3">2.</div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-dark">
                                                        {{ translate('Title') }}
                                                    </label>
                                                    <input type="text" min="0" class="form-control" tabindex="15"
                                                           name="emergency_other_number_title[]"
                                                           placeholder="{{ translate('Ex : Medical') }}">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label class="form-label text-dark">
                                                        {{ translate('Phone Number') }}
                                                    </label>
                                                    <div class="">
                                                        <input type="tel" pattern="[0-9]{1,14}" tabindex="16"
                                                               id="phone_number"
                                                               value=""
                                                               name="emergency_other_number_init[]"
                                                               class="form-control w-100 text-dir-start"
                                                               placeholder="{{ translate('Ex: xxxxx xxxxxx') }}"
                                                        >
                                                        <input type="hidden" tabindex="17"
                                                               id="hidden-element"
                                                               name="emergency_other_number[]"
                                                               value=""
                                                        >
                                                    </div>
                                                </div>
                                                <div class="flex-shrink-0 mb-2 cursor-pointer order-number-close">
                                                    <i class="bi bi-x-circle-fill fs-3 text-danger"></i>
                                                </div>
                                                <div
                                                    class="flex-shrink-0 mb-2 cursor-pointer order-number-clone">
                                                    <i class="bi bi-plus-circle fs-3 text-primary"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            </div>

                            <div class="mt-4 d-flex justify-content-center">
                                <a href="#"
                                   class="text-info fw-semibold fs-12 text-nowrap d-flex gap-2 align-items-center view-advance-btn">
                                    <i class="bi bi-dash-lg"></i>
                                    <span class="text-underline">{{ translate('Hide Advance') }}</span>
                                </a>
                            </div>
                        </div>

                        <div class="d-flex gap-3 justify-content-end mt-4">
                            <button type="reset" class="btn btn-light min-w-120 justify-content-center fw-semibold cmn_focus">
                                Reset
                            </button>
                            <button type="submit"
                                    class="btn btn-primary text-uppercase cmn_focus min-w-120" tabindex="18">{{ translate('submit') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <div id="safetyAlertReasonsStatusCard" class="safety_view-card card mb-3">
        <div class="card-body p-0 collapsible-card-body">
            <div class="p-20 d-flex align-items-center justify-content-between gap-2">
                <div class="w-0 flex-grow-1">
                    <h5 class="mb-2">{{ translate('Safety Alert Reasons') }}</h5>
                    <p class="fs-14 mb-0"> {{ translate('If the toggle is turned ON, customers or drivers will see a list of reasons when sending a safety alert.')}}
                    </p>
                </div>
                <a href="javascript:"
                   class="text-info fw-semibold fs-12 text-nowrap d-flex view-btn">
                    <span class="text-underline">{{ translate('View') }}</span>
                    <span><i class="tio-arrow-downward"></i> </span>
                </a>
                <label class="switcher cmn_focus rounded-pill">
                    <input class="switcher_input collapsible-card-switcher update-business-setting"
                           id="safetyAlertReasonsStatus" tabindex="19"
                           type="checkbox"
                           name="safety_alert_reasons_status"
                           data-name="safety_alert_reasons_status"
                           data-type="{{SAFETY_FEATURE_SETTINGS}}"
                           data-url="{{route('admin.business.setup.update-business-setting')}}"
                           data-icon=" {{($settings->firstWhere('key_name', 'safety_alert_reasons_status')->value ?? 0) == 1 ? dynamicAsset('public/assets/admin-module/img/svg/turn-off-safety-alert-reason.svg') : dynamicAsset('public/assets/admin-module/img/svg/turn-on-safety-alert-reason.svg')}}"
                           data-title="{{translate('Are you sure')}}?"
                           data-sub-title="{{
                                            ($settings->firstWhere('key_name', 'safety_alert_reasons_status')->value?? 0) == 1 ?
                                             translate('Do you want to turn OFF Safety Alert Reasons option for customer and driver')."? ":
                                              translate('Do you want to turn ON Safety Alert Reasons option for customer and driver')."? "
                                              }}"
                           data-confirm-btn="{{($settings->firstWhere('key_name', 'safety_alert_reasons_status')->value?? 0) == 1 ? translate('Turn Off') : translate('Turn On')}}"
                        {{($settings->firstWhere('key_name', 'safety_alert_reasons_status')->value?? 0) == 1? 'checked' : ''}}
                    >
                    <span class="switcher_control"></span>
                </label>
            </div>
            <div class="collapsible-card-content border-top">
                <form
                    action="{{route('admin.business.setup.safety-precaution.safety-alert-reason.store')}}"
                    method="POST">
                    @csrf
                    <div class="p-20 rounded">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                    <div class="row gy-3 align-items-start">
                                        <div class="col-sm-6 ">
                                            <label for="title" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                                {{ translate('Safety Alert Reasons') }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <div class="character-count">
                                                <input id="title" name="reason" type="text" placeholder="{{ translate('Ex : Type your safety alert reason') }}" class="form-control character-count-field" maxlength="150" data-max-character="150" required="" tabindex="20">
                                                <span class="mt-1 d-block text-end text-muted">{{ translate('0/150') }}</span>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="reasonForWhom" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                                {{ translate('User Type') }}<span class="text-danger">*</span>
                                            </label>
                                            <select class="js-select cmn_focus" id="reasonForWhom"  name="reason_for_whom" required="" tabindex="21">
                                                <option value="" disabled="" selected="">{{ translate('Select user type') }}</option>
                                                <option value="{{ DRIVER }}">{{ translate(DRIVER) }}</option>
                                                <option value="{{ CUSTOMER }}">{{ translate(CUSTOMER) }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-3 justify-content-end mt-4">
                            <button type="reset" class="btn btn-light min-w-120 justify-content-center fw-semibold cmn_focus">
                                {{ translate('Reset') }}
                            </button>
                            <button type="submit"
                                    class="btn btn-primary text-uppercase cmn_focus min-w-120" tabindex="22">{{ translate('submit') }}</button>
                        </div>
                    </div>
                </form>
                <div class="border-top pb-4 mt-1"></div>
                <h3 class="px-4 fs-16 pb-1">{{ translate('Safety Alert Reasons List') }}</h3>

                <div class="table-responsive p-20">
                    <table class="table table-borderless align-middle">
                        <thead class="table-light align-middle">
                        <tr>
                            <th class="sl">{{ translate('SL') }}</th>
                            <th class="text-capitalize">{{ translate('Reason') }}</th>
                            <th class="text-capitalize">{{ translate('Reason for Whom') }}</th>
                            <th class="text-capitalize text-center">{{ translate('Status') }}</th>
                            <th class="text-center action">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($safetyAlertReasons as $key => $safetyAlertReason)
                            <tr>
                                <td class="sl">{{ $key + $safetyAlertReasons->firstItem() }}</td>
                                <td>
                                    <div class="min-w300 line--limit-2 targetToolTip"
                                         data-reason="{{ $safetyAlertReason->reason }}"
                                         data-bs-custom-class="des-tooltip"
                                         data-bs-html="true"
                                         data-bs-placement="bottom">
                                        {{ $safetyAlertReason->reason }}
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        {{ $safetyAlertReason->reason_for_whom }}
                                    </div>
                                </td>
                                <td>
                                    <label class="switcher mx-auto">
                                        <input class="switcher_input custom_status_change"
                                               type="checkbox"
                                               id="{{ $safetyAlertReason->id }}"
                                               data-url="{{ route('admin.business.setup.safety-precaution.safety-alert-reason.status') }}"
                                               data-title="{{$safetyAlertReason->is_active == 1 ? translate('Are you sure to turn off this Safety Alert Reason') : translate('Are you sure to turn On this Reason')}}?"
                                               data-sub-title="{{$safetyAlertReason->is_active == 1 ? translate('Once you turn off this Safety Alert Reason') . ', ' .translate('users will no longer see this Safety Alert Reason.') : translate('Once you turn On this Safety Alert Reason') . ', ' . translate('users will see this Safety Alert Reason.')}}"
                                               data-confirm-btn="{{$safetyAlertReason->is_active == 1  ? translate('Turn Off') : translate('Turn On')}}"
                                            {{ $safetyAlertReason->is_active == 1 ? "checked": ""  }}
                                        >
                                        <span class="switcher_control"></span>
                                    </label>
                                </td>
                                <td>
                                    <div
                                        class="d-flex justify-content-center gap-2 align-items-center">
                                        <button
                                            class="btn btn-outline-primary btn-action editSafetyAlertReasonData"
                                            data-id="{{$safetyAlertReason->id}}">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <button data-url="{{ route('admin.business.setup.safety-precaution.safety-alert-reason.delete', ['id' => $safetyAlertReason?->id]) }}"
                                                data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                data-title="{{ translate('Are you sure to delete this Safety Alert Reason') }}?"
                                                data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed from the list.') }}"
                                                data-confirm-btn="{{ translate('Yes, Delete') }}"
                                                data-cancel-btn="{{ translate('Not Now') }}"
                                                type="button"
                                                class="btn btn-outline-danger btn-action delete-button">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div
                                        class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                        <img
                                            src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}"
                                            alt="" width="100">
                                        <p class="text-center">{{translate('no_data_available')}}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
<div class="modal fade" id="editSafetyAlertReasonModal">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
        </div>
    </div>
</div>
