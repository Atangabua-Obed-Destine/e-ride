@extends('adminmodule::layouts.master')

@section('title', translate('Landmarks'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="fs-22 mb-1">{{ translate('Landmarks') }}</h2>
                    <p class="fs-12 text-muted mb-0">{{ translate('The places Bamenda people give directions by. In the app place search they come first, before Google, and the most used ones show before anything is typed. Drop fares are set between landmarks.') }}</p>
                </div>
                @if(!$settings['landmarks_enabled'])
                    <span class="badge bg-warning text-dark fs-12">{{ translate('Off: turn it on in Settings') }}</span>
                @endif
            </div>
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="row g-3">
                <div class="col-xl-8">
                    <div class="card mb-3"><div class="card-body p-0">
                        <div id="landmark-map" style="height: 380px; border-radius: 8px"></div>
                    </div></div>
                    <p class="fs-12 text-muted">{{ translate('Click the map to place the pin of a new landmark, or drag the pin of the one you are editing.') }}</p>

                    <div class="card mb-3"><div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <ul class="nav nav--tabs p-1 rounded bg-white">
                                <li class="nav-item"><a class="nav-link {{ request('show') !== 'hidden' ? 'active' : '' }}" href="{{ route('admin.landmark.index') }}">{{ translate('In the search') }} ({{ $counts['active'] }})</a></li>
                                <li class="nav-item"><a class="nav-link {{ request('show') === 'hidden' ? 'active' : '' }}" href="{{ route('admin.landmark.index', ['show' => 'hidden']) }}">{{ translate('Hidden') }} ({{ $counts['hidden'] }})</a></li>
                            </ul>
                            <form class="d-flex gap-2" method="GET">
                                @if(request('show'))<input type="hidden" name="show" value="{{ request('show') }}">@endif
                                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="{{ translate('Search a name') }}">
                                <button class="btn btn-sm btn-primary">{{ translate('Search') }}</button>
                            </form>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle fs-13">
                                <thead class="table-light"><tr><th>{{ translate('Name') }}</th><th>{{ translate('Other names') }}</th><th>{{ translate('Area') }}</th><th class="text-center">{{ translate('Chosen') }}</th><th class="text-end">{{ translate('Action') }}</th></tr></thead>
                                <tbody>
                                @forelse($list as $l)
                                    <tr>
                                        <td><b>{{ $l->name }}</b>@if($l->place_id)<div class="fs-11 text-muted">{{ translate('From Explore Bamenda') }}</div>@endif</td>
                                        <td class="text-muted">{{ implode(', ', $l->aliasList()) ?: '-' }}</td>
                                        <td>{{ $l->area ?: '-' }}</td>
                                        <td class="text-center">{{ $l->uses }}</td>
                                        <td class="text-end text-nowrap">
                                            @can('place_edit')
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.landmark.index', ['edit' => $l->id] + request()->only('show', 'q', 'page')) }}">{{ translate('Edit') }}</a>
                                                <form class="d-inline" action="{{ route('admin.landmark.toggle', $l) }}" method="POST">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $l->is_active ? translate('Hide') : translate('Show') }}</button></form>
                                                <form class="d-inline" action="{{ route('admin.landmark.destroy', $l) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this landmark') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ translate('Delete') }}</button></form>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No landmarks yet. Add one with the form, or bring in the Explore Bamenda places.') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        {{ $list->links() }}
                    </div></div>
                </div>

                <div class="col-xl-4">
                    @can('place_edit')
                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-3">{{ $edit ? translate('Edit landmark') : translate('New landmark') }}</h5>
                            <form action="{{ $edit ? route('admin.landmark.update', $edit) : route('admin.landmark.store') }}" method="POST">@csrf
                                @if($edit) @method('PUT') @endif
                                <div class="mb-2"><label class="form-label fs-12 mb-1">{{ translate('Name') }}</label>
                                    <input type="text" name="name" class="form-control form-control-sm" required maxlength="120" value="{{ old('name', $edit?->name) }}" placeholder="{{ translate('Hospital Roundabout') }}"></div>
                                <div class="mb-2"><label class="form-label fs-12 mb-1">{{ translate('Other names people use, one per line') }}</label>
                                    <textarea name="aliases" rows="3" class="form-control form-control-sm" maxlength="2000" placeholder="Hosp. Roundabout&#10;Hopital">{{ old('aliases', $edit?->aliases) }}</textarea></div>
                                <div class="mb-2"><label class="form-label fs-12 mb-1">{{ translate('Area (optional)') }}</label>
                                    <input type="text" name="area" class="form-control form-control-sm" maxlength="120" value="{{ old('area', $edit?->area) }}" placeholder="Nkwen"></div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6"><label class="form-label fs-12 mb-1">{{ translate('Latitude') }}</label>
                                        <input type="text" id="lm-lat" name="latitude" class="form-control form-control-sm" required value="{{ old('latitude', $edit?->latitude) }}"></div>
                                    <div class="col-6"><label class="form-label fs-12 mb-1">{{ translate('Longitude') }}</label>
                                        <input type="text" id="lm-lng" name="longitude" class="form-control form-control-sm" required value="{{ old('longitude', $edit?->longitude) }}"></div>
                                </div>
                                <button class="btn btn-primary w-100">{{ $edit ? translate('Save') : translate('Add landmark') }}</button>
                                @if($edit)<a class="btn btn-link w-100 mt-1" href="{{ route('admin.landmark.index') }}">{{ translate('Cancel') }}</a>@endif
                            </form>
                        </div></div>

                        @if($edit && $mergeTargets->isNotEmpty())
                            <div class="card mb-3"><div class="card-body">
                                <h5 class="mb-1">{{ translate('Merge a duplicate') }}</h5>
                                <p class="fs-12 text-muted">{{ translate(key: 'Merge {name} into the landmark to keep. Its names become other names of the kept one, the counts add up, and its drop fares move over. {name} is then deleted.', replace: ['name' => $edit->name]) }}</p>
                                <form action="{{ route('admin.landmark.merge', $edit) }}" method="POST" onsubmit="return confirm('{{ translate('Merge and delete this landmark') }}')">@csrf
                                    <select name="into" class="form-select form-select-sm mb-2" required>
                                        <option value="">{{ translate('Landmark to keep') }}</option>
                                        @foreach($mergeTargets as $t)
                                            <option value="{{ $t->id }}">{{ $t->name }}{{ $t->area ? ' (' . $t->area . ')' : '' }}{{ $t->is_active ? '' : ' - ' . translate('hidden') }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-outline-danger w-100">{{ translate('Merge into it') }}</button>
                                </form>
                            </div></div>
                        @endif

                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-1">{{ translate('Explore Bamenda places') }}</h5>
                            <p class="fs-12 text-muted">{{ translate('Add every verified Explore Bamenda place as a landmark. Places already added are skipped.') }}</p>
                            <form action="{{ route('admin.landmark.import') }}" method="POST">@csrf<button class="btn btn-outline-primary w-100">{{ translate('Add them as landmarks') }}</button></form>
                        </div></div>

                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-3">{{ translate('Settings') }}</h5>
                            <form action="{{ route('admin.landmark.settings') }}" method="POST">@csrf
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="landmarks_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="landmarks-enabled" name="landmarks_enabled" value="1" {{ $settings['landmarks_enabled'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="landmarks-enabled">{{ translate('Landmarks show in the app search') }}</label>
                                </div>
                                <div class="mb-2"><label class="form-label fs-12 mb-1">{{ translate('Landmarks shown while typing, before Google') }}</label>
                                    <input type="number" name="landmarks_search_results" min="1" max="8" class="form-control form-control-sm" value="{{ $settings['search_results'] }}"></div>
                                <div class="mb-2"><label class="form-label fs-12 mb-1">{{ translate('Most used landmarks shown before anything is typed') }}</label>
                                    <input type="number" name="landmarks_popular_results" min="0" max="12" class="form-control form-control-sm" value="{{ $settings['popular_results'] }}"></div>
                                <button class="btn btn-outline-primary w-100 mt-2">{{ translate('Save settings') }}</button>
                            </form>
                            <p class="fs-12 text-muted mt-2 mb-0">{{ translate('Riders always see their own saved places first.') }}</p>
                        </div></div>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    @php($map_key = businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null)
    <script>
        "use strict";
        function initLandmarkMap() {
            const points = @json($mapPoints);
            const editing = @json($edit ? ['id' => $edit->id, 'lat' => $edit->latitude, 'lng' => $edit->longitude] : null);
            const lat = document.getElementById('lm-lat'), lng = document.getElementById('lm-lng');
            const center = editing ? {lat: editing.lat, lng: editing.lng} : (points.length ? {lat: points[0].lat, lng: points[0].lng} : {lat: 5.9631, lng: 10.1591});
            const map = new google.maps.Map(document.getElementById('landmark-map'), {center, zoom: editing ? 16 : 13, streetViewControl: false, mapTypeControl: false});
            points.filter(p => !editing || p.id !== editing.id).forEach(p => new google.maps.Marker({
                position: {lat: p.lat, lng: p.lng}, map, title: p.name,
                icon: {path: google.maps.SymbolPath.CIRCLE, scale: 6, fillColor: '#dc3545', fillOpacity: 0.9, strokeColor: '#fff', strokeWeight: 2},
            }));
            let pin = null;
            const place = (pos) => {
                if (!lat || !lng) return;
                if (!pin) {
                    pin = new google.maps.Marker({position: pos, map, draggable: true});
                    pin.addListener('dragend', e => place(e.latLng));
                }
                pin.setPosition(pos);
                lat.value = (typeof pos.lat === 'function' ? pos.lat() : pos.lat).toFixed(7);
                lng.value = (typeof pos.lng === 'function' ? pos.lng() : pos.lng).toFixed(7);
            };
            if (editing) place({lat: editing.lat, lng: editing.lng});
            map.addListener('click', e => place(e.latLng));
        }
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{ $map_key }}&callback=initLandmarkMap" async defer></script>
@endpush
