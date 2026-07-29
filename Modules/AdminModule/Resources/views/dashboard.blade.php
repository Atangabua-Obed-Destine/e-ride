@section('title', translate('dashboard'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin-module/plugins/apex/apexcharts.css')}}"/>
@endpush

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="row align-items-center mb-3 g-2">
                <div class="col-12">
                    <div class="media gap-3">
                        <img width="38" src="{{dynamicAsset('public/assets/admin-module/img/media/car.png')}}" loading="eager"
                             alt="">
                        <div class="media-body text-dark">
                            <h4 class="mb-1">{{ translate('welcome')}} {{auth('web')->user()?->first_name}}</h4>
                            <p class="fs-12 text-capitalize">{{ translate('monitor_your')}}
                                <strong>{{ getSession('business_name') ?? 'DriveMond' }}</strong> {{ translate('business_statistics')}}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            @can('dashboard')
                <div class="row gy-4">
                    <div class="col-md-6">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="card">
                                    <div class="p-30">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="level-status fs-5 p-2 bg-info w-48 aspect-1">
                                                <img src="{{dynamicAsset('public/assets/admin-module/img/svg/user-grp.svg')}}" class="svg" alt="">
                                            </div>
                                            <div>
                                                <h3 class="fs-18">
                                                    {{abbreviateNumber($customers)}}
                                                </h3>
                                                <div class="title-color text-capitalize">{{ translate('Total Active Customers')}}</div>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="level-status fs-5 p-2 w-48 aspect-1">
                                                <img src="{{dynamicAsset('public/assets/admin-module/img/svg/active_drivers.svg')}}" class="svg" alt="">
                                            </div>
                                            <div class="">
                                                <h3 class="fs-18">
                                                    {{ abbreviateNumber($drivers) }}
                                                </h3>
                                                <div class="title-color text-capitalize">{{ translate('Total Active Drivers')}}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="card">
                                    <div class="p-30">
                                        <div class="d-flex flex-column gap-2">
                                            <div class="level-status fs-5 p-2 bg-warning w-48 aspect-1 mb-3">
                                                <img src="{{dynamicAsset('public/assets/admin-module/img/svg/earning.svg')}}"
                                                     class="svg" alt="">
                                            </div>
                                            <h3 class="fs-24">{{abbreviateNumberWithSymbol($totalEarning) }}</h3>
                                            <div class="title-color text-capitalize">{{ translate('Total Earnings')}}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="card">
                                    <div class="p-30">
                                        <div class="d-flex flex-column gap-2">
                                            <div class="level-status fs-5 p-2 bg-purple w-48 aspect-1 mb-3">
                                                <img src="{{dynamicAsset('public/assets/admin-module/img/svg/delivery_man.svg')}}"
                                                     class="svg" alt="">
                                            </div>
                                            <h3 class="fs-24">
                                                {{ abbreviateNumberWithSymbol($totalParcelsEarning) }}
                                                <small class="fw-normal fs-14">{{translate('Earn')}}</small>
                                            </h3>
                                            <h3 class="fs-18">
                                                <span class="fw-normal fs-14 title-color text-capitalize">{{ translate('Total Parcel')}}</span>
                                                {{ abbreviateNumber($totalParcels) }}
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="card">
                                    <div class="p-30">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="level-status fs-5 p-2 bg-success w-48 aspect-1">
                                                <img src="{{dynamicAsset('public/assets/admin-module/img/svg/ride-sharing.svg')}}" class="svg" alt="">
                                            </div>
                                            <div>
                                                <h3 class="fs-20">
                                                    {{ abbreviateNumberWithSymbol($totalRegularRideEarning) }}
                                                    <small class="fw-normal fs-14">{{translate('Earn')}}</small>
                                                </h3>
                                                <h3 class="fs-18">
                                                    <span class="fw-normal fs-14 title-color text-capitalize">{{ translate('Regular Ride')}}</span>
                                                    {{ abbreviateNumber($totalRegularRide) }}
                                                </h3>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="level-status fs-5 p-2 bg-danger w-48 aspect-1 position-relative">
                                                <img src="{{dynamicAsset('public/assets/admin-module/img/svg/schedule_clock.svg')}}" class="svg position-absolute top-minus4 right-minus4" alt="">
                                                <img src="{{dynamicAsset('public/assets/admin-module/img/svg/ride-sharing.svg')}}" class="svg" alt="">
                                            </div>
                                            <div>
                                                <h3 class="fs-20">
                                                    {{ abbreviateNumberWithSymbol($totalScheduledRideEarning) }}
                                                    <small class="fw-normal fs-14">{{translate('Earn')}}</small>
                                                </h3>
                                                <h3 class="fs-18">
                                                    <span class="fw-normal fs-14 title-color text-capitalize">{{ translate('Schedule Ride')}}</span>
                                                    {{ abbreviateNumber($totalScheduledRide) }}
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3 h-100">
                            <div class="card-header d-flex flex-wrap justify-content-between gap-10">
                                <div class="d-flex flex-column gap-1">
                                    <h6 class="text-capitalize">{{ translate('zone-wise_trip_statistics')}}</h6>
                                    <p>{{ translate('total')}} {{$zones->count()}} {{ translate('zones')}}</p>
                                </div>
                                <div class="d-flex flex-wrap flex-sm-nowrap gap-2 align-items-center">
                                    <select class="js-select cmn_focus" id="zoneWiseRideDate">
                                        <option disabled>{{ translate('Select_Duration')}}</option>
                                        <option value="{{TODAY}}" {{ env('APP_MODE') != 'demo' ? "selected" : "" }}>{{ translate(TODAY)}}</option>
                                        <option value="{{PREVIOUS_DAY}}">{{ translate(PREVIOUS_DAY)}}</option>
                                        <option value="{{LAST_7_DAYS}}">{{translate(LAST_7_DAYS)}}</option>
                                        <option value="{{THIS_WEEK}}">{{translate(THIS_WEEK)}}</option>
                                        <option value="{{LAST_WEEK}}">{{translate(LAST_WEEK)}}</option>
                                        <option value="{{THIS_MONTH}}">{{translate(THIS_MONTH)}}</option>
                                        <option value="{{LAST_MONTH}}">{{translate(LAST_MONTH)}}</option>
                                        <option value="{{ALL_TIME}}" {{ env('APP_MODE') != 'demo' ?  "" : "selected" }}>{{translate(ALL_TIME)}}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="load-all-data">
                                    <div id="zoneWiseTripStatistics"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Earning Statistics -->
                <div class="card my-3">
                    <div class="card-header d-flex flex-wrap justify-content-between gap-10">
                        <div class="d-flex flex-column gap-1">
                            <h5 class="text-capitalize">{{translate('admin_earning_statistics')}}</h5>
                            <p>{{translate('total')}} {{$zones->count()}} {{translate('zone')}}</p>
                        </div>
                        <div class="d-flex flex-wrap flex-sm-nowrap gap-2 align-items-center">
                            <select class="js-select cmn_focus" id="rideZone">
                                <option disabled>{{translate('Select_Area')}}</option>
                                <option selected value="all">{{translate('all')}}</option>
                                @forelse($zones as $zone)
                                    <option value="{{$zone->id}}">{{$zone->name}}</option>
                                @empty
                                @endforelse
                            </select>
                            <select class="js-select cmn_focus" id="rideDate">
                                <option disabled>{{translate('Select_Duration')}}</option>
                                <option value="{{ALL_TIME}}" {{ env('APP_MODE') != 'demo' ? "" : "selected" }}>{{translate(ALL_TIME)}}</option>
                                <option value="{{TODAY}}" {{ env('APP_MODE') != 'demo' ? "selected" : "" }}>{{translate(TODAY)}}</option>
                                <option value="{{PREVIOUS_DAY}}">{{translate(PREVIOUS_DAY)}}</option>
                                <option value="{{LAST_7_DAYS}}">{{translate(LAST_7_DAYS)}}</option>
                                <option value="{{THIS_WEEK}}">{{translate(THIS_WEEK)}}</option>
                                <option value="{{LAST_WEEK}}">{{translate(LAST_WEEK)}}</option>
                                <option value="{{THIS_MONTH}}">{{translate(THIS_MONTH)}}</option>
                                <option value="{{LAST_MONTH}}">{{translate(LAST_MONTH)}}</option>
                                <option value="{{THIS_YEAR}}">{{translate(THIS_YEAR)}}</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body hide-2nd-line-of-chart" id="updating_line_chart">
                        <div id="apex_line-chart"></div>
                    </div>
                </div>
                <!-- End Admin Earning Statistics -->

                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div class="d-flex flex-column align-items-start gap-1">
                                    <h5 class="text-capitalize">{{translate('leader_board')}}</h5>
                                    <span class="badge bg-primary">{{translate('driver')}}</span>
                                </div>


                                <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button value="{{TODAY}}"
                                                class="nav-link text-capitalize leader-board-driver {{ env('APP_MODE') != 'demo' ? "active" : "" }}"
                                                data-bs-toggle="tab"
                                                data-bs-target="#today-tab-pane" aria-selected="{{ env('APP_MODE') != 'demo' ? "true" : "false" }}"
                                                role="tab">{{translate(TODAY)}}</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button value="{{THIS_WEEK}}"
                                                class="nav-link text-capitalize leader-board-driver"
                                                data-bs-toggle="tab"
                                                data-bs-target="#week-tab-pane" aria-selected="false"
                                                role="tab" tabindex="-1">{{translate(THIS_WEEK)}}</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button value="{{THIS_MONTH}}"
                                                class="nav-link text-capitalize leader-board-driver"
                                                data-bs-toggle="tab"
                                                data-bs-target="#month-tab-pane" aria-selected="false"
                                                role="tab" tabindex="-1">{{translate(THIS_MONTH)}}</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button value="{{ALL_TIME}}"
                                                class="nav-link text-capitalize leader-board-driver {{ env('APP_MODE') != 'demo' ? "" : "active" }}"
                                                data-bs-toggle="tab"
                                                data-bs-target="#all-time-tab-pane" aria-selected="{{ env('APP_MODE') != 'demo' ? "false" : "true" }}"
                                                role="tab" tabindex="-1">{{translate(ALL_TIME)}}</button>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    <div id="leader-board-driver"></div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <!-- Recent Transaction -->
                        <div class="card recent-transactions max-h-460px">
                            <div class="card-header">
                                <h4 class="mb-2">{{translate('recent_transactions')}}</h4>
                                <div class="d-flex justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-arrow-up text-primary"></i>
                                        <p class="opacity-75">{{ translate('last') }} {{$transactions->count()}} {{ translate('transactions_this_month') }}</p>
                                    </div>
                                    <a href="{{route('admin.transaction.index')}}"
                                       class="btn-link text-capitalize">{{translate('view_all')}}</a>
                                </div>

                            </div>
                            <div class="card-body overflow-y-auto">

                                <div class="events">
                                    @forelse ($transactions as $transaction)
                                        <div class="event">
                                            <div class="knob"></div>
                                            <div class="title">
                                                @if($transaction->debit>0)
                                                    <h5>{{ getCurrencyFormat($transaction->debit ?? 0) }} {{translate("Debited from ")}}
                                                        {{translate($transaction->account)}}</h5>
                                                @else
                                                    <h5>{{ getCurrencyFormat($transaction->credit ?? 0) }} {{translate("Credited to ")}}
                                                        {{translate($transaction->account)}}</h5>
                                                @endif
                                            </div>
                                            @php($time_format = getSession('time_format'))
                                            <div class="description d-flex gap-3">
                                                @if($transaction?->readable_id)
                                                    <span>#{{ $transaction?->readable_id ?? '' }}</span>
                                                @endif
                                                <span>{{date(DASHBOARD_DATE_FORMAT,strtotime($transaction->created_at))}}</span>
                                            </div>
                                        </div>
                                    @empty

                                    @endforelse
                                    <div class="line"></div>
                                </div>
                            </div>
                        </div>
                        <!-- End Recent Transaction -->
                    </div>
                </div>
                <div class="row g-3 pt-3">
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div class="d-flex flex-column align-items-start gap-1">
                                    <h5 class="text-capitalize">{{translate('leader_board')}}</h5>
                                    <span class="badge bg-primary">{{translate('customer')}}</span>
                                </div>

                                <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button value="{{TODAY}}"
                                                class="nav-link text-capitalize leader-board-customer {{ env('APP_MODE') != 'demo' ? "active" : "" }}"
                                                data-bs-toggle="tab"
                                                data-bs-target="#today-tab-pane" aria-selected="{{ env('APP_MODE') != 'demo' ? "true" : "false" }}"
                                                role="tab">{{translate(TODAY)}}</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button value="{{THIS_WEEK}}"
                                                class="nav-link text-capitalize leader-board-customer"
                                                data-bs-toggle="tab"
                                                data-bs-target="#today-tab-pane" aria-selected="false"
                                                role="tab">{{translate(THIS_WEEK)}}</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button value="{{THIS_MONTH}}"
                                                class="nav-link text-capitalize leader-board-customer"
                                                data-bs-toggle="tab"
                                                data-bs-target="#today-tab-pane" aria-selected="false"
                                                role="tab">{{translate(THIS_MONTH)}}</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button value="{{ALL_TIME}}"
                                                class="nav-link text-capitalize leader-board-customer {{ env('APP_MODE') != 'demo' ? "" : "active" }}"
                                                data-bs-toggle="tab"
                                                data-bs-target="#today-tab-pane" aria-selected="{{ env('APP_MODE') != 'demo' ? "false" : "true" }}"
                                                role="tab">{{translate(ALL_TIME)}}</button>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    <div id="leader-board-customer"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <!-- Recent Trips Activity -->
                        <div class="card recent-activities max-h-460px">
                            <div class="card-header d-flex justify-content-between gap-10">
                                <div class="d-flex flex-column gap-1">
                                    <h5 class="text-capitalize">{{translate('recent_trips_activity')}}</h5>
                                    <p class="text-capitalize">{{translate('all_activities')}}</p>
                                </div>
                                <a href="{{route('admin.trip.index', ['all'])}}"
                                   class="btn-link text-capitalize">{{translate('view_all')}}</a>
                            </div>
                            <div class="card-body overflow-y-auto" id="recent_trips_activity">
                            </div>
                        </div>
                        <!-- End Recent Trips Activity -->
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection

@push('script')
    <!-- Apex Chart -->
    <script src="{{dynamicAsset('public/assets/admin-module/plugins/apex/apexcharts.min.js')}}"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/js/admin-module/dashboard.js')}}"></script>
    <!-- Google Map -->

    <script>
        "use strict";

        $(".leader-board-customer").on('click', function () {
            let data = $(this).val();
            loadPartialView('{{route('admin.leader-board-customer')}}', '#leader-board-customer', data)
        })
        $(".leader-board-driver").on('click', function () {
            let data = $(this).val();
            loadPartialView('{{route('admin.leader-board-driver')}}', '#leader-board-driver', data)
        })


        $("#rideZone,#rideDate").on('change', function () {
            let date = $("#rideDate").val();
            let zone = $("#rideZone").val();
            adminEarningStatistics(date, zone)
        })

        function adminEarningStatistics(date, zone = null) {
            $.get({
                url: '{{route('admin.earning-statistics')}}',
                dataType: 'json',
                data: {date: date, zone: zone},
                beforeSend: function () {
                    $('#resource-loader').show();
                },
                success: function (response) {
                    let hours = response.label;
                    // Remove double quotes from each string value
                    hours = hours.map(function (hour) {
                        return hour.replace(/"/g, '');
                    });
                    document.getElementById('apex_line-chart').remove();
                    let graph = document.createElement('div');
                    graph.setAttribute("id", "apex_line-chart");
                    document.getElementById("updating_line_chart").appendChild(graph);
                    let options = {
                        series: [
                            {
                                name: '{{translate("Admin Commission")}} ($)',
                                data: [0].concat(Object.values(response.totalAdminCommission))
                            },
                            {
                                name: '{{translate("Ride")}}',
                                data: [0].concat(Object.values(response.totalRideCount))
                            },
                            {
                                name: '{{translate("Parcel")}}',
                                data: [0].concat(Object.values(response.totalParcelCount))
                            },

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
                            toolbar: {
                                show: false
                            },
                        },
                        colors: ['#14B19E'],
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
                            size: 1,
                            strokeColors: [ '#14B19E'],
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
                            categories: ['00'].concat(hours),
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
                            tickAmount: 10,
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

        $("#zoneWiseRideDate").on('change', function () {
            let date = $("#zoneWiseRideDate").val()
            zoneWiseTripStatistics(date)
        })

        function zoneWiseTripStatistics(date) {
            $.get({
                url: '{{route('admin.zone-wise-statistics')}}',
                dataType: 'json',
                data: {date: date},
                beforeSend: function () {
                    $('#resource-loader').show();
                },
                success: function (response) {
                    $('#zoneWiseTripStatistics').empty().html(response)
                },
                complete: function () {
                    $('#resource-loader').hide();
                },
                error: function (xhr, status, error) {
                    $('#resource-loader').hide();
                    toastr.error('{{translate('failed_to_load_data')}}')
                },
            });

        }

        // partial view
        loadPartialView('{{route('admin.recent-trip-activity')}}', '#recent_trips_activity', null);
        loadPartialView('{{route('admin.leader-board-driver')}}', '#leader-board-driver', '{{ env('APP_MODE') != 'demo' ? "today" : "all_time" }}');
        loadPartialView('{{route('admin.leader-board-customer')}}', '#leader-board-customer', '{{ env('APP_MODE') != 'demo' ? "today" : "all_time" }}');
        zoneWiseTripStatistics(document.getElementById('zoneWiseRideDate').value);
        adminEarningStatistics('{{ env('APP_MODE') != 'demo' ? "today" : "all_time" }}', 'all')

    </script>
    @include('adminmodule::partials.dashboard.map')

@endpush
