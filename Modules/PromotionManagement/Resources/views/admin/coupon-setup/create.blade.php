@section('title', translate('add_New_Coupon'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">

            <form action="{{ route('admin.promotion.coupon-setup.store') }}" method="POST" id="coupon_form">
                @csrf

                <h4 class="text-capitalize mb-4">{{ translate('add_coupon') }}</h4>

                <div class="card">
                    <div class="card-body">
                        <h5 class="text-primary fw-medium text-uppercase mb-3">{{ translate('coupon_information') }}</h5>
                        <div class="row align-items-start g-4 mb-4">
                            <div class="col-sm-6">
                                <label for="discount_title" class="mb-2">{{ translate('coupon_title') }} <span class="text-danger">*</span>
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                        title="{{ translate('write_the_coupon_title_within_50_characters.') }}"></i>
                                </label>
                                <input type="text" id="coupon_title" value="{{old('coupon_title')}}"
                                        name="coupon_title" maxlength="50" class="form-control" placeholder="Ex: 20% Coupon"
                                        required tabindex="1">
                            </div>

                            <div class="col-sm-6">
                                <label for="short_desc" class="mb-2">{{ translate('short_description') }} <span class="text-danger">*</span>
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                        title="{{ translate('write_the_short_description_title_within_800_characters') }}"></i>

                                </label>
                                <div class="character-count">
                                    <input id="short_desc" name="short_desc" class="form-control character-count-field"
                                           maxlength="800" data-max-character="800"
                                           placeholder="{{ translate('type_here') }}..." value="{{old('short_desc')}}" tabindex="2" required />
                                    <span>{{translate('0/800')}}</span>
                                </div>
                            </div>
                        </div>

                        <h5 class="text-primary fw-medium text-uppercase mb-3">{{ translate('coupon_logics') }}</h5>

                        <div class="row align-items-start">
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="coupon_type" class="mb-2">
                                        {{ translate('coupon_type') }} <span class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                            title="{{ translate('when_you_choose_a_coupon_type_and_submit_it_once.') . ' ' . translate('_you_can_not_change_it_in_future') }}"></i>
                                    </label>
                                    <select class="js-select cmn_focus" id="coupon_type" name="coupon_type" required tabindex="3">
                                        <option value="" selected disabled>
                                            -- {{ translate('Select_Coupon_Type') }} --
                                        </option>
                                        <option value="default">{{ translate('default') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between">
                                        <label for="coupon_code" class="mb-2">{{ translate('coupon_code') }} <span class="text-danger">*</span>
                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                                title="{{ translate('type_the_coupon_code_using_either_the_"underscore"_') . "(_)" .  translate('_or_no_space_within_30_characters._') . "e.g., newyear23 or new_year_23" }}"></i>
                                        </label>

                                    </div>
                                    <div class="position-relative">
                                        <input type="text" id="coupon_code" name="coupon_code" class="form-control"
                                               placeholder="Ex: New Year 23" value="{{old('coupon_code')}}"
                                               required tabindex="4">
                                        <a href="javascript:void(0)" class="generate-code form-control"
                                           id="generateCode" tabindex="5"><img src="{{ dynamicAsset("public/assets/admin-module/img/coupon-generate.png") }}" alt="{{translate("generate_coupon")}}"></a>
                                    </div>

                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="limit_same_user"
                                            class="mb-2">{{ translate('limit_for_the_same_user') }} <span class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                            title="{{ translate('set_how_many_times_a_user_can_use_this_coupon') }}"></i>
                                    </label>
                                    <input type="number" id="limit_same_user" name="limit_same_user"
                                            class="form-control"
                                            placeholder="Ex: 10" value="{{old('limit_same_user')}}" required tabindex="6">
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="start_date"
                                            class="mb-2">{{ translate('start_date') }} <span class="text-danger">*</span></label>
                                    <input type="date" value="{{old('start_date')}}" id="start_date"
                                            min="{{date('Y-m-d',strtotime(now()))}}"
                                            name="start_date" class="form-control" required tabindex="7">
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="end_date" class="mb-2">{{ translate('end_date') }} <span class="text-danger">*</span></label>
                                    <input type="date" id="end_date" value="{{old('end_date')}}" name="end_date"
                                            min="{{date('Y-m-d',strtotime(now()))}}"
                                            class="form-control" required tabindex="8">
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4 min_trip_amount">
                                <div class="mb-4">
                                    <label for="minimum_trip_amount"
                                            class="mb-2">{{ translate('minimum_trip_amount') }}
                                        ({{session()->get('currency_symbol') ?? '$'}}) <span class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                            title="{{ translate('set_the_minimum_trip_amount_that_is_required_to_use_this_coupon') }}"></i>
                                    </label>
                                    <input type="number" id="minimum_trip_amount" name="minimum_trip_amount"
                                            class="form-control"
                                            placeholder="Ex: 100" step="any"
                                            value="{{old('minimum_trip_amount')}}" required tabindex="9">
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="coupon_amount" class="mb-2"><span id="coupon_amount_label">{{ translate('coupon_amount') }}
                                        ({{session()->get('currency_symbol') ?? '$'}})</span> <span class="text-danger">*</span></label>
                                    <div class="position-relative">
                                        <input type="number" id="coupon" value="{{old('coupon')}}" name="coupon" class="form-control" placeholder="Ex: 5" step="any" required tabindex="10">
                                        <select class="js-select cmn_focus currency-type-select" id="amount_type" name="amount_type" required tabindex="11">
                                            <option value="amount" selected>{{session()->get('currency_symbol') ?? '$'}}</option>
                                            <option value="percentage">%</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="max_coupon"
                                            class="mb-2">{{ translate('maximum_discount_limit') }}
                                        ({{session()->get('currency_symbol') ?? '$'}})
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                            title="{{ translate('set_the_maximum_discount_limit_for_this_coupon,_and_the_discount_amount_will_not_increase_after_reaching_this_limit') }}"></i>
                                    </label>
                                    <input type="number" id="max_coupon" name="max_coupon_amount"
                                            class="form-control"
                                            placeholder="Ex: 60" value="{{old('max_coupon_amount')}}" step=".01" tabindex="12">
                                </div>
                            </div>
                        </div>


                        <h5 class="text-primary fw-medium text-uppercase mb-3">{{ translate('Coupon Availability') }}</h5>
                        <div class="row align-items-start">
                            <div class="col-sm-6 col-xl-4 user_level">
                                <div class="mb-4">
                                    <label for="customerLevelCouponType" class="mb-2">
                                        {{ translate('customer_level') }} <span class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                           title="{{ translate('customer_level_select_first_otherwise_customer_not_found_in_select_customer_dropdown') }}"></i>
                                    </label>
                                    <select class="js-select-2 cmn_focus" id="customerLevelCouponType"
                                            data-placeholder="{{translate('select_customer_level')}}"
                                            name="customer_level_coupon_type[]" multiple="multiple" required tabindex="13">
                                        <option value="{{ALL}}">{{translate('ALL')}}</option>
                                        @foreach($levels as $level)
                                            <option value="{{$level->id}}">{{$level->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4 text-capitalize">
                                    <label for="customerCouponType" class="mb-2">
                                        {{ translate('customer') }} <span class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                           title="{{ translate('customer_show_when_you_select_customer_level') }}"></i>
                                    </label>
                                    <select class="js-select-2 cmn_focus" id="customerCouponType" data-placeholder="{{translate('select_customer')}}"
                                            name="customer_coupon_type[]" multiple="multiple" required disabled tabindex="14">
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4 vehicle_category">
                                <div class="mb-4 text-capitalize">
                                    <label for="categoryCouponType"
                                            class="mb-2">{{ translate('category') }} <span class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                            title="{{ translate('choose_in_which_vehicle_category_this_coupon_will_be_applicable.') }}"></i>
                                    </label>
                                    <select id="categoryCouponType" class="js-select-2 cmn_focus" data-placeholder="{{translate('select_category')}}"
                                            name="category_coupon_type[]" multiple="multiple" required tabindex="15">
                                        <option value="{{ALL}}">{{translate('ALL')}}</option>
                                        @foreach($vehicleCategories as $vehicleCategory)
                                            <option
                                                value="{{$vehicleCategory->id}}">{{ $vehicleCategory->name }}</option>
                                        @endforeach
                                        <option value="{{PARCEL}}">{{ translate(PARCEL) }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="mb-4 text-capitalize">
                                    <label for="zoneDiscountType"
                                            class="mb-2">{{ translate('Zones') }} <span class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                            title="{{ translate('zones') }}"></i>
                                    </label>
                                    <select class="js-select-2 cmn_focus" id="zoneDiscountType"
                                            name="zone_coupon_type[]"
                                            data-placeholder="{{translate('select_zone')}}"
                                            multiple="multiple" required tabindex="16">
                                        <option value="{{ALL}}">{{translate('ALL')}}</option>
                                        @foreach($zones as $zone)
                                            <option value="{{$zone->id}}">{{$zone->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-3">
                            <a href="" class="btn btn-secondary cmn_reset" tabindex="17">{{ translate('reset') }}</a>
                            <button class="btn btn-primary cmn_focus" type="submit" tabindex="18">{{ translate('submit') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script src="{{dynamicAsset('public/assets/admin-module/js/promotion-management/coupon-setup/create.js')}}"></script>

    <script>
        "use strict";

        $(document).ready(function () {

            const amountType = $('#amount_type');
            const maxCoupon = $('#max_coupon');
            amountTypeCheck();
            amountType.on('change', function () {
                amountTypeCheck();
            });

            function amountTypeCheck(){
                if (amountType.val() == 'amount') {

                    maxCoupon.attr("readonly", "true");
                    maxCoupon.attr("title", "not editable");
                    maxCoupon.val(0);

                    $("#coupon_amount_label").text("{{translate('Coupon Amount')}} ({{session()->get('currency_symbol') ?? '$'}})");
                    $("#coupon").attr("placeholder", "Ex: 500")
                } else {
                    maxCoupon.removeAttr("readonly");
                    maxCoupon.removeAttr("title");
                    $("#coupon_amount_label").text("{{translate('Coupon Percent ')}}(%)")
                    $("#coupon").attr("placeholder", "Ex: 50%")
                }
            }
        });
        $('#customerLevelCouponType').on('change', function () {
            let selectElement = document.getElementById('customerCouponType');
            selectElement.removeAttribute('disabled');
            let selectedValues = $(this).val();
            $.ajax({
                url: '{{route('admin.customer.get-level-wise-customer')}}',
                type: 'GET',
                data: {
                    levels: selectedValues
                },
                success: function (response) {
                    $('#customerCouponType').empty();
                    if(response.length>0){
                        $('#customerCouponType').append('<option value="{{ALL}}">{{translate('ALL')}}</option>');
                        $.each(response, function (index, value) {
                            $('#customerCouponType').append('<option value="' + value.id + '">' + value.first_name + ' ' + value.last_name + '</option>');
                        });
                    }else{
                        let selectElement = document.getElementById('customerCouponType');
                        selectElement.setAttribute('disabled','disabled');
                    }

                }
            });
        });

    </script>
@endpush
