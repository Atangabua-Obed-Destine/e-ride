"use strict";

$(document).ready(function () {
    const DEFAULT_LOCATION = { lat: 23.8103, lng: 90.4125 };
    const DEFAULT_ZOOM = 13;
    const geocoder = new google.maps.Geocoder();
    const $textarea = $("#business_address");

    const initialAddress = $textarea.val().trim();

    function initMap(mapSelector, input, title) {
        const mapElement = document.getElementById(mapSelector);
        const searchInput = input;

        let map, marker, infoWindow;

        function reverseGeocode(lat, lng, callback) {
            geocoder.geocode({ location: { lat, lng } }, (results, status) => {
                if (status === "OK" && results.length) {
                    let result = results.find(r => !r.types.includes("plus_code")) || results[0];
                    let components = result.address_components;

                    let street = components.find(c => c.types.includes("route"))?.long_name || "";
                    let number = components.find(c => c.types.includes("street_number"))?.long_name || "";
                    let city = components.find(c => c.types.includes("sublocality_level_1") || c.types.includes("locality"))?.long_name || "";
                    let state = components.find(c => c.types.includes("administrative_area_level_1"))?.long_name || "";
                    let country = components.find(c => c.types.includes("country"))?.long_name || "";

                    let addrParts = [];
                    if (street || number) addrParts.push([number, street].filter(Boolean).join(" "));
                    if (city) addrParts.push(city);
                    if (state) addrParts.push(state);
                    if (country) addrParts.push(country);

                    let addr = addrParts.filter(Boolean).join(", ");
                    if (!addr) addr = result.formatted_address;

                    callback(addr);
                } else {
                    callback("");
                }
            });
        }

        function updateMarker(lat, lng, addr = "", zoomLevel = 15, forceUpdate = false) {
            marker.setPosition({ lat, lng });
            map.setCenter({ lat, lng });
            map.setZoom(zoomLevel);
            infoWindow.setContent(addr || title || "Current location");
            infoWindow.open(map, marker);
            $(searchInput).val(addr);

            if (forceUpdate || !$textarea.data("editing")) {
                $textarea.val(addr);
            }
        }

        function saveMarkerLocation(lat, lng) {
            localStorage.setItem("savedMarker", JSON.stringify({ lat, lng }));
        }

        function loadMarkerLocation() {
            const saved = localStorage.getItem("savedMarker");
            return saved ? JSON.parse(saved) : null;
        }

        function initMapWithCoords(lat, lng) {
            map = new google.maps.Map(mapElement, {
                center: { lat, lng },
                zoom: DEFAULT_ZOOM,
                fullscreenControl: true,
            });

            marker = new google.maps.Marker({
                position: { lat, lng },
                map: map,
                draggable: false,
                title: title || "Current location",
            });

            infoWindow = new google.maps.InfoWindow({ content: title || "Current location" });
            infoWindow.open(map, marker);

            map.addListener("click", function (event) {
                const lat = event.latLng.lat();
                const lng = event.latLng.lng();
                reverseGeocode(lat, lng, function (addr) {
                    updateMarker(lat, lng, addr, 15, true);
                    saveMarkerLocation(lat, lng);
                });
            });

            const searchBox = new google.maps.places.SearchBox(searchInput);
            map.addListener("bounds_changed", () => searchBox.setBounds(map.getBounds()));
            searchBox.addListener("places_changed", () => {
                const places = searchBox.getPlaces();
                if (!places.length) return;
                const place = places[0];
                if (!place.geometry || !place.geometry.location) return;

                const addr = place.formatted_address || place.name;
                const pos = place.geometry.location;

                updateMarker(pos.lat(), pos.lng(), addr, 15, true);
                saveMarkerLocation(pos.lat(), pos.lng());
            });
        }

        function determineInitialCoordinates() {
            const currentAddress = $textarea.val().trim();

            if (currentAddress) {
                geocoder.geocode({ address: currentAddress }, (results, status) => {
                    if (status === "OK" && results[0]) {
                        const loc = results[0].geometry.location;
                        initMapWithCoords(loc.lat(), loc.lng());
                    } else {
                        initMapWithSavedMarker();
                    }
                });
            } else {
                initMapWithSavedMarker();
            }
        }

        function initMapWithSavedMarker() {
            const saved = loadMarkerLocation();
            if (saved) {
                initMapWithCoords(saved.lat, saved.lng);
            } else {
                initMapWithCoords(DEFAULT_LOCATION.lat, DEFAULT_LOCATION.lng);
            }
        }

        determineInitialCoordinates();

        $textarea.on("input", function () {
            $(this).data("editing", true);
        });

        $textarea.on("blur", function () {
            const query = $(this).val().trim();
            if (!query) return;

            geocoder.geocode({ address: query }, (results, status) => {
                if (status === "OK" && results[0]) {
                    const loc = results[0].geometry.location;
                    updateMarker(loc.lat(), loc.lng(), results[0].formatted_address, 15, true);
                    saveMarkerLocation(loc.lat(), loc.lng());
                } else {
                    console.warn("Address not found:", query);
                }
            });
        });
    }

    $(".map--container").each(function () {
        const map = $(this).find("#map-bind-with-address");
        const input = $(this).find(".map-search-input")[0];
        const title = map.data("title") || "Current location";

        initMap(map.attr("id"), input, title);
    });
});
