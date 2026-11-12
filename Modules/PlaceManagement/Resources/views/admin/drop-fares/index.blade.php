@extends('adminmodule::layouts.master')

@section('title', translate('Drop fares'))

@section('content')
    @php($hour = fn($h) => $h === null ? '' : str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="fs-22 mb-1">{{ translate('Drop fares') }}</h2>
                    <p class="fs-12 text-muted mb-0">{{ translate('A fixed price between two landmarks for one vehicle category, like a taxi drop. When a ride starts near one landmark and ends near the other, the rider sees Drop price and pays exactly that, VAT included: no per-km, waiting, delay or surge charges. A coupon still applies. Rides with stops, bids and moves use the normal fare, and so does a ride that ends away from the destination landmark.') }}</p>
                </div>
                @if(!$settings['drop_fares_enabled'])
                    <span class="badge bg-warning text-dark fs-12">{{ translate('Off: turn it on in Settings') }}</span>
                @endif
            </div>
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="row g-3">
                <div class="col-xl-8">
                    <div class="card mb-3"><div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle fs-13">
                                <thead class="table-light"><tr>
                                    <th>{{ translate('Route') }}</th><th>{{ translate('Vehicle') }}</th><th class="text-end">{{ translate('Price') }}</th>
                                    <th>{{ translate('Hours') }}</th><th class="text-center">{{ translate('Rides') }}</th><th class="text-end">{{ translate('Action') }}</th>
                                </tr></thead>
                                <tbody>
                                @forelse($routes as $r)
                                    <tr class="{{ $r->is_active ? '' : 'text-muted' }}">
                                        <td><b>{{ $r->from?->name }}</b> {{ $r->both_ways ? '⇄' : '→' }} <b>{{ $r->to?->name }}</b>
                                            <div class="fs-11 text-muted">{{ translate(key: 'Within {m} m of each landmark', replace: ['m' => $r->radius_m]) }}@if(!$r->is_active) · {{ translate('off') }}@endif</div></td>
                                        <td>{{ $r->vehicleCategory?->name }}</td>
                                        <td class="text-end text-nowrap">{{ set_currency_symbol($r->price) }}</td>
                                        <td>{{ $r->hour_from === null ? translate('All day') : $hour($r->hour_from) . ' - ' . $hour($r->hour_to) }}</td>
                                        <td class="text-center">{{ $r->uses }}</td>
                                        <td class="text-end text-nowrap">
                                            @can('fare_add')
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.drop-fare.index', ['edit' => $r->id]) }}">{{ translate('Edit') }}</a>
                                                <form class="d-inline" action="{{ route('admin.drop-fare.toggle', $r) }}" method="POST">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $r->is_active ? translate('Turn off') : translate('Turn on') }}</button></form>
                                                <form class="d-inline" action="{{ route('admin.drop-fare.destroy', $r) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this drop fare') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ translate('Delete') }}</button></form>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">{{ translate('No drop fares yet. Add landmarks first, then set a price between two of them.') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div></div>
                </div>

                <div class="col-xl-4">
                    @can('fare_add')
                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-3">{{ $edit ? translate('Edit drop fare') : translate('New drop fare') }}</h5>
                            @if($landmarks->count() < 2)
                                <p class="fs-12 text-muted mb-2">{{ translate('You need at least two landmarks.') }}</p>
                                <a class="btn btn-outline-primary w-100" href="{{ route('admin.landmark.index') }}">{{ translate('Go to Landmarks') }}</a>
                            @else
                                <form action="{{ $edit ? route('admin.drop-fare.update', $edit) : route('admin.drop-fare.store') }}" method="POST">@csrf
                                    @if($edit) @method('PUT') @endif
                                    @foreach(['from_landmark_id' => translate('From'), 'to_landmark_id' => translate('To')] as $field => $label)
                                        <div class="mb-2"><label class="form-label fs-12 mb-1">{{ $label }}</label>
                                            <select name="{{ $field }}" class="form-select form-select-sm" required>
                                                <option value="">{{ translate('Choose a landmark') }}</option>
                                                @foreach($landmarks as $l)
                                                    <option value="{{ $l->id }}" {{ old($field, $edit?->$field) === $l->id ? 'selected' : '' }}>{{ $l->name }}{{ $l->area ? ' (' . $l->area . ')' : '' }}</option>
                                                @endforeach
                                            </select></div>
                                    @endforeach
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="both-ways" name="both_ways" value="1" {{ old('both_ways', $edit?->both_ways ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label fs-12" for="both-ways">{{ translate('Same price both ways') }}</label>
                                    </div>
                                    <div class="mb-2"><label class="form-label fs-12 mb-1">{{ translate('Vehicle category') }}</label>
                                        <select name="vehicle_category_id" class="form-select form-select-sm" required>
                                            @foreach($categories as $c)
                                                <option value="{{ $c->id }}" {{ old('vehicle_category_id', $edit?->vehicle_category_id) === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                            @endforeach
                                        </select></div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6"><label class="form-label fs-12 mb-1">{{ translate('Price, VAT included') }}</label>
                                            <input type="number" step="any" min="1" name="price" class="form-control form-control-sm" required value="{{ old('price', $edit?->price) }}"></div>
                                        <div class="col-6"><label class="form-label fs-12 mb-1">{{ translate('Around each (metres)') }}</label>
                                            <input type="number" min="50" max="2000" name="radius_m" class="form-control form-control-sm" required value="{{ old('radius_m', $edit?->radius_m ?? $settings['drop_radius_m']) }}"></div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6"><label class="form-label fs-12 mb-1">{{ translate('From hour (optional)') }}</label>
                                            <input type="number" min="0" max="23" name="hour_from" class="form-control form-control-sm" value="{{ old('hour_from', $edit?->hour_from) }}" placeholder="6"></div>
                                        <div class="col-6"><label class="form-label fs-12 mb-1">{{ translate('Until hour (optional)') }}</label>
                                            <input type="number" min="0" max="23" name="hour_to" class="form-control form-control-sm" value="{{ old('hour_to', $edit?->hour_to) }}" placeholder="20"></div>
                                    </div>
                                    <button class="btn btn-primary w-100">{{ $edit ? translate('Save') : translate('Add drop fare') }}</button>
                                    @if($edit)<a class="btn btn-link w-100 mt-1" href="{{ route('admin.drop-fare.index') }}">{{ translate('Cancel') }}</a>@endif
                                </form>
                            @endif
                        </div></div>

                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-3">{{ translate('Settings') }}</h5>
                            <form action="{{ route('admin.drop-fare.settings') }}" method="POST">@csrf
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="drop_fares_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="drop-enabled" name="drop_fares_enabled" value="1" {{ $settings['drop_fares_enabled'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="drop-enabled">{{ translate('Drop fares are on') }}</label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="drop_fares_scheduled" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="drop-scheduled" name="drop_fares_scheduled" value="1" {{ $settings['drop_fares_scheduled'] ? 'checked' : '' }}>
                                    <label class="form-check-label fs-12" for="drop-scheduled">{{ translate('Scheduled rides get drop prices too') }}</label>
                                    <div class="fs-11 text-muted">{{ translate('A route with hours is judged at the pickup time, not when the ride is booked. Off: scheduled rides pay the normal fare.') }}</div>
                                </div>
                                <div class="mb-2"><label class="form-label fs-12 mb-1">{{ translate('Default distance around each landmark for new routes (metres)') }}</label>
                                    <input type="number" name="drop_fares_radius_m" min="50" max="2000" class="form-control form-control-sm" value="{{ $settings['drop_radius_m'] }}"></div>
                                <button class="btn btn-outline-primary w-100 mt-2">{{ translate('Save settings') }}</button>
                            </form>
                        </div></div>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection
