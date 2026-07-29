@extends('adminmodule::layouts.master')

@section('title', translate('Trip Settings'))

@section('content')

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{translate('business_management')}}</h2>
            <div class="col-12 mb-3">
                <div class="">
                    @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
                </div>
            </div>
            <div class="card mb-3 text-capitalize">
                <form action="{{route('admin.business.setup.trip-fare.store')."?type=".TRIP_SETTINGS}}" id="trips_form"
                      method="POST">
                    @csrf

                    <div class="card-header">
                        <div>
                            <h5 class="d-flex align-items-center gap-2 mb-2">
                                {{ translate('trips_settings') }}
                            </h5>
                            <div class="fs-14">
                                {{ translate('Set up the general configuration for the customer journey.') }}
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-4">
                            <div class="pb-xl-4 pb-3">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-6 col-lg-5">
                                        <div>
                                            <h5 class="d-flex align-items-center gap-2 mb-1">
                                                {{ translate('Route Option Between Pickup & Destination') }}
                                            </h5>
                                            <div class="fs-14">
                                                {{ translate('Allow customers to add extra stops during booking.') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-7">
                                        <div class="form-control gap-2 align-items-center d-flex justify-content-between rounded cmn_focus">
                                            <div class="d-flex align-items-center fw-medium gap-2">
                                                {{ translate('Enable extra stops?') }}
                                            </div>
                                            <div class="position-relative">
                                                <label class="switcher">
                                                    <input type="checkbox" name="add_intermediate_points"
                                                        class="switcher_input" tabindex="1"
                                                        {{$settings->firstWhere('key_name', 'add_intermediate_points')?->value ? 'checked' : ''}}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pb-xl-4 pb-3 border-bottom">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-6 col-lg-5">
                                        <div>
                                            <h5 class="d-flex align-items-center gap-2 mb-1">
                                                {{ translate('Trip Request Active Time') }}
                                            </h5>
                                            <div class="fs-14">
                                                {{ translate('Set how long the trip request stays visible to drivers before it expires.') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-7">
                                        <div class="bg-F6F6F6 rounded p-lg-4 p-3">
                                            <label for="trip_request_active_time"
                                                   class="mb-2">{{ translate('Active time (mins)') }} <span class="text-danger">*</span></label>
                                            <div class="floating-form-group ">
                                                <div class="input-group_tooltip">
                                                    <input required type="number" class="form-control" placeholder="Ex: 5"
                                                           id="trip_request_active_time" name="trip_request_active_time"
                                                           value="{{$settings->firstWhere('key_name', 'trip_request_active_time')?->value}}" tabindex="2">
                                                    <i class="bi bi-info-circle-fill text-primary tooltip-icon"
                                                       data-bs-toggle="tooltip"
                                                       data-bs-title="{{translate('Customers’ trip requests will be visible to drivers for the time (in minutes) you have set here') . '. '. translate('When the time is over, the requests get removed automatically.')}}"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pb-xl-4 pb-3">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-6 col-lg-5">
                                        <div>
                                            <h5 class="d-flex align-items-center gap-2 mb-1">
                                                {{ translate('Driver OTP Confirmation for Trip') }}
                                            </h5>
                                            <div class="fs-14">
                                                {{ translate('Require OTP verification for extra security before starting the trip.') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-7">
                                        <div class="form-control gap-2 align-items-center d-flex justify-content-between rounded cmn_focus">
                                            <div class="d-flex align-items-center fw-medium gap-2">
                                                {{ translate('OTP confirmation required to start trip.') }}
                                            </div>
                                            <div class="position-relative">
                                                <label class="switcher">
                                                    <input type="checkbox" name="driver_otp_confirmation_for_trip"
                                                           class="switcher_input" tabindex="3"
                                                        {{ $settings->where('key_name', 'driver_otp_confirmation_for_trip')->first()->value ?? 0 == 1 ? 'checked' : '' }}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pb-xl-4 pb-3 border-bottom">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-6 col-lg-5">
                                        <div>
                                            <h5 class="d-flex align-items-center gap-2 mb-1">
                                                {{ translate('Customer & Driver Real-time location share') }}
                                            </h5>
                                            <div class="fs-14">
                                                {{ translate('Allow Customer & Driver to share their live location during the trip') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-7">
                                        <div class="form-control gap-2 align-items-center d-flex justify-content-between rounded cmn_focus">
                                            <div class="d-flex align-items-center fw-medium gap-2">
                                                {{ translate('Real-time location share option') }}
                                            </div>
                                            <div class="position-relative">
                                                <label class="switcher">
                                                    <input type="checkbox" name="enable_real_time_location_sharing" class="switcher_input" tabindex="4"
                                                        {{ $settings->where('key_name', 'enable_real_time_location_sharing')->first()->value ?? 0 == 1 ? 'checked' : '' }}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pb-xl-4 pb-3">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-6 col-lg-5">
                                        <div>
                                            <h5 class="d-flex align-items-center gap-2 mb-1">
                                                {{ translate('Female-Only Ride Service') }}
                                            </h5>
                                            <div class="fs-14">
                                                {{ translate('Allow female customer to request rides only with female drivers.') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-7">
                                        <div class="form-control gap-2 align-items-center d-flex justify-content-between rounded cmn_focus">
                                            <div class="d-flex align-items-center fw-medium gap-2">
                                                {{ translate('Enable Choose only Female Drivers option') }}
                                            </div>
                                            <div class="position-relative">
                                                <label class="switcher">
                                                    <input type="checkbox" name="female_only_ride_service" class="switcher_input" tabindex="5"
                                                        {{ $settings->where('key_name', 'female_only_ride_service')->first()->value ?? 0 == 1 ? 'checked' : '' }}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-3 flex-wrap justify-content-end">
                            <button class="btn btn-secondary text-uppercase cmn_focus"
                                    type="reset" tabindex="6">{{ translate('Reset') }}</button>
                            <button type="submit"
                                    class="btn btn-primary text-uppercase cmn_focus" tabindex="7">{{ translate('Save') }}</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card mb-3 text-capitalize">
                <form action="{{ route('admin.business.setup.schedule-trip.store')}}"
                      id="schedule_trip_form" method="POST">
                    @csrf
                    <div class="collapsible-card-body">
                        <div class="card-header flex-sm-nowrap flex-wrap d-flex align-items-center justify-content-between gap-3">
                            <div class="w-0 flex-grow-1">
                                <h5 class="mb-2">{{ translate('Schedule_trip') }}</h5>
                                <div class="fs-14">
                                    {{ translate('enable_customers_to_book_scheduled_trips_and_complete_the_setup_below_for_scheduled_trip_management.') }}
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <a href="javascript:"
                                   class="text-info fw-semibold fs-12 text-nowrap d-flex view-btn">
                                    <span class="text-underline">{{ translate('View') }}</span>
                                    @if(($settings->firstWhere('key_name', 'schedule_trip_status')->value ?? 0) == 1 )
                                        <span><i class="tio-arrow-upward"></i> </span>
                                    @else
                                        <span><i class="tio-arrow-downward"></i> </span>
                                    @endif
                                </a>
                                <label class="switcher rounded cmn_focus">
                                    <input class="switcher_input collapsible-card-switcher update-business-setting"
                                           id="scheduleTripStatus" type="checkbox"
                                           name="schedule_trip_status" tabindex="6"
                                           data-name="schedule_trip_status" data-type="{{ SCHEDULE_TRIP_SETTINGS }}"
                                           data-url="{{ route('admin.business.setup.update-business-setting') }}"
                                           data-icon=" {{ ($settings->firstWhere('key_name', 'schedule_trip_status')->value ?? 0) == 1 ? dynamicAsset('public/assets/admin-module/img/svg/turn-off-schedule-trip.svg') : dynamicAsset('public/assets/admin-module/img/svg/turn-on-schedule-trip.svg') }}"
                                           data-title="{{ ($settings->firstWhere('key_name', 'schedule_trip_status')->value ?? 0) == 1 ? translate('Want to disable scheduled trips') : translate('Want to enable scheduled trips') }}?"
                                           data-sub-title="{{ ($settings->firstWhere('key_name', 'schedule_trip_status')->value ?? 0) == 1 ? translate('If you disable scheduled trips, then users will no longer be able to book rides in advance.') : translate('If you enable scheduled trips, then users will be able to book rides in advance.') }}"
                                           data-confirm-btn="{{ ($settings->firstWhere('key_name', 'schedule_trip_status')->value ?? 0) == 1 ? translate('Turn Off') : translate('Turn On') }}"
                                        {{ ($settings->firstWhere('key_name', 'schedule_trip_status')->value ?? 0) == 1 ? 'checked' : '' }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                        </div>
                        <div class="card-body collapsible-card-content">
                            <div class="d-flex flex-column gap-4">
                                <div class="pb-xl-4 pb -3 border-bottom">
                                    <div class="row g-3">
                                        <div class="col-md-6 col-lg-4">
                                            <div>
                                                <h5 class="d-flex align-items-center gap-2 mb-2">
                                                    {{ translate('Trip Setup') }}
                                                </h5>
                                                <div class="fs-14">
                                                    {{ translate('Set how early or how far in advance customers can schedule a trip.') }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-8">
                                            <div class="bg-F6F6F6 rounded p-lg-4 p-3">
                                                <div class="mb-20">
                                                    <label for="minimumScheduleBook"
                                                           class="form-label">{{ translate('minimum_schedule_book') }} <span class="text-danger">*</span> <i
                                                            class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-title="{{ translate('Set the minimum time (in minutes or hours) before the ride start time that a customer can schedule a trip.') }}"></i></label>
                                                    <div class="input-group input--group">
                                                        <input type="number" name="minimum_schedule_book_time" id="minimumScheduleBook"
                                                               step="1" min="1" max="99999999" class="form-control"
                                                               value="{{ $settings->firstWhere('key_name', 'minimum_schedule_book_time')?->value }}"
                                                               placeholder="Ex : 40" required tabindex="7">
                                                        <select id="minimumScheduleBookTimeType" class="form-select" name="minimum_schedule_book_time_type" tabindex="8">
                                                            <option value="day"
                                                                {{ $settings->firstWhere('key_name', 'minimum_schedule_book_time_type')?->value == 'day' ? 'selected' : '' }}>
                                                                {{ translate('Day') }}</option>
                                                            <option value="hour"
                                                                {{ $settings->firstWhere('key_name', 'minimum_schedule_book_time_type')?->value == 'hour' ? 'selected' : '' }}>
                                                                {{ translate('Hour') }}</option>
                                                            <option value="minute"
                                                                {{ $settings->firstWhere('key_name', 'minimum_schedule_book_time_type')?->value == 'minute' ? 'selected' : '' }}>
                                                                {{ translate('Minute') }}</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label for="advanceScheduleBook"
                                                        class="form-label">{{ translate('advance_schedule_book') }} <span class="text-danger">*</span> <i
                                                            class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-title="{{ translate('Define how far in advance (in days or hours) a customer can book a scheduled ride.') }}"></i></label>
                                                    <div class="input-group input--group">
                                                        <input type="number" name="advance_schedule_book_time" id="advanceScheduleBook"
                                                            step="1" min="1" max="99999999" class="form-control"
                                                            value="{{ $settings->firstWhere('key_name', 'advance_schedule_book_time')?->value }}"
                                                            placeholder="Ex : 60" required tabindex="9">
                                                        <select id="advanceScheduleBookTimeType" class="form-select" name="advance_schedule_book_time_type" tabindex="10">
                                                            <option value="day"
                                                                {{ $settings->firstWhere('key_name', 'advance_schedule_book_time_type')?->value == 'day' ? 'selected' : '' }}>
                                                                {{ translate('Day') }}</option>
                                                            <option value="hour"
                                                                {{ $settings->firstWhere('key_name', 'advance_schedule_book_time_type')?->value == 'hour' ? 'selected' : '' }}>
                                                                {{ translate('Hour') }}</option>
                                                            <option value="minute"
                                                                {{ $settings->firstWhere('key_name', 'advance_schedule_book_time_type')?->value == 'minute' ? 'selected' : '' }}>
                                                                {{ translate('Minute') }}</option>
                                                        </select>
                                                    </div>
                                                    <p id="time_conflicts_text_for_advance_schedule_book" class="text-danger text-end mt-2">{{ translate('your_input_time_conflicts_with_Minimum_Schedule_Book.') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="pb-xl-4 pb-3 border-bottom">
                                    <div class="row g-4">
                                        <div class="col-md-6 col-lg-4">
                                            <div>
                                                <h5 class="d-flex align-items-center gap-2 mb-2">
                                                    {{ translate('Driver Notification') }}
                                                </h5>
                                                <div class="fs-14">
                                                    {{ translate('Set how long before the trip the driver should be notified.') }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-8">
                                            <div class="bg-F6F6F6 rounded p-lg-4 p-3">
                                                <div>
                                                    <label for="driverRequestNotify"
                                                        class="form-label">{{ translate('driver_request_notify') }} <span class="text-danger">*</span> <i
                                                            class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-title="{{ translate('Set when drivers should be notified about upcoming scheduled trips (e.g., 30 mins before start).') }}"></i></label>
                                                    <div class="input-group input--group">
                                                        <input type="number" name="driver_request_notify_time" id="driverRequestNotify"
                                                            step="1" min="1" max="99999999" class="form-control"
                                                            value="{{ $settings->firstWhere('key_name', 'driver_request_notify_time')?->value }}"
                                                            placeholder="Ex : 60" required tabindex="11">
                                                        <select id="driverRequestNotifyTimeType" class="form-select" name="driver_request_notify_time_type" tabindex="12">
                                                            <option value="day"
                                                                {{ $settings->firstWhere('key_name', 'driver_request_notify_time_type')?->value == 'day' ? 'selected' : '' }}>
                                                                {{ translate('Day') }}</option>
                                                            <option value="hour"
                                                                {{ $settings->firstWhere('key_name', 'driver_request_notify_time_type')?->value == 'hour' ? 'selected' : '' }}>
                                                                {{ translate('Hour') }}</option>
                                                            <option value="minute"
                                                                {{ $settings->firstWhere('key_name', 'driver_request_notify_time_type')?->value == 'minute' ? 'selected' : '' }}>
                                                                {{ translate('Minute') }}</option>
                                                        </select>
                                                    </div>
                                                    <p id="time_conflicts_text_for_driver_request_notify" class="text-danger text-end mt-2">{{ translate('your_input_time_conflicts_with_Minimum_Schedule_Book.') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="pb-xl-4 pb-3">
                                    <div class="row g-4">
                                        <div class="col-md-6 col-lg-4">
                                            <div>
                                                <h5 class="d-flex align-items-center gap-2 mb-2">
                                                    {{ translate('Fare Increase') }}
                                                </h5>
                                                <div class="fs-14">
                                                    {{ translate('Charge a higher fare for scheduled trips compared to regular trips.') }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-lg-8">
                                            <div class="bg-F6F6F6 rounded p-lg-4 p-3">
                                                <div class="mb-20" id="increaseFareWrapper">
                                                    <label class="form-label" for="increaseFare">
                                                        {{ translate('Increase_Fare') }}
                                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                        data-bs-toggle="tooltip"
                                                        title="{{ translate('Enable automatic fare increase for scheduled trips during peak hours or high-demand times.') }}">
                                                        </i>
                                                    </label>
                                                    <div class="form-control gap-2 cmn_focus rounded align-items-center d-flex justify-content-between">
                                                        <div class="d-flex align-items-center fw-medium gap-2 text-capitalize">
                                                            {{ translate('Increase_fare_rate') . '?'}}
                                                        </div>
                                                        <div class="position-relative">
                                                            <label class="switcher">
                                                                <input type="checkbox" name="increase_fare"
                                                                    class="switcher_input" tabindex="13"
                                                                    {{ $settings->where('key_name', 'increase_fare')->first()->value ?? 0 == 1 ? 'checked' : '' }}>
                                                                <span class="switcher_control"></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="{{ $settings->where('key_name', 'increase_fare')->first()->value ?? 0 == 1 ? '' : 'visually-hidden' }}" id="increaseFareAmountWrapper">
                                                    <label for="IncreaseFareAmount" class="form-label">
                                                        {{ translate('Increase_Fare_amount_(%)') }} <span class="text-danger">*</span>
                                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                                        data-bs-title="{{ translate('Specify the percentage increase in fare for scheduled trips when demand or time-based pricing applies.') }}"></i></label>
                                                    <input type="number" name="increase_fare_amount" min="1" max="100" step="1" id="IncreaseFareAmount" class="form-control" placeholder="Ex : 10"
                                                        value="{{ $settings->firstWhere('key_name', 'increase_fare_amount')?->value }}" tabindex="14"
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-3 flex-wrap justify-content-end">
                                <button class="btn btn-secondary text-uppercase cmn_focus"
                                        type="reset" tabindex="15">{{ translate('Reset') }}</button>
                                <button type="submit"
                                        class="btn btn-primary text-uppercase cmn_focus" tabindex="16">{{ translate('submit') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>


            <div class="card mb-3 text-capitalize">
                <div class="card-header">
                   <div>
                        <h5 class="d-flex align-items-center mb-2 gap-2">
                            <!-- <i class="bi bi-person-fill-gear"></i> -->
                            {{ translate('trips_cancellation_messages') }}
                            <!-- <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                            data-bs-toggle="tooltip"
                            title="{{ translate('changes_may_take_some_hours_in_app') }}"></i> -->
                        </h5>
                        <div class="fs-14">
                            {{ translate('Here you can add the reasons that customer & user will select for cancel parcel') }}
                        </div>
                   </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.business.setup.trip-fare.cancellation_reason.store') }}"
                          method="post">
                        @csrf
                        <div class="bg-F6F6F6 rounded p-lg-4 p-3">
                            <div class="row gy-3 align-items-start">
                                <div class="col-sm-6 col-md-6">
                                    <label for="title" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                        {{ translate('trip_cancellation_reason') }}
                                        <small>({{translate('Max 255 character')}})</small>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip"
                                           title="{{ translate('Add or manage preset cancellation reasons that users or drivers can select when cancelling a trip.') }}">
                                        </i>
                                    </label>
                                    <div class="character-count">
                                        <input id="title" name="title" type="text"
                                               placeholder="{{translate('Ex : vehicle problem')}}"
                                               class="form-control character-count-field"
                                               maxlength="255" data-max-character="255" required tabindex="17">
                                        <span class="mt-1 d-block text-end text-muted">{{translate('0/255')}}</span>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label for="cancellationType" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                        {{ translate('cancellation_type') }} <span class="text-danger">*</span>
                                    </label>
                                    <select class="js-select cmn_focus" id="cancellationType" tabindex="18" name="cancellation_type"
                                            required>
                                        <option value="" disabled
                                                selected>{{translate('select_cancellation_type')}}</option>
                                        @foreach(CANCELLATION_TYPE as $key=> $item)
                                            <option value="{{$key}}">{{translate($item)}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label for="userType" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                        {{ translate('user_type') }} <span class="text-danger">*</span>
                                    </label>
                                    <select class="js-select cmn_focus" tabindex="19" id="userType" name="user_type" required>
                                        <option value="" disabled selected>{{translate('select_user_type')}}</option>
                                        <option value="driver">{{translate('driver')}}</option>
                                        <option value="customer">{{translate('customer')}}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-3 flex-wrap justify-content-end mt-4">
                            <button class="btn btn-secondary text-uppercase cmn_focus"
                                    type="reset" tabindex="20">{{ translate('Reset') }}</button>
                            <button type="submit"
                                    class="btn btn-primary text-uppercase cmn_focus" tabindex="21">{{ translate('submit') }}</button>
                        </div>
                    </form>
                    <div class="border-top pt-4 mt-4">
                        <div class="mb-20 border-0 d-flex flex-wrap gap-3 justify-content-between align-items-center">
                            <h5 class="d-flex align-items-center gap-2 m-0">
                                <!-- <i class="bi bi-person-fill-gear"></i> -->
                                {{ translate('trip_cancellation_reason_list') }}
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle">
                                    <thead class="table-light align-middle">
                                    <tr>
                                        <th class="sl">{{translate('SL')}}</th>
                                        <th class="text-capitalize">{{translate('Reason')}}</th>
                                        <th class="text-capitalize text-nowrap">{{translate('cancellation_type')}}</th>
                                        <th class="text-capitalize text-nowrap">{{translate('user_type')}}</th>
                                        <th class="text-capitalize">{{translate('Status')}}</th>
                                        <th class="text-center action">{{translate('Action')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($cancellationReasons as $key => $cancellationReason)
                                        <tr>
                                            <td class="sl">{{ $key + $cancellationReasons->firstItem() }}</td>
                                            <td>
                                                <p class="m-0 max-w-450 min-w-200">
                                                    {{$cancellationReason->title}}
                                                </p>
                                            </td>
                                            <td>
                                                {{ CANCELLATION_TYPE[$cancellationReason->cancellation_type] }}
                                            </td>
                                            <td>
                                                {{ $cancellationReason->user_type == 'driver' ? translate('driver') : translate('customer') }}
                                                {{$cancellationReason->status}}
                                            </td>
                                            <td class="text-center">
                                                <label class="switcher mx-auto">
                                                    <input class="switcher_input status-change"
                                                           data-url="{{ route('admin.business.setup.trip-fare.cancellation_reason.status') }}"
                                                           id="{{ $cancellationReason->id }}"
                                                           type="checkbox"
                                                           name="status" {{ $cancellationReason->is_active == 1 ? "checked": ""  }} >
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-2 align-items-center">
                                                    <button class="btn btn-outline-primary btn-action editData"
                                                            data-id="{{$cancellationReason->id}}">
                                                        <i class="bi bi-pencil-fill"></i>
                                                    </button>
                                                    <button data-url="{{ route('admin.business.setup.trip-fare.cancellation_reason.delete', ['id' => $cancellationReason?->id]) }}"
                                                            data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                            data-title="{{ translate('Are you sure to delete this Cancellation Reason') }}?"
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


        </div>
    </div>
    <div class="d-flex justify-content-end mt-3">
        {{ $cancellationReasons->links() }}
    </div>

    <div class="modal fade" id="editDataModal">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <!-- End Main Content -->
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";


        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#trips_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
        $(document).ready(function () {
            $('#time_conflicts_text_for_driver_request_notify, #time_conflicts_text_for_advance_schedule_book').hide();
            $('.editData').click(function () {
                let id = $(this).data('id');
                let url = "{{ route('admin.business.setup.trip-fare.cancellation_reason.edit', ':id') }}";
                url = url.replace(':id', id);
                $.get({
                    url: url,
                    success: function (data) {
                        $('#editDataModal .modal-content').html(data);
                        $('#updateForm').removeClass('d-none');
                        $('#editDataModal').modal('show');
                        $.getScript('{{ dynamicAsset('public/assets/admin-module/js/dev.js') }}');
                    },
                    error: function (xhr, status, error) {
                        console.log(error);
                    }
                });
            });

            // Set up event listener to handle selection change
            $('.js-select').select2();
            let select = $('.js-select-2').select2({
                placeholder: $(this).data('placeholder')
            });
            select.on('select2:select', function (e) {
                let select = $(this);
                if (e.params.data.id === 'all') {
                    select.find('option').prop('selected', false);
                    select.val(['all']).trigger('change');
                } else {
                    let selectedValues = select.val().filter(item => item !== 'all');
                    select.find('option[value="all"]').prop('selected', false);
                    select.val(selectedValues).trigger('change');
                }
            });

            select.on('select2:unselect', function (e) {
                let select = $(this);
                select.find('option[value="all"]').prop('selected', false);
            });

            // show input conflicts text
            $('#driverRequestNotify, #driverRequestNotifyTimeType, #minimumScheduleBook, #minimumScheduleBookTimeType, #advanceScheduleBook, #advanceScheduleBookTimeType').on('input', function () {
                showTimeConflictsText();
            });

            function convertToSeconds(value, type) {
                const timeMultipliers = {
                    day: 86400,
                    hour: 3600,
                    minute: 60,
                };

                return value * (timeMultipliers[type] || 0);
            }

            function showTimeConflictsText() {
                const getSeconds = (id, typeId) => convertToSeconds(parseInt($(id).val()), $(typeId).val()) || 0;

                const notifyTime = getSeconds('#driverRequestNotify', '#driverRequestNotifyTimeType');
                const minSchedule = getSeconds('#minimumScheduleBook', '#minimumScheduleBookTimeType');
                const advanceSchedule = getSeconds('#advanceScheduleBook', '#advanceScheduleBookTimeType');

                let hasConflict = false;

                if (notifyTime > minSchedule) {
                    $('#time_conflicts_text_for_driver_request_notify').show();
                    hasConflict = true;
                } else {
                    $('#time_conflicts_text_for_driver_request_notify').hide();
                }

                if (advanceSchedule <= minSchedule) {
                    $('#time_conflicts_text_for_advance_schedule_book').show();
                    hasConflict = true;
                } else {
                    $('#time_conflicts_text_for_advance_schedule_book').hide();
                }

                $('#schedule_trip_form button[type="submit"]').attr('disabled', hasConflict);
            }
            // show input conflicts text ends

            // Handle increase fare toggle
            $('#increaseFareWrapper').on('change', 'input[name="increase_fare"]', function () {
                if ($(this).is(':checked')) {
                    $('#increaseFareAmountWrapper').removeClass('visually-hidden');
                } else {
                    $('#increaseFareAmountWrapper').addClass('visually-hidden');
                }
            });

        });

    </script>
@endpush
