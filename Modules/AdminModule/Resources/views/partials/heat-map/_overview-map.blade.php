<div class="map-container h-100">
    <input class="form-control map-search-input" type="text"
           placeholder="Search for a location">
    <div id="map" class="heat-map rounded map h-100" data-lat="{{$centerLat}}" data-lng="{{$centerLng}}"
         data-title="Map Title"
         data-markers='{{$markers}}'
         data-polygon='{{$polygons}}'
    >
    </div>
</div>

