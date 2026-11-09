@extends('adminmodule::layouts.master')

@section('title', translate('At_the_place'))

@section('content')
    @php($listing = $verification->listing)
    <div class="main-content">
        <div class="container-fluid" style="max-width: 640px">
            <a href="{{ route('admin.listing.visits') }}" class="fs-12"><i class="bi bi-arrow-left"></i> {{ translate('Visits_to_do') }}</a>
            <h2 class="fs-20 my-2">{{ $listing->title }}</h2>
            <div class="fs-12 text-muted mb-3">{{ translate($listing->type) }} · {{ $listing->neighbourhood }} · {{ $listing->owner?->first_name }} {{ $listing->owner?->phone }}</div>

            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form action="{{ route('admin.listing.visit.submit', $verification) }}" method="POST" enctype="multipart/form-data" id="visit-form">
                @csrf
                <input type="hidden" name="latitude" id="lat" value="{{ old('latitude') }}">
                <input type="hidden" name="longitude" id="lng" value="{{ old('longitude') }}">

                <div class="card mb-3"><div class="card-body">
                    <h5 class="mb-2">1. {{ translate('Your_location') }}</h5>
                    <p class="fs-12 text-muted mb-2">{{ translate('You must be within') }} {{ $radius }} m {{ translate('of_the_pin') }}.</p>
                    <div id="loc-status" class="fs-14 fw-semibold">{{ translate('Finding_your_location') }}...</div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="locate()">{{ translate('Try_again') }}</button>
                </div></div>

                <div class="card mb-3"><div class="card-body">
                    <h5 class="mb-3">2. {{ translate('Checklist') }}</h5>
                    @foreach([
                        'at_pin' => translate('The place is at the pin on the map'),
                        'owner_id' => translate('Owner or caretaker present, ID matches the account (or a written authorisation)'),
                        'units' => translate('Rooms, type and number free match the listing'),
                        'water' => translate('Water runs as listed'),
                        'light' => translate('Light works, meter as listed'),
                        'toilet_kitchen' => translate('Toilet and kitchen as listed'),
                        'price' => translate('Rent, advance and caution confirmed with the owner'),
                    ] as $key => $label)
                        <label class="d-flex gap-2 align-items-start mb-2 fs-14">
                            <input type="checkbox" name="checks[{{ $key }}]" value="1" class="mt-1" {{ old('checks.' . $key) ? 'checked' : '' }}> <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div></div>

                <div class="card mb-3"><div class="card-body">
                    <h5 class="mb-2">3. {{ translate('Photos_on_site') }}</h5>
                    <p class="fs-12 text-muted">{{ translate('Take them now with the camera: rooms, toilet, kitchen, outside. They are marked Photo by E-Ride in the app.') }}</p>
                    <input type="file" name="photos[]" accept="image/*" capture="environment" multiple class="form-control">
                </div></div>

                <div class="card mb-3"><div class="card-body">
                    <h5 class="mb-3">4. {{ translate('Result') }}</h5>
                    <label class="d-block mb-2 fs-14"><input type="radio" name="result" value="pass" {{ old('result', 'pass') === 'pass' ? 'checked' : '' }}> <b>{{ translate('Pass') }}</b>: {{ translate('every check is true') }}</label>
                    <label class="d-block mb-2 fs-14"><input type="radio" name="result" value="fixable" {{ old('result') === 'fixable' ? 'checked' : '' }}> <b>{{ translate('Things_to_correct') }}</b>: {{ translate('the owner fixes the listing, it stays Listed') }}</label>
                    <label class="d-block mb-3 fs-14"><input type="radio" name="result" value="fake" {{ old('result') === 'fake' ? 'checked' : '' }}> <b>{{ translate('Fake_or_misleading') }}</b>: {{ translate('removed, owner blocked') }}</label>
                    <textarea name="note" class="form-control" rows="3" maxlength="500" placeholder="{{ translate('Note_for_the_owner_(needed_unless_it_passes)') }}">{{ old('note') }}</textarea>
                </div></div>

                <button type="submit" class="btn btn-primary w-100 mb-5" id="submit-btn">{{ translate('Send_the_result') }}</button>
            </form>
        </div>
    </div>
@endsection

@push('script')
    <script>
        const pin = {lat: {{ $listing->latitude }}, lng: {{ $listing->longitude }}}, radius = {{ $radius }};
        function metres(a, b) {
            const r = 6371000, t = Math.PI / 180, dLat = (b.lat - a.lat) * t, dLng = (b.lng - a.lng) * t;
            const h = Math.sin(dLat / 2) ** 2 + Math.cos(a.lat * t) * Math.cos(b.lat * t) * Math.sin(dLng / 2) ** 2;
            return Math.round(2 * r * Math.asin(Math.sqrt(h)));
        }
        function locate() {
            const status = document.getElementById('loc-status');
            if (!navigator.geolocation) { status.textContent = '{{ translate('This_browser_cannot_share_its_location') }}'; return; }
            navigator.geolocation.getCurrentPosition(p => {
                const here = {lat: p.coords.latitude, lng: p.coords.longitude}, d = metres(here, pin);
                document.getElementById('lat').value = here.lat;
                document.getElementById('lng').value = here.lng;
                status.textContent = d + ' m {{ translate('from_the_pin') }}' + (d <= radius ? ' ✓' : ' : {{ translate('go_closer') }}');
                status.className = 'fs-14 fw-semibold ' + (d <= radius ? 'text-success' : 'text-danger');
            }, () => { status.textContent = '{{ translate('Allow_location_for_this_page,_then_Try_again') }}'; status.className = 'fs-14 fw-semibold text-danger'; },
                {enableHighAccuracy: true, timeout: 15000, maximumAge: 0});
        }
        locate();
    </script>
@endpush
