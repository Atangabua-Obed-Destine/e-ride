@extends('adminmodule::layouts.master')

@section('title', translate('Edit_Zone_Setup'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3 justify-content-between mb-4">
                <h2 class="fs-22 text-capitalize">{{ translate('zone_setup') }}</h2>
            </div>
            <form id="zone_form" action="{{ route('admin.zone.update', ['id'=>$zone->id]) }}"
                  enctype="multipart/form-data" method="POST">
                @csrf
                @method('put')
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row justify-content-between">
                                    <div class="col-lg-5 col-xl-4 mb-5 mb-lg-0">
                                        <h5 class="text-primary text-uppercase mb-4">{{ translate('instructions') }}</h5>
                                        <div class="d-flex flex-column">
                                            <p>{{ translate('create_zone_by_click_on_map_and_connect_the_dots_together') }}</p>

                                            <div class="media mb-2 gap-3 align-items-center">
                                                <img
                                                    src="{{dynamicAsset('public/assets/admin-module/img/map-drag.png') }}"
                                                    alt="">
                                                <div class="media-body ">
                                                    <p>{{ translate('use_this_to_drag_map_to_find_proper_area') }}</p>
                                                </div>
                                            </div>

                                            <div class="media gap-3 align-items-center">
                                                <img
                                                    src="{{dynamicAsset('public/assets/admin-module/img/map-draw.png') }}"
                                                    alt="">
                                                <div class="media-body ">
                                                    <p>{{ translate('click_this_icon_to_start_pin_points_in_the_map_and_connect_them_
                                                            to_draw_a_
                                                            zone_._Minimum_3_points_required') }}</p>
                                                </div>
                                            </div>
                                            <div class="map-img mt-4">
                                                <img
                                                    src="{{ dynamicAsset('public/assets/admin-module/img/instructions.gif') }}"
                                                    alt="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-7">
                                        <div class="mb-4">
                                            <label for="zone_name"
                                                   class="form-label text-capitalize">{{ translate('zone_name') }} <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="name" id="zone_name"
                                                   value="{{ $zone->name }}" placeholder="{{ translate('ex') }}: {{ translate('Dhanmondi') }}" required>
                                        </div>

                                        <div class="form-group mb-3 d-none">
                                            <label class="input-label"
                                                   for="coordinates">{{ translate('coordinates') }}
                                                <span
                                                    class="input-label-secondary">{{ translate('draw_your_zone_on_the_map') }}</span>
                                            </label>
                                            <textarea type="text" rows="8" name="coordinates" id="coordinates" class="form-control" readonly>@foreach($zone->coordinates[0]->toArray()['coordinates'] as $key=>$coords)<?php if (count($zone->coordinates[0]->toArray()['coordinates']) != $key + 1) {if ($key != 0) echo(','); ?>({{$coords[1]}}, {{$coords[0]}})<?php } ?>@endforeach</textarea>
                                        </div>

                                        <!-- Start Map -->
                                        <div class="map-warper position-relative map-pac-controller overflow-hidden rounded">
                                            <input id="pac-input" class="controls rounded map-search-box"
                                                   title="{{ translate('search_your_location_here') }}" type="text"
                                                   placeholder="{{ translate('search_here') }}"/>
                                            <div id="map-canvas" class="map-height"></div>
                                        </div>
                                        <!-- End Map -->
                                    </div>

                                    <div class="d-flex justify-content-end gap-3 mt-3">
                                        <button class="btn btn-secondary cmn_reset" type="reset" id="reset_btn">
                                            {{ translate('reset') }}
                                        </button>
                                        <button class="btn btn-primary cmn_focus" type="submit">
                                            {{ translate('update') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    @php($map_key = businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null)
    <span class="zone-map-data-to-js d-none"
          data-map-id="{{ $map_key }}"
          data-close-polygon-text="{{ translate('Click_to_close_polygon') }}"
          data-clear-drawing-text="{{ translate('Clear_drawing') }}"></span>
    <script src="https://maps.googleapis.com/maps/api/js?key={{ $map_key }}&libraries=places,marker"></script>
    <script src="{{dynamicAsset('public/assets/admin-module/js/zone-management/zone/zone-map.js') }}"></script>
    <script>
        "use strict";

        let bounds = new google.maps.LatLngBounds();

        function initialize() {
            let myLatlng = new google.maps.LatLng({{trim(explode(' ',$zone->center)[1], 'POINT()') }}, {{trim(explode(' ',$zone->center)[0], 'POINT()') }});
            let myOptions = {
                zoom: 13,
                center: myLatlng,
                mapId: mapId,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                mapTypeControlOptions: { position: google.maps.ControlPosition.TOP_LEFT }
            };
            map = new google.maps.Map(document.getElementById("map-canvas"), myOptions);

            const polygonCoords = [

                    @foreach($area['coordinates'] as $coords)
                {
                    lat: {{$coords[1]}}, lng: {{$coords[0]}}
                },
                @endforeach
            ];

            let zonePolygon = new google.maps.Polygon({
                paths: polygonCoords,
                clickable: false,
                strokeColor: "#000000",
                strokeOpacity: 0.2,
                strokeWeight: 2,
                fillColor: "#000000",
                fillOpacity: 0.05,
            });

            zonePolygon.setMap(map);

            zonePolygon.getPaths().forEach(function (path) {
                path.forEach(function (latlng) {
                    bounds.extend(latlng);
                    map.fitBounds(bounds);
                });
            });

            setupDrawingTools();
        }

        google.maps.event.addDomListener(window, 'load', initialize);

        function set_all_zones() {
            $.get({
                url: '{{route('admin.zone.get-zones',[$zone->id])}}',
                dataType: 'json',
                success: function (data) {
                    for (let i = 0; i < data.length; i++) {
                        polygons.push(new google.maps.Polygon({
                            paths: data[i],
                            clickable: false,
                            strokeColor: "#FF0000",
                            strokeOpacity: 0.8,
                            strokeWeight: 2,
                            fillColor: "#FF0000",
                            fillOpacity: 0.1,
                        }));
                        polygons[i].setMap(map);
                    }

                },
            });
        }

        $(document).on('ready', function () {
            set_all_zones();
            $("#zone_form").on('keydown', function (e) {
                if (e.keyCode === 13) {
                    e.preventDefault();
                }
            })
        });

        $('#reset_btn').click(function () {
            $('#name').val(null);
            clearDrawing();
        })

    </script>
@endpush
