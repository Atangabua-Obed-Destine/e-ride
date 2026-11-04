@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride_Move_Directory'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-3">{{ translate('E-Ride_Move') }}</h2>
            @include('tripmanagement::admin.move._nav', ['active' => 'directory'])

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                        <form class="d-flex gap-2" method="GET">
                            <input type="search" name="search" value="{{ $search }}" class="form-control w-auto" placeholder="{{ translate('Name_or_phone') }}">
                            <button class="btn btn-primary">{{ translate('Search') }}</button>
                        </form>
                        <a href="{{ route('admin.move.directory.create') }}" class="btn btn-primary">{{ translate('Add_truck_owner') }}</a>
                    </div>
                    <p class="fs-12 text-muted">{{ translate('Customers see these owners with Call and WhatsApp buttons when no truck is available in the app. Only add owners who agreed to have their number shown.') }}</p>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>{{ translate('Owner') }}</th>
                                <th>{{ translate('Vehicle') }}</th>
                                <th>{{ translate('Zone') }}</th>
                                <th>{{ translate('Areas') }}</th>
                                <th class="text-end">{{ translate('Contacts') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($listings as $listing)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if ($listing->photoUrl())
                                                <img src="{{ $listing->photoUrl() }}" alt="" width="40" height="40" class="rounded" style="object-fit: cover">
                                            @endif
                                            <div>
                                                {{ $listing->owner_name }}
                                                <div class="fs-12 text-muted">{{ $listing->phone }}{{ $listing->whatsapp && $listing->whatsapp !== $listing->phone ? ' · WhatsApp ' . $listing->whatsapp : '' }}</div>
                                                @if ($listing->user)
                                                    <div class="fs-12 text-success">{{ translate('E-Ride_driver') }}: {{ trim($listing->user->first_name . ' ' . $listing->user->last_name) }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $listing->vehicleCategory?->name }}</td>
                                    <td>{{ $listing->zone?->name }}</td>
                                    <td class="fs-12">{{ $listing->areas }}</td>
                                    <td class="text-end">{{ $listing->contacts_count }}</td>
                                    <td>
                                        <span class="badge {{ $listing->is_active ? 'badge-success' : 'badge-secondary' }}">{{ $listing->is_active ? translate('Shown') : translate('Hidden') }}</span>
                                    </td>
                                    <td class="text-nowrap text-end">
                                        <a href="{{ route('admin.move.directory.edit', $listing->id) }}" class="btn btn-sm btn-outline-primary">{{ translate('Edit') }}</a>
                                        <form action="{{ route('admin.move.directory.toggle', $listing->id) }}" method="POST" class="d-inline">
                                            @csrf @method('PUT')
                                            <button class="btn btn-sm btn-outline-secondary">{{ $listing->is_active ? translate('Hide') : translate('Show') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-5">{{ translate('No_truck_owners_yet') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $listings->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
