@extends('adminmodule::layouts.master')

@section('title', translate('Explore_categories'))

@section('content')
    @php($iconMap = ['pharmacy' => 'capsule', 'hospital' => 'hospital', 'bank' => 'bank', 'bus' => 'bus-front', 'bed' => 'building', 'store' => 'shop', 'fuel' => 'fuel-pump', 'police' => 'shield-check', 'school' => 'mortarboard', 'church' => 'house-heart', 'restaurant' => 'cup-hot', 'atm' => 'credit-card', 'money' => 'cash-coin', 'market' => 'basket', 'place' => 'geo-alt'])
    <div class="main-content">
        <div class="container-fluid">
            <div class="mb-3">
                <h2 class="fs-22 mb-1">{{ translate('Explore_Bamenda') }}</h2>
                <p class="fs-12 text-muted mb-0">{{ translate('The categories customers can explore. Each one lists your verified places first, then nearby places from Google of the types below.') }}</p>
            </div>

            @include('placemanagement::admin.places._nav', ['active' => 'categories'])

            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>{{ translate('Category') }}</th>
                                <th>{{ translate('Google_place_types') }}</th>
                                <th class="text-center">{{ translate('Verified') }}</th>
                                <th class="text-center">{{ translate('Price_per_night') }}</th>
                                <th class="text-center">{{ translate('Active') }}</th>
                                <th class="text-center">{{ translate('Order') }}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($categories as $c)
                                <tr>
                                    <td colspan="7" class="p-0">
                                        <form action="{{ route('admin.place.categories.update', $c) }}" method="POST" class="d-flex flex-wrap align-items-center gap-2 p-2 border-bottom">
                                            @csrf @method('PUT')
                                            <span class="d-inline-flex align-items-center justify-content-center rounded bg-light" style="width:36px;height:36px"><i class="bi bi-{{ $iconMap[$c->icon] ?? 'geo-alt' }} fs-5"></i></span>
                                            <div style="min-width: 190px">
                                                <input type="text" name="name" class="form-control form-control-sm mb-1" value="{{ $c->name }}" required maxlength="80" @cannot('place_edit') disabled @endcannot>
                                                <input type="text" name="name_fr" class="form-control form-control-sm" value="{{ $c->name_fr }}" maxlength="80" placeholder="{{ translate('French_name') }}" @cannot('place_edit') disabled @endcannot>
                                            </div>
                                            <select name="icon" class="form-select form-select-sm" style="width: 120px" @cannot('place_edit') disabled @endcannot>
                                                @foreach(\Modules\PlaceManagement\Entities\PlaceCategory::ICONS as $icon)
                                                    <option value="{{ $icon }}" {{ $c->icon === $icon ? 'selected' : '' }}>{{ $icon }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" name="google_types" class="form-control form-control-sm flex-grow-1" style="min-width: 200px" value="{{ implode(', ', $c->google_types ?? []) }}" @cannot('place_edit') disabled @endcannot>
                                            <span class="badge bg-light text-dark" title="{{ translate('Verified_listings') }}">{{ $counts[$c->key] ?? 0 }}</span>
                                            <label class="d-flex align-items-center gap-1 fs-12"><input type="hidden" name="has_price" value="0"><input type="checkbox" name="has_price" value="1" {{ $c->has_price ? 'checked' : '' }}> {{ translate('Price') }}</label>
                                            <label class="d-flex align-items-center gap-1 fs-12"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" {{ $c->is_active ? 'checked' : '' }}> {{ translate('Active') }}</label>
                                            <input type="number" name="sort" class="form-control form-control-sm" style="width: 70px" value="{{ $c->sort }}">
                                            @can('place_edit')<button class="btn btn-sm btn-primary">{{ translate('Save') }}</button>@endcan
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="fs-12 text-muted mt-2 mb-0">
                        {{ translate('Google place types must be from Google list of place types (Table A), for example pharmacy, hospital, doctor, bank, atm, bus_station, gas_station, police, school, supermarket. Load Google results for a category to check it returns what you expect.') }}
                    </p>
                </div>
            </div>

            @can('place_edit')
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">{{ translate('Add_a_category') }}</h5>
                        <form action="{{ route('admin.place.categories.store') }}" method="POST" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-sm-3"><label class="form-label">{{ translate('Name') }} *</label><input type="text" name="name" class="form-control" required maxlength="80" placeholder="{{ translate('Ex: Petrol stations') }}"></div>
                            <div class="col-sm-3"><label class="form-label">{{ translate('French_name') }}</label><input type="text" name="name_fr" class="form-control" maxlength="80" placeholder="{{ translate('Ex: Stations-service') }}"></div>
                            <div class="col-sm-2"><label class="form-label">{{ translate('Icon') }}</label>
                                <select name="icon" class="form-select">@foreach(\Modules\PlaceManagement\Entities\PlaceCategory::ICONS as $icon)<option value="{{ $icon }}">{{ $icon }}</option>@endforeach</select>
                            </div>
                            <div class="col-sm-3"><label class="form-label">{{ translate('Google_place_types') }}</label><input type="text" name="google_types" class="form-control" placeholder="gas_station"></div>
                            <input type="hidden" name="is_active" value="1">
                            <div class="col-sm-1"><button class="btn btn-primary w-100">{{ translate('Add') }}</button></div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
