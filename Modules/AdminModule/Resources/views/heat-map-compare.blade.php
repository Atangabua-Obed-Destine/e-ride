@section('title', translate('heat_map_compare'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin-module/plugins/daterangepicker/daterangepicker.css')}}">
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin-module/plugins/apex/apexcharts.css')}}"/>
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin-module/plugins/swiper@11/swiper-bundle.min.css')}}"/>
    {{-- callback=initLazyLoadedMaps --}}
    @php($map_key = businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null)
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
                        <div class="flex-grow-1"></div>
                        <div class="overview-button-group">
                            <a href="{{route('admin.heat-map')}}">{{translate("Overview")}}</a>
                            <a href="{{route('admin.heat-map-compare')}}" class="active">{{translate("Compare")}}</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="zone-lists d-flex flex-wrap gap-3">
                        <div class="zone-lists__left">
                            <h4 class="mb-20">{{translate("Filter")}}</h4>
                            <form class="mb-4" action="{{url()->full()}}" method="GET">
                                <div class="mb-30">
                                    <div class="floating-form-group">
                                        <label for="zone" class="floating-form-label fs-12">{{translate("Select Zone")}} <i
                                                class="bi bi-info-circle-fill fs-10 text-primary" data-bs-toggle="tooltip"
                                                title="{{translate("Specify the zone for heatmap analysis")}}"></i></label>
                                        <select class="form-select js-select" id="zone" name="zone_id" required>
                                            <option selected disabled>{{translate("select_zone")}}</option>
                                            @foreach($allZones as $allZone)
                                                <option
                                                    value="{{$allZone?->id}}" {{$zone->id == $allZone?->id ? "selected" : ""}}>{{$allZone->name}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-30">
                                    <div class="floating-form-group ">
                                        <label for="times" class="floating-form-label fs-12">{{translate("Select Time Frame")}} <i
                                                class="bi bi-info-circle-fill fs-10 text-primary" data-bs-toggle="tooltip"
                                                title="{{translate("Select Time Frame")}}"></i></label>
                                        <select class="form-select js-select select-time-frame" id="times" name="time_frame" required>
                                            <option value="{{ ALL_TIME }}"
                                                @selected(!request()->has('time_frame') || request()->input('time_frame') == ALL_TIME)>
                                                {{translate("All Time")}}
                                            </option>
                                            <option value="{{ THIS_WEEK }}" @selected((request()->input('time_frame') ?? '') == THIS_WEEK)>{{translate("This Week")}}</option>
                                            <option value="{{ THIS_MONTH }}" @selected((request()->input('time_frame') ?? '') == THIS_MONTH)>{{translate("This Month")}}</option>
                                            <option value="{{ THIS_YEAR }}" @selected((request()->input('time_frame') ?? '') == THIS_YEAR)>{{translate("This Year")}}</option>
                                            <option value="{{ CUSTOM }}" @selected((request()->input('time_frame') ?? '') == CUSTOM)>{{translate("Custom")}}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-30 d-none">
                                    <div class="floating-form-group">
                                        <label for="date" class="floating-form-label fs-12">{{translate("Date Range")}} <i
                                                class="bi bi-info-circle-fill fs-10 text-primary" data-bs-toggle="tooltip"
                                                title="{{translate("Choose the timeframe to display the heatmap")}}"></i></label>
                                        <div class="position-relative">
                                            <input type="text" class="form-control date-range-picker"
                                                   value="{{$dateRange}}" name="date_range"
                                                   id="dateRange" required autocomplete="off">
                                            <span class="icon-calendar">
                                                <i class="bi bi-calendar-event"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-3">
                                    <a href="{{ route('admin.heat-map-compare') }}" class="btn btn-secondary flex-grow-1 justify-content-center text-uppercase"
                                            >{{translate("Reset")}}
                                    </a>
                                    <button class="btn btn-primary cmn_focus flex-grow-1 justify-content-center text-uppercase"
                                            type="submit">{{translate("Submit")}}
                                    </button>
                                </div>
                            </form>
                            <h4 class="mb-2">{{translate("List")}}</h4>
                            <ul class="zone-list">
                                @forelse($dateWiseTrips as $key => $dateWiseTrip)
                                    <li>
                                        <label class="form-check">
                                            <input type="checkbox" class="form-check-input date-checkbox"
                                                   value="{{$key}}"
                                                   data-ride="{{$dateWiseTrip->ride_count}}"
                                                   data-parcel="{{$dateWiseTrip->ride_count}}"
                                                   data-date="{{date('d M, Y', strtotime($dateWiseTrip->date)) }}"
                                                   data-zone="{{$zone?->name ?? "N?A"}}" checked>
                                            <div class="form-check-label">
                                                <h5 class="zone-name">
                                                    {{$dateWiseTrip?->year ? ($dateWiseTrip->year .' ('.date('d M', strtotime($dateWiseTrip->startDate)). ' - '.date('d M', strtotime($dateWiseTrip->endDate)).')') : ""}}
                                                    {{$dateWiseTrip?->date? date('d M, Y', strtotime($dateWiseTrip->date)) :""}}
                                                    {{$dateWiseTrip?->day ? ('('.$dateWiseTrip?->day.')') :""}}
                                                    {{$dateWiseTrip?->hour ?('('. \Carbon\Carbon::createFromTime($dateWiseTrip->hour, 0)->format('g A') .')'): ""}}
                                                </h5>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <div>
                                                        <span>{{translate("Ride")}}</span>
                                                        <span>:</span>
                                                        <span>{{$dateWiseTrip->ride_count}}</span>
                                                    </div>
                                                    <span class="fs-8">|</span>
                                                    <div>
                                                        <span>{{translate("Parcel")}}</span>
                                                        <span>:</span>
                                                        <span>{{$dateWiseTrip->parcel_count}}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    </li>
                                @empty
                                    <div class="card gap-3 p-4">
                                        <div class="d-flex justify-content-center"><img
                                                src="{{dynamicAsset("public/assets/admin-module/img/zone_empty.png")}}"
                                                alt=""></div>
                                        <div
                                            class="text-capitalize d-flex justify-content-center">{{translate("no_result_found")}}</div>
                                    </div>

                                @endforelse

                            </ul>
                        </div>
                        <div class="zone-lists__map">
                            <div class="mb-4">
                                <div class="swiper-auto-slider overflow-hidden">
                                    <div class="swiper-wrapper">
                                        @foreach($dateWiseTrips as $key => $dateWiseTrip)
                                            <div class="swiper-slide" id="slider{{$key}}">
                                                <div class="zone-compare-slide-item">
                                                    <h5 class="mb-2">
                                                        {{$dateWiseTrip?->year ? ($dateWiseTrip->year .' ('.date('d M', strtotime($dateWiseTrip->startDate)). ' - '.date('d M', strtotime($dateWiseTrip->endDate)).')') : ""}}
                                                        {{$dateWiseTrip?->date? date('d M, Y', strtotime($dateWiseTrip->date)) :""}}
                                                        {{$dateWiseTrip?->day ? ('('.$dateWiseTrip?->day.')') :""}}
                                                        {{$dateWiseTrip?->hour ?('('. \Carbon\Carbon::createFromTime($dateWiseTrip->hour, 0)->format('g A') .')'): ""}}
                                                    </h5>
                                                    <div class="map-container">
                                                        <div data-bs-target="#compare-modal{{$key}}"
                                                             data-bs-toggle="modal"
                                                             class="full-screen-button">
                                                            <i class="bi bi-arrows-fullscreen"></i>
                                                        </div>
                                                        <div class="map" id="map{{$key}}" data-lat="{{$centerLat}}"
                                                             data-lng="{{$centerLng}}"
                                                             data-title="Map Title"
                                                             data-markers='{{$markers[$dateWiseTrip->markerKey]}}'
                                                             data-polygon='{{$polygons}}'>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="card border-0">
                                <div class="card-header d-flex justify-content-between shadow-sm">
                                    <h5 class="m-0 fs-14">{{translate("Trip Statistics")}}</h5>
                                    <span class="fs-14">{{translate("Total Trip")}}: {{$tripCount}}</span>
                                </div>
                                <div class="card-body hide-2nd-line-of-chart" id="updating_line_chart">
                                    <div id="apex_line-chart"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @foreach($dateWiseTrips as $key => $dateWiseTrip)

        <div class="modal fade compare-modal" id="compare-modal{{$key}}">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header border-0 align-items-start pb-1">
                        <div class="heatmap-view-top-modal">
                            <div class="item">
                                <span>{{translate("Zone")}}:</span>
                                <h5 id="zoneName">{{$zone?->name}}</h5>
                            </div>
                            <div class="item">
                                <span>{{translate("Date")}}:</span>
                                <h5 id="zoneDate">
                                    {{$dateWiseTrip?->year ? ($dateWiseTrip->year .' ('.date('d M', strtotime($dateWiseTrip->startDate)). ' - '.date('d M', strtotime($dateWiseTrip->endDate)).')') : ""}}
                                    {{$dateWiseTrip?->date? date('d M, Y', strtotime($dateWiseTrip->date)) :""}}
                                    {{$dateWiseTrip?->day ? ('('.$dateWiseTrip?->day.')') :""}}
                                    {{$dateWiseTrip?->hour ?('('. \Carbon\Carbon::createFromTime($dateWiseTrip->hour, 0)->format('g A') .')'): ""}}
                                </h5>
                            </div>
                            <div class="item">
                                <span>{{translate("Trip Count")}}:</span>
                                <h5>{{translate("Ride")}}: <span
                                        id="zoneRide">{{$dateWiseTrip->ride_count}}</span>, {{translate("Parcel")}}:
                                    <span
                                        id="zoneParcel">{{$dateWiseTrip->parcel_count}}</span></h5>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="position-relative map-container">
                            <input class="form-control map-search-input" type="text"
                                   placeholder="Search for a location">
                            <div class="heat-map rounded map" id="map-{{$key}}" data-lat="{{$centerLat}}"
                                 data-lng="{{$centerLng}}"
                                 data-title="Map Title"
                                 data-markers='{{$markers[$dateWiseTrip->markerKey]}}'
                                 data-polygon='{{$polygons}}'>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

@endsection

@push('script')
    <script src="{{dynamicAsset('public/assets/admin-module/plugins/apex/apexcharts.min.js')}}"></script>
    <script type="text/javascript"
            src="{{dynamicAsset('public/assets/admin-module/plugins/daterangepicker/moment.min.js')}}"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/plugins/daterangepicker/daterangepicker.min.js')}}"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/plugins/swiper@11/swiper-bundle.min.js')}}"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/js/date-range-picker.js')}}"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/js/maps/map-init.js')}}"></script>

    <script>
        $(document).ready(function () {
            $(".map-modal").on('click', function () {
                var marker = $(this).data('marker');
                var polygon = $(this).data('polygon');
                var ride = $(this).data('ride');
                var parcel = $(this).data('parcel');
                var date = $(this).data('date');
                const mapDiv = document.getElementById('map');
                mapDiv.setAttribute('data-markers', JSON.stringify(marker));
                mapDiv.setAttribute('data-polygon', JSON.stringify(polygon));
                $("#zoneDate").html(date);
                $("#zoneRide").html(ride);
                $("#zoneParcel").html(parcel);
                $("#compare-modal").modal('show');

            })


            const swiper = new Swiper('.swiper-auto-slider', {
                speed: 400,
                spaceBetween: 20,
                slidesPerView: 'auto'
            });
        })

        // Line Charts
        $(document).ready(function () {
            let totalTripRequest = {!! json_encode($tripStatisticsData['totalTripRequest']) !!};
            let totalAdminCommission = {!! json_encode($tripStatisticsData['totalAdminCommission']) !!};

            let label = {!! json_encode($tripStatisticsData['label']) !!};

            let options = {
                series: [
                    {
                        name: '{{translate("Total Trips")}}',
                        data: [0].concat(Object.values(totalTripRequest))
                    },
                    {
                        name: '{{translate("Admin Commission")}} ($)',
                        data: [0].concat(Object.values(totalAdminCommission))
                    }
                ],
                chart: {
                    height: 290,
                    type: 'line',
                    dropShadow: {
                        enabled: true,
                        color: '#000',
                        top: 18,
                        left: 0,
                        blur: 10,
                        opacity: 0.1
                    },
                    toolbar: {
                        show: false
                    },
                },
                colors: ['#F4A164', '#14B19E'],
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    curve: 'smooth',
                    width: 2,
                },
                grid: {
                    yaxis: {
                        lines: {
                            show: true
                        }
                    },
                    borderColor: '#ddd',
                },
                markers: {
                    size: 2,
                    strokeColors: ['#F4A164', '#14B19E'],
                    strokeWidth: 1,
                    fillOpacity: 0,
                    hover: {
                        sizeOffset: 2
                    }
                },
                theme: {
                    mode: 'light',
                },
                xaxis: {
                    categories: ['00'].concat(label),
                    labels: {
                        offsetX: 0,
                    },
                },
                legend: {
                    show: false,
                    position: 'bottom',
                    horizontalAlign: 'left',
                    floating: false,
                    offsetY: -10,
                    itemMargin: {
                        vertical: 10
                    },
                },
                yaxis: {
                    // tickAmount: 10,
                    tickAmount: 5,
                    labels: {
                        offsetX: 0,
                    },
                }
            };

            if (localStorage.getItem('dir') === 'rtl') {
                options.yaxis.labels.offsetX = -20;
            }

            let chart = new ApexCharts(document.querySelector("#apex_line-chart"), options);
            chart.render();
        });


        document.querySelectorAll('.date-checkbox').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                toggleRelatedInput(this);
            });
        });

        function toggleRelatedInput(checkbox) {
            const checkboxValue = checkbox.value;
            if (checkbox.checked) {
                $("#slider" + checkboxValue).removeClass("d-none"); // Hide the related input
            } else {
                $("#slider" + checkboxValue).addClass("d-none"); // Hide the related input
            }
        }

        function toggleDateRangePicker() {
            const selectedValue = $('.select-time-frame').val();
            const $dateRangePicker = $('.date-range-picker');

            if (selectedValue == 'custom') {
                $dateRangePicker.closest('.mb-30').removeClass('d-none');
            } else {
                $dateRangePicker.closest('.mb-30').addClass('d-none');
                let start, end;
                if (selectedValue === 'all_time') {
                    start = moment('2023-10-01');
                    end = moment();
                } else if (selectedValue === 'this_year') {
                    start = moment().startOf('year');
                    end = moment().endOf('year');
                } else if (selectedValue === 'this_week') {
                    start = moment().subtract(6, 'days');
                    end = moment();
                } else if (selectedValue === 'this_month') {
                    start = moment().startOf('month');
                    end = moment().endOf('month');
                } else {
                    start = moment('2023-10-01');
                    end = moment();
                }
                $dateRangePicker.val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
            }
        }

        $('.select-time-frame').on('change', function () {
            toggleDateRangePicker();
        });

        $(document).ready(function () {
            toggleDateRangePicker();
            $('form').on('reset', function () {
                setTimeout(toggleDateRangePicker, 0);
            });
        });

    </script>
@endpush
