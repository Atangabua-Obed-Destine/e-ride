@section('title', translate('fleet_map'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
    @php($map_key = businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null)
    <script src="https://maps.googleapis.com/maps/api/js?key={{$map_key}}&libraries=places"></script>
@endpush

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex gap-2 flex-column flex-sm-row justify-content-between">
                    <div class="w-100 max-w-299px">
                        <h4>{{translate('User Live View')}}</h4>
                        <p>{{translate("Monitor your users from here")}}</p>
                    </div>
                    <div class="get-zone-message">
                        @include('adminmodule::partials.fleet-map._safety-alert-get-zone-message')
                    </div>
                </div>
                <div class="card-body tab-filter-container">
                    <div class="border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                        {{-- Tab Menu --}}
                        <ul class="nav d-inline-flex nav--tabs-2 rounded bg-white align-items-center mt-2"
                            id="zone-tab-menu">
                            <li class="nav-item">
                                <a href="{{route('admin.fleet-map',['type' => ALL_DRIVER, 'zone_id' => request('zone_id')])}}"
                                   class="nav-link text-capitalize {{request('type') == ALL_DRIVER ? 'active' : ''}}"
                                   data-tab-target="all-driver">{{translate("All Drivers")}}</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{route('admin.fleet-map',['type' => DRIVER_ON_TRIP, 'zone_id' => request('zone_id')])}}"
                                   class="nav-link text-capitalize {{request('type') == DRIVER_ON_TRIP ? 'active' : ''}}"
                                   data-tab-target="trip-driver">{{translate("On-trip")}}</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{route('admin.fleet-map',['type' => DRIVER_IDLE, 'zone_id' => request('zone_id')])}}"
                                   class="nav-link text-capitalize {{request('type') == DRIVER_IDLE ? 'active' : ''}}"
                                   data-tab-target="idle-driver">{{translate("Idle")}}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-capitalize">|</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{route('admin.fleet-map',['type' => ALL_CUSTOMER, 'zone_id' => request('zone_id')])}}"
                                   class="nav-link text-capitalize {{request('type') == ALL_CUSTOMER ? 'active' : ''}}"
                                   data-tab-target="customer">{{translate("Customers")}}</a>
                            </li>
                        </ul>
                        <form action="{{request()->fullUrl()}}" id="zoneSubmitForm" class="pb-1">
                            <div class="">
                                <select class="js-select-custom min-w-200 h-35" name="zone_id" id="selectZone">
                                    @if(count($zones)>0)
                                        @foreach($zones as $key =>$zone)
                                            <option value="{{$zone->id}}"
                                                    {{request('zone_id') == $zone->id ? "selected" :""}} data-show-shield="{{ in_array($zone->id, $safetyAlertZones) ? 'true' : '' }}">{{$zone->name}}</option>
                                        @endforeach
                                    @else
                                        <option selected disabled>{{translate("zone_not_found")}}</option>
                                    @endif
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="zone-lists d-flex flex-wrap gap-3">
                        <div class="zone-lists__left">
                            <div id="zone-tab-content">
                                <div>
                                    @if(request('type') == ALL_DRIVER)
                                        <div data-tab-type="all-driver">
                                            <h4 class="mb-2">{{translate("Driver List")}}</h4>
                                            <form action="javascript:;" class="search-form search-form_style-two"
                                                  method="GET">
                                                <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                    <input type="text" class="theme-input-style search-form__input"
                                                           value="{{ request('search') }}" name="search" id="search"
                                                           placeholder="{{ translate('search_driver') }}">
                                                </div>
                                                <button type="submit" class="btn btn-primary search-submit"
                                                        data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                                            </form>
                                            <ul class="zone-list">
                                                @include('adminmodule::partials.fleet-map._fleet-map-driver-list')
                                            </ul>
                                        </div>
                                    @endif
                                    @if(request('type') == DRIVER_ON_TRIP)
                                        <div data-tab-type="trip-driver">
                                            <h4 class="mb-2">{{translate("On Trip Driver")}}</h4>
                                            <form action="javascript:;" class="search-form search-form_style-two"
                                                  method="GET">
                                                <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                    <input type="text" class="theme-input-style search-form__input"
                                                           value="{{ request('search') }}" name="search" id="search"
                                                           placeholder="{{ translate('search_driver') }}">
                                                </div>
                                                <button type="submit" class="btn btn-primary search-submit"
                                                        data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                                            </form>
                                            <ul class="zone-list">
                                                @include('adminmodule::partials.fleet-map._fleet-map-driver-list')
                                            </ul>
                                        </div>

                                    @endif
                                    @if(request('type') == DRIVER_IDLE)
                                        <div data-tab-type="idle-driver">
                                            <h4 class="mb-2">{{translate("Idle Driver")}}</h4>
                                            <form action="javascript:;"
                                                  class="search-form search-form_style-two" method="GET">
                                                <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                    <input type="text" class="theme-input-style search-form__input"
                                                           value="{{ request('search') }}" name="search" id="search"
                                                           placeholder="{{ translate('search_driver') }}">
                                                </div>
                                                <button type="submit" class="btn btn-primary search-submit"
                                                        data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                                            </form>
                                            <ul class="zone-list">
                                                @include('adminmodule::partials.fleet-map._fleet-map-driver-list')
                                            </ul>
                                        </div>

                                    @endif
                                    @if(request('type') == ALL_CUSTOMER)
                                        <div data-tab-type="customer">
                                            <h4 class="mb-2">{{translate("Customer List")}}</h4>
                                            <form action="javascript:;" class="search-form search-form_style-two"
                                                  method="GET">
                                                <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                    <input type="text" class="theme-input-style search-form__input"
                                                           value="{{ request('search') }}" name="search" id="search"
                                                           placeholder="{{ translate('search_customer') }}">
                                                </div>
                                                <button type="submit" class="btn btn-primary search-submit"
                                                        data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                                            </form>
                                            <ul class="zone-list">
                                                @include('adminmodule::partials.fleet-map._fleet-map-customer-list')
                                            </ul>
                                        </div>
                                    @endif

                                </div>
                            </div>

                            {{-- Driver Details --}}
                            <div id="userDetails">
                            </div>
                        </div>
                        <div class="zone-lists__map" id="partialFleetMap">
                            @include('adminmodule::partials.fleet-map._fleet-map-view')
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Overlay --}}
        <div class="js-select-overlay">
            <div class="inner-div">
                <select class="js-select cmn_focus">
                    <option value="">{{count($zones)?$zones[0]?->name:translate("zone_not_found")}}</option>
                </select>
                <div class="mt-2">
                    {{translate('From here select your zone and see the filtered data')}}
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="userId">
@endsection

@push('script')
    <script>
        "use strict";
        $(document).ready(function () {
            function formatState(state) {
                if (!state.id) {
                    return state.text;
                }

                // Check for a custom data attribute to determine if image should be shown
                var shouldShowImage = $(state.element).data('show-shield');

                if (shouldShowImage) {
                    var $state = $('<div class="d-flex align-items-center gap-2 justify-content-between">' +
                        state.text +
                        '<img src="{{dynamicAsset('public/assets/admin-module/img/svg/shield-red.svg')}}" class="svg" alt="" />' +
                        '</div>');
                    return $state;
                }

                // If no image should be shown, return just the text
                return state.text;
            }

            $(".js-select-custom").select2({
                templateResult: formatState
            });

            let zoneMessageHide = $('.zone-message-hide');
            let zoneMessage = $('.zone-message');
            let showZoneMessage = sessionStorage.getItem('showZoneMessage');
            if (showZoneMessage) {
                zoneMessage.addClass('invisible');
            } else {
                zoneMessage.removeClass('invisible');
            }
            zoneMessageHide.on('click', function () {
                zoneMessage.addClass('invisible');
                sessionStorage.setItem('showZoneMessage', 'false');
            });
        });
        $(document).ready(function () {
            let bounds = new google.maps.LatLngBounds();
            let map = "";
            let polygons = [];
            let activeInfoWindow = null;
            let markers = [];
            let allPoints = [];
            let renderedMarkers = new Map();
            let activeData = null

            function initMap(
                mapSelector,
                lat,
                lng,
                title,
                markersData,
                input,
                data = []
            ) {

                let zoomValue = 13;
                if (lat == 0 && lng == 0) {
                    zoomValue = 2;
                }
                let polygons = [];
                if (zoomValue == 2) {
                    map = new google.maps.Map(document.getElementById(mapSelector), {
                        zoom: 2, // Low zoom level to display the world
                        center: {lat: 0, lng: 0},
                    });
                } else {
                    map = new google.maps.Map(document.getElementById(mapSelector), {
                        zoom: zoomValue,
                        center: {lat: lat, lng: lng},
                        fullscreenControl: true,
                        mapTypeControlOptions: {
                            position: google.maps.ControlPosition.BOTTOM_LEFT,
                        },
                    });
                }

                if (zoomValue == 13) {
                    for (let i = 0; i < data.length; i++) {
                        polygons.push(
                            new google.maps.Polygon({
                                paths: data[i],
                                strokeColor: "#000000",
                                strokeOpacity: 0.2,
                                strokeWeight: 2,
                                fillColor: "#000000",
                                fillOpacity: 0.05,
                            })
                        );
                        polygons[i].setMap(map);
                        polygons[i].getPaths().forEach(function (path) {
                            path.forEach(function (latlng) {
                                bounds.extend(latlng);
                            });
                        });
                    }
                    map.fitBounds(bounds);
                }

                const infoWindow = new google.maps.InfoWindow();


                const searchBox = new google.maps.places.SearchBox(input);

                map.addListener("bounds_changed", function () {
                    searchBox.setBounds(map.getBounds());
                });

                let searchMarkers = [];

                searchBox.addListener("places_changed", function () {
                    const places = searchBox.getPlaces();

                    if (places.length === 0) {
                        return;
                    }

                    searchMarkers.forEach(function (marker) {
                        marker.setMap(null);
                    });
                    searchMarkers = [];

                    const bounds = new google.maps.LatLngBounds();

                    places.forEach(function (place) {
                        if (!place.geometry || !place.geometry.location) {
                            console.info("Returned place contains no geometry");
                            return;
                        }

                        const marker = new google.maps.Marker({
                            map: map,
                            icon: {
                                url: place.icon,
                                size: new google.maps.Size(71, 71),
                                origin: new google.maps.Point(0, 0),
                                anchor: new google.maps.Point(17, 34),
                                scaledSize: new google.maps.Size(25, 25),
                            },
                            title: place.name,
                            position: place.geometry.location,
                        });

                        searchMarkers.push(marker);

                        if (place.geometry.viewport) {
                            bounds.union(place.geometry.viewport);
                        } else {
                            bounds.extend(place.geometry.location);
                        }
                    });

                    map.fitBounds(bounds);
                });

                map.addListener("idle", renderClusters);
                updateMarkers(markersData);

            }


            const infoWindow = new google.maps.InfoWindow();
            let currentlyOpenInfoWindow = null;
            let currentlyOpenMarkerId = null; // ID of the currently opened marker
            let isSingleView = false;
            let singleInterval, doubleInterval;
            let currentUrl = window.location.href;
            let substrings = ['all-customer', 'driver-on-trip', 'driver-idle'];
            let found = substrings.some(substring => currentUrl.includes(substring));
            let safetyAlertUserDetailsStatus = localStorage.getItem('safetyAlertUserDetailsStatus');
            let safetyAlertUserId = localStorage.getItem('safetyAlertUserId');
            if (localStorage.getItem('safetyAlertUserIdFromTrip')) {
                safetyAlertUserId = localStorage.getItem('safetyAlertUserIdFromTrip');
            }

            if (found && safetyAlertUserId && safetyAlertUserDetailsStatus) {
                loadUserDetails(safetyAlertUserId);
                fetchSingleModelUpdate();
                localStorage.removeItem('safetyAlertUserDetailsStatus');
                localStorage.removeItem('safetyAlertUserIdFromTrip');
            }

            function openInfoWindowForMarker(marker, data) {
                const newContent = `
        <div class="map-clusters-custom-window">
            <a class="d-flex justify-content-between gap-1 align-items-center" href="${data.driver ?? data.customer}" target="_blank">
                <h6>${data.title}</h6>
                ${data.safetyAlertIcon ? `<img src="${data.safetyAlertIcon}" alt="safety alert icon" height="22px" width="22px">` : ''}
            </a>
            <a href="${data.trip}" target="_blank"><p>${data.subtitle || ""}</p></a>
        </div>
    `;

                if (currentlyOpenInfoWindow && currentlyOpenMarkerId === data.id && currentlyOpenInfoWindow.getContent() === newContent) {
                    return; // No update needed
                }

                if (currentlyOpenInfoWindow) currentlyOpenInfoWindow.close();
                infoWindow.setContent(newContent);
                infoWindow.setPosition(marker.getPosition());
                infoWindow.open(map, marker);

                currentlyOpenInfoWindow = infoWindow;
                currentlyOpenMarkerId = data.id;
                singleViewZoom(data.position);
            }

            const CLUSTER_CELL_PX = 55;
            const RENDER_ALL_THRESHOLD = 200;

            function updateMarkers(markerData, openMarkers = false) {
                allPoints = Array.isArray(markerData) ? markerData : [];
                renderClusters();
            }

            function upsertPointMarker(data, nextKeys, nextMarkers) {
                const key = 'p:' + data.id;
                nextKeys.add(key);
                let marker = renderedMarkers.get(key);
                if (marker && marker.__isCluster) {
                    marker.setMap(null);
                    renderedMarkers.delete(key);
                    marker = null;
                }
                if (!marker) {
                    marker = new google.maps.Marker({
                        position: data.position,
                        title: data.title,
                        icon: data.icon,
                    });
                    marker.id = data.id;
                    marker.__data = data;
                    marker.addListener('click', () => openInfoWindowForMarker(marker, marker.__data));
                    marker.setMap(map);
                    renderedMarkers.set(key, marker);
                } else {
                    marker.__data = data;
                    if (marker.getIcon() !== data.icon) marker.setIcon(data.icon);
                    const cur = marker.getPosition();
                    if (!cur || cur.lat() !== data.position.lat || cur.lng() !== data.position.lng) {
                        marker.setPosition(data.position);
                    }
                    if (currentlyOpenMarkerId === data.id) openInfoWindowForMarker(marker, data);
                }
                nextMarkers.push(marker);
            }

            function upsertClusterMarker(cellKey, center, count, nextKeys, cell) {
                const key = 'c:' + cellKey;
                nextKeys.add(key);
                let marker = renderedMarkers.get(key);
                if (marker && !marker.__isCluster) {
                    marker.setMap(null);
                    renderedMarkers.delete(key);
                    marker = null;
                }
                if (!marker) {
                    marker = new google.maps.Marker({position: center});
                    marker.__isCluster = true;
                    marker.addListener('click', () => {
                        google.maps.event.addListenerOnce(map, 'idle', () => {
                            if (map.getZoom() > 21) map.setZoom(21);
                        });
                        map.fitBounds(marker.__clusterBounds, 100);
                    });
                    marker.setMap(map);
                    renderedMarkers.set(key, marker);
                } else {
                    marker.setPosition(center);
                }
                marker.__clusterBounds = new google.maps.LatLngBounds(
                    {lat: cell.mnLat, lng: cell.mnLng},
                    {lat: cell.mxLat, lng: cell.mxLng}
                );
                marker.setIcon({
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 12 + Math.min(count, 500) / 12,
                    fillColor: '#1e88e5',
                    fillOpacity: 0.9,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                });
                marker.setLabel({text: String(count), color: '#ffffff', fontSize: '12px', fontWeight: 'bold'});
            }

            function renderClusters() {
                if (!map || typeof map.getProjection !== 'function') return;
                const projection = map.getProjection();
                const mapBounds = map.getBounds();
                if (!projection || !mapBounds) return;

                const scale = Math.pow(2, map.getZoom());
                const nextKeys = new Set();
                const nextMarkers = [];

                const useViewport = allPoints.length > RENDER_ALL_THRESHOLD;
                const cells = new Map();
                allPoints.forEach(p => {
                    const pos = p.position;
                    if (!pos || (pos.lat === 0 && pos.lng === 0)) return;
                    const ll = new google.maps.LatLng(pos.lat, pos.lng);
                    if (useViewport && !mapBounds.contains(ll)) return;
                    const world = projection.fromLatLngToPoint(ll);
                    const cx = Math.floor(world.x * scale / CLUSTER_CELL_PX);
                    const cy = Math.floor(world.y * scale / CLUSTER_CELL_PX);
                    const k = cx + '_' + cy;
                    let cell = cells.get(k);
                    if (!cell) {
                        cell = {pts: [], sLat: 0, sLng: 0, mnLat: pos.lat, mxLat: pos.lat, mnLng: pos.lng, mxLng: pos.lng};
                        cells.set(k, cell);
                    }
                    cell.pts.push(p);
                    cell.sLat += pos.lat;
                    cell.sLng += pos.lng;
                    if (pos.lat < cell.mnLat) cell.mnLat = pos.lat;
                    if (pos.lat > cell.mxLat) cell.mxLat = pos.lat;
                    if (pos.lng < cell.mnLng) cell.mnLng = pos.lng;
                    if (pos.lng > cell.mxLng) cell.mxLng = pos.lng;
                });
                cells.forEach((cell, k) => {
                    if (cell.pts.length === 1) {
                        upsertPointMarker(cell.pts[0], nextKeys, nextMarkers);
                    } else {
                        upsertClusterMarker(k, {lat: cell.sLat / cell.pts.length, lng: cell.sLng / cell.pts.length}, cell.pts.length, nextKeys, cell);
                    }
                });

                renderedMarkers.forEach((marker, key) => {
                    if (!nextKeys.has(key)) {
                        marker.setMap(null);
                        renderedMarkers.delete(key);
                    }
                });
                markers = nextMarkers;

                if (currentlyOpenMarkerId && !markers.find(m => m.id === currentlyOpenMarkerId) && currentlyOpenInfoWindow) {
                    currentlyOpenInfoWindow.close();
                    currentlyOpenInfoWindow = null;
                    currentlyOpenMarkerId = null;
                }
            }

            function fetchModelUpdate() {
                if (document.hidden) return;
                const requestData = getRequestData();
                $.get({
                    url: "{{ route('admin.fleet-map-view-using-ajax') }}",
                    dataType: "json",
                    data: requestData,
                    timeout: 12000,
                    success: function (response) {
                        if (response && !$("#userId").val()) {
                            updateMarkers(JSON.parse(response.markers), false);
                        }
                    },
                    error: showError('{{ translate('failed_to_load_data') }}')
                });
            }

            function fetchSingleModelUpdate() {
                if (document.hidden) return;
                if (typeof safetyAlertUserId !== 'undefined' && safetyAlertUserId && $("#userId").val() == '') {
                    $("#userId").val(safetyAlertUserId);
                }
                const id = $("#userId").val();
                const url = getSingleViewUrl(id);
                $.get({
                    url,
                    dataType: "json",
                    data: {zone_id: "{{ request('zone_id') }}"},
                    timeout: 12000,
                    success: function (response) {
                        const markerData = JSON.parse(response.markers);
                        updateMarkers(markerData, true);
                        if (!isSingleView && markerData.length) {
                            const firstMarker = markerData[0];
                            singleViewZoom(firstMarker.position);
                            isSingleView = true;
                            const marker = markers.find(m => m.id === firstMarker.id);
                            if (marker) openInfoWindowForMarker(marker, firstMarker);
                        }
                        loadUserDetails(id);
                    },
                    error: showError('{{ translate('failed_to_load_data') }}')
                });
            }

            function manageIntervals() {
                if ($("#userId").val()) {
                    clearInterval(doubleInterval);
                    if (!singleInterval) singleInterval = setInterval(fetchSingleModelUpdate, 60000);
                } else {
                    clearInterval(singleInterval);
                    if (!doubleInterval) doubleInterval = setInterval(fetchModelUpdate, 60000);
                }
            }

            function getRequestData() {
                return {
                    zone_id: "{{ request('zone_id') }}",
                    type: "{{ $type }}",
                    search: "{{ request('search') }}"
                };
            }

            function getSingleViewUrl(id) {
                const baseUrl = @json($type) ===
                'all-customer'
                    ? "{{ route('admin.fleet-map-view-single-customer', ':id') }}"
                    : "{{ route('admin.fleet-map-view-single-driver', ':id') }}";
                return baseUrl.replace(':id', id);
            }

            function getUserDetails(id) {
                const baseUrl =  @json($type) ===
                'all-customer'
                    ? "{{ route('admin.fleet-map-customer-details', ':id') }}"
                    : "{{ route('admin.fleet-map-driver-details', ':id') }}";
                return baseUrl.replace(':id', id);

            }

            function singleViewZoom(center) {
                if (map.getZoom() <= 19) {
                    map.setCenter(center);
                    map.setZoom(19);
                }
            }

            function showError(message) {
                return function () {
                    toastr.error(message);
                };
            }

            function resetView() {
                $('#zone-tab-content').show();
                $('#userDetails').hide();
            }

            function userZoneList() {
                $('.zone-list').off('click.fleetUser').on('click.fleetUser', '.user-details label', function (e) {
                    const id = $(this).data('id');
                    $("#userId").val(id);
                    fetchSingleModelUpdate();
                    if (singleInterval) clearInterval(singleInterval);
                    singleInterval = setInterval(fetchSingleModelUpdate, 60000);

                    isSingleView = false;
                    clearInterval(doubleInterval);
                    e.preventDefault();
                    loadUserDetails(id);
                });
            }

            function loadUserDetails(id) {
                const url = getUserDetails(id);
                $.get({
                    url,
                    dataType: 'json',
                    timeout: 12000,
                    success: function (response) {
                        $('#zone-tab-content').hide();
                        $('#userDetails').show().empty().html(response);
                        $('.customer-back-btn').on('click', resetViewAndFetch);
                        $(".markAsSolvedBtn").on('click', function (e) {
                            markAsSolved(id, this, e)
                        });
                    },
                    error: showError('{{ translate('failed_to_load_data') }}')
                });
            }

            function markAsSolved(id, thisInput, e) {
                e.preventDefault();
                let markAsSolvedUrl = $(thisInput).data('url');
                const csrfToken = $('meta[name="csrf-token"]').attr('content');
                $.ajax({
                    url: markAsSolvedUrl,
                    method: 'PUT',
                    data: {
                        _token: csrfToken,
                    },
                    success: function (response) {
                        toastr.success(response.success);
                        loadUserDetails(id);
                        fetchSingleModelUpdate();
                        getSafetyAlerts();
                        fetchSafetyAlertIcon();
                        getZoneMessage();
                    },
                    error: function (xhr, status, error) {
                        const response = xhr.responseJSON;
                        if (response && response.status == 403) {
                            toastr.error(response.message);
                            loadUserDetails(id);
                            fetchSingleModelUpdate();
                            getSafetyAlerts();
                            fetchSafetyAlertIcon();
                            getZoneMessage();
                        } else {
                            showError('{{ translate('failed_to_load_data') }}');
                        }
                    }
                });
            }

            function resetViewAndFetch(e) {
                e.preventDefault();
                $("#userId").val("");
                clearInterval(singleInterval);
                fetchModelUpdate();
                doubleInterval = setInterval(fetchModelUpdate, 60000);
                map.fitBounds(bounds);
                if (infoWindow) infoWindow.close();
                resetView();
            }

            manageIntervals();
            userZoneList();
            resetView();

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    if ($("#userId").val()) {
                        fetchSingleModelUpdate();
                    } else {
                        fetchModelUpdate();
                    }
                }
            });

            $(".map-container").each(function () {
                const map = $(this).find(".map");
                const input = $(this).find(".map-search-input")[0];
                const lat = map.data("lat");
                const lng = map.data("lng");
                const title = map.data("title");
                const markers = map.data("markers");
                const polygonData = map.data("polygon");
                initMap(map.attr("id"), lat, lng, title, markers, input, polygonData);
            });

            $('#selectZone').on('change', function () {
                $('#zoneSubmitForm').submit();
            });


            if (localStorage.getItem('firstTimeUser') === null) {
                $('.js-select-overlay').show();
                localStorage.setItem('firstTimeUser', 'true');
            }
            $('.js-select-overlay').on('click', function () {
                $(this).hide()
            })
        });
    </script>
@endpush
