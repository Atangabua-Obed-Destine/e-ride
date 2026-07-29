@php use Carbon\Carbon; @endphp
@extends('adminmodule::layouts.master')

@section('title', translate('Surge_Price_Details'))

@push('css_or_js')
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin-module/plugins/daterangepicker/daterangepicker.css')}}">
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin-module/plugins/apex/apexcharts.css')}}"/>
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-end align-items-center gap-3 mb-4">
                <div class="flex-grow-1">
                    <h2 class="fs-22 mb-2 text-capitalize">{{ translate('Surge Price Details') }}</h2>
                    <h4 class="mb-0 d-flex gap-2 align-items-center">
                        <span class="opacity-75 fw-bold">{{translate("ID")}} #{{$surgePrice->readable_id}}</span>

                        <span class="badge {{$surgePrice->statistic['badge']}}">{{ translate($surgePrice->statistic['name']) }}</span>
                    </h4>
                </div>
                <div>
                    <a
                        data-url="{{ route('admin.fare.surge-pricing.delete', ['id' => $surgePrice?->id]) }}"
                        data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                        data-title="{{ translate('Are you sure to delete this Surge Price')."?" }}"
                        data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed from the list.') }}"
                        data-confirm-btn="{{translate("Yes, Delete")}}"
                        data-cancel-btn="{{translate("Not Now")}}"
                        class="btn btn-outline-danger h-40px delete-button">
                        <i class="bi bi-trash-fill"></i>{{ translate('Delete') }}
                    </a>
                </div>
                <div
                        class="align-items-center border-primary d-flex form-control gap-3 justify-content-between w-130px h-40px">
                    <div>{{ translate('Status') }}</div>
                    <label class="switcher mx-auto">
                        <input class="switcher_input surge_price_status_change"
                               type="checkbox"
                               id="{{ $surgePrice->id }}"
                               data-url="{{ route('admin.fare.surge-pricing.status') }}"
                               data-icon=" {{ $surgePrice->is_active == 1 ? dynamicAsset('public/assets/admin-module/img/svg/turn-off-surge-pricing.svg') : dynamicAsset('public/assets/admin-module/img/svg/turn-on-surge-pricing.svg')}}"
                               data-title="{{translate('Are you sure')}}?"
                               data-sub-title="{{$surgePrice->is_active == 1 ? translate('Do you want to turn OFF Surge Price for? When it is off the customer do not need to pay extra fare.') : translate('Do you want to turn On Surge Price? When it is on the customer needs to pay extra fare.')}}"
                               data-confirm-btn="{{$surgePrice->is_active == 1  ? translate('Turn Off') : translate('Turn On')}}"
                                {{ $surgePrice->is_active == 1 ? "checked": ""  }}
                        >
                        <span class="switcher_control"></span>
                    </label>
                </div>

                <a href="javascript:" class="btn btn-primary h-40px surge-pricing-offcanvas"
                   data-url="{{ route('admin.fare.surge-pricing.edit', $surgePrice->id) }}"
                   data-bs-toggle="offcanvas"
                   data-bs-target="#surge-price-offcanvas">
                    <i class="bi bi-pencil"></i>
                    {{ translate('Edit Surge Price') }}
                </a>
            </div>

            <div class="card card-body border-0 mb-3">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <div>
                            <h4 class="mb-1">{{ $surgePrice->name }}</h4>
                            <div class="mb-1 d-flex gap-1 text-dark fs-12">
                                <span class="opacity-75">{{ translate('Created Date') }}</span>
                                :
                                <span class="fw-medium">{{ $surgePrice->created_at->format('d F Y, h:i a') }}</span>
                            </div>
                            <div class="d-flex gap-1 text-dark fs-12">
                                <span class="opacity-75">{{ translate('Last Edited Date') }}</span>
                                :
                                <span class="fw-medium">{{ $surgePrice->updated_at->format('d F Y, h:i a') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="bg-light rounded p-2">
                            <h6 class="fw-semibold mb-2">{{ translate('Price Increase Rate') }}</h6>
                            <div class="row g-2">
                                @if( $surgePrice->increase_for_all_parcels)
                                    <div class="col-sm-6">
                                        <div
                                                class="rounded bg-white p-2 min-h-40px p-2 d-flex justify-content-center align-items-center gap-2">
                                            <span class="text-dark fw-bold h4 mb-0">{{$surgePrice->all_parcel_surge_percent}}%</span>
                                            <span class="fs-12">{{ translate('For All Parcels') }}</span>
                                        </div>
                                    </div>
                                @endif
                                @if($surgePrice->increase_for_all_vehicles)
                                    <div class="col-sm-6">
                                        <div
                                                class="rounded bg-white p-2 min-h-40px p-2 d-flex justify-content-center align-items-center gap-2">
                                            <span class="text-dark fw-bold h4 mb-0">{{$surgePrice->all_vehicle_surge_percent}}%</span>
                                            <span class="fs-12">{{ translate('For All Types Ride') }}</span>
                                        </div>
                                    </div>
                                @endif
                                @if(!$surgePrice->increase_for_all_vehicles && in_array($surgePrice->surge_pricing_for,['ride','both']))
                                    @foreach($surgePrice->surgePricingServiceCategories as $surgePriceServiceCategory)
                                        @if($surgePriceServiceCategory->service_category_type === \Modules\VehicleManagement\Entities\VehicleCategory::class)
                                            <div class="col-sm-6">
                                                <div
                                                        class="rounded bg-white p-2 min-h-40px d-flex justify-content-center align-items-center gap-2">
                                                    <span class="text-dark fw-bold h4 mb-0">
                                                        {{ $surgePriceServiceCategory->surge_multiplier }}%
                                                    </span>
                                                    <span class="fs-12">
                                                        {{ $surgePriceServiceCategory->serviceCategory->name ?? 'Unknown Vehicle Category' }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @php
                $timeSlot =  $surgePrice?->surgePricingTimeSlot;
                $startDate = $timeSlot?->start_date;
                $endDate = $timeSlot?->end_date;
                $totalDates = 0;
                if ( isset($surgePrice) && $surgePrice->schedule === 'weekly' && $surgePrice?->surgePricingTimeSlot?->end_date !== 'unlimited'){
                        $selectedDates = $timeSlot?->selected_days ?? [];
                        $period = new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), (new DateTime($endDate))->modify('+1 day'));
                        $totalDates = count(array_filter(iterator_to_array($period), fn($date) => in_array($date->format('l'), $selectedDates)));
                } elseif (isset($surgePrice) && $surgePrice?->schedule === 'daily'){
                    $totalDates = Carbon::parse($surgePrice->surgePricingTimeSlot->start_date)->diffInDays(Carbon::parse($surgePrice->surgePricingTimeSlot->end_date)) + 1;
                } elseif(isset($surgePrice) && $surgePrice?->schedule === 'custom'){
                    $totalDates = count($surgePrice->surgePricingTimeSlot->slots);
                }

                $slot = $surgePrice->surgePricingTimeSlot->slots[0];
                $start = Carbon::parse($slot['start_time'])->format('h:i A');
                $end = Carbon::parse($slot['end_time'])->format('h:i A');
                $formattedDateRange = Carbon::parse($surgePrice?->surgePricingTimeSlot?->start_date)->format('d M Y')
                . ' - ' .
                (
                    $surgePrice?->surgePricingTimeSlot?->end_date === 'unlimited'
                        ? 'until turn off'
                        : Carbon::parse($surgePrice?->surgePricingTimeSlot?->end_date)->format('d M Y')
                );
            @endphp
            <div class="card card-body border-0 mb-3">
                <h5 class="mb-3">{{ translate('Surge Price Details') }}</h5>
                <div class="row g-3">
                    @if($surgePrice->schedule == 'daily')
                        <div class="col-lg-8">
                            <div class="card card-body border-0 h-100">
                                <div class="mb-2 d-flex gap-1 align-items-center justify-content-between mb-3">
                                    <h6 class="fw-semibold">{{ translate('Surge Price Schedule') }}</h6>
                                    <a href="javascript:"
                                       data-bs-toggle="offcanvas"
                                       data-url="{{ route('admin.fare.surge-pricing.edit-schedule', $surgePrice->id) }}"
                                       data-bs-target="#surge-price-offcanvas"
                                       class="d-flex gap-2 align-items-center text-info surge-pricing-offcanvas"
                                    >
                                        {{ translate('Edit') }}
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                                <div
                                        class="d-flex gap-3 flex-wrap justify-content-between align-items-strech text-dark mb-10px">
                                    <div
                                            class="bg-light rounded p-2 min-h-40px d-flex align-item-center justify-content-between flex-wrap gap-2 flex-grow-1">
                                        <div class="d-flex flex-column gap-1 flex-grow-1">
                                            <div class="d-flex gap-2 align-items-start flex-wrap">
                                                <span><i class="bi bi-calendar text-primary"></i> </span>
                                                <div>
                                                    <div
                                                            class="text-dark fw-medium">{{ $formattedDateRange }}
                                                    </div>
                                                </div>
                                                <span
                                                        class="bg-white rounded px-2 py-1 fs-12 fw-semibold"> {{ translate('Daily') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                            class="bg-light rounded p-2 min-h-40px d-flex gap-2 align-items-center justify-content-center flex-grow-1">
                                        <i class="bi bi-clock text-primary"></i>
                                        <span>
                                                {{ $start }} - {{ $end }}
                                        </span>
                                    </div>
                                </div>
                                <div
                                        class="mb-2 fw-medium">{{ translate('Total').' '.$totalDates .' ' .translate('Days Applied') }}</div>
                                <div
                                        class="fs-12">{{ translate('This surge price will be Automatically activated during the times and dates listed above.') }}</div>
                            </div>
                        </div>
                    @elseif($surgePrice->schedule == 'weekly')
                        <div class="col-lg-8">
                            <div class="card card-body border-0 h-100">
                                <div class="mb-2 d-flex gap-1 align-items-center justify-content-between mb-3">
                                    <h6 class="fw-semibold">{{ translate('Surge Price Schedule') }}</h6>
                                    <a href="javascript:"
                                       data-bs-toggle="offcanvas"
                                       data-url="{{ route('admin.fare.surge-pricing.edit-schedule', $surgePrice->id) }}"
                                       data-bs-target="#surge-price-offcanvas"
                                       class="d-flex gap-2 align-items-center text-info surge-pricing-offcanvas"
                                    >
                                        {{ translate('Edit') }}
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                                <div
                                        class="d-flex gap-3 flex-wrap justify-content-between align-items-strech text-dark mb-10px">
                                    <div
                                            class="bg-light rounded p-2 min-h-40px d-flex align-item-center justify-content-between flex-wrap gap-2 flex-grow-1">
                                        <div class="d-flex flex-column gap-1 flex-grow-1">
                                            <div class="d-flex gap-2 align-items-start flex-wrap">
                                                <span><i class="bi bi-calendar text-primary"></i> </span>
                                                <div>
                                                    <div
                                                            class="text-dark fw-medium">{{ $formattedDateRange }}</div>
                                                    <div class="mt-2">{{implode(' , ', $surgePrice?->surgePricingTimeSlot?->selected_days ?? [])}}</div>
                                                </div>
                                                <span
                                                        class="bg-white rounded px-2 py-1 fs-12 fw-semibold"> {{ translate('Weekly') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                            class="bg-light rounded p-2 min-h-40px d-flex gap-2 align-items-center justify-content-center flex-grow-1">
                                        <i class="bi bi-clock text-primary"></i>
                                        <span>{{ $start }} - {{ $end }}</span>
                                    </div>
                                </div>
                                @if($surgePrice->surgePricingTimeSlot?->end_date !== 'unlimited')
                                    <div
                                        class="mb-2 fw-medium">Total {{ $totalDates }} Days Applied</div>

                                @endif
                                <div
                                        class="fs-12">{{ translate('This surge price will be Automatically activated during the times and dates listed above.') }}</div>
                            </div>
                        </div>
                    @elseif($surgePrice->schedule == 'custom')
                        <div class="col-lg-8">
                            <div class="card card-body border-0 h-100">
                                <div class="mb-2 d-flex gap-1 align-items-center justify-content-between mb-3">
                                    <h6 class="fw-semibold">{{ translate('Surge Price Schedule') }}</h6>
                                    <a href="javascript:"
                                       data-bs-toggle="offcanvas"
                                       data-url="{{ route('admin.fare.surge-pricing.edit-schedule', $surgePrice->id) }}"
                                       data-bs-target="#surge-price-offcanvas"
                                       class="d-flex gap-2 align-items-center text-info surge-pricing-offcanvas">
                                        {{ translate('Edit') }}
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                                <div
                                        class="d-flex gap-3 flex-wrap justify-content-between align-items-strech text-dark mb-10px">
                                    <div
                                            class="bg-light rounded p-2 min-h-40px d-flex align-item-center justify-content-between flex-wrap gap-2 flex-grow-1">
                                        <div class="d-flex flex-column gap-1 flex-grow-1">
                                            <div class="d-flex gap-2 align-items-start flex-wrap">
                                                <span><i class="bi bi-calendar text-primary"></i> </span>
                                                <div>
                                                    <div class="text-dark fw-medium">{{ $formattedDateRange }}</div>
                                                </div>
                                                <span
                                                        class="bg-white rounded px-2 py-1 fs-12 fw-semibold"> {{ translate('Custom') }}</span>
                                            </div>
                                        </div>
                                        <a href="javascript:"
                                           class="d-flex gap-2 align-items-center text-info surge-pricing-offcanvas no-form-submit"
                                           data-url="{{ route('admin.fare.surge-pricing.get-custom-date-list-in-details', $surgePrice->id) }}"
                                           data-bs-toggle="offcanvas"
                                           data-bs-target="#surge-price-offcanvas"
                                        >
                                            <span
                                                    class="text-underline">{{ translate('View Custom Dates & Times') }}</span>
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="mb-2 fw-medium">{{ translate(key: 'Total {countDays} Days Applied', replace: ['countDays' => $totalDates]) }}</div>
                                <div
                                        class="fs-12">{{ translate('This surge price will be Automatically activated during the times and dates listed above.') }}</div>
                            </div>
                        </div>
                    @endif
                    <div class="col-lg-4">
                        <div class="card card-body border-0 h-100">
                            <div class="mb-2 d-flex gap-1 align-items-center justify-content-between mb-3">
                                <h6 class="fw-semibold">{{ translate('Surge Price Applicable For') }}</h6>
                                <a href="javascript:"
                                   data-bs-toggle="offcanvas"
                                   data-url="{{ route('admin.fare.surge-pricing.edit-price-applicable-for', $surgePrice->id) }}"
                                   data-bs-target="#surge-price-offcanvas"
                                   class="d-flex gap-2 align-items-center text-info surge-pricing-offcanvas">
                                    {{ translate('Edit') }}
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                            <div class="d-flex gap-2 flex-wrap align-items-center">
                                @if(in_array($surgePrice->surge_pricing_for,['ride','both']))
                                    <span
                                            class="bg-F6F6F6 fw-medium px-2 py-1 rounded">{{ translate('Ride Sharing') }}</span>
                                @endif
                                @if(in_array($surgePrice->surge_pricing_for,['parcel','both']))
                                    <span
                                            class="bg-F6F6F6 fw-medium px-2 py-1 rounded">{{ translate('Parcel Delivery') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-body border-0 mb-3">
                <h5 class="mb-3">{{ translate('Other Details') }}</h5>
                <div class="row g-3">
                    @if(empty($surgePrice->customer_note))
                        <div class="col-lg-7">
                            <div class="card card-body border-0 h-100 d-flex flex-column gap-3">
                                <div class="d-flex gap-1 align-items-center justify-content-between">
                                    <h6 class="fw-semibold">{{ translate('Note_For_Customer') }}</h6>
                                    <a href="javascript:"
                                       data-bs-toggle="modal" data-bs-target="#editNoteForCustomerModal"
                                       class="d-flex align-items-center text-success">
                                        {{ translate('Add_Note') }}
                                        <i class="bi bi-plus fs-24"></i>
                                    </a>
                                </div>
                                <div class="bg-light rounded p-20 d-flex gap-3 flex-column align-items-center justify-content-center flex-grow-1">
                                    <img src="{{ dynamicAsset('public/assets/admin-module/img/svg/note.svg') }}" class="svg" alt="">
                                    <p class="fs-12 mb-0">{{ translate('no_notes_have_been_set_for_customers_yet.') }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="col-lg-7">
                            <div class="card card-body border-0 h-100">
                                <div class="mb-2 d-flex gap-1 align-items-center justify-content-between mb-3">
                                    <h6 class="fw-semibold">{{ translate('Note For Customer') }}</h6>
                                    <a class="d-flex gap-2 align-items-center text-info" data-bs-toggle="modal" data-bs-target="#editNoteForCustomerModal"
                                       href="javascript:">
                                        {{ translate('Edit') }}
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                                <p class="fs-12 mb-0">
                                    {{ $surgePrice->customer_note }}
                                </p>
                            </div>
                        </div>
                    @endif
                    <div class="col-lg-5">
                        <div class="card card-body border-0 h-100 min-h-180px">
                            <div class="mb-2 d-flex gap-1 align-items-center justify-content-between mb-3">
                                <h6 class="fw-semibold">{{ translate('Applied Zone') }}</h6>
                                <a href="javascript:" data-bs-toggle="modal" data-bs-target="#EditZoneModal"
                                   class="d-flex gap-2 align-items-center text-info">
                                    {{ translate('Edit') }}
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                            <div class="d-flex gap-2 flex-wrap align-items-center">
                                @foreach($surgePrice->surgePricingZones->pluck('name')->toArray() as $zone)
                                    <span
                                            class="bg-F6F6F6 fw-medium px-2 py-1 rounded">{{ $zone }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trip Statistics -->
            <div class="card border-0">
                <div class="card-header d-flex flex-wrap justify-content-between gap-2 border-0 shadow-sm">
                    <div class="d-flex flex-column gap-1">
                        <h6 class="text-capitalize">{{translate('Trip Statistics')}}</h6>
                        <p class="fs-14 mb-0">Total {{ count($surgePrice->zones) }} Area</p>
                    </div>
                    <div>
                        <select class="js-select cmn_focus" id="getZoneStatsForSurgePricing">
                            <option disabled>{{translate('Select_Area')}}</option>
                            <option selected value="all">{{translate('All Zones')}}</option>
                            @foreach($surgePrice->zones as $id => $zone)
                                <option value="{{ $id }}">{{ $zone }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="card-body hide-2nd-line-of-chart" id="updating_line_chart">
                    <div id="apex_line-chart"></div>
                </div>
            </div>
            <!-- End Trip Statistics -->
        </div>
    </div>

    <div class="offcanvas offcanvas-end" id="surge-price-offcanvas" style="--bs-offcanvas-width: 490px">

    </div>

    @if($surgePrice->schedule === 'weekly')
        <div class="modal fade weekly-days-modal" id="selectWeeklyDaysModal-{{ $surgePrice->id }}"
             data-modal-id="selectWeeklyDaysModal-{{ $surgePrice->id }}" tabindex="-1"
             aria-labelledby="selectWeeklyDaysModalLabel-{{ $surgePrice->id }}"
             aria-hidden="true">
                <?php $surgePricingSelectedDays = $surgePrice?->surgePricingTimeSlot->selected_days; ?>
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="text-center mb-30">
                            <h3 class="mb-1">{{ translate('Select Days') }}</h3>
                            <p class="fs-12">{{ translate('Your Surge price active date ') }}</p>
                        </div>

                        <div class="bg-light mt-3 rounded p-3 mb-3">
                            <div class="d-flex flex-wrap column-gap-4 row-gap-2 user-select-none align-items-center">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="saturday"
                                           name="for_whom[]" @checked(in_array(translate('saturday'), $surgePricingSelectedDays))>
                                    <label class="form-check-label">{{ translate('saturday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="sunday"
                                           name="for_whom[]" @checked(in_array(translate('sunday'), $surgePricingSelectedDays))>
                                    <label class="form-check-label">{{ translate('sunday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="monday"
                                           name="for_whom[]" @checked(in_array(translate('monday'), $surgePricingSelectedDays))>
                                    <label class="form-check-label">{{ translate('monday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="tuesday"
                                           name="for_whom[]" @checked(in_array(translate('tuesday'), $surgePricingSelectedDays))>
                                    <label class="form-check-label">{{ translate('tuesday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="wednesday"
                                           name="for_whom[]" @checked(in_array(translate('wednesday'), $surgePricingSelectedDays))>
                                    <label class="form-check-label">{{ translate('wednesday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="thursday"
                                           name="for_whom[]" @checked(in_array(translate('thursday'), $surgePricingSelectedDays))>
                                    <label class="form-check-label">{{ translate('thursday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="friday"
                                           name="for_whom[]" @checked(in_array(translate('friday'), $surgePricingSelectedDays))>
                                    <label class="form-check-label">{{ translate('friday') }}</label>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light mt-3 rounded p-3 mb-3">
                            <h4 class="mb-1">{{ translate('Date Range') }}</h4>
                            <p class="fs-12">{{ translate('Select the date range you want to repeat this cycle every week') }}
                                .</p>

                            <div class="mb-3">
                                <div class="position-relative select_date_range_wrapper">
                                    <input type="text" placeholder="{{ translate('Select_the_date_range') }}"
                                           value="{{ $surgePrice?->surgePricingTimeSlot?->end_date === 'unlimited' ? '' : date('m/d/Y', strtotime($surgePrice?->surgePricingTimeSlot?->start_date)) . ' - ' . date('m/d/y', strtotime($surgePrice?->surgePricingTimeSlot?->end_date)) }}"
                                           class="form-control date-range-picker">
                                    <div
                                            class="position-absolute top-0 h-100 p-3 d-flex justify-content-center align-items-center date_range_calender_icon">
                                        <i class="bi bi-calendar"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input assignAllTime" type="checkbox" id="assign" value="assign"
                                       name="assign[]" @checked($surgePrice?->surgePricingTimeSlot?->end_date === 'unlimited')>
                                <label class="form-check-label"
                                       for="assign">{{ translate('Assign this surge price permanently') }}</label>
                            </div>
                        </div>

                        <div class="d-flex gap-3 justify-content-end flex-wrap">
                            <button type="button" class="btn btn-secondary cmn_reset"
                                    data-bs-dismiss="modal">{{ translate('cancel') }}</button>
                            <button type="button"
                                    class="btn btn-primary saveWeeklyModalData">{{ translate('save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="modal fade weekly-days-modal" id="selectWeeklyDaysModal-create" tabindex="-1"
             aria-labelledby="selectWeeklyDaysModalLabel-create"
             aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="text-center mb-30">
                            <h3 class="mb-1">{{ translate('Select Days') }}</h3>
                            <p class="fs-12">{{ translate('Your Surge price active date ') }}</p>
                        </div>

                        <div class="bg-light mt-3 rounded p-3 mb-3">
                            <div class="d-flex flex-wrap column-gap-4 row-gap-2 user-select-none align-items-center">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="saturday" value="saturday"
                                           name="for_whom[]">
                                    <label class="form-check-label" for="saturday">{{ translate('saturday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="sunday" value="sunday"
                                           name="for_whom[]">
                                    <label class="form-check-label" for="sunday">{{ translate('sunday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="monday" value="monday"
                                           name="for_whom[]">
                                    <label class="form-check-label" for="monday">{{ translate('monday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="tuesday" value="tuesday"
                                           name="for_whom[]">
                                    <label class="form-check-label" for="tuesday">{{ translate('tuesday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="wednesday" value="wednesday"
                                           name="for_whom[]">
                                    <label class="form-check-label" for="wednesday">{{ translate('wednesday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="thursday" value="thursday"
                                           name="for_whom[]">
                                    <label class="form-check-label" for="thursday">{{ translate('thursday') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="friday" value="friday"
                                           name="for_whom[]">
                                    <label class="form-check-label" for="friday">{{ translate('friday') }}</label>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light mt-3 rounded p-3 mb-3">
                            <h4 class="mb-1">{{ translate('Date Range') }}</h4>
                            <p class="fs-12">{{ translate('Select the date range you want to repeat this cycle every week') }}
                                .</p>

                            <div class="mb-3">
                                <div class="position-relative select_date_range_wrapper">
                                    <input type="text" placeholder="{{ translate('Select_the_date_range') }}" value=""
                                           class="form-control date-range-picker">
                                    <div
                                            class="position-absolute top-0 h-100 p-3 d-flex justify-content-center align-items-center date_range_calender_icon">
                                        <i class="bi bi-calendar"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="form-check form-check-inline">
                                <input class="form-check-input assignAllTime" type="checkbox" id="assign" value="assign"
                                       name="assign[]">
                                <label class="form-check-label"
                                       for="assign">{{ translate('Assign this surge price permanently') }}</label>
                            </div>
                        </div>

                        <div class="d-flex gap-3 justify-content-end flex-wrap">
                            <button type="button" class="btn btn-secondary cmn_reset"
                                    data-bs-dismiss="modal">{{ translate('cancel') }}</button>
                            <button type="button"
                                    class="btn btn-primary saveWeeklyModalData">{{ translate('save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="modal shedule-modal fade" id="custom_shedule_modal" style="--bs-modal-zindex: 1080;" tabindex="-1"
         aria-labelledby="custom_shedule_modal" aria-hidden="true">
        <div class="modal-dialog modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-end border-0 p-0">
                    <div class="p-3">
                        <button type="button" class="btn btn-secondary text-absolute-white rounded-circle btn-action" data-bs-toggle="modal" aria-label="Close">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-body pt-0">
                    <div class="row g-3 pricing-schedule-calendar-wrapper">
                        <div class="col-md-6">
                            <div class="calendar">
                                <div class="d-flex align-items-center gap-4 justify-content-center mb-3">
                                    <pre class="mb-0 left"><i class="tio-chevron-left fs-24 cursor-pointer"></i></pre>
                                    <div class="header-display">
                                        <p class="display fs-14 font-semibold">""</p>
                                    </div>
                                    <pre class="mb-0 right"><i class="tio-chevron-right fs-24 cursor-pointer"></i></pre>
                                </div>

                                <div class="week">
                                    <div>Su</div>
                                    <div>Mo</div>
                                    <div>Tu</div>
                                    <div>We</div>
                                    <div>Th</div>
                                    <div>Fr</div>
                                    <div>Sa</div>
                                </div>
                                <div class="days"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="selected-listall">
                                <h5 class="mb-20">{{ translate('Selected Days List') }}</h5>
                                <div
                                        class="d-flex align-items-center justify-content-md-start justify-content-between gap-1 mb-2">
                                    <span
                                            class="fs-12 text-dark opacity-50 text-uppercase fw-bold min-w-120">{{ translate('Date') }}</span>
                                    <span
                                            class="fs-12 text-dark opacity-50 text-uppercase fw-bold pe-30 me-3">{{ translate('Time') }}</span>
                                </div>
                                <div class="selected-list-inner d-flex flex-column gap-3">

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 justify-content-end mt-4">
                        <button type="button" class="btn btn-secondary cmn_reset"
                                data-bs-dismiss="modal"> {{ translate('Cancel') }}</button>
                        <button type="button"
                                class="btn btn-primary saveCustomModalData"> {{ translate('Update') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Zone modal --}}
    <div class="modal fade" id="EditZoneModal" style="--bs-modal-width: 650px">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-end border-0 pb-0">
                    <button type="button" class="btn btn-secondary text-absolute-white rounded-circle btn-action"
                            data-bs-toggle="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form
                    action="{{ route('admin.fare.surge-pricing.update-zone-list', $surgePrice?->id) }}"
                    data-form-method="PUT" class="submit-form">
                    <div class="modal-body pt-0">
                        <h4 class="fw-semibold mb-2">{{ translate('Update Zone') }}</h4>
                        <p class="text-dark mb-3">{{ translate('Update zone for this surge price') }}</p>
                        <?php
                        $surgePricingZones = $surgePrice?->surgePricingZones->pluck('id')->toArray();
                        ?>
                        <div class="bg-light rounded p-3 d-flex flex-column gap-3 mb-20">
                            <label class="form-label fs-16 fw-semibold mb-0">
                                {{ translate('Zone') }}
                                <span class="text-danger">*</span>
                                <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                   data-bs-title="{{ translate('need_content') }}"></i>
                            </label>
                            <select class="js-select-multiple multiple-select2 js-select-2" name="zones[]" multiple="multiple"
                                    data-placeholder="{{ translate('Select_Zone') }}" required>
                                <option value="{{ALL}}" @selected($surgePrice->zone_setup_type == 'all')>{{translate('All Zones')}}</option>
                                @foreach($zones as $id => $zoneName)
                                    <option
                                        value="{{ $id }}" @selected(in_array($id, $surgePricingZones) && $surgePrice->zone_setup_type == 'custom')>{{ $zoneName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex flex-wrap gap-3 justify-content-end">
                            <button type="submit" class="btn btn-primary cmn_focus"> {{ translate('Update') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Note for customer modal --}}
    <div class="modal fade" id="editNoteForCustomerModal" style="--bs-modal-width: 650px">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-end border-0 pb-0">
                    <button type="button" class="btn btn-secondary text-absolute-white rounded-circle btn-action" data-bs-toggle="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form
                    action="{{ route('admin.fare.surge-pricing.update-customer-note', $surgePrice?->id) }}"
                    data-form-method="PUT" class="submit-form">
                    <div class="modal-body pt-0">
                        <h4 class="fw-semibold mb-1">{{ translate('Update Note For Customer') }}</h4>
                        <p class="text-dark mb-20">{{ translate('Update note for customer for this surge price') }}</p>
                        <div class="bg-light rounded p-3 mb-20 character-count">
                            <label class="form-label fw-semibold">{{ translate('Note for Customer') }}</label>
                            <textarea name="customer_note" id="note" rows="1"
                                      class="form-control character-count-field"
                                      maxlength="30"
                                      data-max-character="30"
                                      placeholder="{{ translate('Type note for customer') }}">{{ $surgePrice->customer_note }}</textarea>
                            <span class="d-flex justify-content-end">0/30</span>
                        </div>
                        <div class="d-flex flex-wrap gap-3 justify-content-end">
                            <button type="submit" class="btn btn-primary cmn_focus"> {{ translate('Update') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <span class="script-url"
          data-script-select-2="{{ dynamicAsset('public/assets/admin-module/js/select-2-init.js') }}"
          data-script-date-range-picker="{{ dynamicAsset('public/assets/admin-module/js/date-range-picker.js') }}"
          data-script-surge-price-schedule="{{ dynamicAsset('public/assets/admin-module/js/surge-price-schedule.js') }}"
    ></span>
@endsection

@push('script')
    <script src="{{dynamicAsset('public/assets/admin-module/plugins/apex/apexcharts.min.js')}}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/plugins/daterangepicker/moment.min.js') }}"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/plugins/daterangepicker/daterangepicker.min.js')}}"></script>
    <script src="{{  dynamicAsset('public/assets/admin-module/js/fare-management/surge-pricing/create-edit.js') }}"></script>
    <script>
        function tripStatistics(zone = 'all') {
            $.get({
                url: '{{ route('admin.fare.surge-pricing.get-statistics-data', $surgePrice->id) }}',
                dataType: 'json',
                data: { zone: zone },
                beforeSend: function () {
                    $('#resource-loader').show();
                },
                success: function (response) {
                    // Use backend keys and provide fallback
                    let hours = response.labels || [];
                    hours = hours.map(hour => hour.replace(/"/g, ''));
                    let timeRange = '{{ ($surgePrice->schedule == 'daily' || $surgePrice->schedule == 'weekly') ? ' (' . date('h:i A', strtotime($surgePrice->surgePricingTimeSlot->slots[0]['start_time'])) . ' - ' . date('h:i A', strtotime($surgePrice->surgePricingTimeSlot->slots[0]['end_time'])) . ')'  : '' }}';

                    let tripAmount = response.totalAmount || [];
                    let totalRide = response.totalRides || [];
                    let totalParcel = response.totalParcels || [];

                    // Remove old chart if exists
                    let oldChart = document.getElementById('apex_line-chart');
                    if (oldChart) oldChart.remove();

                    // Create new chart container
                    let graph = document.createElement('div');
                    graph.setAttribute("id", "apex_line-chart");
                    document.getElementById("updating_line_chart").appendChild(graph);

                    // Chart options
                    let options = {
                        series: [
                            { name: '{{translate("Trip Amount")}} ({{ businessConfig('currency_symbol')?->value ?? "$" }})', data: [0].concat(tripAmount) },
                            { name: '{{translate("Ride")}}', data: [0].concat(totalRide) },
                            { name: '{{translate("Parcel")}}', data: [0].concat(totalParcel) },
                        ],
                        chart: {
                            height: 366,
                            type: 'line',
                            dropShadow: {
                                enabled: true,
                                color: '#000',
                                top: 18,
                                left: 0,
                                blur: 10,
                                opacity: 0.1
                            },
                            toolbar: { show: false },
                        },
                        colors: ['#14B19E', '#f39c12', '#6c5ce7'],
                        dataLabels: { enabled: false },
                        stroke: { curve: 'smooth', width: 2 },
                        grid: {
                            yaxis: { lines: { show: true } },
                            borderColor: '#ddd',
                        },
                        markers: {
                            size: 1,
                            strokeColors: ['#14B19E', '#f39c12', '#6c5ce7'],
                            strokeWidth: 1,
                            fillOpacity: 0,
                            hover: { sizeOffset: 2 }
                        },
                        theme: { mode: 'light' },
                        xaxis: {
                            categories: ['00'].concat(hours),
                            labels: { offsetX: 0 },
                        },
                        legend: {
                            show: false,
                            position: 'bottom',
                            horizontalAlign: 'left',
                            floating: false,
                            offsetY: -10,
                            itemMargin: { vertical: 10 },
                        },
                        tooltip: {
                            shared: true,
                            intersect: false,
                            x: {
                                formatter: function(val, opts) {
                                    let dateLabel = hours[opts.dataPointIndex - 1] || val;
                                    return dateLabel + timeRange;
                                }
                            }
                        },
                        yaxis: {
                            tickAmount: 5,
                            labels: { offsetX: 0 },
                        }
                    };

                    // RTL support
                    if (localStorage.getItem('dir') === 'rtl') {
                        options.yaxis.labels.offsetX = -20;
                    }

                    // Render chart
                    let chart = new ApexCharts(document.querySelector("#apex_line-chart"), options);
                    chart.render();
                },
                complete: function () {
                    $('#resource-loader').hide();
                },
                error: function (xhr, status, error) {
                    $('#resource-loader').hide();
                    toastr.error('{{translate('failed_to_load_data')}}');
                },
            });
        }
        document.addEventListener("DOMContentLoaded", function () {
            tripStatistics();
        });
        $("#getZoneStatsForSurgePricing").on('change', function () {
            let zone = $("#getZoneStatsForSurgePricing").val();
            tripStatistics(zone);
        })
    </script>
@endpush
