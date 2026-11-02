@extends('adminmodule::layouts.master')

@section('title', $store->exists ? $store->name : translate('Add_store'))

@section('content')
    @php($mapKey = businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null)
    @php($lat = old('latitude', $store->latitude ?? 5.9631))
    @php($lng = old('longitude', $store->longitude ?? 10.1591))
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ route('admin.store.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                <h2 class="fs-22 mb-0">{{ $store->exists ? $store->name : translate('Add_store') }}</h2>
            </div>
            @include('storemanagement::admin.stores._tabs', ['active' => 'details'])

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form action="{{ $store->exists ? route('admin.store.update', $store) : route('admin.store.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if($store->exists) @method('PUT') @endif
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-3">{{ translate('Store') }}</h5>
                                <div class="row g-3">
                                    <div class="col-sm-8">
                                        <label class="form-label">{{ translate('Name') }} *</label>
                                        <input type="text" name="name" class="form-control" required maxlength="120" value="{{ old('name', $store->name) }}" placeholder="{{ translate('Ex: ROMZ Restaurant') }}">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label">{{ translate('Type') }}</label>
                                        <select name="type" class="form-select">
                                            @foreach(\Modules\StoreManagement\Entities\Store::TYPES as $type)
                                                <option value="{{ $type }}" {{ old('type', $store->type) === $type ? 'selected' : '' }}>{{ translate($type) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('Phone') }} *</label>
                                        <input type="tel" name="phone" class="form-control" required maxlength="25" value="{{ old('phone', $store->phone) }}" placeholder="+237 6XX XXX XXX">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('WhatsApp') }}</label>
                                        <input type="tel" name="whatsapp" class="form-control" maxlength="25" value="{{ old('whatsapp', $store->whatsapp) }}">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">{{ translate('Short_description') }}</label>
                                        <textarea name="description" class="form-control" rows="2" maxlength="1000" placeholder="{{ translate('Ex: Grilled fish, jollof and fresh juices') }}">{{ old('description', $store->description) }}</textarea>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('Usual_preparation_time_(minutes)') }} *</label>
                                        <input type="number" name="default_prep_minutes" class="form-control" required min="5" max="180" value="{{ old('default_prep_minutes', $store->default_prep_minutes ?? 20) }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('Minimum_order_(FCFA)') }}</label>
                                        <input type="number" name="min_order_amount" class="form-control" min="0" step="1" value="{{ old('min_order_amount', $store->min_order_amount ?? 0) }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('Logo') }}</label>
                                        <input type="file" name="logo" class="form-control" accept="image/*">
                                        @if($store->logoUrl())<img src="{{ $store->logoUrl() }}" class="mt-2 rounded" height="48" alt="">@endif
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('Cover_photo') }}</label>
                                        <input type="file" name="cover" class="form-control" accept="image/*">
                                        @if($store->coverUrl())<img src="{{ $store->coverUrl() }}" class="mt-2 rounded" height="48" alt="">@endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-1">{{ translate('Pickup_location') }}</h5>
                                <p class="fs-12 text-muted">{{ translate('Where riders collect orders. Tap the map to move the pin. It must be inside an active zone.') }}</p>
                                <div class="mb-3">
                                    <label class="form-label">{{ translate('Address') }} *</label>
                                    <input type="text" name="address" class="form-control" required maxlength="255" value="{{ old('address', $store->address) }}" placeholder="{{ translate('Ex: Commercial Avenue, opposite the main market') }}">
                                </div>
                                @if($mapKey)
                                    <div id="store-map" class="rounded border mb-3" style="height: 300px"></div>
                                @endif
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">{{ translate('Latitude') }} *</label>
                                        <input type="number" step="any" name="latitude" id="store-lat" class="form-control" required value="{{ $lat }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ translate('Longitude') }} *</label>
                                        <input type="number" step="any" name="longitude" id="store-lng" class="form-control" required value="{{ $lng }}">
                                    </div>
                                </div>
                                @if($store->exists && !$store->zone_id)
                                    <div class="alert alert-warning fs-12 mt-3 mb-0">{{ translate('This location is outside every active zone, so customers cannot order from this store yet.') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-2">{{ translate('Opening_hours') }}</h5>
                                @include('storemanagement::shared._hours')
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
                                        <input class="switcher_input" type="checkbox" name="is_active" value="1" {{ old('is_active', $store->is_active) ? 'checked' : '' }}>
                                        <span class="switcher_control"></span>
                                    </span>
                                </label>
                                <label class="d-flex justify-content-between align-items-center form-control">
                                    <span>{{ translate('Open_for_orders') }}</span>
                                    <span class="switcher">
                                        <input type="hidden" name="is_open" value="0">
                                        <input class="switcher_input" type="checkbox" name="is_open" value="1" {{ old('is_open', $store->is_open ?? true) ? 'checked' : '' }}>
                                        <span class="switcher_control"></span>
                                    </span>
                                </label>
                                <p class="fs-12 text-muted mt-2 mb-0">{{ translate('The partner can also switch the store open or closed from their portal.') }}</p>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-1">{{ translate('Partner_login') }}</h5>
                                <p class="fs-12 text-muted">{{ translate('The owner signs in at') }} <span class="text-dark">{{ route('partner.login') }}</span></p>
                                <div class="mb-3">
                                    <label class="form-label">{{ translate('Owner_name') }} *</label>
                                    <input type="text" name="owner_name" class="form-control" required maxlength="120" value="{{ old('owner_name', $store->owner?->first_name) }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ translate('Login_email') }} *</label>
                                    <input type="email" name="owner_email" class="form-control" required maxlength="191" autocomplete="off" value="{{ old('owner_email', $store->owner?->email) }}">
                                </div>
                                <div>
                                    <label class="form-label">{{ $store->exists ? translate('New_password') : translate('Password') }} {{ $store->exists ? '' : '*' }}</label>
                                    <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" {{ $store->exists ? '' : 'required' }} placeholder="{{ $store->exists ? translate('Leave blank to keep the current password') : translate('At least 8 characters') }}">
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-1">{{ translate('Payouts') }}</h5>
                                <p class="fs-12 text-muted">{{ translate('Where E-Ride sends the money for delivered orders.') }}</p>
                                <div class="mb-3">
                                    <label class="form-label">{{ translate('Method') }}</label>
                                    <select name="payout_method" class="form-select">
                                        @foreach(['' => translate('Not_set'), 'mtn_momo' => 'MTN MoMo', 'orange_money' => 'Orange Money', 'bank' => translate('Bank'), 'cash' => translate('Cash')] as $value => $label)
                                            <option value="{{ $value }}" {{ old('payout_method', $store->payout_method) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ translate('Number_or_account') }}</label>
                                    <input type="text" name="payout_number" class="form-control" maxlength="40" value="{{ old('payout_number', $store->payout_number) }}">
                                </div>
                                <div>
                                    <label class="form-label">{{ translate('Account_name') }}</label>
                                    <input type="text" name="payout_name" class="form-control" maxlength="120" value="{{ old('payout_name', $store->payout_name) }}">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            @if($store->exists)
                                @can('store_delete')
                                    <button type="submit" form="delete-store" class="btn btn-outline-danger">{{ translate('Remove_store') }}</button>
                                @endcan
                            @endif
                            <button type="submit" class="btn btn-primary">{{ $store->exists ? translate('Save') : translate('Create_store') }}</button>
                        </div>
                    </div>
                </div>
            </form>
            @if($store->exists)
                <form id="delete-store" action="{{ route('admin.store.destroy', $store) }}" method="POST" onsubmit='return confirm(@json(translate("Remove this store? Customers will no longer see it.")))'>
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
                var lat = document.getElementById('store-lat'), lng = document.getElementById('store-lng');
                var start = {lat: parseFloat(lat.value) || 5.9631, lng: parseFloat(lng.value) || 10.1591};
                var map = new google.maps.Map(document.getElementById('store-map'), {center: start, zoom: 15, streetViewControl: false, mapTypeControl: false});
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
