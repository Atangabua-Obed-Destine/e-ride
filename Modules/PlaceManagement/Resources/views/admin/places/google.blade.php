@extends('adminmodule::layouts.master')

@section('title', translate('Google_results'))

@section('content')
    @php($mapKey = businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null)
    <div class="main-content">
        <div class="container-fluid">
            <div class="mb-3">
                <h2 class="fs-22 mb-1">{{ translate('Hotels_&_Stays') }}</h2>
                <p class="fs-12 text-muted mb-0">{{ translate('What customers see from Google around a point. Hide results that are closed or are not places to stay, and turn the good ones into verified listings.') }}</p>
            </div>

            @include('placemanagement::admin.places._nav', ['active' => 'google'])

            <div class="card mb-3">
                <div class="card-body">
                    <form action="{{ route('admin.place.google') }}" method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="load" value="1">
                        @if($mapKey)
                            <div class="col-12">
                                <div id="preview-map" class="rounded border" style="height: 260px"></div>
                                <div class="fs-12 text-muted mt-1">{{ translate('Tap the map to choose where the customer is.') }}</div>
                            </div>
                        @endif
                        <div class="col-sm-4">
                            <label class="form-label">{{ translate('Latitude') }}</label>
                            <input type="number" step="any" name="latitude" id="preview-lat" class="form-control" value="{{ $lat }}">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">{{ translate('Longitude') }}</label>
                            <input type="number" step="any" name="longitude" id="preview-lng" class="form-control" value="{{ $lng }}">
                        </div>
                        <div class="col-sm-4">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> {{ translate('Load_Google_results') }}</button>
                        </div>
                        <div class="col-12 fs-12 text-muted">
                            {{ translate('Within') }} {{ rtrim(rtrim(number_format($settings['radius_km'], 1), '0'), '.') }} km.
                            {{ translate('Each load is one Google search and counts toward the monthly limit.') }}
                            {{ translate('This month') }}: {{ number_format($usage['search']) }} / {{ number_format($settings['search_cap']) }} {{ translate('searches') }}.
                        </div>
                    </form>
                </div>
            </div>

            @if($results === null)
                <div class="alert alert-warning">
                    {{ translate('Google results are unavailable: the monthly search limit is reached, the server map key is missing, or Google refused the request. Check Settings and the server log.') }}
                </div>
            @elseif(is_array($results))
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="mb-3">{{ count($results) }} {{ translate('results,_nearest_first') }}</h5>
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle">
                                <thead class="table-light align-middle">
                                <tr>
                                    <th>{{ translate('Place') }}</th>
                                    <th>{{ translate('Rating') }}</th>
                                    <th>{{ translate('Distance') }}</th>
                                    <th>{{ translate('In_the_app') }}</th>
                                    <th class="text-end">{{ translate('action') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($results as $r)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">
                                                @if($r['maps_url'])<a href="{{ $r['maps_url'] }}" target="_blank" rel="noopener">{{ $r['name'] }}</a>@else{{ $r['name'] }}@endif
                                            </div>
                                            <div class="fs-12 text-muted">
                                                {{ translate($r['category']) }}
                                                @if($r['phone']) · {{ $r['phone'] }} @else · <span class="text-warning">{{ translate('no_phone') }}</span> @endif                                            </div>
                                            <div class="fs-12 text-muted">{{ $r['address'] }}</div>
                                        </td>
                                        <td class="text-nowrap">
                                            @if($r['rating'] !== null)
                                                <i class="bi bi-star-fill text-warning"></i> {{ number_format($r['rating'], 1) }}
                                                <span class="text-muted fs-12">({{ $r['reviews'] }})</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">{{ $r['distance_km'] !== null ? $r['distance_km'] . ' km' : '-' }}</td>
                                        <td>
                                            @switch($r['status'])
                                                @case('linked')
                                                    <span class="badge bg-success">{{ translate('Verified_listing') }}</span>
                                                    <div class="fs-12"><a href="{{ route('admin.place.edit', $r['linked_place']->id) }}">{{ $r['linked_place']->name }}</a></div>
                                                    @break
                                                @case('hidden')
                                                    <span class="badge bg-secondary">{{ translate('Hidden') }}</span>
                                                    @if($r['hidden_reason'])<div class="fs-12 text-muted">{{ $r['hidden_reason'] }}</div>@endif
                                                    @break
                                                @case('closed')
                                                    <span class="badge bg-dark">{{ translate('Closed_on_Google') }}</span>
                                                    @break
                                                @default
                                                    <span class="badge bg-info">{{ translate('Shown') }}</span>
                                                    @if($r['in_top_rated'])<div class="fs-12 text-muted">{{ translate('Can_appear_in_Top_rated') }}</div>@endif
                                            @endswitch
                                        </td>
                                        <td class="text-end">
                                            @can('place_edit')
                                                <div class="d-flex flex-wrap justify-content-end gap-2">
                                                    @if($r['status'] === 'hidden')
                                                        <form action="{{ route('admin.place.google.unhide') }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="google_place_id" value="{{ $r['google_place_id'] }}">
                                                            <button class="btn btn-sm btn-outline-secondary">{{ translate('Show_again') }}</button>
                                                        </form>
                                                    @elseif($r['status'] === 'shown')
                                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#hide-{{ $loop->index }}">{{ translate('Hide') }}</button>
                                                    @endif
                                                    @if($r['status'] !== 'linked')
                                                        @can('place_add')
                                                            <a class="btn btn-sm btn-primary" href="{{ route('admin.place.create', [
                                                                'google_place_id' => $r['google_place_id'], 'name' => $r['name'], 'category' => $r['category'],
                                                                'address' => $r['address'], 'latitude' => $r['latitude'], 'longitude' => $r['longitude'],
                                                                'phone' => $r['phone'], 'website' => $r['website'],
                                                            ]) }}">{{ translate('Make_a_verified_listing') }}</a>
                                                        @endcan
                                                    @endif
                                                </div>
                                                @if($r['status'] === 'shown')
                                                    <form id="hide-{{ $loop->index }}" class="collapse mt-2" action="{{ route('admin.place.google.hide') }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="google_place_id" value="{{ $r['google_place_id'] }}">
                                                        <input type="hidden" name="label" value="{{ $r['name'] }}">
                                                        <div class="input-group input-group-sm">
                                                            <select name="reason" class="form-select">
                                                                <option value="{{ translate('Not a place to stay') }}">{{ translate('Not a place to stay') }}</option>
                                                                <option value="{{ translate('Closed') }}">{{ translate('Closed') }}</option>
                                                                <option value="{{ translate('Wrong location') }}">{{ translate('Wrong location') }}</option>
                                                                <option value="{{ translate('Duplicate') }}">{{ translate('Duplicate') }}</option>
                                                                <option value="{{ translate('Unsafe or bad reports') }}">{{ translate('Unsafe or bad reports') }}</option>
                                                            </select>
                                                            <button class="btn btn-danger">{{ translate('Hide') }}</button>
                                                        </div>
                                                    </form>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <h5 class="mb-1">{{ translate('Hidden_Google_places') }}</h5>
                    <p class="fs-12 text-muted">{{ translate('Never shown in the app, wherever the customer is.') }}</p>
                    @forelse($hidden as $h)
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom py-2">
                            <div>
                                <div class="fw-semibold">{{ $h->label ?? $h->google_place_id }}</div>
                                <div class="fs-12 text-muted">{{ $h->reason }} · {{ $h->author?->first_name }} · {{ $h->created_at?->format('d M Y') }}</div>
                            </div>
                            @can('place_edit')
                                <form action="{{ route('admin.place.google.unhide') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="google_place_id" value="{{ $h->google_place_id }}">
                                    <button class="btn btn-sm btn-outline-secondary">{{ translate('Show_again') }}</button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ translate('Nothing hidden.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@if($mapKey)
    @push('script')
        <script src="https://maps.googleapis.com/maps/api/js?key={{ $mapKey }}"></script>
        <script>
            (function () {
                var lat = document.getElementById('preview-lat'), lng = document.getElementById('preview-lng');
                var start = {lat: parseFloat(lat.value) || 5.9597, lng: parseFloat(lng.value) || 10.1460};
                var map = new google.maps.Map(document.getElementById('preview-map'), {center: start, zoom: 13, streetViewControl: false, mapTypeControl: false});
                var pin = new google.maps.Marker({position: start, map: map, draggable: true});
                var circle = new google.maps.Circle({map: map, center: start, radius: {{ (float)$settings['radius_km'] * 1000 }}, strokeColor: '#C8102E', strokeOpacity: 0.6, strokeWeight: 1, fillColor: '#C8102E', fillOpacity: 0.06, clickable: false});
                function set(p) { lat.value = p.lat().toFixed(7); lng.value = p.lng().toFixed(7); circle.setCenter(p); }
                map.addListener('click', function (e) { pin.setPosition(e.latLng); set(e.latLng); });
                pin.addListener('dragend', function (e) { set(e.latLng); });
                @if(is_array($results))
                    @foreach($results as $r)
                        @if($r['latitude'] !== null)
                            new google.maps.Marker({position: {lat: {{ $r['latitude'] }}, lng: {{ $r['longitude'] }}}, map: map, title: @json($r['name']),
                                icon: {path: google.maps.SymbolPath.CIRCLE, scale: 6, fillColor: @json(['linked' => '#12A150', 'hidden' => '#888888', 'closed' => '#222222'][$r['status']] ?? '#1A73E8'), fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2}});
                        @endif
                    @endforeach
                @endif
            })();
        </script>
    @endpush
@endif
