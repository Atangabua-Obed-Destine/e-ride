@php use Modules\ParcelManagement\Entities\ParcelCategory;use Modules\VehicleManagement\Entities\VehicleCategory; @endphp
@extends('adminmodule::layouts.master')

@section('title', translate('Surge_Price_Setup'))

@push('css_or_js')
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin-module/plugins/daterangepicker/daterangepicker.css')}}">
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-4">
                <h2 class="fs-22 mb-2 text-capitalize">{{ translate('Surge Price Setup') }}</h2>
                <h5 class="d-flex align-items-center gap-2 text-dark fw-medium cursor-pointer read-instruction"
                    data-bs-toggle="offcanvas" data-bs-target="#howItWork-offcanvas">
                    {{ translate('How it Works') }}
                    <i class="bi bi-info-circle"></i>
                </h5>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                        <form action="javascript:;" class="search-form search-form_style-two"
                              method="GET">
                            <div class="input-group search-form__input_group cmn_focus">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                <input type="search" class="theme-input-style search-form__input"
                                       value="{{ request()->get('search') }}" name="search" id="search"
                                       placeholder="{{ translate('search_here_by_id, name') }}" tabindex="1">
                            </div>
                            <button type="submit" class="btn btn-primary search-submit" tabindex="2"
                                    data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                        </form>

                        <div class="d-flex flex-wrap gap-3">
                            <a href="{{ route('admin.fare.surge-pricing.index') }}" class="btn btn-outline-primary px-3" data-bs-toggle="tooltip" tabindex="3"
                               data-bs-title="Refresh">
                                <i class="bi bi-arrow-repeat"></i>
                            </a>

                            <div class="dropdown">
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="dropdown" tabindex="4">
                                    <i class="bi bi-download"></i>
                                    {{ translate('Download') }}
                                    <i class="bi bi-caret-down-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                    <li><a class="dropdown-item"
                                           href="{{ route('admin.fare.surge-pricing.export') }}?search={{ request()->get('search') }}&&file=excel">{{ translate('excel') }}</a>
                                    </li>
                                </ul>
                            </div>
                            <button type="button" class="btn btn-primary surge-pricing-offcanvas" tabindex="5"
                                    data-url="{{ route('admin.fare.surge-pricing.create') }}"
                                    data-is-create-blade="true"
                                    data-bs-toggle="offcanvas"
                                    data-bs-target="#surge-price-offcanvas">
                                <i class="bi bi-plus-circle-fill"></i>
                                {{ translate('Create Surge Price') }}
                            </button>

                        </div>
                    </div>

                    <div id="trip-list-view">
                        @if(count($surgePricing) > 0)
                            <div class="table-responsive mt-3">
                                <table class="table table-borderless align-middle table-hover text-nowrap text-dark">
                                    <thead class="table-light align-middle text-capitalize">
                                    <tr>
                                        <th>{{ translate('SL') }}</th>
                                        <th>{{ translate('Surge  Info') }}</th>
                                        <th>{{ translate('Zone') }}</th>
                                        <th>{{ translate('Extra Price (%)') }}</th>
                                        <th>{{ translate('Time & Date') }}</th>
                                        <th class="text-center">{{ translate('Statistic') }}</th>
                                        <th class="text-center">{{ translate('Status') }}</th>
                                        <th class="text-center">{{ translate('Action') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($surgePricing as $key => $data)
                                        <tr>
                                            <td>{{ ++$key }}</td>
                                            <td>
                                                <a href="{{route('admin.fare.surge-pricing.show',$data?->id)}}" class="fw-semibold fs-14">{{ $data->name }}</a>
                                                <br>
                                                <div class="opacity-75">#{{ $data->readable_id }}</div>
                                            </td>
                                            <td>
                                                <a
                                                   @if(count($data['zones']) > 1)
                                                       href="javascript:"
                                                       class="text-underline surge-pricing-offcanvas no-form-submit"

                                                       data-url="{{ route('admin.fare.surge-pricing.get-zones', $data->id) }}"
                                                   data-bs-toggle="offcanvas"
                                                   data-bs-target="#surge-price-offcanvas"
                                                    @endif
                                                >
                                                    {{ $data['zone_text'] }}
                                                </a>
                                            </td>
                                            <td>
                                                <div class="max-w-140 min-w-120 text-wrap">
                                                    {!! $data->surge_multipliers !!}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="max-w-240">
                                                    <div class="d-flex gap-1 align-items-center mb-1">
                                                        <span class="fw-medium">{{ $data->date_range }}</span>
                                                        <span
                                                            class="badge bg-F6F6F6 opacity-75 px-2 rounded-10 text-dark fs-12">{{ translate(ucwords($data->schedule)) }}</span>
                                                    </div>
                                                    @if($data->schedule === 'custom')
                                                        <a
                                                            href="javascript:"
                                                            class="text-info fs-12 surge-pricing-offcanvas no-form-submit"
                                                            data-url="{{ route('admin.fare.surge-pricing.get-custom-date-list', $data->id) }}"
                                                            data-bs-toggle="offcanvas"
                                                            data-bs-target="#surge-price-offcanvas"
                                                        >
                                                            {{ translate('See All Custom Times') }}
                                                        </a>
                                                    @else
                                                        <div class="text-dark fs-12 mb-1"> {{ $data['date_time_slots']['time_slot'] }}</div>
                                                    @endif
                                                    @if($data->schedule === 'weekly')
                                                        <div class="fs-12 opacity-75 min-w-200 text-wrap">
                                                            {{ $data->selected_days }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center">
                                                    <span class="badge {{ $data['statistic']['badge'] }}">{{ translate($data['statistic']['name']) }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <label class="switcher mx-auto">
                                                    <input class="switcher_input surge_price_status_change"
                                                           type="checkbox"
                                                           id="{{ $data->id }}"
                                                           data-url="{{ route('admin.fare.surge-pricing.status') }}"
                                                           data-icon=" {{ $data->is_active == 1 ? dynamicAsset('public/assets/admin-module/img/svg/turn-off-surge-pricing.svg') : dynamicAsset('public/assets/admin-module/img/svg/turn-on-surge-pricing.svg')}}"
                                                           data-title="{{translate('Are you sure')}}?"
                                                           data-sub-title="{{$data->is_active == 1 ? translate('Do you want to turn OFF Surge Price') . '? ' . translate('When it is off the customer does not need to pay an extra fare.') : translate('Do you want to turn On Surge Price') . '? ' . translate('When it is on the customer needs to pay extra fare.') }}"
                                                           data-confirm-btn="{{$data->is_active == 1  ? translate('Turn Off') : translate('Turn On')}}"
                                                        {{ $data->is_active == 1 ? "checked": ""  }}
                                                    >
                                                    <span class="switcher_control"></span>
                                                </label>
                                            </td>
                                            <td class="text-center action">
                                                <div class="d-flex justify-content-center gap-2 align-items-center">
                                                    <a href="{{route('admin.fare.surge-pricing.show',$data?->id)}}"
                                                       class="btn btn-outline-success btn-action">
                                                        <i class="bi bi-eye-fill"></i>
                                                    </a>
                                                    <a href="javascript:" class="btn btn-outline-info btn-action surge-pricing-offcanvas"
                                                       data-url="{{ route('admin.fare.surge-pricing.edit', $data->id) }}"
                                                       data-bs-toggle="offcanvas"
                                                       data-bs-target="#surge-price-offcanvas">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a
                                                        data-url="{{ route('admin.fare.surge-pricing.delete', ['id' => $data?->id]) }}"
                                                        data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                        data-title="{{ translate('Are you sure to delete this Surge Price') }}?"
                                                        data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed from the list.') }}"
                                                        data-confirm-btn="{{translate("Yes, Delete")}}"
                                                        data-cancel-btn="{{translate("Not Now")}}"
                                                        class="btn btn-outline-danger btn-action d-flex justify-content-center align-items-center delete-button"
                                                        data-bs-toggle="tooltip" title="{{translate("Delete")}}">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        @if($data->schedule === 'weekly')
                                            <div class="modal fade weekly-days-modal" id="selectWeeklyDaysModal-{{ $data->id }}" data-modal-id="selectWeeklyDaysModal-{{ $data->id }}" tabindex="-1" aria-labelledby="selectWeeklyDaysModalLabel-{{ $data->id }}"
                                                 aria-hidden="true">
                                                <?php $surgePricingSelectedDays = $data?->surgePricingTimeSlot->selected_days;?>
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
                                                                        <label class="form-check-label" >{{ translate('saturday') }}</label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline">
                                                                        <input class="form-check-input" type="checkbox"  value="sunday"
                                                                               name="for_whom[]" @checked(in_array(translate('sunday'), $surgePricingSelectedDays))>
                                                                        <label class="form-check-label">{{ translate('sunday') }}</label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline">
                                                                        <input class="form-check-input" type="checkbox" value="monday"
                                                                               name="for_whom[]" @checked(in_array(translate('monday'), $surgePricingSelectedDays))>
                                                                        <label class="form-check-label" >{{ translate('monday') }}</label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline">
                                                                        <input class="form-check-input" type="checkbox"  value="tuesday"
                                                                               name="for_whom[]" @checked(in_array(translate('tuesday'), $surgePricingSelectedDays))>
                                                                        <label class="form-check-label" >{{ translate('tuesday') }}</label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline">
                                                                        <input class="form-check-input" type="checkbox" value="wednesday"
                                                                               name="for_whom[]" @checked(in_array(translate('wednesday'), $surgePricingSelectedDays))>
                                                                        <label class="form-check-label" >{{ translate('wednesday') }}</label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline">
                                                                        <input class="form-check-input" type="checkbox"  value="thursday"
                                                                               name="for_whom[]" @checked(in_array(translate('thursday'), $surgePricingSelectedDays))>
                                                                        <label class="form-check-label" >{{ translate('thursday') }}</label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline">
                                                                        <input class="form-check-input" type="checkbox"  value="friday"
                                                                               name="for_whom[]" @checked(in_array(translate('friday'), $surgePricingSelectedDays))>
                                                                        <label class="form-check-label" >{{ translate('friday') }}</label>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="bg-light mt-3 rounded p-3 mb-3">
                                                                <h4 class="mb-1">{{ translate('Date Range') }}</h4>
                                                                <p class="fs-12">{{ translate('Select the date range you want to repeat this cycle every week') }}
                                                                    .</p>

                                                                <div class="mb-3">
                                                                    <div class="position-relative select_date_range_wrapper">
                                                                        <input type="text" placeholder="{{ translate('Select_the_date_range') }}" value="{{ $data?->surgePricingTimeSlot?->end_date === 'unlimited' ? '' : date('m/d/Y', strtotime($data?->surgePricingTimeSlot?->start_date)) . ' - ' . date('m/d/y', strtotime($data?->surgePricingTimeSlot?->end_date)) }}"
                                                                               class="form-control date-range-picker">
                                                                        <div
                                                                            class="position-absolute top-0 h-100 p-3 d-flex justify-content-center align-items-center date_range_calender_icon">
                                                                            <i class="bi bi-calendar"></i>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="form-check form-check-inline">
                                                                    <input class="form-check-input assignAllTime" type="checkbox" id="assign" value="assign"
                                                                           name="assign[]" @checked($data?->surgePricingTimeSlot?->end_date === 'unlimited')>
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
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="rounded bg-light mt-3">
                                <div class="px-3 py-5">
                                    <div class="d-flex flex-column gap-2 align-items-center">
                                        <img src="{{dynamicAsset('public/assets/admin-module/img/svg/surge.svg')}}" class="svg"
                                             alt="">
                                        <h5>{{ translate('Currently you don’t have any Surge Price') }}</h5>
                                        <p>{{ translate('In this page you see all the surge Price you added. Please create new surge Price.') }}</p>
                                        <button type="button" class="btn btn-primary surge-pricing-offcanvas"
                                                data-url="{{ route('admin.fare.surge-pricing.create') }}"
                                                data-bs-toggle="offcanvas"
                                                data-bs-target="#surge-price-offcanvas">
                                            <i class="bi bi-plus-circle-fill"></i>
                                            {{ translate('Create Surge Price') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-end">
                        {!! $surgePricing->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" id="surge-price-offcanvas" style="--bs-offcanvas-width: 490px">

    </div>

    {{-- How Verification Work Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="howItWork-offcanvas" style="--bs-offcanvas-width: 490px">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                <h4 class="offcanvas-title flex-grow-1 text-center">
                    {{ translate('How Surge Price Work') }}
                </h4>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="d-flex flex-column gap-20">
                    <div class="bg-fafafa rounded p-sm-4 p-3">
                        <h5 class="fw-medium mb-3">{{ translate('What is Surge Price') . '?' }}</h5>
                        <div class="bg-white rounded p-lg-3 p-3">
                            <ul class="fs-14 d-flex flex-column gap-2 mb-0 ps-18px">
                                <li>
                                    {{ translate('Surge Price is an extra fee added to rides or parcel deliveries during high-demand or difficult times when completing a trip requires more effort from drivers') . '—' . translate('for example,
                                    during heavy rain, traffic jams, or other challenging conditions.') }}
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="bg-fafafa rounded p-sm-4 p-3">
                        <h5 class="fw-medium mb-3">{{ translate('Why Surge Price is Needed') }}?</h5>
                        <div class="bg-white rounded p-lg-3 p-3">
                            <ul class="fs-14 d-flex flex-column gap-2 mb-0 ps-18px">
                                <li>
                                    {{ translate('To fairly compensate drivers for the extra time and effort needed in tough situations.') }}
                                </li>
                                <li>
                                    {{ translate('To ensure customers can still get rides or deliveries when conditions are less favorable.') }}
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="bg-fafafa rounded p-sm-4 p-3">
                        <h5 class="fw-medium mb-3">{{ translate('How Surge Price Work') }}</h5>
                        <div class="bg-white rounded p-lg-3 p-3">
                            <ul class="fs-14 d-flex flex-column gap-2 mb-0 ps-18px">
                                <li>
                                    {{ translate('The admin manually creates a surge price rule for specific zones, vehicle types, or time periods.') }}
                                </li>
                                <li>
                                    {{ translate('When active, the surge fee is automatically added to the normal fare for rides or parcel deliveries.') }}
                                </li>
                                <li>
                                    {{ translate('Customers see the surge fee before confirming, and drivers receive higher earnings for those trips.') }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div
                class="offcanvas-footer d-flex gap-3 bg-white shadow position-sticky bottom-0 p-3 justify-content-center">
                <button type="button" class="btn btn-primary fw-semibold"
                        data-bs-dismiss="offcanvas">
                    {{translate('Okay, Got It') }}
                </button>
            </div>
        </form>
    </div>

    <!-- Weekly Select Days Modal -->
    <div class="modal fade weekly-days-modal" id="selectWeeklyDaysModal-create" tabindex="-1" aria-labelledby="selectWeeklyDaysModalLabel-create"
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

    <!-- Custom Schedule Select Calender Modal -->
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

    <span class="script-url"
          data-script-select-2="{{ dynamicAsset('public/assets/admin-module/js/select-2-init.js') }}"
          data-script-date-range-picker="{{ dynamicAsset('public/assets/admin-module/js/date-range-picker.js') }}"
          data-script-surge-price-schedule="{{ dynamicAsset('public/assets/admin-module/js/surge-price-schedule.js') }}"
    ></span>
@endsection

@push('script')
    <script src="{{  dynamicAsset('public/assets/admin-module/plugins/daterangepicker/moment.min.js') }}"></script>
    <script src="{{  dynamicAsset('public/assets/admin-module/plugins/daterangepicker/daterangepicker.min.js') }}"></script>
    <script src="{{  dynamicAsset('public/assets/admin-module/js/fare-management/surge-pricing/create-edit.js') }}"></script>
@endpush
