@extends('adminmodule::layouts.master')

@section('title', $place->exists ? $place->name : translate('Add_listing'))

@section('content')
    @php($mapKey = businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null)
    @php($lat = old('latitude', $place->latitude ?? 5.9597))
    @php($lng = old('longitude', $place->longitude ?? 10.1460))
    @php($amenities = old('amenities', $place->amenities ?? []))
    @php($photoBase = dynamicStorage('storage/app/public/place/photo'))
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ route('admin.place.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                <h2 class="fs-22 mb-0">{{ $place->exists ? $place->name : translate('Add_listing') }}</h2>
                @if($place->exists && $place->is_featured)<span class="badge bg-warning text-dark">{{ translate('Featured') }}</span>@endif
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @if(!$place->exists && $place->google_place_id)
                <div class="alert alert-info fs-12">
                    {{ translate('Started from a Google listing. Check every detail with the hotel before saving: E-Ride vouches for what it marks Verified.') }}
                </div>
            @endif

            <form action="{{ $place->exists ? route('admin.place.update', $place) : route('admin.place.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if($place->exists) @method('PUT') @endif
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-3">{{ translate('Place') }}</h5>
                                <div class="row g-3">
                                    <div class="col-sm-8">
                                        <label class="form-label">{{ translate('Name') }} *</label>
                                        <input type="text" name="name" class="form-control" required maxlength="120" value="{{ old('name', $place->name) }}" placeholder="{{ translate('Ex: Blue Pearl Hotel') }}">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label">{{ translate('Type') }}</label>
                                        <select name="category" class="form-select">
                                            @foreach(['hotel' => translate('Hotel'), 'guest_house' => translate('Guest_house'), 'lodging' => translate('Other_lodging')] as $value => $label)
                                                <option value="{{ $value }}" {{ old('category', $place->category) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">{{ translate('Short_description') }}</label>
                                        <textarea name="description" class="form-control" rows="3" maxlength="1500" placeholder="{{ translate('Ex: Quiet rooms near Commercial Avenue, generator all night, secure parking.') }}">{{ old('description', $place->description) }}</textarea>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('Lowest_price_per_night_(FCFA)') }}</label>
                                        <input type="number" name="price_min" class="form-control" min="0" step="1" value="{{ old('price_min', $place->price_min) }}" placeholder="15000">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('Highest_price_per_night_(FCFA)') }}</label>
                                        <input type="number" name="price_max" class="form-control" min="0" step="1" value="{{ old('price_max', $place->price_max) }}" placeholder="40000">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label">{{ translate('Check-in_from') }}</label>
                                        <input type="time" name="check_in" class="form-control" value="{{ old('check_in', $place->check_in) }}">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label">{{ translate('Check-out_by') }}</label>
                                        <input type="time" name="check_out" class="form-control" value="{{ old('check_out', $place->check_out) }}">
                                    </div>
                                    <div class="col-sm-4 d-flex align-items-end">
                                        <label class="d-flex justify-content-between align-items-center form-control mb-0">
                                            <span>{{ translate('24h_reception') }}</span>
                                            <span class="switcher">
                                                <input type="hidden" name="reception_24h" value="0">
                                                <input class="switcher_input" type="checkbox" name="reception_24h" value="1" {{ old('reception_24h', $place->reception_24h) ? 'checked' : '' }}>
                                                <span class="switcher_control"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-1">{{ translate('Amenities') }}</h5>
                                <p class="fs-12 text-muted">{{ translate('Only tick what the hotel really has. Guests choose on these, especially power and hot water.') }}</p>
                                <div class="row g-2">
                                    @foreach(\Modules\PlaceManagement\Entities\Place::amenityLabels() as $key => $label)
                                        <div class="col-6 col-md-4">
                                            <label class="form-check d-flex align-items-center gap-2 mb-0">
                                                <input class="form-check-input mt-0" type="checkbox" name="amenities[]" value="{{ $key }}" {{ in_array($key, $amenities, true) ? 'checked' : '' }}>
                                                <span class="form-check-label">{{ $label }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-1">{{ translate('Photos') }}</h5>
                                <p class="fs-12 text-muted">{{ translate('Up to 8 real photos: the entrance, a room, the bathroom. The first one is the cover.') }}</p>
                                @if(count($place->photos ?? []))
                                    <div class="d-flex flex-wrap gap-3 mb-3">
                                        @foreach($place->photos as $i => $file)
                                            <div class="border rounded p-2 text-center" style="width: 140px">
                                                <img src="{{ $photoBase . '/' . $file }}" class="rounded w-100 object-fit-cover mb-2" style="height: 90px" alt="">
                                                <label class="d-flex align-items-center gap-1 fs-12 mb-1">
                                                    <input type="radio" name="cover_photo" value="{{ $file }}" {{ $i === 0 ? 'checked' : '' }}> {{ translate('Cover') }}
                                                </label>
                                                <label class="d-flex align-items-center gap-1 fs-12 text-danger mb-0">
                                                    <input type="checkbox" name="remove_photos[]" value="{{ $file }}"> {{ translate('Remove') }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @if(count($place->photos ?? []) < \Modules\PlaceManagement\Entities\Place::MAX_PHOTOS)
                                    <input type="file" name="new_photos[]" class="form-control" accept="image/*" multiple>
                                    <div class="fs-12 text-muted mt-1">{{ translate('You can add') }} {{ \Modules\PlaceManagement\Entities\Place::MAX_PHOTOS - count($place->photos ?? []) }} {{ translate('more') }}.</div>
                                @endif
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-1">{{ translate('Location') }}</h5>
                                <p class="fs-12 text-muted">{{ translate('Put the pin on the entrance: Ride there drops guests exactly here. Tap the map or drag the pin.') }}</p>
                                <div class="mb-3">
                                    <label class="form-label">{{ translate('Address') }} *</label>
                                    <input type="text" name="address" class="form-control" required maxlength="255" value="{{ old('address', $place->address) }}" placeholder="{{ translate('Ex: Up Station road, near the Governor office') }}">
                                </div>
                                @if($mapKey)
                                    <div id="place-map" class="rounded border mb-3" style="height: 300px"></div>
                                @endif
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ translate('Latitude') }} *</label>
                                        <input type="number" step="any" name="latitude" id="place-lat" class="form-control" required value="{{ $lat }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ translate('Longitude') }} *</label>
                                        <input type="number" step="any" name="longitude" id="place-lng" class="form-control" required value="{{ $lng }}">
                                    </div>
                                </div>
                                @if($place->exists && !$place->zone_id)
                                    <div class="alert alert-warning fs-12 mt-3 mb-0">{{ translate('This location is outside every active zone, so customers cannot book a ride there yet.') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-3">{{ translate('Status') }}</h5>
                                <label class="d-flex justify-content-between align-items-center form-control mb-2">
                                    <span>{{ translate('Active_(visible_in_the_app)') }}</span>
                                    <span class="switcher">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="switcher_input" type="checkbox" name="is_active" value="1" {{ old('is_active', $place->is_active) ? 'checked' : '' }}>
                                        <span class="switcher_control"></span>
                                    </span>
                                </label>
                                <label class="d-flex justify-content-between align-items-center form-control mb-2">
                                    <span>{{ translate('Featured_(shown_first)') }}</span>
                                    <span class="switcher">
                                        <input type="hidden" name="is_featured" value="0">
                                        <input class="switcher_input" type="checkbox" name="is_featured" value="1" {{ old('is_featured', $place->is_featured) ? 'checked' : '' }}>
                                        <span class="switcher_control"></span>
                                    </span>
                                </label>
                                <label class="form-label mt-2">{{ translate('Sort_order') }}</label>
                                <input type="number" name="sort" class="form-control" value="{{ old('sort', $place->sort ?? 0) }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('Lower numbers come first when distance is equal.') }}</div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-3">{{ translate('Contact') }}</h5>
                                <div class="mb-3">
                                    <label class="form-label">{{ translate('Phone') }}</label>
                                    <input type="tel" name="phone" class="form-control" maxlength="25" value="{{ old('phone', $place->phone) }}" placeholder="+237 6XX XXX XXX">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ translate('WhatsApp') }}</label>
                                    <input type="tel" name="whatsapp" class="form-control" maxlength="25" value="{{ old('whatsapp', $place->whatsapp) }}" placeholder="+237 6XX XXX XXX">
                                    <div class="fs-12 text-muted mt-1">{{ translate('Guests can message the hotel to ask for a room. Use the international format.') }}</div>
                                </div>
                                <div>
                                    <label class="form-label">{{ translate('Website') }}</label>
                                    <input type="url" name="website" class="form-control" maxlength="255" value="{{ old('website', $place->website) }}" placeholder="https://">
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-1">{{ translate('Google_listing') }}</h5>
                                <p class="fs-12 text-muted">{{ translate('When linked, the app shows this place once, as Verified, with its live Google rating. Link it from the Google results page.') }}</p>
                                <input type="text" name="google_place_id" class="form-control fs-12" maxlength="255" value="{{ old('google_place_id', $place->google_place_id) }}" placeholder="{{ translate('Not_linked') }}">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            @if($place->exists)
                                @can('place_delete')
                                    <button type="submit" form="delete-place" class="btn btn-outline-danger">{{ translate('Remove_listing') }}</button>
                                @endcan
                            @endif
                            <button type="submit" class="btn btn-primary">{{ $place->exists ? translate('Save') : translate('Create_listing') }}</button>
                        </div>
                    </div>
                </div>
            </form>
            @if($place->exists)
                <form id="delete-place" action="{{ route('admin.place.destroy', $place) }}" method="POST" onsubmit='return confirm(@json(translate("Remove this listing? Customers will no longer see it.")))'>
                    @csrf @method('DELETE')
                </form>
            @endif
        </div>
    </div>
@endsection

@if($mapKey)
    @push('script')
        <script src="https://maps.googleapis.com/maps/api/js?key={{ $mapKey }}"></script>
        <script>
            (function () {
                var lat = document.getElementById('place-lat'), lng = document.getElementById('place-lng');
                var start = {lat: parseFloat(lat.value) || 5.9597, lng: parseFloat(lng.value) || 10.1460};
                var map = new google.maps.Map(document.getElementById('place-map'), {center: start, zoom: 16, streetViewControl: false, mapTypeControl: false});
                var pin = new google.maps.Marker({position: start, map: map, draggable: true});
                function set(p) { lat.value = p.lat().toFixed(7); lng.value = p.lng().toFixed(7); }
                map.addListener('click', function (e) { pin.setPosition(e.latLng); set(e.latLng); });
                pin.addListener('dragend', function (e) { set(e.latLng); });
                function moveFromInputs() {
                    var p = {lat: parseFloat(lat.value), lng: parseFloat(lng.value)};
                    if (!isNaN(p.lat) && !isNaN(p.lng)) { pin.setPosition(p); map.panTo(p); }
                }
                lat.addEventListener('change', moveFromInputs); lng.addEventListener('change', moveFromInputs);
            })();
        </script>
    @endpush
@endif
