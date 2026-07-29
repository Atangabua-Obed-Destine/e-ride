@section('title', translate('heat_map_overview'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
    @php($map_key = businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null)
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin-module/plugins/daterangepicker/daterangepicker.css')}}">
    <script src="https://maps.googleapis.com/maps/api/js?key={{$map_key}}&libraries=places"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/js/maps/markerclusterer.js')}}"></script>
@endpush

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-3">
                    <div class="w-100 max-w-299px">
                        <h4>{{translate('Trip Heat Map')}}</h4>
                        <p>{{translate("Monitor your trips from here")}}</p>
                    </div>
                    <div class="d-flex flex-grow-1 gap-4">
                        <div class="flex-grow-1">
                            <form action="{{ url()->full() }}" id="dateRangeForm">
                                <div class="position-relative">
                                    <input type="text" class="form-control date-range-picker"
                                           value="{{ request('date_range') }}" name="date_range"
                                           id="dateRange" autocomplete="off"
                                           placeholder="MM/DD/YYYY - MM/DD/YYYY">
                                    <span class="icon-calendar">
                                    <i class="bi bi-calendar-event"></i>
                                </span>
                                </div>
                            </form>
                        </div>
                        <div class="overview-button-group">
                            <a href="{{route('admin.heat-map')}}" class="active">{{translate("Overview")}}</a>
                            <a href="{{route('admin.heat-map-compare')}}" class="">{{translate("Compare")}}</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="zone-lists d-flex flex-wrap gap-3">
                        <div class="zone-lists__left">
                            <h4 class="mb-2">{{translate("Zone List")}}</h4>
                            <form action="javascript:;"
                                  class="search-form search-form_style-two" method="GET">
                                <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                    <input type="search" class="theme-input-style search-form__input"
                                           value="{{ request('search') }}" name="search" id="search"
                                           placeholder="{{ translate('search_here_by_zone_name') }}">
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary search-submit"
                                        data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                                    <a href="{{ url()->current() }}" class="btn--reset text-danger fs-12 mt-2 text-end d-block fw-semibold {{request('search') ? '' : 'd-none'}}">{{ translate('Clear All') }}</a>
                                </div>
                            </form>
                            <ul class="zone-list">
                                @if(!request('search'))
                                    <li>
                                        <label class="form-check">
                                            <input type="checkbox" class="form-check-input" id="selectAllZones" checked>
                                            <div class="form-check-label">
                                                <h5 class="zone-name">{{translate("All Zone")}}</h5>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <div>
                                                        <span>{{translate("Ride")}}</span>
                                                        <span>:</span>
                                                        <span>{{$totalRideRequests}}</span>
                                                    </div>
                                                    <span class="fs-8">|</span>
                                                    <div>
                                                        <span>{{translate("Parcel")}}</span>
                                                        <span>:</span>
                                                        <span>{{$totalParcelRequests}}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    </li>
                                @endif
                                @forelse($zones as $zone)
                                    <li>
                                        <label class="form-check">
                                            <input type="checkbox" class="form-check-input zone-checkbox"
                                                   value="{{$zone->id}}" checked>
                                            <div class="form-check-label">
                                                <h5 class="zone-name">{{$zone->name}} <span
                                                        class="fw-normal">#{{$zone->readable_id}}</span></h5>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <div>
                                                        <span>{{translate("Ride")}}</span>
                                                        <span>:</span>
                                                        <span>{{$zone->ride_request}}</span>
                                                    </div>
                                                    <span class="fs-8">|</span>
                                                    <div>
                                                        <span>{{translate("Parcel")}}</span>
                                                        <span>:</span>
                                                        <span>{{$zone->parcel_request}}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    </li>
                                @empty
                                    <div class="card gap-3 p-4 mt-3">
                                        <div class="d-flex justify-content-center"><img
                                                src="{{dynamicAsset("public/assets/admin-module/img/zone_empty.png")}}"
                                                alt=""></div>
                                        <div
                                            class="text-capitalize d-flex justify-content-center">{{translate("no_result_found")}}</div>
                                    </div>

                                @endforelse
                            </ul>
                        </div>
                        <div class="zone-lists__map" id="overviewMap">
                            @include('adminmodule::partials.heat-map._overview-map')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')

    <script type="text/javascript"
            src="{{dynamicAsset('public/assets/admin-module/plugins/daterangepicker/moment.min.js')}}"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/plugins/daterangepicker/daterangepicker.min.js')}}"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/js/date-range-picker.js')}}"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/js/maps/map-init-overview.js')}}"></script>

    <script>

        $(function () {
            $('#dateRange').on('apply.daterangepicker', function (ev, picker) {
                $('#dateRangeForm').submit();
            });

            $('#dateRange').on('cancel.daterangepicker', function (ev, picker) {
                $('#dateRangeForm').submit();
            });
        });
    </script>

    //selection zone
    <script>
        const selectAllZones = document.getElementById('selectAllZones');

        if (selectAllZones) {
            selectAllZones.addEventListener('change', function () {
                const checkboxes = document.querySelectorAll('.zone-checkbox');
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = this.checked;
                }, this);

                updateSelectAllZone(); // Update the "Select All" checkbox status
            });
        }

        // document.getElementById('selectAllZones').addEventListener('change', function () {
        //     var checkboxes = document.querySelectorAll('.zone-checkbox');
        //     checkboxes.forEach(function (checkbox) {
        //         checkbox.checked = this.checked;
        //     }, this);
        //     updateSelectAllZone(); // Update the "Select All" checkbox status
        // });

        document.querySelectorAll('.zone-checkbox').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                updateSelectAllZone(); // Update the "Select All" checkbox status
            });
        });

        function updateSelectAllZone() {
            var checkboxes = document.querySelectorAll('.zone-checkbox');
            var selectAllCheckbox = document.getElementById('selectAllZones');

            var allChecked = true;
            var anyChecked = false;
            checkboxes.forEach(function (checkbox) {
                if (!checkbox.checked) {
                    allChecked = false;
                } else {
                    anyChecked = true;
                }
            });
            if (selectAllCheckbox) {
                if (anyChecked) {
                    selectAllCheckbox.checked = allChecked;
                } else {
                    selectAllCheckbox.checked = false;
                }
            }

            const selectedValues = $('.zone-checkbox:checked').map(function () {
                return this.value;
            }).get();
            heatMapOverview(selectedValues);
        }

        function heatMapOverview(zoneIds) {
            const dateRange = $('#dateRange').val();
            $.get({
                url: '{{route('admin.heat-map-overview-data')}}',
                dataType: 'json',
                data: {date_range: dateRange, zone_ids: zoneIds},
                beforeSend: function () {
                    $('#resource-loader').show();
                },
                success: function (response) {
                    $('#overviewMap').empty().html(response);
                    $.getScript('{{ dynamicAsset('public/assets/admin-module/js/maps/map-init-overview.js') }}');
                },
                complete: function () {
                    $('#resource-loader').hide();
                },
                error: function (xhr, status, error) {
                    let err = eval("(" + xhr.responseText + ")");
                    // alert(err.Message);
                    $('#resource-loader').hide();
                    toastr.error('{{translate('failed_to_load_data')}}')
                },
            });

        }
    </script>

@endpush
