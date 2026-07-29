@extends('adminmodule::layouts.master')

@section('title', translate('referral_earning_setting'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{translate('business_management')}}</h2>

            <div class="mb-3">
                @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
            </div>
            <div class="d-flex flex-column gap-3">
                    <div class="card collapsible-card-body">
                        <form action="{{ route('admin.business.setup.referral-earning.store') }}" method="post"
                              enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="user_type" value="{{ CUSTOMER }}">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="w-0 flex-grow-1">
                                    <h5 class="mb-2">{{translate('Customer Referral Earning')}}</h5>
                                    <div class="fs-12">
                                        {{ translate("Allow customers to refer your app to friends and family using a unique code and earn rewards.") }}
                                    </div>
                                </div>
                                <div class="card-head-group d-flex align-items-center gap-2">
                                    <div class="view-btn cursor-pointer text-link fs-12 fw-semibold d-flex align-items-center gap-0">
                                        View
                                        <i class="tio-arrow-upward"></i>
                                    </div>
                                    <label class="switcher cmn_focus rounded-pill">
                                        <input class="switcher_input collapsible-card-switcher update-referral-setting"
                                               id="customerReferralEarningStatus" tabindex="1"
                                               type="checkbox"
                                               name="referral_earning_status"
                                               data-name="referral_earning_status"
                                               data-type="{{ CUSTOMER }}"
                                               data-url="{{route('admin.business.setup.referral-earning.update-referral-setting')}}"
                                               data-icon=" {{ ($customerSettings->firstWhere('key_name', 'referral_earning_status')->value?? 0) == 1 ? dynamicAsset('public/assets/admin-module/img/svg/turn-off-referral-earning.svg') : dynamicAsset('public/assets/admin-module/img/svg/turn-on-referral-earning.svg') }}"
                                               data-title="{{ ($customerSettings->firstWhere('key_name', 'referral_earning_status')->value?? 0) == 1 ? translate('Want to disable referral earnings') : translate('Want to enable referral earnings')}}?"
                                               data-sub-title="{{($customerSettings->firstWhere('key_name', 'referral_earning_status')->value?? 0) == 1 ? translate('If you disable referral earnings, customers will no longer receive rewards for referring new users') : translate('If you enable referral earnings, customers will be able to earn rewards by referring new users.')}}"
                                               data-confirm-btn="{{($customerSettings->firstWhere('key_name', 'referral_earning_status')->value?? 0) == 1 ? translate('Turn Off') : translate('Turn On')}}"
                                            {{ $customerSettings->firstWhere('key_name','referral_earning_status')?->value == 1 ? "checked" : "" }}
                                               data-target-content=".customer-referral-card"
                                        >
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body collapsible-card-content customer-referral-card">
                            <div class="">
                                <div class="">
                                    <div class="">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <h6 class="mb-2">{{translate("Who Share the code")}}</h6>
                                                <div class="fs-12">
                                                    {{translate("Set the reward for the customer who is sharing the code with friends & family to refer the app.")}}
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                                    <div>
                                                        <label
                                                            class="form-label">{{translate("Earnings to Each Referral")}}
                                                            ({{session()->get('currency_symbol') ?? '$'}})</label>
                                                        <input type="number" name="customer_share_code_earning" tabindex="2"
                                                               step="{{stepValue()}}"
                                                               value="{{ $customerSettings->firstWhere('key_name','share_code_earning')?->value ?? old('customer_share_code_earning', 0) }}"
                                                               class="form-control" placeholder="Ex : 2.50"
                                                               min="0" max="9999999">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-bottom my-4"></div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <h6 class="mb-2">{{translate("Who Use the code")}}</h6>
                                                <div class="fs-12">
                                                    {{translate("Set up the discount that the customer will receive when using the refer code In signup and taking their first ride")}}
                                                </div>
                                            </div>
                                            @php($useCodeEarning = $customerSettings->firstWhere('key_name','use_code_earning')?->value)
                                            <div class="col-md-8">
                                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                                    <div class="collapsible-card-body">
                                                        <label
                                                            class="form-label">{{translate("Discount on First Ride")}}
                                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                               data-bs-toggle="tooltip"
                                                               data-bs-title="{{translate("Allow customers to receive discounts who sign up using a referral code.")}}">
                                                            </i>
                                                        </label>
                                                        <div
                                                            class="form-control cmn_focus rounded gap-2 align-items-center d-flex justify-content-between">
                                                            <div
                                                                class="d-flex align-items-center fw-medium gap-2 text-capitalize">
                                                                {{ translate('Status') }}
                                                            </div>
                                                            <div class="position-relative">
                                                                <label class="switcher">
                                                                    <input type="checkbox" tabindex="3"
                                                                           name="customer_first_ride_discount_status"
                                                                           class="switcher_input collapsible-card-switcher" {{$useCodeEarning && $useCodeEarning['first_ride_discount_status'] == 1 ? "checked" : "" }}>
                                                                    <span class="switcher_control"></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="collapsible-card-content">
                                                            <div class="pt-3">
                                                                <div class="row g-3">
                                                                    <div class="col-sm-6">
                                                                        <label for="discount_amount" class="form-label">
                                                                            <span id="discount_amount_label">{{ translate('discount_amount') }}
                                                                            ({{session()->get('currency_symbol') ?? '$'}}
                                                                            )
                                                                            </span>
                                                                            <span class="text-danger">*</span>
                                                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                               data-bs-toggle="tooltip"
                                                                               data-bs-title="{{translate("Set the discount value which will be applicable on the total booking amount of ride booking")}}">
                                                                            </i></label>
                                                                        <div class="input-group input--group">
                                                                            <input type="number" name="customer_discount_amount" tabindex="4"
                                                                                   class="form-control" id="discount" step="{{stepValue()}}" min="0"
                                                                                   value="{{ $useCodeEarning && $useCodeEarning['discount_amount'] ?$useCodeEarning['discount_amount'] : old('customer_discount_amount', 0) }}"
                                                                                   placeholder="Ex : 5">
                                                                            <select
                                                                                class="form-select currency-type-select" tabindex="5"
                                                                                id="amount_type" name="customer_discount_amount_type">
                                                                                <option
                                                                                    value="amount" {{!$useCodeEarning || $useCodeEarning['discount_amount_type'] =="amount" ? "selected" : ""}}>{{session()->get('currency_symbol') ?? '$'}}</option>
                                                                                <option
                                                                                    value="percentage" {{$useCodeEarning && $useCodeEarning['discount_amount_type'] =="percentage" ? "selected" : ""}}>
                                                                                    %
                                                                                </option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <label class="form-label">{{translate("Validity")}}<span class="text-danger">*</span> <i
                                                                                class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                                                data-bs-toggle="tooltip"
                                                                                data-bs-title="{{translate("Set the value of the day, after that day is over can’t get the referral discount.")}}">
                                                                            </i></label>
                                                                        <div class="input-group input--group">
                                                                            <input type="number" min="0" max="9999999" tabindex="6"
                                                                                   class="form-control" name="customer_discount_validity"
                                                                                   value="{{$useCodeEarning && array_key_exists('discount_validity',$useCodeEarning) ? $useCodeEarning['discount_validity'] : old("customer_discount_validity", 0)}}"
                                                                                   placeholder="Ex : 5">
                                                                            <select class="form-select" tabindex="7"
                                                                                    name="customer_discount_validity_type">
                                                                                <option
                                                                                    value="day" {{!$useCodeEarning || $useCodeEarning['discount_validity_type'] == 'day' ? "selected" : "" }}>
                                                                                    Day
                                                                                </option>
                                                                                <option
                                                                                    value="week" {{$useCodeEarning && $useCodeEarning['discount_validity_type'] == 'week' ? "selected" : "" }}>
                                                                                    Week
                                                                                </option>
                                                                                <option
                                                                                    value="month" {{$useCodeEarning && $useCodeEarning['discount_validity_type'] == 'month' ? "selected" : "" }}>
                                                                                    Month
                                                                                </option>
                                                                                <option
                                                                                    value="year" {{$useCodeEarning && $useCodeEarning['discount_validity_type'] == 'year' ? "selected" : "" }}>
                                                                                    Year
                                                                                </option>
                                                                            </select>
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
                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <button class="btn min-w--120 btn-light h-40px justify-content-center fw-semibold cmn_focus" type="reset" tabindex="5">{{ translate('Reset') }}</button>
                                <button type="submit" class="btn min-w--120 btn-primary text-uppercase cmn_focus" tabindex="11">{{translate('submit')}}</button>
                            </div>
                        </div>
                        </form>
                    </div>
                    <div class="card collapsible-card-body ">
                        <form action="{{ route('admin.business.setup.referral-earning.store') }}" method="post"
                              enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="user_type" value="{{ DRIVER }}">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="w-0 flex-grow-1">
                                    <h5 class="mb-2">{{translate('Setup Driver Referral Earning')}}</h5>
                                    <div class="fs-12">
                                        {{translate("Allow Drivers to refer your app to friends and family using a unique code and earn rewards.")}}
                                    </div>
                                </div>
                                <div class="card-head-group d-flex align-items-center gap-2">
                                    <div class="view-btn cursor-pointer text-link fs-12 fw-semibold d-flex align-items-center gap-0">
                                        View
                                        <i class="tio-arrow-upward"></i>
                                    </div>
                                    <label class="switcher cmn_focus rounded-pill">
                                        <input class="switcher_input collapsible-card-switcher update-referral-setting"
                                               id="driverReferralEarningStatus" tabindex="1"
                                               type="checkbox"
                                               name="referral_earning_status"
                                               data-name="referral_earning_status"
                                               data-type="{{ DRIVER }}"
                                               data-url="{{route('admin.business.setup.referral-earning.update-referral-setting')}}"
                                               data-icon=" {{ ($driverSettings->firstWhere('key_name', 'referral_earning_status')->value?? 0) == 1 ? dynamicAsset('public/assets/admin-module/img/svg/turn-off-referral-earning.svg') : dynamicAsset('public/assets/admin-module/img/svg/turn-on-referral-earning.svg') }}"
                                               data-title="{{ ($driverSettings->firstWhere('key_name', 'referral_earning_status')->value?? 0) == 1 ? translate('Want to disable referral earnings') : translate('Want to enable referral earnings')}}?"
                                               data-sub-title="{{($driverSettings->firstWhere('key_name', 'referral_earning_status')->value?? 0) == 1 ? translate('If you disable referral earnings, drivers will no longer receive rewards for referring new users') : translate('If you enable referral earnings, drivers will be able to earn rewards by referring new users.')}}"
                                               data-confirm-btn="{{($driverSettings->firstWhere('key_name', 'referral_earning_status')->value?? 0) == 1 ? translate('Turn Off') : translate('Turn On')}}"
                                               {{ $driverSettings->firstWhere('key_name','referral_earning_status')?->value == 1 ? "checked" : "" }}
                                               data-target-content=".driver-referral-card"
                                        >
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body collapsible-card-content driver-referral-card">
                            <div class="">
                                <div class="">
                                    <div class="">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <h6 class="mb-2">{{translate("Who Share the code")}}</h6>
                                                <div class="fs-12">
                                                    {{translate("Set the reward for the driver who is sharing the code with friends & family to refer the app.")}}
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                                    <div>
                                                        <label
                                                            class="form-label">{{ translate("Earnings to Each Referral") }}
                                                            ({{session()->get('currency_symbol') ?? '$'}})
                                                            <span class="text-danger">*</span></label>
                                                        <input type="text" name="driver_share_code_earning" step="{{stepValue()}}" tabindex="9"
                                                               value="{{ $driverSettings->firstWhere('key_name','share_code_earning')?->value ?? old('driver_share_code_earning', 0) }}"
                                                               class="form-control" placeholder="Ex : 2.50"
                                                               min="0" max="9999999">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-bottom my-4"></div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <h6 class="mb-2">{{translate("Who Use the code")}}</h6>
                                                <div class="fs-12">
                                                    {{translate("Set up the reward that the driver will receive when using the refer code in signup.")}}
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                                    <div>
                                                        <label class="form-label">{{translate("Bonus in Wallet")}}
                                                            ({{session()->get('currency_symbol') ?? '$'}})
                                                            <span class="text-danger">*</span></label>
                                                        <input type="text" name="driver_use_code_earning" step="{{stepValue()}}" tabindex="10"
                                                               value="{{ $driverSettings->firstWhere('key_name','use_code_earning')?->value ?? old('driver_use_code_earning', 0) }}"
                                                               class="form-control" placeholder="Ex : 2.50"
                                                               min="0" max="9999999">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <button class="btn min-w--120 btn-light h-40px justify-content-center fw-semibold cmn_focus" type="reset" tabindex="5">{{ translate('Reset') }}</button>
                                <button type="submit" class="btn min-w--120 btn-primary text-uppercase cmn_focus" tabindex="11">{{translate('submit')}}</button>
                            </div>
                        </div>
                        </form>
                    </div>
                </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')

    <script>
        $(document).ready(function () {

            const amountType = $('#amount_type');
            amountTypeCheck();
            amountType.on('change', function () {
                amountTypeCheck();
            });

            function amountTypeCheck() {
                if (amountType.val() == 'amount') {
                    $("#discount_amount_label").text("{{translate('Discount Amount')}} ({{session()->get('currency_symbol') ?? '$'}})");
                    $("#discount").attr("placeholder", "Ex: 500")
                    $("#discount").attr("max", "999999999")
                } else {
                    $("#discount_amount_label").text("{{translate('Discount Percent ')}}(%)")
                    $("#discount").attr("placeholder", "Ex: 50%")
                    $("#discount").attr("max", "100")
                }
            }


            function collapsibleCard(thisInput) {
                let $card = thisInput.closest('.collapsible-card-body');
                let $content = $card.children('.collapsible-card-content');
                if (thisInput.prop('checked')) {
                    $content.slideDown();
                } else {
                    $content.slideUp();
                }
            }

            $('.collapsible-card-switcher').on('change', function () {
                collapsibleCard($(this))
            });
            $('.collapsible-card-switcher').each(function () {
                collapsibleCard($(this))
            });
        });
    </script>

    <script src="{{ dynamicAsset('public/assets/admin-module/js/business-management/business-setup/driver.js') }}"></script>

    <script>
        "use strict";
        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        function updateContentVisibility(triggerSelector) {
            const $trigger = $(triggerSelector);
            const targetSelector = $trigger.data('target-content');
            const $targetContent = $(targetSelector);
            const $arrowIcon = $($trigger.data('arrow-icon'));

            console.log($targetContent, $arrowIcon)
            if ($trigger.attr('data-confirm-btn') === "Turn Off") {
                $targetContent.slideDown();
                $arrowIcon.removeClass('tio-arrow-downward').addClass('tio-arrow-upward');
            } else {
                $targetContent.slideUp();
                $arrowIcon.removeClass('tio-arrow-upward').addClass('tio-arrow-downward');
            }
        }

        $(document).ready(function() {
            $('[data-target-content]').each(function() {
                console.log(123)
                updateContentVisibility(this);
            });
        });

    </script>

    <script>
        $('#loyalty_point_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_donot_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
    </script>
@endpush
