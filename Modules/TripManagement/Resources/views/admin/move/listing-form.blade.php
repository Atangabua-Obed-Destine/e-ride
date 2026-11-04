@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride_Move_Directory'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 760px">
            <h2 class="fs-22 mb-3">{{ $listing->exists ? translate('Edit_truck_owner') : translate('Add_truck_owner') }}</h2>
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @if ($categories->isEmpty())
                <div class="alert alert-warning">{{ translate('No move vehicles yet. Add a vehicle category with the type Tricycle, Pickup, Dyna or Truck first.') }}</div>
            @endif
            <form action="{{ $listing->exists ? route('admin.move.directory.update', $listing->id) : route('admin.move.directory.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if ($listing->exists) @method('PUT') @endif
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Owner_name') }} *</label>
                                <input type="text" name="owner_name" class="form-control" required maxlength="120" value="{{ old('owner_name', $listing->owner_name) }}">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Vehicle') }} *</label>
                                <select name="vehicle_category_id" class="form-select" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('vehicle_category_id', $listing->vehicle_category_id) === $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Phone') }} *</label>
                                <input type="tel" name="phone" class="form-control" required maxlength="30" placeholder="6XXXXXXXX" value="{{ old('phone', $listing->phone) }}">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('WhatsApp_(if_different)') }}</label>
                                <input type="tel" name="whatsapp" class="form-control" maxlength="30" value="{{ old('whatsapp', $listing->whatsapp) }}">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Zone') }} *</label>
                                <select name="zone_id" class="form-select" required>
                                    @foreach ($zones as $zone)
                                        <option value="{{ $zone->id }}" {{ old('zone_id', $listing->zone_id) === $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Areas_served') }}</label>
                                <input type="text" name="areas" class="form-control" maxlength="255" placeholder="{{ translate('e.g. Nkwen, Commercial Avenue, Mile 4') }}" value="{{ old('areas', $listing->areas) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ translate('Note_for_customers') }}</label>
                                <textarea name="note" class="form-control" rows="2" maxlength="500" placeholder="{{ translate('e.g. Has 2 loaders. Available every day from 7am.') }}">{{ old('note', $listing->note) }}</textarea>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Photo_of_the_vehicle') }}</label>
                                <input type="file" name="photo" class="form-control" accept="image/*">
                                @if ($listing->photoUrl())
                                    <img src="{{ $listing->photoUrl() }}" alt="" width="80" height="60" class="rounded mt-2" style="object-fit: cover">
                                @endif
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('E-Ride_driver_phone_(optional)') }}</label>
                                <input type="tel" name="driver_phone" class="form-control" maxlength="30" value="{{ old('driver_phone', $listing->user?->phone) }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('If the owner also drives on E-Ride, their driver account phone. The listing then shows "On E-Ride".') }}</div>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $listing->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">{{ translate('Show_to_customers') }}</label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" required {{ $listing->consent_at ? 'checked' : '' }}>
                                    <label class="form-check-label" for="consent">{{ translate('The owner agreed that E-Ride shows this name and phone number to customers.') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.move.directory') }}" class="btn btn-outline-secondary">{{ translate('Back') }}</a>
                    <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
