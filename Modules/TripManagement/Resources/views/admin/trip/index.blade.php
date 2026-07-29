@extends('adminmodule::layouts.master')

@section('title', translate('Trips'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h4 class="text-capitalize mb-4 ">{{ translate('trip_list')}}</h4>

            <div class="row mb-4">
                @include('tripmanagement::admin.trip.partials._trip-inline-menu')
            </div>
            @if($type == 'all')
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                            <h5 class="text-primary fw-medium text-uppercase mb-3">{{translate('trips_statistics')}}</h5>

                            <div class="dropdown custom-date-dropdown">
                                <select name="date_range" id="date-range"
                                        class="js-select btn btn-outline-primary form-control">
                                    <option value="{{ALL_TIME}}" class="text-primary"
                                            selected>{{translate(ALL_TIME)}}</option>
                                    <option value="{{TODAY}}">{{translate(TODAY)}}</option>
                                    <option value="{{PREVIOUS_DAY}}">{{translate(PREVIOUS_DAY)}}</option>
                                    <option value="{{LAST_7_DAYS}}">{{translate(LAST_7_DAYS)}}</option>
                                    <option value="{{THIS_WEEK}}">{{translate(THIS_WEEK)}}</option>
                                    <option value="{{LAST_WEEK}}">{{translate(LAST_WEEK)}}</option>
                                    <option value="{{THIS_MONTH}}">{{translate(THIS_MONTH)}}</option>
                                    <option value="{{LAST_MONTH}}">{{translate(LAST_MONTH)}}</option>
                                </select>
                            </div>
                        </div>
                        <div id="trip-stats">
                            @include('tripmanagement::admin.trip.partials._trip-list-stat')
                        </div>
                    </div>
                </div>
            @endif
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <h4 class="text-capitalize">{{ translate('all_trips')}}</h4>
                <div class="d-flex align-items-center gap-2 text-capitalize">
                    <span class="text-muted">{{translate('total_trips')}} : </span>
                    <h4 class="" id="">{{$trips->total()}}</h4>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                        <form action="{{url()->current()}}" class="search-form search-form_style-two">
                            <div class="input-group search-form__input_group">
                                    <span class="search-form__icon">
                                        <i class="bi bi-search"></i>
                                    </span>
                                <input type="search" name="search" value="{{request()->search}}"
                                       class="theme-input-style search-form__input"
                                       placeholder="{{translate('Search_here_by_Trip_ID')}}">
                            </div>
                            <button type="submit" class="btn btn-primary cmn_focus">{{translate('search')}}</button>
                        </form>

                        <div class="d-flex flex-wrap gap-3">
                            @can('super-admin')
                                <a href="{{ route('admin.trip.index', ['type' => request('type')]) }}"
                                   class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                                   data-bs-title="{{ translate('refresh') }}">
                                    <i class="bi bi-arrow-repeat"></i>
                                </a>
                            @endcan
                            @can('trip_log')
                                <a href="{{route('admin.trip.log')}}" class="btn btn-outline-primary px-3"
                                   data-bs-toggle="tooltip" data-bs-title="{{ translate('view_Log') }}">
                                    <i class="bi bi-clock-fill"></i>
                                </a>
                            @endcan

                            @can('trip_export')
                                <div class="dropdown">
                                    <button type="button" class="btn btn-outline-primary"
                                            data-bs-toggle="dropdown">
                                        <i class="bi bi-download"></i>
                                        {{translate('download')}}
                                        <i class="bi bi-caret-down-fill"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                        <li>
                                            <a class="dropdown-item" href="{{route('admin.trip.export',[
                                                'file'=>'excel','search' =>request()->get('search'),'type'=>$type,
                                                'customer_id'=> request()->get('customer_id'),
                                                'driver_id'=>request()->get('driver_id'),
                                                'trip_type'=>request()->get('trip_type'),
                                                'trip_status'=>request()->get('trip_status'),
                                                'filter_date'=>request()->get('filter_date'),
                                                'start_date'=>request()->get('start_date'),
                                                'end_date'=>request()->get('end_date')
                                            ]
                                            )}}">{{ translate('excel') }}</a>
                                        </li>
                                    </ul>
                                </div>
                            @endcan
                            <button type=" button" class="btn btn-outline-primary" data-bs-toggle="offcanvas"
                                    data-bs-target="#filter-offcanvas">
                                <i class="bi bi-funnel"></i>
                                {{translate('Filter')}}
                            </button>

                        </div>
                    </div>
                    <div id="trip-list-view">
                        @include('tripmanagement::admin.trip.partials._trip-list')
                    </div>
                </div>
            </div>
        </div>
        <div class="offcanvas offcanvas-end" id="filter-offcanvas">
            <form class="d-flex flex-column h-100" action="{{url()->full()}}" id="filterForm">
                <div class="offcanvas-header">
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    <h4 class="offcanvas-title flex-grow-1 text-center">
                        {{translate('Trip List Filter')}}
                    </h4>
                </div>
                <input type="hidden" name="search" id="search" value="{{ request()->input('search') }}">
                <div class="offcanvas-body scrollbar-thin">
                    <div class="mb-4 custom-input-grp position-relative">
                        <label class="mb-2 position-absolute bg-white left-3 px-1 index-2">
                            {{translate("Select Customer")}}
                        </label>
                        <select class="js-select-offcanvas" name="customer_id">
                            <option
                                value="{{ALL}}" {{request() && request()->input('customer_id') == ALL ? "selected" : ""}}>
                                {{translate("All Customers")}}
                            </option>
                            @foreach($customers as $customer)
                                <option
                                    value="{{$customer->id}}" {{request() && request()->input('customer_id') == $customer->id ? "selected" : ""}}>{{$customer->full_name ?? $customer->first_name . ' '. $customer->last_name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4 custom-input-grp position-relative">
                        <label class="mb-2 mb-2 position-absolute bg-white left-3 px-1 index-2">
                            {{translate("Select Driver")}}
                        </label>
                        <select class="js-select-offcanvas" name="driver_id">
                            <option
                                value="{{ALL}}" {{request() && request()->input('driver_id') == ALL ? "selected" : ""}}>
                                {{translate("All Drivers")}}
                            </option>
                            @foreach($drivers as $driver)
                                <option
                                    value="{{$driver->id}}" {{request() && request()->input('driver_id') == $driver->id ? "selected" : ""}}>{{$driver->full_name ?? $driver->first_name . ' '. $driver->last_name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4 custom-input-grp position-relative">
                        <label class="mb-2 mb-2 position-absolute bg-white left-3 px-1 index-2">
                            {{translate("Select Trip Type")}}
                        </label>
                        <select class="js-select-offcanvas" name="trip_type">
                            <option
                                value="{{ALL}}" {{request() && request()->input('trip_type') == ALL ? "selected" : ""}}>
                                {{translate("All Trips")}}
                            </option>
                            <option
                                value="{{RIDE_REQUEST}}" {{request() && request()->input('trip_type') == RIDE_REQUEST ? "selected" : ""}}>{{translate("Ride Request")}}</option>
                            <option
                                value="{{PARCEL}}" {{request() && request()->input('trip_type') == PARCEL ? "selected" : ""}}>{{translate("Parcel Request")}}</option>
                        </select>
                    </div>
                    @if(Request::is('admin/trip/list/all*'))
                        <div class="mb-4 custom-input-grp position-relative">
                            <label class="mb-2 mb-2 position-absolute bg-white left-3 px-1 index-2">
                                {{translate("Trip Status")}}
                            </label>
                            <select class="js-select-offcanvas" name="trip_status">
                                <option
                                    value="{{ALL}}" {{request() && request()->input('trip_status') == ALL ? "selected" : ""}}>
                                    {{translate("All Trip Status")}}
                                </option>
                                <option
                                    value="{{PENDING}}" {{request() && request()->input('trip_status') == PENDING ? "selected" : ""}}>{{translate(PENDING)}}</option>
                                <option
                                    value="{{SCHEDULED}}" {{request() && request()->input('trip_status') == SCHEDULED ? "selected" : ""}}>{{translate(SCHEDULED)}}</option>
                                <option
                                    value="{{ACCEPTED}}" {{request() && request()->input('trip_status') == ACCEPTED ? "selected" : ""}}>{{translate(ACCEPTED)}}</option>
                                <option
                                    value="{{ONGOING}}" {{request() && request()->input('trip_status') == ONGOING ? "selected" : ""}}>{{translate(ONGOING)}}</option>
                                <option
                                    value="{{COMPLETED}}" {{request() && request()->input('trip_status') == COMPLETED ? "selected" : ""}}>{{translate(COMPLETED)}}</option>
                                <option
                                    value="{{CANCELLED}}" {{request() && request()->input('trip_status') == CANCELLED ? "selected" : ""}}>{{translate(CANCELLED)}}</option>
                                <option
                                    value="{{RETURNING}}" {{request() && request()->input('trip_status') == RETURNING ? "selected" : ""}}>{{translate(RETURNING)}}</option>
                                <option
                                    value="{{RETURNED}}" {{request() && request()->input('trip_status') == RETURNED ? "selected" : ""}}>{{translate(RETURNED)}}</option>
                            </select>
                        </div>
                    @endif
                    <div class="mb-4 custom-input-grp position-relative">
                        <label class="mb-2 mb-2 position-absolute bg-white left-3 px-1 index-2">
                            {{ translate('Select Date') }}
                        </label>
                        <select class="js-select-offcanvas" name="filter_date" id="filterDate">
                            <option value="{{ALL_TIME}}" class="text-primary"
                                {{request() && request()->input('filter_date') == ALL_TIME ? "selected" : ""}}>{{translate(ALL_TIME)}}</option>
                            <option
                                value="{{TODAY}}" {{request() && request()->input('filter_date') == TODAY ? "selected" : ""}}>{{translate(TODAY)}}</option>
                            <option
                                value="{{THIS_WEEK}}" {{request() && request()->input('filter_date') == THIS_WEEK ? "selected" : ""}}>{{translate(THIS_WEEK)}}</option>
                            <option
                                value="{{THIS_MONTH}}" {{request() && request()->input('filter_date') == THIS_MONTH ? "selected" : ""}}>{{translate(THIS_MONTH)}}</option>
                            <option
                                value="{{THIS_YEAR}}" {{request() && request()->input('filter_date') == THIS_YEAR ? "selected" : ""}}>{{translate(THIS_YEAR)}}</option>
                            <option
                                value="{{CUSTOM_DATE}}" {{request() && request()->input('filter_date') == CUSTOM_DATE ? "selected" : ""}}>{{translate(CUSTOM_DATE)}}</option>
                        </select>
                    </div>
                    <div id="filterCustomDate" class="d-none">
                        <div class="row">
                            <div class="col-6">
                                <label class="mb-2">{{translate("Start date")}}</label>
                                <input type="date" value="{{request()->input('start_date')}}" id="start_date"
                                       name="start_date" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="mb-2">{{translate("End date")}}</label>
                                <input type="date" id="end_date" value="{{request()->input('end_date')}}"
                                       name="end_date" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="offcanvas-footer d-flex gap-3 bg-white position-sticky bottom-0 p-3 pos-sticky-btn-shadow justify-content-center">
                    <button type="reset" class="btn btn-secondary cmn_reset" data-bs-dismiss="offcanvas" aria-label="Close">
                        {{translate('Cancel')}}</button>
                    <button type="submit" class="btn btn-primary cmn_focus">
                        {{translate('Apply') }}
                    </button>
                </div>
            </form>
        </div>

    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script>
        $(document).ready(function () {
            $('.js-select-offcanvas').select2({
                dropdownParent: $('#filter-offcanvas')
            });
        })

        $("select").closest("form").on("reset", function (ev) {
            var targetJQForm = $(ev.target);
            setTimeout((function () {
                this.find("select").trigger("change");
            }).bind(targetJQForm), 0);
        });
    </script>
    <script>
        function loadPartialView(url, divId, data) {
            $.get({
                url: url,
                dataType: 'json',
                data: {data},
                beforeSend: function () {
                    $('#resource-loader').show();
                },
                success: function (response) {
                    $(divId).empty().html(response)
                },
                complete: function () {
                    $('#resource-loader').hide();
                },
                error: function () {
                    $('#resource-loader').hide();
                    toastr.error('{{translate('failed_to_load_data')}}')
                },
            });
        }

        let data_range = $('#date-range');
        let data_input = $('#data-input');

        data_range.on('change', function () {
            if (data_range.val() === 'custom_date') {
                data_input.css('display', 'flex')
            } else {
                data_input.css('display', 'none')
                loadPartialView('{{url()->full()}}', '#trip-stats', data_range.val())
            }
        });


        function getDate() {
            let start = $('#start_date').val()
            let end = $('#end_date').val()
            if (!start || !end || start > end) {
                toastr.error('{{translate('please_select_proper_date_range')}}');
                return;
            }
            let data = {start: start, end: end}
            loadPartialView('{{url()->full()}}', '#trip-stats', data)
        }


        //filter


        let filterDate = $('#filterDate');
        let filterCustomDate = $('#filterCustomDate');
        filterDateChange();

        filterDate.on('change', function () {
            filterDateChange();
        });

        function filterDateChange() {
            if (filterDate.val() == 'custom_date') {
                filterCustomDate.removeClass('d-none')
                $("#start_date").attr('required', 'true')
                $("#end_date").attr('required', 'true')
            } else {
                $("#start_date").removeAttr('required')
                $("#end_date").removeAttr('required')
                filterCustomDate.addClass('d-none')
            }
        }

        document.getElementById('start_date').addEventListener('change', function () {
            // Get the selected start date value
            var startDate = this.value;

            // Set the minimum value of the end date to the selected start date
            var endDateInput = document.getElementById('end_date');
            endDateInput.setAttribute('min', startDate);

            // Optional: If the current end date is less than the new start date, update it to match the start date
            if (endDateInput.value && endDateInput.value < startDate) {
                endDateInput.value = startDate;
            }
        });
    </script>

@endpush
