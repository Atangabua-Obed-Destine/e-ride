@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride_Move_Deposits'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 860px">
            <h2 class="fs-22 mb-3">{{ translate('E-Ride_Move') }}</h2>
            @include('tripmanagement::admin.move._nav', ['active' => 'deposits'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            @if ($categories->isEmpty())
                <div class="card"><div class="card-body text-muted">
                    {{ translate('No move vehicles yet. Add a vehicle category with the type Tricycle, Pickup, Dyna or Truck first.') }}
                    <a href="{{ route('admin.vehicle.attribute-setup.category.index') }}">{{ translate('Vehicle_categories') }}</a>
                </div></div>
            @elseif (!$zone)
                <div class="card"><div class="card-body text-muted">{{ translate('No active zone.') }}</div></div>
            @else
                <form method="GET" class="mb-3">
                    <select name="zone_id" class="form-select w-auto" onchange="this.form.submit()">
                        @foreach ($zones as $z)
                            <option value="{{ $z->id }}" {{ $z->id === $zone->id ? 'selected' : '' }}>{{ $z->name }}</option>
                        @endforeach
                    </select>
                </form>
                <form action="{{ route('admin.move.deposits.update') }}" method="POST">
                    @csrf @method('PUT')
                    <input type="hidden" name="zone_id" value="{{ $zone->id }}">
                    <div class="card mb-3">
                        <div class="card-body">
                            <p class="fs-12 text-muted">{{ translate('Tick the sizes customers can book in this zone and set the booking fee for each. E-Ride sets no price for moves: the customer and driver agree it in person.') }}</p>
                            <table class="table table-borderless align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>{{ translate('Offered') }}</th>
                                    <th>{{ translate('Vehicle') }}</th>
                                    <th style="width: 220px">{{ translate('Booking_fee') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($categories as $category)
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input" name="offered[{{ $category->id }}]" value="1" {{ $amounts->has($category->id) ? 'checked' : '' }}></td>
                                        <td>
                                            {{ $category->name }}
                                            <div class="fs-12 text-muted">{{ translate('move_type_' . $category->type) }}{{ $category->is_active ? '' : ' · ' . translate('inactive') }}</div>
                                        </td>
                                        <td><input type="number" class="form-control" name="deposit[{{ $category->id }}]" min="0" step="1" value="{{ (float)($amounts[$category->id] ?? 1000) }}"></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
