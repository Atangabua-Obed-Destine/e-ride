@extends('adminmodule::layouts.master')

@section('title', translate('Business_Info'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-20 fw-bold mb-3 text-capitalize">{{translate('business_management')}}</h2>
            <div class="mb-3">
                <div class="">
                    @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-12">
                    <div class="card card-body">
                        <div class="d-flex flex-md-nowrap flex-wrap align-items-center justify-content-between gap-3">
                            <div class="w-0 flex-grow-1">
                                <h4 class="text-capitalize mb-2">{{translate('Driver_Self_Registration')}}</h4>
                                <div class="fs-14">
                                    {{ translate('allow_drivers_to_register_directly_from_the_driver_app.') }}
                                </div>
                            </div>
                            <label class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                                <label for="customerVerification" class="fs-14 lh-1 d-block cursor-pointer text-dark">{{translate('status')}}</label>
                                <label class="switcher cmn_focus rounded-pill">
                                    <input class="switcher_input update-business-setting" id="driverSelfRegistration"
                                           name="driver_self_registration" type="checkbox"
                                           data-name="driver_self_registration"
                                           data-type="business_information" tabindex="4"
                                           data-url="{{ route('admin.business.setup.update-business-setting') }}"
                                           {{--                                           data-icon="{{ ($settings->firstWhere('key_name', 'driver_self_registration')->value ?? 0) == 0 ? dynamicAsset('public/assets/admin-module/img/level-up-on.png') : dynamicAsset('public/assets/admin-module/img/level-up-off.png') }}"--}}
                                           data-title="{{ $driverSelfRegistrationStatus == 0 ? translate('Want to enable driver self-registration') : translate('Want to disable driver self-registration')}}?"
                                           data-sub-title="{{ $driverSelfRegistrationStatus == 0 ? translate(' If you turn on the driver self-registration, drivers can register directly. This streamlines onboarding.') : translate('If you turn off the driver self-registration, drivers will require an internal administrator to register their accounts.') }}"
                                        {{ $driverSelfRegistrationStatus == 1 ? 'checked' : '' }}
                                    >
                                    <span class="switcher_control"></span>
                                </label>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card card-body h-100">
                        <div class="mb-20">
                            <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                {{translate('Driver_review')}}
                            </h5>
                            <p class="mb-0">{{ translate('allow_drivers_to_rate_or_review_customers_after_trips.') }}</p>
                        </div>
                        <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                            <label class="text-capitalize mb-2">
                                {{translate('Driver_can_review_customer')}}
                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                   data-bs-toggle="tooltip"
                                   title="{{translate('enable_this_to_allow_passengers_to_rate_and_review_drivers_after_completing_a_trip.')}}"></i>
                            </label>
                            <label class="form-control d-flex align-items-center justify-content-between">
                                <label for="driverReview"
                                       class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                                <label class="switcher cmn_focus rounded-pill">
                                    <input class="switcher_input" name="{{ DRIVER_REVIEW }}" type="checkbox"
                                           data-type="{{ DRIVER_SETTINGS }}" id="driverReview" tabindex="3"
                                        {{ $settings->firstWhere('key_name', DRIVER_REVIEW)?->value == 1 ? 'checked' : '' }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card card-body h-100">
                        <div class="mb-20">
                            <h5 class="d-flex align-items-center gap-2 justify-content-between text-capitalize mb-2">
                                {{translate('Driver_Level')}}
                                <a href="{{ route('admin.driver.level.index') }}"
                                   class="text-link fs-12 fw-semibold d-flex gap-1 align-items-center">
                                    {{translate('Go_to_settings')}}
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </h5>
                            <p class="mb-0">{{ translate('manage_driver_level_features_like_experience_or_performance.') }}</p>
                        </div>
                        <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                            <label class="text-capitalize mb-2">
                                {{translate('Active_level_feature')}}
                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                   data-bs-toggle="tooltip"
                                   title="{{translate('define_performance-based_levels_or_tiers_(e.g.,_bronze,_silver,_gold)_to_reward_drivers_for_activity,_ratings,_and_experience.')}}"></i>
                            </label>
                            <label class="form-control d-flex align-items-center justify-content-between">
                                <label for="customerLevel"
                                       class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                                <label class="switcher cmn_focus rounded-pill">
                                    <input class="switcher_input update-business-setting" id="customerLevel"
                                           name="driver_level" type="checkbox" data-name="driver_level"
                                           data-type="driver_settings" tabindex="4"
                                           data-url="{{ route('admin.business.setup.update-business-setting') }}"
                                           data-icon="{{ ($settings->firstWhere('key_name', 'driver_level')->value ?? 0) == 0 ? dynamicAsset('public/assets/admin-module/img/level-up-on.png') : dynamicAsset('public/assets/admin-module/img/level-up-off.png') }}"
                                           data-title="{{ ($settings->firstWhere('key_name', 'driver_level')->value ?? 0) == 0 ? translate('By Turning ON Level Feature') . '?' : translate('By Turning OFF Level Feature') . '?' }}"
                                           data-sub-title="{{ ($settings->firstWhere('key_name', 'driver_level')->value ?? 0) == 0 ? translate('If you turn ON level feature, customer will see this feature on app.') : translate('If you turning off customer level feature, please do it at the beginning stage of business. Because once driver use this feature & you will off this feature they will be confused or worried about it.') }}"
                                        {{ ($settings->firstWhere('key_name', 'driver_level')->value ?? 0) == 1 ? 'checked' : '' }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card card-body h-100">
                        <div class="mb-20">
                            <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                {{translate('Loyalty_Point')}}
                            </h5>
                            <p class="mb-0">{{ translate('reward_drivers_with_points_for_completed_trips_or_actions.') }}</p>
                        </div>
                        <form action="{{ route('admin.business.setup.driver.store') }}?type=loyalty_point"
                              id="loyalty_point_form" method="post">
                            @csrf
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="mb-20">
                                    <label class="text-capitalize mb-2">
                                        {{translate('Driver_can_earn_loyalty_point')}}
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{translate('enable_loyalty_points_for_drivers_based_on_completed_rides,_ratings,_or_milestones._points_can_be_used_for_rewards_or_benefits.')}}"></i>
                                    </label>
                                    <label class="form-control d-flex align-items-center justify-content-between">
                                        <label for="loyalty_point_switch"
                                               class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                                        <label class="switcher cmn_focus rounded-pill">
                                            <input class="switcher_input" type="checkbox" name="loyalty_points[status]"
                                                   id="loyalty_point_switch" tabindex="5"
                                                {{ $settings->firstWhere('key_name', 'loyalty_points')?->value['status'] == 1 ? 'checked' : ''}}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </label>
                                </div>
                                <div>
                                    <div>
                                        <label for="equivalent_points"
                                               class="mb-2">{{ getCurrencyFormat(1) . ' ' . translate('equivalent_to_points') }}
                                            <span class="text-danger">*</span></label>
                                        <input type="tel" name="loyalty_points[value]" id="equivalent_points"
                                               class="form-control" required pattern="[1-9][0-9]{0,200}"
                                               title="Please input integer value. Ex:1,2,22,10"
                                               placeholder="{{ translate('Ex: 5') }}"
                                               value="{{ $settings->where('key_name', 'loyalty_points')->first()?->value['points'] }}"
                                               tabindex="6">
                                    </div>
                                </div>
                            </div>
                            <div class="btn--container justify-content-end mt-4">
                                <button type="reset" class="btn btn-secondary min-w-120 cmn_focus"
                                        tabindex="7">{{ translate('reset') }}</button>
                                <button type="submit" class="btn btn-primary min-w-120 cmn_focus"
                                        tabindex="8">{{ translate('save') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card card-body h-100">
                        <div class="mb-20">
                            <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                {{translate('Parcel_Limit_Setup')}}
                            </h5>
                            <p class="mb-0">{{ translate('control_how_many_parcels_a_driver_can_accept_at_once.') }}</p>
                        </div>
                        <form
                            action="{{ route('admin.business.setup.driver.store') }}?type=maximum_parcel_request_accept_limit"
                            id="maximumParcelRequestAcceptLimit" method="post">
                            @csrf
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="mb-20">
                                    <label class="text-capitalize mb-2">
                                        {{translate('Max._Parcel_Req._Accept_Limit')}}
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{translate('set_a_limit_on_how_many_parcel_deliveries_a_driver_can_handle_at_once_to_ensure_safe_and_efficient_service.')}}"></i>
                                    </label>
                                    <label class="form-control d-flex align-items-center justify-content-between">
                                        <label for="maximum_parcel_request_accept_limit"
                                               class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                                        <label class="switcher">
                                            <input class="switcher_input cmn_focus rounded-pill" type="checkbox"
                                                   name="maximum_parcel_request_accept_limit[status]"
                                                   id="maximum_parcel_request_accept_limit" tabindex="9"
                                                {{ $settings->firstWhere('key_name', 'maximum_parcel_request_accept_limit')?->value['status'] == 1 ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </label>
                                </div>
                                <div>
                                    <div>
                                        <label for="equivalent_points"
                                               class="mb-2">{{ translate('Limit') }} <span class="text-danger">*</span></label>
                                        <input type="tel" name="maximum_parcel_request_accept_limit[value]"
                                               class="form-control" required pattern="[1-9][0-9]{0,200}"
                                               title="Please input integer value. Ex:1,2,22,10"
                                               placeholder="{{ translate('Ex: 2') }}" tabindex="10"
                                               value={{ $settings->where('key_name', 'maximum_parcel_request_accept_limit')->first()?->value['limit'] }}>
                                    </div>
                                </div>
                            </div>
                            <div class="btn--container justify-content-end mt-4">
                                <button type="reset" class="btn btn-secondary min-w-120 cmn_focus"
                                        tabindex="11">{{ translate('reset') }}</button>
                                <button type="submit" class="btn btn-primary min-w-120 cmn_focus"
                                        tabindex="12">{{ translate('save') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card card-body h-100 collapsible-card-body">
                        <div class="d-flex gap-2 justify-content-between align-items-center">
                            <div>
                                <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                    {{translate('Update_Vehicle')}}
                                </h5>
                                <p class="mb-0">{{ translate('when_driver_update_a_existing_vehicle_which_info_need_admin_approval.') }}</p>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <a href="javascript:" class="text-link fw-semibold fs-12 text-nowrap d-flex view-btn">
                                    <span>{{ translate('View') }}</span> <span><i class="tio-arrow-downward"></i></span>
                                </a>

                                <label class="switcher rounded-pill cmn_focus">
                                    <input class="switcher_input collapsible-card-switcher update-business-setting"
                                           id="updateVehicle" type="checkbox" name="update_vehicle_status"
                                           data-name="update_vehicle_status" data-type="{{ DRIVER_SETTINGS }}"
                                           data-url="{{ route('admin.business.setup.update-business-setting') }}"
                                           data-icon="{{ ($settings->firstWhere('key_name', 'update_vehicle_status')?->value ?? 0) == 1 ? dynamicAsset('public/assets/admin-module/img/media/car5.png') : dynamicAsset('public/assets/admin-module/img/media/car4.png') }}"
                                           data-title="{{ translate('Are you sure?') }}" tabindex="13"
                                           data-sub-title="{{ ($settings->firstWhere('key_name', 'update_vehicle_status')?->value ?? 0) == 1 ? translate('Do you want to turn OFF update vehicle?') : translate('Do you want to turn ON update vehicle?') }}"
                                           data-confirm-btn="{{ ($settings->firstWhere('key_name', 'update_vehicle_status')?->value ?? 0) == 1 ? translate('Turn Off') : translate('Turn On') }}"
                                        {{ ($settings->firstWhere('key_name', 'update_vehicle_status')?->value ?? 0) == 1 ? 'checked' : '' }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                        </div>
                        <div class="collapsible-card-content mt-4">
                            <form
                                action="{{ route('admin.business.setup.driver.vehicle-update') . '?type=' . DRIVER_SETTINGS }}"
                                id="updateVehicleForm" method="POST">
                                @csrf
                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                    <div class="d-flex gap-3 justify-content-between flex-wrap flex-column flex-sm-row">
                                        @foreach(UPDATE_VEHICLE as $updateVehicle)
                                            <label class="custom-checkbox rounded">
                                                <input type="checkbox" class="module-checkbox text-capitalize"
                                                       name="update_vehicle[]"
                                                       value="{{$updateVehicle}}"
                                                       tabindex="14" {{in_array($updateVehicle, $settings->firstWhere('key_name', 'update_vehicle')?->value ??[], true) ? "checked" : ""}}>
                                                {{translate($updateVehicle)}}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="btn--container justify-content-end mt-4">
                                    <button type="reset" class="btn btn-secondary min-w-120 cmn_focus"
                                            tabindex="15">{{ translate('reset') }}</button>
                                    <button type="submit" class="btn btn-primary min-w-120 cmn_focus"
                                            tabindex="16">{{ translate('save') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    @php
                        $decimalPoint = (int)businessConfig('currency_decimal_point', BUSINESS_INFORMATION)?->value ?? 2;
                    @endphp
                    <div class="card card-body h-100 collapsible-card-body">
                        <div class="d-flex gap-2 justify-content-between align-items-center">
                            <div>
                                <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                    {{translate('Driver_Cash_in_Hand_Setup')}}
                                </h5>
                                <p class="mb-0">{{ translate('set_how_much_cash_drivers_can_keep_before_paying_the_company.') }}</p>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <a href="javascript:" class="text-link fw-semibold fs-12 text-nowrap d-flex view-btn">
                                    <span>{{ translate('View') }}</span> <span><i
                                            class="{{ ($settings->firstWhere('key_name', 'cash_in_hand_setup_status')?->value ?? 0) == 1 ? 'tio-arrow-downward' : 'tio-arrow-upward' }}"></i></span>
                                </a>

                                <label class="switcher rounded-pill cmn_focus">
                                    <input class="switcher_input collapsible-card-switcher update-business-setting"
                                           id="cashInHandSetup" type="checkbox" name="cash_in_hand_setup_status"
                                           data-name="cash_in_hand_setup_status" data-type="{{ DRIVER_SETTINGS }}"
                                           data-url="{{ route('admin.business.setup.update-business-setting') }}"
                                           data-icon="{{ dynamicAsset('public/assets/admin-module/img/svg/cash-in-hand.svg') }}"
                                           data-title="{{ ($settings->firstWhere('key_name', 'cash_in_hand_setup_status')?->value ?? 0) == 1 ? translate('Turn Off Cash In Hand Limit') . '?' : translate('Turn On Cash In Hand Limit') . '?' }}"
                                           tabindex="17"
                                           data-sub-title="{{ ($settings->firstWhere('key_name', 'cash_in_hand_setup_status')?->value ?? 0) == 1 ? translate('If you turn off this feature, driver will not have a limit on holding cash') : translate('If you turn on this feature, driver will have a limit on holding cash')  }}"
                                           data-confirm-btn="{{ ($settings->firstWhere('key_name', 'cash_in_hand_setup_status')?->value ?? 0) == 1 ? translate('Turn Off') : translate('Turn On') }}"
                                        {{ ($settings->firstWhere('key_name', 'cash_in_hand_setup_status')?->value ?? 0) == 1 ? 'checked' : '' }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                        </div>
                        <div class="collapsible-card-content mt-4">
                            <form action="{{ route('admin.business.setup.driver.update-cash-in-hand-setup')}}"
                                  method="POST">
                                @csrf
                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                    <div class="row g-lg-4 g-3">
                                        <div class="col-md-6">
                                            <div class="">
                                                <label for="" class="mb-2">
                                                    {{ translate('Max_Amount_to_Hold_Cash') }}
                                                    ({{ ((session()->get('currency_symbol') ?? businessConfig('currency_symbol', 'business_information')?->value) ?? "$") }}
                                                    )
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input type="text"
                                                       value="{{ $settings->firstWhere('key_name', 'max_amount_to_hold_cash')?->value ?? 100 }}"
                                                       class="form-control"
                                                       name="max_amount_to_hold_cash"
                                                       placeholder="{{ translate('Ex') }} : 5"
                                                       tabindex="18"
                                                       data-decimal="{{ $decimalPoint }}"
                                                       required
                                                >
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="">
                                                <label for="" class="mb-2">
                                                    {{ translate('Minimum Amount to Pay') }}
                                                    ({{ ((session()->get('currency_symbol') ?? businessConfig('currency_symbol', 'business_information')?->value) ?? "$")}}
                                                    )</label>
                                                <span class="text-danger">*</span>
                                                <input type="text"
                                                       value="{{ $settings->firstWhere('key_name', 'min_amount_to_pay')?->value ?? 20 }}"
                                                       class="form-control"
                                                       name="min_amount_to_pay"
                                                       placeholder="{{ translate('Ex') }} : 5"
                                                       tabindex="19"
                                                       data-decimal="{{ $decimalPoint }}"
                                                       required
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="btn--container justify-content-end mt-4">
                                    <button type="reset" class="btn btn-secondary min-w-120 cmn_focus"
                                            tabindex="20">{{ translate('reset') }}</button>
                                    <button type="submit" class="btn btn-primary min-w-120 cmn_focus"
                                            tabindex="21">{{ translate('save') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card card-body h-100 collapsible-card-body">
                        <div class="d-flex gap-2 justify-content-between align-items-center">
                            <div>
                                <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                    {{translate('Driver_Identity_Verification')}}
                                </h5>
                                <p class="mb-0">{{ translate('Activate face verification to ensure that only authorized drivers can access the platform.') }}</p>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <a href="javascript:" class="text-link fw-semibold fs-12 text-nowrap d-flex view-btn">
                                    <span>{{ translate('View') }}</span> <span><i
                                            class="{{ ($faceVerificationSettings->firstWhere('key_name', 'driver_identity_verification_status')?->value ?? 0) == 1 ? 'tio-arrow-downward' : 'tio-arrow-upward' }}"></i></span>
                                </a>

                                <label class="switcher rounded-pill cmn_focus"
                                       @if(!$faceVerificationApiStatus)
                                           data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('Please turn on Face Verification Api status first') }}"
                                    @endif
                                >
                                    <input class="switcher_input collapsible-card-switcher update-business-setting"
                                           @if($faceVerificationApiStatus)
                                               id="driverIdentityVerification" type="checkbox"
                                           name="driver_identity_verification_status"
                                           data-name="driver_identity_verification_status"
                                           data-type="{{ FACE_VERIFICATION_SETTINGS }}"
                                           data-url="{{ route('admin.business.setup.update-business-setting') }}"
                                           data-icon="{{($faceVerificationSettings->firstWhere('key_name', 'driver_identity_verification_status')?->value ?? 0) == 0 ? dynamicAsset('public/assets/admin-module/img/svg/info-circle-green.svg') : dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                                           data-title="{{ ($faceVerificationSettings->firstWhere('key_name', 'driver_identity_verification_status')?->value ?? 0) == 1 ? translate('Turn Off Driver Identity Verification') . '?' : translate('Turn On Driver Identity Verification') . '?' }}"
                                           tabindex="22"
                                           data-sub-title="{{ ($faceVerificationSettings->firstWhere('key_name', 'driver_identity_verification_status')?->value ?? 0) == 1 ? translate('If you turn off this feature, driver will not have to verify his identity') : translate('If you turn on this feature, driver will have to verify his identity')  }}"
                                           data-confirm-btn="{{ ($faceVerificationSettings->firstWhere('key_name', 'driver_identity_verification_status')?->value ?? 0) == 1 ? translate('Turn Off') : translate('Turn On') }}"
                                           @else
                                               disabled
                                        @endif

                                        {{ ($faceVerificationSettings->firstWhere('key_name', 'driver_identity_verification_status')?->value ?? 0) == 1 ? 'checked' : '' }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                        </div>
                        <div class="collapsible-card-content mt-4 ">
                            <form action="{{ route('admin.business.setup.driver.update-identity-verification') }}"
                                  method="POST">
                                @csrf
                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                    <div class="mb-3">
                                        <h5 class="text-capitalize mb-2">
                                            {{translate('When to Initiate Face Verification')}}
                                        </h5>
                                        <p class="mb-0">{{ translate('Define when identity verification will be required for drivers.') }}</p>
                                    </div>
                                    <div class="bg-white rounded p-3">
                                        <div class="row g-3">
                                            <div class="col-lg-6">
                                                <div class="checked-label-wrapper d-flex gap-2 lh-1">
                                                    <input type="checkbox" name="initiate_face_verification[]"
                                                           value="during_sign_up" id="initiate_verification"
                                                           tabindex="23"
                                                        {{ in_array('during_sign_up', $faceVerificationSettings->firstWhere('key_name', 'initiate_face_verification')?->value ?? []) || is_null($faceVerificationSettings->firstWhere('key_name', 'initiate_face_verification')) ? 'checked' : '' }}
                                                    >
                                                    <label for="initiate_verification">
                                                        <div class="text-dark checked-label-bold mb-2">
                                                            {{ translate('Initiate Verification During Sign-Up') }}
                                                        </div>
                                                        <p class="fs-12 mb-0">{{ translate('Drivers need to verify their identity as part of the registration process.') }}</p>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="checked-label-wrapper d-flex gap-2 lh-1">
                                                    <input type="checkbox" name="initiate_face_verification[]"
                                                           value="at_intervals" id="trigger_verification" tabindex="24"
                                                        {{ in_array('at_intervals', $faceVerificationSettings->firstWhere('key_name', 'initiate_face_verification')?->value ?? []) ? 'checked' : '' }}
                                                    >
                                                    <label for="trigger_verification">
                                                        <div class="text-dark checked-label-bold mb-2">
                                                            {{ translate('Trigger Verification at Intervals') }}
                                                        </div>
                                                        <p class="fs-12 mb-0">{{ translate('Drivers need to confirm their identity again after a set period of time.') }}</p>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="trigger-content d--none">
                                        <hr>
                                        <h5 class="text-capitalize mb-3">{{ translate('Choose When to Trigger Periodically') }}</h5>
                                        <div class="bg-white rounded p-3">
                                            <div class="row g-3">
                                                <div class="col-lg-6">
                                                    <div class="checked-label-wrapper d-flex gap-2 lh-1">
                                                        <input type="radio" name="choose_verification_when_to_trigger"
                                                               value="within_a_time_period" id="trigger_within_period"
                                                               tabindex="25"
                                                            {{ $faceVerificationSettings->firstWhere('key_name', 'choose_verification_when_to_trigger')?->value == 'within_a_time_period' ? 'checked' : '' }}
                                                        >
                                                        <label for="trigger_within_period">
                                                            <div class="text-dark checked-label-bold mb-1">
                                                                {{ translate('Trigger Within a Time Period') }}
                                                            </div>
                                                            <p class="fs-12 mb-0">{{ translate('Drivers will be able to verify their identity again after a specific time frame.') }}</p>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="checked-label-wrapper d-flex gap-2 lh-1">
                                                        <input type="radio" name="choose_verification_when_to_trigger"
                                                               value="before_going_online" id="trigger_before_online"
                                                               tabindex="26"
                                                            {{ $faceVerificationSettings->firstWhere('key_name', 'choose_verification_when_to_trigger')?->value == 'before_going_online' || is_null($faceVerificationSettings->firstWhere('key_name', 'choose_verification_when_to_trigger')) ? 'checked' : '' }}
                                                        >
                                                        <label for="trigger_before_online">
                                                            <div class="text-dark checked-label-bold mb-1">
                                                                {{ translate('Trigger when switching to online') }}
                                                            </div>
                                                            <p class="fs-12 mb-0">{{ translate('Drivers will see the identity verification option when switching from offline to online.') }}</p>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                class="trigger_within_period_content p-lg-3 p-2 rounded bg-F6F6F6 mt-3 d--none">
                                                <div class="row g-3 align-items-end">
                                                    <div class="col-lg-6">
                                                        <div>
                                                            <label class="text-capitalize mb-2">
                                                                {{ translate('Verification Frequency') }}
                                                                <span class="text-danger">*</span>
                                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                   data-bs-toggle="tooltip"
                                                                   aria-label="{{ translate('Verification Frequency') }}"
                                                                   title="{{ translate('Verification Frequency') }}"></i>
                                                            </label>
                                                            <div class="input-group input--group">
                                                                <input type="number"
                                                                       name="trigger_frequency_time_within_a_time_period"
                                                                       id="" step="1" min="1" max="99999999"
                                                                       class="form-control"
                                                                       value="{{ $faceVerificationSettings->firstWhere('key_name', 'trigger_frequency_time_within_a_time_period')?->value ?? 1 }}"
                                                                       placeholder="{{ translate('Example:_5') }}"
                                                                       tabindex="28">
                                                                <select id="" class="form-select"
                                                                        name="trigger_frequency_time_type_within_a_time_period"
                                                                        tabindex="29">
                                                                    @foreach(['day' => 'days', 'hour' => 'hours', 'minute' => 'minutes'] as $key => $value)
                                                                        <option value="{{ $key }}"
                                                                            {{ $faceVerificationSettings->firstWhere('key_name', 'trigger_frequency_time_type_within_a_time_period')?->value == $key ? 'selected' : '' }}
                                                                        >
                                                                            {{ translate($value) }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div
                                                            class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark bg-warning bg-opacity-10">
                                                            <i class="bi bi-info-circle-fill text-warning"></i>
                                                            <span>
                                                                {{ translate('Drivers will be asked to verify their identity when they') }}
                                                                <strong>{{ translate('Open the App') }}</strong>
                                                                {{ translate('after the set time period.') }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="btn--container justify-content-end mt-4">
                                    <button type="reset" class="btn btn-secondary min-w-120 cmn_focus"
                                            tabindex="30">{{ translate('reset') }}</button>
                                    <button type="submit" class="btn btn-primary min-w-120 cmn_focus"
                                            tabindex="31">{{ translate('save') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script
        src="{{ dynamicAsset('public/assets/admin-module/js/business-management/business-setup/driver.js') }}"></script>

    <script>
        "use strict";
        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#driverReview').on('change', function () {
            let url = '{{ route('admin.business.setup.update-business-setting') }}';
            updateBusinessSetting(this, url)
        })

        function updateBusinessSetting(obj, url) {
            if (!permission) {
                toastr.error('{{ translate('you_donot_have_enough_permission_to_update_this_settings') }}');

                let checked = $(obj).prop("checked");
                let status = checked === true ? 1 : 0;
                if (status === 1) {
                    $('#' + obj.id).prop('checked', false)

                } else if (status === 0) {
                    $('#' + obj.id).prop('checked', true)
                }
                return;
            }

            let value = $(obj).prop('checked') === true ? 1 : 0;
            let name = $(obj).attr('name');
            let type = $(obj).data('type');
            let checked = $(obj).prop("checked");
            let status = checked === true ? 1 : 0;


            Swal.fire({
                title: '{{ translate('are_you_sure') }}?',
                text: '{{ translate('want_to_change_status') }}',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--bs-primary)',
                cancelButtonColor: 'default',
                cancelButtonText: '{{ translate('no') }}',
                confirmButtonText: '{{ translate('yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        data: {
                            value: value,
                            name: name,
                            type: type
                        },
                        success: function () {
                            toastr.success("{{ translate('status_changed_successfully') }}");
                        },
                        error: function () {
                            if (status === 1) {
                                $('#' + obj.id).prop('checked', false)
                            } else if (status === 0) {
                                $('#' + obj.id).prop('checked', true)
                            }
                            toastr.error("{{ translate('status_change_failed') }}");
                        }
                    });
                } else {

                    if (status === 1) {
                        $('#' + obj.id).prop('checked', false)
                    } else if (status === 0) {
                        $('#' + obj.id).prop('checked', true)
                    }
                }
            })
        }

        // Collapse card with switcher
        function collapsibleCard(thisInput) {
            let $card = thisInput.closest('.collapsible-card-body');
            let $content = $card.children('.collapsible-card-content');
            if (thisInput.prop('checked')) {
                $content.slideDown();
            } else {
                $content.slideUp();
            }
        }

        $('.collapsible-card-switcher').each(function () {
            collapsibleCard($(this))
        });
        // Collapse card with switcher ends
    </script>

    <script>
        $('#loyalty_point_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_donot_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
    </script>
    <script>
        $(document).ready(function () {
            const $switcher = $('#maximum_parcel_request_accept_limit');
            const $inputField = $('input[name="maximum_parcel_request_accept_limit[value]"]');

            // Initial state
            $inputField.prop('disabled', !$switcher.is(':checked'));

            // Listen for changes
            $switcher.on('change', function () {
                $inputField.prop('disabled', !$switcher.is(':checked'));
            });

            $('#maximumParcelRequestAcceptLimit').on('reset', function () {
                setTimeout(() => {
                    $inputField.prop('disabled', !$switcher.is(':checked'));
                }, 10);
            });
        });

        function chooseWhenTrigger() {
            $('#trigger_verification').is(':checked') ? $('.trigger-content').slideDown(300) : $('.trigger-content').slideUp(300);
            $('#trigger_within_period').is(':checked') ? $('.trigger_within_period_content').slideDown(300) : $('.trigger_within_period_content').slideUp(300);
            $('#trigger_randomly').is(':checked') ? $('.trigger_randomly_content').slideDown(300) : $('.trigger_randomly_content').slideUp(300);
        }

        chooseWhenTrigger();

        $(document).ready(function () {
            chooseWhenTrigger();

            $('input[type="checkbox"], input[type="radio"]').on('change', function () {
                chooseWhenTrigger();
            });

            $('form').on('reset', function () {
                setTimeout(function () {
                    chooseWhenTrigger();
                }, 0);
            });
        });

    </script>
@endpush
