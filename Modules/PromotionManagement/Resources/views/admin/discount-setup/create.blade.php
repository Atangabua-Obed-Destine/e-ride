@section('title', translate('add_New_Discount'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">

            <form action="{{ route('admin.promotion.discount-setup.store') }}" method="POST" id="discountForm"
                  enctype="multipart/form-data">
                @csrf
                <h4 class="text-capitalize mb-4">{{ translate('add_discount') }}</h4>

                <div class="card">
                    <div class="card-body">
                        <h5 class="text-primary fw-medium text-uppercase mb-3">{{ translate('discount_information') }}</h5>
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="title" class="mb-2">{{ translate('Title') }} <span
                                                class="text-danger">*</span>
                                        </label>
                                        <input type="text" id="title" value="{{old('title')}}"
                                               name="title" maxlength="100" class="form-control"
                                               placeholder="Ex: 20% discount"
                                               required tabindex="1">
                                    </div>
                                    <div class="col-12">
                                        <label for="shortDescription"
                                               class="mb-2">{{ translate('short_description') }}
                                            <small>({{translate('Max 800 character')}})</small> <span
                                                class="text-danger">*</span>
                                        </label>
                                        <div class="character-count">
                                                    <textarea id="shortDescription" name="short_description" cols="30"
                                                              rows="4" class="form-control character-count-field"
                                                              maxlength="800" data-max-character="800" tabindex="2"
                                                              required>{{old('short_description')}}</textarea>
                                            <span>{{translate('0/800')}}</span>
                                        </div>

                                    </div>
                                    <div class="col-12">
                                        <label for="termsConditions"
                                               class="mb-2">{{ translate('Terms & Conditions') }}
                                            <small>({{translate('Max 1500 character')}})</small> <span
                                                class="text-danger">*</span>
                                        </label>
                                        <div class="character-count">
                                                    <textarea id="termsConditions" name="terms_conditions" cols="30"
                                                              rows="5" class="form-control character-count-field"
                                                              maxlength="1500" data-max-character="1500" tabindex="3"
                                                              required>{{old('terms_conditions')}}</textarea>
                                            <span>{{translate('0/1500')}}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @php
                                $maxSize = readableUploadMaxFileSize('image');
                            @endphp
                            <div class="col-md-6">
                                <div
                                    class="bg-input h-100 rounded d-flex flex-column justify-content-center py-3">
                                    <div
                                        class="d-flex flex-column justify-content-around align-items-center gap-3 mb-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <h5 class="text-capitalize">{{ translate('discount_image') }} <span
                                                    class="text-danger">*</span></h5>
                                        </div>
                                        <div class="d-flex justify-content-center">
                                            <div class="upload-file auto profile-image-upload-file rounded-10 cmn_focus">
                                                <input type="file" name="image" class="upload-file__input"
                                                       accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}" required tabindex="4" data-max-upload-size="{{ $maxSize }}">
                                                <div
                                                    class="upload-file__img border-gray d-flex justify-content-center align-items-center h-100px w-250px aspect-ratio-3-1 p-0 bg-white">
                                                    <div class="upload-file__textbox text-center">
                                                        <img width="34" height="34"
                                                             src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.png') }}"
                                                             alt="" class="svg">
                                                        <h6 class="mt-2 fw-semibold fs-12">
                                                            <span class="text-info">{{ translate('Click to upload') }}</span>
                                                            <br>
                                                            {{ translate('or drag and drop') }}
                                                        </h6>
                                                    </div>
                                                    <img class="upload-file__img__img h-100 aspect-ratio-inherit"
                                                         loading="lazy" alt="">
                                                </div>
                                                <a href="javascript:void(0)" class="remove-img-icon d-none">
                                                    <i class="tio-clear"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <p class="opacity-75 mx-auto max-h-340px max-w220 text-center">
                                            {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}, Image Ratio - {ratio}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize, 'ratio' => '3:1']) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h5 class="text-primary fw-medium text-uppercase mb-3">{{ translate('discount_logics') }}</h5>
                        <div class="row align-items-start">
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="limitPerUser" class="mb-2">
                                        {{ translate('Limit for Same User') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" id="limitPerUser" name="limit_per_user"
                                           value="{{old('limit_per_user')}}" min="1"
                                           placeholder="{{translate('Ex : 10')}}" class="form-control" required tabindex="5">
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="discountAmount"
                                           class="mb-2"><span
                                            id="discountAmountLabel">{{ translate('Discount Amount') }}</span> <span
                                            class="text-danger">*</span></label>
                                    <div class="position-relative text-center">
                                        <input type="number" id="discountAmount" value="{{old('discount_amount')}}"
                                               name="discount_amount" class="form-control" placeholder="Ex: 5"
                                               step="any"
                                               required tabindex="6">
                                        <select class="js-select cmn_focus currency-type-select" id="discountAmountType"
                                                name="discount_amount_type" required tabindex="7">
                                            <option value="amount"
                                                    selected>{{session()->get('currency_symbol') ?? '$'}}</option>
                                            <option value="percentage">%</option>
                                        </select>
                                    </div>

                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="maxDiscountAmount"
                                           class="mb-2">{{ translate('Max Amount') }}
                                        ({{session()->get('currency_symbol') ?? '$'}})
                                    </label>
                                    <input type="number" id="maxDiscountAmount" name="max_discount_amount"
                                           class="form-control"
                                           placeholder="Ex: 100" step="any" min="1"
                                           value="{{old('max_discount_amount')}}" tabindex="7">
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="minTripAmount"
                                           class="mb-2">{{ translate('Min Trip Amount') }}
                                        ({{session()->get('currency_symbol') ?? '$'}}) <span
                                            class="text-danger">*</span>
                                    </label>
                                    <input type="number" id="minTripAmount" name="min_trip_amount" min="1"
                                           class="form-control"
                                           placeholder="Ex: 60" value="{{old('min_trip_amount')}}" step="any"
                                           required tabindex="8">
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="start_date"
                                           class="mb-2">{{ translate('start_date') }} <span class="text-danger">*</span></label>
                                    <input type="date" value="{{old('start_date')}}" id="start_date"
                                           min="{{date('Y-m-d',strtotime(now()))}}"
                                           name="start_date" class="form-control" required tabindex="9">
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="end_date" class="mb-2">{{ translate('end_date') }} <span
                                            class="text-danger">*</span></label>
                                    <input type="date" id="end_date" value="{{old('end_date')}}" name="end_date"
                                           min="{{date('Y-m-d',strtotime(now()))}}"
                                           class="form-control" required tabindex="10">
                                </div>
                            </div>
                        </div>
                        <h5 class="text-primary fw-medium text-uppercase mb-3">{{ translate('discount_availability') }}</h5>
                        <div class="row align-items-start">
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="customerLevelDiscountType"
                                           class="mb-2">{{ translate('select_customer_level') }} <span
                                            class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('customer_level_select_first_otherwise_customer_not_found_in_select_customer_dropdown') }}"></i>
                                    </label>
                                    <select class="js-select-2 cmn_focus" id="customerLevelDiscountType"
                                            data-placeholder="{{translate('select_customer_level')}}"
                                            name="customer_level_discount_type[]" multiple="multiple" required tabindex="11">
                                        <option value="{{ALL}}">{{translate('ALL')}}</option>
                                        @foreach($levels as $level)
                                            <option value="{{$level->id}}">{{$level->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="customerDiscountType"
                                           class="mb-2">{{ translate('select_customer') }} <span
                                            class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('customer_show_when_you_select_customer_level') }}"></i>

                                    </label>
                                    <select class="js-select-2 cmn_focus" id="customerDiscountType"
                                            data-placeholder="{{translate('select_customer')}}" tabindex="12"
                                            name="customer_discount_type[]" multiple="multiple" required disabled>

                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="mb-4">
                                    <label for="moduleDiscountType" class="mb-2">
                                        {{ translate('category') }} <span class="text-danger">*</span>
                                    </label>
                                    <select class="js-select-2 cmn_focus" id="moduleDiscountType"
                                            data-placeholder="{{translate('select_category')}}"
                                            name="module_discount_type[]" multiple="multiple" required tabindex="13">
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
                                <div class="mb-4">
                                    <label for="zoneDiscountType" class="mb-2">
                                        {{ translate('select_zone') }} <span class="text-danger">*</span>
                                    </label>
                                    <select class="js-select-2 cmn_focus" id="zoneDiscountType"
                                            name="zone_discount_type[]"
                                            data-placeholder="{{translate('select_zone')}}"
                                            multiple="multiple" required tabindex="14">
                                        <option value="{{ALL}}">{{translate('ALL')}}</option>
                                        @foreach($zones as $zone)
                                            <option value="{{$zone->id}}">{{$zone->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-3 mt-3">
                            <button class="btn btn-secondary cmn_reset" type="reset" tabindex="15">{{ translate('reset') }}</button>
                            <button class="btn btn-primary cmn_focus" type="submit" tabindex="16">{{ translate('submit') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script src="{{dynamicAsset('public/assets/admin-module/js/promotion-management/discount-setup/create.js')}}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload.js') }}"></script>

    <script>
        "use strict";

        $(document).ready(function () {
            const prevImg = $('.upload-file__img_banner img').attr('src');
            $('#discountForm').on('reset', function () {
                setTimeout(function () {
                    $('.js-select').select2("destroy").select2()
                    $('.js-select-2').select2("destroy").select2()
                    $('.upload-file__img_banner img').attr('src', prevImg);
                }, 0);
            })


        })
        $(document).ready(function () {

            const amountType = $('#discountAmountType');
            const maxDiscountAmount = $('#maxDiscountAmount');

            amountTypeCheck();
            amountType.on('change', function () {
                amountTypeCheck();
            });

            function amountTypeCheck() {
                if (amountType.val() == 'amount') {
                    maxDiscountAmount.attr("readonly", "true");
                    document.getElementById('maxDiscountAmount').setAttribute("title", "{{translate('Max discount amount field not editable for discount amount type Fixed amount')}}");
                    maxDiscountAmount.val(0);
                    $("#discountAmountLabel").text("{{translate('Discount Amount')}} ({{session()->get('currency_symbol') ?? '$'}})");
                    $("#discountAmount").attr("placeholder", "Ex: 500")
                } else {
                    maxDiscountAmount.removeAttr("readonly");
                    maxDiscountAmount.removeAttr("title");
                    $("#discountAmountLabel").text("{{translate('Discount Percent ')}}(%)")
                    $("#discountAmount").attr("placeholder", "Ex: 50%")
                }
            }

        });


        $('#customerLevelDiscountType').on('change', function () {
            let selectElement = document.getElementById('customerDiscountType');
            selectElement.removeAttribute('disabled');
            let selectedValues = $(this).val();
            $.ajax({
                url: '{{route('admin.customer.get-level-wise-customer')}}',
                type: 'GET',
                data: {
                    levels: selectedValues
                },
                success: function (response) {
                    $('#customerDiscountType').empty();
                    if (response.length > 0) {
                        // $("#customerDiscountType").removeAttribute("disabled");
                        $('#customerDiscountType').append('<option value="{{ALL}}">{{translate('ALL')}}</option>');
                        $.each(response, function (index, value) {
                            $('#customerDiscountType').append('<option value="' + value.id + '">' + value.first_name + ' ' + value.last_name + '</option>');
                        });
                    } else {
                        let selectElement = document.getElementById('customerDiscountType');
                        selectElement.setAttribute('disabled', 'disabled');
                    }
                }
            });
        });
    </script>
@endpush
