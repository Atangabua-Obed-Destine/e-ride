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
                <div class="col-lg-6">
                    <div class="card card-body h-100">
                        <div class="mb-20">
                            <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                {{translate('customer_review')}}
                            </h5>
                            <p class="mb-0">{{ translate('allow_drivers_to_rate_or_review_driver_after_trips') }}</p>
                        </div>
                        <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                            <label class="text-capitalize mb-2">
                                {{translate('customer_can_give_review_a_driver')}}
                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                    data-bs-toggle="tooltip"
                                    title="{{translate('enable_this_option_to_let_drivers_rate_and_review_customers_after_each_ride_to_promote_safe_and_respectful_interactions')}}"></i>
                            </label>
                            <label class="form-control d-flex align-items-center justify-content-between">
                                <label for="customerReview" class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                                <label class="switcher rounded-pill cmn_focus">
                                    <input class="switcher_input" id="customerReview" name="customer_review"
                                           type="checkbox" data-type="{{CUSTOMER_SETTINGS}}" tabindex="2"
                                        {{($settings->firstWhere('key_name', CUSTOMER_REVIEW)->value?? 0) == 1? 'checked' : ''}}
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
                            <h5 class="d-flex align-items-center gap-2 justify-content-between text-capitalize mb-2">
                                {{translate('Customer_Level')}}
                                <a href="{{route('admin.customer.level.index')}}" class="text-link fs-12 fw-semibold d-flex gap-1 align-items-center">
                                    {{translate('Go_to_settings')}}
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </h5>
                            <p class="mb-0">{{ translate('manage_customer_level_features_like_experience_or_performance.') }}</p>
                        </div>
                        <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                            <label class="text-capitalize mb-2">
                                {{translate('Active_level_feature')}}
                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                    data-bs-toggle="tooltip"
                                    title="{{translate('define_customer_levels_or_loyalty_tiers_(e.g.,_regular,_premium,_vip)_based_on_ride_frequency_or_reward_points.')}}"></i>
                            </label>
                            <label class="form-control d-flex align-items-center justify-content-between">
                                <label for="customerLevel" class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                                <label class="switcher rounded-pill cmn_focus">
                                    <input class="switcher_input update-business-setting" id="customerLevel" tabindex="3"
                                           name="{{CUSTOMER_LEVEL}}"
                                           type="checkbox"
                                           data-name="{{CUSTOMER_LEVEL}}"
                                           data-type="{{CUSTOMER_SETTINGS}}"
                                           data-url="{{route('admin.business.setup.update-business-setting')}}"
                                           data-icon="{{($settings->firstWhere('key_name', CUSTOMER_LEVEL)->value?? 0) == 0 ? dynamicAsset('public/assets/admin-module/img/level-up-on.png') : dynamicAsset('public/assets/admin-module/img/level-up-off.png')}}"
                                           data-title="{{($settings->firstWhere('key_name', CUSTOMER_LEVEL)->value?? 0) == 0?translate('By Turning ON Level Feature') .'?' : translate('By Turning OFF Level Feature').'?'}}"
                                           data-sub-title="{{($settings->firstWhere('key_name', CUSTOMER_LEVEL)->value?? 0) == 0?translate('If you turn ON level feature, customer will see this feature on app.') : translate('If you turning off customer level feature, please do it at the beginning stage of business. Because once driver use this feature & you will off this feature they will be confused or worried about it.')}}"
                                        {{($settings->firstWhere('key_name', CUSTOMER_LEVEL)->value?? 0) == 1? 'checked' : ''}}
                                    >
                                    <span class="switcher_control"></span>
                                </label>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card card-body h-100">
                        <div class="mb-20">
                            <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                {{translate('Loyalty_Point')}}
                            </h5>
                            <p class="mb-0">{{ translate('manage_customer_loyalty_points_and_conversion_rate.') }}</p>
                        </div>
                        <form action="{{route('admin.business.setup.customer.store')}}?type=loyalty_point"
                           id="loyalty_point_form" method="post">
                           @csrf
                           <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                               <div class="row g-4">
                                   <div class="col-lg-6">
                                       <label class="text-capitalize mb-2">
                                           {{translate('customer_can_earn_loyalty_point')}}
                                           <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                               data-bs-toggle="tooltip"
                                               title="{{translate('allow_customers_to_earn_loyalty_points_on_each_completed_ride,_which_can_be_redeemed_for_discounts_or_offers.')}}"></i>
                                       </label>
                                       <label class="form-control d-flex align-items-center justify-content-between">
                                           <label for="loyalty_point_switch" class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                                           <label class="switcher cmn_focus rounded-pill">
                                               <input class="switcher_input" type="checkbox" name="loyalty_points[status]"
                                                      id="loyalty_point_switch" tabindex="4"
                                                   {{ $settings->firstWhere('key_name', 'loyalty_points')?->value['status'] == 1 ? 'checked' : ''}}>
                                               <span class="switcher_control"></span>
                                           </label>
                                       </label>
                                   </div>
                                   <div class="col-lg-6">
                                       <div>
                                           <label for="equivalent_points"
                                               class="mb-2">{{getCurrencyFormat(1). ' ' . translate('equivalent_to_points')}} <span class="text-danger">*</span></label>
                                           <input type="tel" name="loyalty_points[value]" id="equivalent_points"
                                               class="form-control" required pattern="[1-9][0-9]{0,200}"
                                               data-bs-toggle="tooltip" title="Please input integer value. Ex:1,2,22,10"
                                               value="{{$settings->firstWhere('key_name', 'loyalty_points')?->value['points'] ?? ''}}"
                                               placeholder="{{translate('Ex: 2')}}" tabindex="5">
                                       </div>
                                   </div>
                               </div>
                           </div>
                           <div class="btn--container justify-content-end mt-4">
                               <button type="reset" class="btn btn-secondary min-w-120 cmn_focus" tabindex="6">{{ translate('reset') }}</button>
                               <button type="submit" class="btn btn-primary min-w-120 cmn_focus" tabindex="7">{{ translate('save') }}</button>
                           </div>
                       </form>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card card-body h-100">
                        <div class="mb-20">
                            <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                {{translate('Wallet')}}
                            </h5>
                            <p class="mb-0">{{ translate('manage_wallet_funds_and_minimum_deposit_limit.') }}</p>
                        </div>
                        @php
                            $decimalPoint = (int)businessConfig('currency_decimal_point', BUSINESS_INFORMATION)?->value ?? 2;
                        @endphp
                        <form action="{{route('admin.business.setup.customer.store')}}?type=wallet"
                              id="wallet_form" method="post">
                            @csrf
                           <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                               <div class="row g-4">
                                   <div class="col-lg-6">
                                       <label class="text-capitalize mb-2">
                                           {{translate('Add_funds_to_wallet')}}
                                           <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                               data-bs-toggle="tooltip"
                                               title="{{translate('allow_customers_to_add_money_to_their_in-app_wallet_for_quick_and_cashless_ride_payments.')}}"></i>
                                       </label>
                                       <label class="form-control d-flex align-items-center justify-content-between">
                                           <label for="wallet_switch" class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                                           <label class="switcher cmn_focus rounded-pill">
                                                <input class="switcher_input" type="checkbox" name="customer_wallet[add_fund_status]"
                                                    id="wallet_switch" tabindex="8"
                                                    {{ $settings->firstWhere('key_name', 'customer_wallet')?->value['add_fund_status'] == 1 ? 'checked' : ''}}>
                                                <span class="switcher_control"></span>
                                            </label>
                                       </label>
                                   </div>
                                   <div class="col-lg-6">
                                       <div>
                                           <label for="wallet"
                                           class="mb-2">
                                                {{ translate('Minimum_Add_Amount') . ' (' . ((session()->get('currency_symbol') ?? businessConfig('currency_symbol', 'business_information')?->value) ?? "$") . ')' }}
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                data-bs-toggle="tooltip"
                                                title="{{translate('set_minimum_amount_users_have_to_add')}}"></i>
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="customer_wallet[min_deposit_limit]"
                                                id="wallet"
                                                class="form-control" required
                                                value="{{$settings->firstWhere('key_name', 'customer_wallet')?->value['min_deposit_limit'] ?? 10 }}"
                                                min="1"
                                                placeholder="{{translate('Ex: 2')}}"
                                                data-decimal="{{ $decimalPoint }}"
                                                tabindex="9"
                                            >
                                       </div>
                                   </div>
                               </div>
                           </div>
                           <div class="btn--container justify-content-end mt-4">
                               <button type="reset" class="btn btn-secondary min-w-120 cmn_focus" tabindex="10">{{ translate('reset') }}</button>
                               <button type="submit" class="btn btn-primary min-w-120 cmn_focus" tabindex="11">{{ translate('save') }}</button>
                           </div>
                       </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/business-management/business-setup/customer.js') }}"></script>
    <script>
        "use strict";
        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#loyalty_point_form, #wallet_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });

        $('#loyal_customer_tag_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });

        $('#customerReview').on('change', function () {
            let url = '{{route('admin.business.setup.update-business-setting')}}';
            updateBusinessSetting(this, url)
        })

        function updateBusinessSetting(obj, url) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');

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
                title: '{{translate('are_you_sure')}}?',
                text: '{{translate('want_to_change_status')}}',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--bs-primary)',
                cancelButtonColor: 'default',
                cancelButtonText: '{{ translate("no")}}',
                confirmButtonText: '{{ translate("yes")}}',
                reverseButtons: true
            }).then((result) => {
                    if (result.value) {
                        $.ajax({
                            url: url,
                            data: {value: value, name: name, type: type},
                            success: function () {
                                toastr.success("{{translate('status_changed_successfully')}}");
                            },
                            error: function () {
                                if (status === 1) {
                                    $('#' + obj.id).prop('checked', false)
                                } else if (status === 0) {
                                    $('#' + obj.id).prop('checked', true)
                                }
                                toastr.error("{{translate('status_change_failed')}}");
                            }
                        });
                    } else {
                        if (status === 1) {
                            $('#' + obj.id).prop('checked', false)
                        } else if (status === 0) {
                            $('#' + obj.id).prop('checked', true)
                        }
                    }
                }
            )
        }
    </script>
@endpush
