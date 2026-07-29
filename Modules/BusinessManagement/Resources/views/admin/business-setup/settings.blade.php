@extends('adminmodule::layouts.master')

@section('title', translate('Business_Information'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <form action="{{route('admin.business.setup.info.update-settings')}}" method="post" id="settings_form"
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
                        <div class="card card-body h-100">
                            <div class="mb-20">
                                <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                    {{translate('Business_Configuration')}}
                                </h5>
                                <p class="mb-0">{{ translate('general_settings_for_business_operation,_trip_behavior,_and_tax_policies.') }}</p>
                            </div>
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="row g-4">
                                    <div class="col-lg-6">
                                        <label class="text-capitalize mb-2">
                                            {{translate('Trip_Commission')}} (%) <span class="text-danger">*</span>
                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                data-bs-toggle="tooltip"
                                                title="{{translate('set_the_percentage_of_commission_the_platform_earns_per_trip. ')}}"></i>
                                        </label>
                                        <input type="number" name="trip_commission"
                                            class="form-control" id="business_name"
                                            placeholder="{{translate('Ex: 5')}}" step="0.1"
                                            value="{{$settings->firstWhere('key_name', 'trip_commission')->value ?? ''}}"
                                        tabindex="1">
                                    </div>
                                    <div class="col-lg-6">
                                        <div>
                                            <label class="text-capitalize mb-2">
                                                {{translate('VAT')}} (%) <span class="text-danger">*</span>
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                    data-bs-toggle="tooltip"
                                                    title="{{translate('enter_the_applicable_value-added_tax_rate_for_trip_billing.')}}"></i>
                                            </label>
                                            <input type="number" name="vat_percent" class="form-control"
                                                    id="business_contact_num" tabindex="2"
                                                    placeholder="{{translate('Ex: 5')}}" step="0.1"
                                                    value="{{$settings->firstWhere('key_name', 'vat_percent')->value ?? ''}}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card card-body h-100">
                            <div class="mb-20">
                                <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                    {{translate('Real-Time_&_Location_Settings')}}
                                </h5>
                                <p class="mb-0">{{ translate('configure_location_settings_and_live_updates_to_track_trips_and_keep_drivers_informed_in_real_time.') }}</p>
                            </div>
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="row g-4">
                                    <div class="col-lg-6">
                                        <label class="text-capitalize mb-2">
                                            {{translate('Search_Radius')}} (Km) <span class="text-danger">*</span>
                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                data-bs-toggle="tooltip"
                                                title="{{translate('defines_how_far_(in_kilometers)_a_customer_can_search_for_nearby_drivers_when_a_ride_request_is_made._by_default,_it_is_set_to_5_kilometers.')}}"></i>
                                        </label>
                                        <input type="number" name="search_radius" class="form-control"
                                            step="any" tabindex="3"
                                            id="business_email" placeholder="{{translate('Ex: 5')}}"
                                            value="{{$settings->firstWhere('key_name', 'search_radius')->value ?? ''}}">

                                    </div>
                                    <div class="col-lg-6">
                                        <div>
                                            <label class="text-capitalize mb-2">
                                                {{translate('Driver_Completion_Radius')}} (Meter) <span class="text-danger">*</span>
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                    data-bs-toggle="tooltip"
                                                    title="{{translate('drivers_can_complete_the_ride_within_the_radius_(in_meter)_you_have_set_here._by_default,_it_is_set_to_10_meters.')}}"></i>
                                            </label>
                                            <input type="number" name="driver_completion_radius"
                                                class="form-control" step="any" tabindex="4"
                                                id="driver_completion_radius"
                                                placeholder="{{translate('Ex: 15')}}"
                                                value="{{$settings->firstWhere('key_name', 'driver_completion_radius')->value ?? ''}}">

                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div>
                                            <label class="text-capitalize mb-2">
                                                {{translate('WebSocket_URL')}} <span class="text-danger">*</span>
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                    data-bs-toggle="tooltip"
                                                    title="{{translate('enter_the_websocket_server_url,_that_is_used_for_real-time_communication_between_drivers,_riders,_and_the_system.')}}"></i>
                                            </label>
                                            <input type="text" name="websocket_url" class="form-control"
                                                id="websocket_url" tabindex="5"
                                                placeholder="{{translate('Ex: your_domain_name')}}"
                                                value="{{$settings->firstWhere('key_name', 'websocket_url')->value ?? env('PUSHER_HOST')}}" disabled>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div>
                                            <label class="text-capitalize mb-2">
                                                {{translate('WebSocket_Port')}} <span class="text-danger">*</span>
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                    data-bs-toggle="tooltip"
                                                    title="{{translate('specify_the_port_number_for_the_websocket_connection_to_enable_live_tracking_and_instant_updates._port_default_6001.')}}"></i>
                                            </label>
                                            <input type="number" name="websocket_port" class="form-control"
                                                id="business_email" placeholder="{{translate('Ex: 6001')}}" tabindex="6"
                                                value="{{$settings->firstWhere('key_name', 'websocket_port')->value ?? ''}}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card card-body h-100">
                            <div class="mb-20">
                                <h5 class="d-flex align-items-center gap-2 text-capitalize mb-2">
                                    {{translate('Fare_Bidding')}}
                                </h5>
                                <p class="mb-0">{{ translate('enable_fare_bidding_between_drivers_and_customers.') }}</p>
                            </div>
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <label class="text-capitalize mb-2">
                                    {{translate('allow_drivers_and_customers_to_bid_on_trip_fares.')}}
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                        data-bs-toggle="tooltip"
                                        title="{{translate('enable_this_option_to_allow_drivers_to_bid_on_ride_fares_instead_of_accepting_fixed_prices.')}}"></i>
                                </label>
                                <label class="form-control d-flex align-items-center justify-content-between">
                                    <label for="customerReview" class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                                    <label class="switcher">
                                        <input class="switcher_input" type="checkbox" name="bid_on_fare"
                                                id="loyalty_point_switch" tabindex="12"
                                            {{ (businessConfig('bid_on_fare', 'business_settings')->value ?? 0) == 1 ? 'checked' : ''}}>
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
                                    {{translate('Pagination_Setting')}}
                                </h5>
                                <p class="mb-0">{{ translate('define_how_many_items_appear_per_page_in_listings.') }}</p>
                            </div>
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <label class="text-capitalize mb-2">
                                    {{translate('Pagination_Limit')}} <span class="text-danger">*</span>
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                        title="{{translate('set_the_number_of_items_(e.g.,_rides,_users,_drivers)_displayed_per_page_in_the_admin_panel_listings._by_default_it_is_10.')}}"></i>
                                </label>
                                <input type="number" name="pagination_limit" tabindex="13"
                                    class="form-control" id="business_email"
                                    placeholder="{{translate('Ex: 15')}}"
                                    value="{{$settings->firstWhere('key_name', 'pagination_limit')->value ?? ''}}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-sticky">
                <div class="container-fluid">
                    <div class="btn--container justify-content-end py-4">
                        <button type="reset" class="btn btn-secondary min-w-120 cmn_focus" tabindex="14">{{ translate('reset') }}</button>
                        <button type="submit"
                                class="btn btn-primary min-w-120 cmn_focus" tabindex="15">{{ translate('save_information') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')

    <script>
        "use strict";

        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#settings_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });

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

@endpush
