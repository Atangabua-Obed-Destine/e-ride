"use strict";

const zoneMapData = $('.zone-map-data-to-js');
const mapId = zoneMapData.data('map-id') ?? '';
const CLOSE_POLYGON_TEXT = zoneMapData.data('close-polygon-text') ?? '';
const CLEAR_DRAWING_TEXT = zoneMapData.data('clear-drawing-text') ?? '';
const { AdvancedMarkerElement } = google.maps.marker;

let map;
let drawingPolyline = null;
let drawingPolygon = null;
let polygonClosed = false;
let lastpolygon = null;
let polygons = [];
let drawingMode = true;
let vertexMarkers = [];
let handToolEl = null;
let shapeToolEl = null;
const MIN_VERTICES = 3;

function auto_grow() {
    let element = document.getElementById("coordinates");
    element.style.height = "5px";
    element.style.height = (element.scrollHeight) + "px";
}

function vertexElement(highlighted) {
    const size = highlighted ? 20 : 12;
    const div = document.createElement('div');
    div.style.cssText =
        'width:' + size + 'px;' +
        'height:' + size + 'px;' +
        'border-radius:50%;' +
        'background:' + (highlighted ? '#00b35c' : '#FF0000') + ';' +
        'border:2px solid #fff;' +
        'box-shadow:0 1px 3px rgba(0,0,0,0.3);' +
        'cursor:' + (highlighted ? 'pointer' : 'default') + ';' +
        'transform:translateY(50%);';
    return div;
}

function currentPath() {
    if (polygonClosed && drawingPolygon) return drawingPolygon.getPath().getArray();
    if (drawingPolyline) return drawingPolyline.getPath().getArray();
    return [];
}

function syncVertexMarkers() {
    vertexMarkers.forEach(function (m) { m.map = null; });
    vertexMarkers = [];
    if (polygonClosed) return;
    const { AdvancedMarkerElement } = google.maps.marker;
    const path = currentPath();
    path.forEach(function (latLng, idx) {
        const isFirst = idx === 0;
        const canClose = isFirst && path.length >= MIN_VERTICES;
        const marker = new AdvancedMarkerElement({
            position: latLng,
            map: map,
            content: vertexElement(canClose),
            gmpClickable: canClose,
            title: canClose ? CLOSE_POLYGON_TEXT : "",
            zIndex: 9999,
        });
        if (canClose) marker.addListener("gmp-click", closePolygon);
        vertexMarkers.push(marker);
    });
}

function clearDrawing() {
    if (drawingPolygon) {
        drawingPolygon.setMap(null);
        drawingPolygon = null;
    }
    if (drawingPolyline) {
        drawingPolyline.getPath().clear();
        drawingPolyline.setMap(map);
        lastpolygon = drawingPolyline;
    }
    polygonClosed = false;
    vertexMarkers.forEach(function (m) { m.map = null; });
    vertexMarkers = [];
    $('#coordinates').val('');
    auto_grow();
}

function updateCoordinates() {
    const path = currentPath();
    $('#coordinates').val(path.length ? path.toString() : '');
    auto_grow();
    syncVertexMarkers();
}

function closePolygon() {
    if (!drawingPolyline) return;
    const path = drawingPolyline.getPath().getArray();
    if (path.length < MIN_VERTICES) return;

    drawingPolyline.setMap(null);
    drawingPolygon = new google.maps.Polygon({
        map: map,
        paths: path,
        editable: true,
        clickable: false,
        strokeColor: "#FF0000",
        strokeOpacity: 0.8,
        strokeWeight: 2,
        fillColor: "#FF0000",
        fillOpacity: 0.1,
    });
    polygonClosed = true;
    lastpolygon = drawingPolygon;

    const polyPath = drawingPolygon.getPath();
    google.maps.event.addListener(polyPath, "set_at", updateCoordinates);
    google.maps.event.addListener(polyPath, "insert_at", updateCoordinates);
    google.maps.event.addListener(polyPath, "remove_at", updateCoordinates);

    vertexMarkers.forEach(function (m) { m.map = null; });
    vertexMarkers = [];
    updateCoordinates();
}

function setDrawingMode(drawing) {
    drawingMode = drawing;
    if (map) {
        map.setOptions({ draggableCursor: drawing ? "crosshair" : null });
    }
    if (shapeToolEl) {
        shapeToolEl.style.backgroundColor = drawing ? "#e7f0ff" : "#fff";
        shapeToolEl.style.color = drawing ? "#050df2" : "#444";
    }
    if (handToolEl) {
        handToolEl.style.backgroundColor = drawing ? "#fff" : "#e7f0ff";
        handToolEl.style.color = drawing ? "#444" : "#050df2";
    }
}

function buildDrawingControl() {
    const wrapper = document.createElement("div");
    wrapper.style.cssText = "margin:10px;display:flex;border-radius:4px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.3);background:#fff;font-family:Roboto,Arial,sans-serif;";

    handToolEl = document.createElement("div");
    handToolEl.title = "Hand Tool — pan the map";
    handToolEl.style.cssText = "cursor:pointer;display:flex;align-items:center;justify-content:center;width:36px;height:36px;font-size:18px;color:#444;";
    handToolEl.innerHTML = `<i class="tio-hand-draw"></i>`;

    shapeToolEl = document.createElement("div");
    shapeToolEl.title = "Shape Tool — click the map to connect the dots";
    shapeToolEl.style.cssText = "cursor:pointer;display:flex;align-items:center;justify-content:center;width:36px;height:36px;font-size:18px;color:#444;border-left:1px solid #e6e6e6;";
    shapeToolEl.innerHTML = `<i class="tio-free-transform"></i>`;

    handToolEl.addEventListener("click", function () { setDrawingMode(false); });
    shapeToolEl.addEventListener("click", function () { setDrawingMode(true); });

    wrapper.appendChild(handToolEl);
    wrapper.appendChild(shapeToolEl);
    return wrapper;
}

function resetMap(controlDiv) {
    const controlUI = document.createElement("div");
    controlUI.title = CLEAR_DRAWING_TEXT;
    controlUI.setAttribute("class", "reset-map-btn");
    controlUI.style.cssText =
        "margin:10px;width:36px;height:36px;display:flex;align-items:center;justify-content:center;" +
        "background:#fff;border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.3);cursor:pointer;" +
        "color:#e3342f;font-size:18px;transition:background .15s ease;";
    controlUI.innerHTML = `<i class="tio-delete-outlined"></i>`;
    controlUI.addEventListener("mouseenter", () => { controlUI.style.background = "#fdecea"; });
    controlUI.addEventListener("mouseleave", () => { controlUI.style.background = "#fff"; });
    controlUI.addEventListener("click", () => {
        clearDrawing();
    });
    controlDiv.appendChild(controlUI);
}

function applyResponsiveMapControls() {
    map.setOptions({
        mapTypeControlOptions: {
            position: google.maps.ControlPosition.TOP_LEFT,
            style: window.innerWidth < 992
                ? google.maps.MapTypeControlStyle.DROPDOWN_MENU
                : google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
        },
    });
}

function setupDrawingTools() {
    applyResponsiveMapControls();
    window.addEventListener('resize', applyResponsiveMapControls);

    drawingPolyline = new google.maps.Polyline({
        map: map,
        editable: false,
        clickable: false,
        strokeColor: "#FF0000",
        strokeOpacity: 0.8,
        strokeWeight: 2,
    });
    drawingPolyline.setPath([]);
    const polylinePath = drawingPolyline.getPath();
    lastpolygon = drawingPolyline;

    google.maps.event.addListener(polylinePath, "set_at", updateCoordinates);
    google.maps.event.addListener(polylinePath, "insert_at", updateCoordinates);
    google.maps.event.addListener(polylinePath, "remove_at", updateCoordinates);

    google.maps.event.addListener(map, "click", function (event) {
        if (!drawingMode) return;
        if (polygonClosed) return;
        polylinePath.push(event.latLng);
        updateCoordinates();
    });

    map.controls[google.maps.ControlPosition.LEFT_TOP].push(buildDrawingControl());
    setDrawingMode(true);

    const resetDiv = document.createElement("div");
    resetMap(resetDiv, lastpolygon);
    map.controls[google.maps.ControlPosition.RIGHT_TOP].push(resetDiv);

    setupSearchBox();
}

function setupSearchBox() {
    const input = document.getElementById("pac-input");
    const searchBox = new google.maps.places.SearchBox(input);
    map.addListener("bounds_changed", () => {
        searchBox.setBounds(map.getBounds());
    });
    let markers = [];
    searchBox.addListener("places_changed", () => {
        const places = searchBox.getPlaces();
        if (places.length === 0) {
            return;
        }
        markers.forEach((m) => { m.map = null; });
        markers = [];
        const bounds = new google.maps.LatLngBounds();
        places.forEach((place) => {
            if (!place.geometry || !place.geometry.location) {
                return;
            }
            markers.push(
                new AdvancedMarkerElement({
                    map,
                    title: place.name,
                    position: place.geometry.location,
                })
            );

            if (place.geometry.viewport) {
                bounds.union(place.geometry.viewport);
            } else {
                bounds.extend(place.geometry.location);
            }
        });
        map.fitBounds(bounds);
    });
}

auto_grow();
