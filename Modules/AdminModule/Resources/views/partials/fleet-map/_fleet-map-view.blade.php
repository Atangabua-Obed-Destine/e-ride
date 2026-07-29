<div class="map-container flet-map-container position-relative h-100">
   <div class="safety-alert-icon-map">
       @include('adminmodule::partials.fleet-map._safety-alert-icon-in-map')
   </div>
    <input class="form-control map-search-input" type="text"
           placeholder="Search for a location">
    <div id="map" class="heat-map rounded map h-100" data-lat="{{$centerLat}}"
         data-lng="{{$centerLng}}"
         data-title="Map Title"
         data-markers='{{$markers}}'
         data-polygon='{{$polygons}}'>
    </div>
</div>
