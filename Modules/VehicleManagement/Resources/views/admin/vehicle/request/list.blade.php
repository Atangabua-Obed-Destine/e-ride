@section('title', translate('New Vehicle Request List'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-20">
                <h2 class="fs-20 text-capitalize">{{ translate('New Vehicle Request List') }}</h2>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted text-capitalize">{{ translate('Total Pending Request') }} : </span>
                    <span class="text-primary fs-16 fw-bold" id="total_record_count">{{ $vehicles?->count() }}</span>
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-3">
                <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ !request()->has('vehicle_request_status') || request()->get('vehicle_request_status') == 'pending' ? 'active' : '' }}"
                           href="{{ url()->current() }}?vehicle_request_status=pending">
                            {{ translate('Pending Request') }}
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ request()->get('vehicle_request_status') == 'denied' ? 'active' : '' }}"
                           href="{{ url()->current() }}?vehicle_request_status=denied">
                            {{ translate('Denied Request') }}
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                        <form action="javascript:;" class="search-form search-form_style-two"
                              method="GET">
                            <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                <input type="search" class="theme-input-style search-form__input"
                                       value="{{ request()->get('search') }}" name="search" id="search"
                                       placeholder="{{ translate('search_here_by_Vin_&_License') }}">
                            </div>
                            <button type="submit" class="btn btn-primary search-submit"
                                    data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                        </form>

                        <div class="d-flex flex-wrap gap-3">
                            <a href="{{ route('admin.vehicle.request.list') }}"
                                class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                                data-bs-title="{{ translate('refresh') }}">
                                <i class="bi bi-arrow-repeat"></i>
                            </a>

                            <div class="dropdown">
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="dropdown">
                                    <i class="bi bi-download"></i>
                                    {{ translate('download') }}
                                    <i class="bi bi-caret-down-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                    <li><a class="dropdown-item"
                                            href="{{ route('admin.vehicle.request.export') }}?{{ request()->getQueryString() }}&&file=excel">{{ translate('excel') }}</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-borderless align-middle table-hover text-nowrap">
                            <thead class="table-light align-middle text-capitalize">
                                <tr>
                                    <th class="sl">{{ translate('SL') }}</th>
                                    <th>{{ translate('Vehicle_ID') }}</th>
                                    <th>{{ translate('Vehicle_Category') }}</th>
                                    <th>{{ translate('Brand_&_Model') }}</th>
                                    <th>{{ translate('VIN_&_License') }}</th>
                                    <th>{{ translate('Owner_info') }}</th>
                                    <th>{{ translate('Vehicle Features') }}</th>
                                    <th class="text-center">{{ translate('action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($vehicles as $key => $vehicle)
                                <tr>
                                    <td class="sl">{{ $vehicles->firstItem() + $key }}</td>
                                    <td>#{{ $vehicle?->ref_id }}</td>
                                    <td>{{ $vehicle?->category?->name }}</td>
                                    <td>
                                        {{ $vehicle?->brand?->name }}
                                        <br>
                                        {{ $vehicle?->model?->name }}
                                    </td>
                                    <td>
                                        VIN - {{ $vehicle?->vin_number ?? 'N/A' }}
                                        <br>
                                        L -  {{ $vehicle?->licence_plate_number }}
                                    </td>
                                    <td>
                                        <span class="fw-bold">{{ $vehicle?->ownership }}</span>
                                        <br>
                                        {{ $vehicle?->driver?->full_name ?? $vehicle?->driver?->first_name . ' ' . $vehicle?->driver?->last_name }}
                                    </td>
                                    <td class="p-0">
                                        <table class="table table-borderless d-flex align-items-center m-0">
                                            <tr>
                                                <td class="py-1">Seat</td>
                                                <td class="py-1">: {{ $vehicle?->model?->seat_capacity }}</td>
                                            </tr>
                                            <tr>
                                                <td class="py-1">Hatch Bag</td>
                                                <td class="py-1">: {{ $vehicle?->model?->hatch_bag_capacity }}</td>
                                            </tr>
                                            <tr>
                                                <td class="py-1">Fuel</td>
                                                <td class="py-1">: {{ $vehicle?->fuel_type }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td class="action">
                                        <div class="d-flex justify-content-center gap-2 align-items-center">
                                            <div class="d-flex justify-content-center gap-2 align-items-center">
                                                <a href="{{ route('admin.vehicle.request.details', $vehicle->id) }}"
                                                   class="btn btn-outline-link btn-action" data-bs-toggle="tooltip"
                                                   title="View Details">
                                                    <i class="bi bi-eye-fill"></i>
                                                </a>

                                                @if($vehicle->vehicle_request_status == PENDING)
                                                    <a
                                                        data-url="{{route('admin.vehicle.request.approved', [$vehicle->id])}}"
                                                        data-icon="{{ dynamicAsset('public/assets/admin-module/img/modal/mark.png') }}"
                                                        data-confirm-btn="{{translate("Approve")}}"
                                                        class="btn btn-outline-success btn-action d-flex justify-content-center align-items-center approval-button-vehicle-request"
                                                        data-bs-toggle="tooltip" title="{{translate("Approve")}}">
                                                        <i class="bi bi-check2 fs-16"></i>
                                                    </a>


                                                    <a
                                                        data-url="{{route('admin.vehicle.request.denied', [$vehicle->id])}}"
                                                        data-icon="{{ dynamicAsset('public/assets/admin-module/img/modal/mark.png') }}"
                                                        data-confirm-btn="{{translate("Deny")}}"
                                                        class="btn btn-outline-danger btn-action d-flex justify-content-center align-items-center deny-button-vehicle-request"
                                                        data-bs-toggle="tooltip" title="{{translate("Deny")}}">
                                                        <i class="bi bi-x-lg"></i>
                                                    </a>
                                                @endif

                                                @if($vehicle->vehicle_request_status == DENIED)
                                                    <a href="{{ route('admin.vehicle.request.edit', $vehicle->id) }}"
                                                       class="btn btn-outline-info btn-action">
                                                        <i class="bi bi-pencil-fill"></i>
                                                    </a>

                                                    <a
                                                        data-url="{{route('admin.vehicle.permanent-delete', [$vehicle->id])}}"
                                                        data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                        data-title="{{ translate('Are you sure to permanently delete this Request') }}?"
                                                        data-sub-title="{{ translate('Once you delete it') . ', ' . translate('this action cannot be undone and the data will be lost forever.') }}"
                                                        data-confirm-btn="{{translate("Yes, Delete")}}"
                                                        data-cancel-btn="{{translate("Not Now")}}"
                                                        data-button-position="left"
                                                        class="btn btn-outline-danger btn-action d-flex justify-content-center align-items-center delete-button"
                                                        data-bs-toggle="tooltip" title="{{translate("Delete")}}">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14">
                                        <div class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                            <img src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}" alt="" width="100">
                                            <p class="text-center">{{translate('no_data_available')}}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end">
                        {{-- {{ $customers->links() }} --}}
                    </div>

                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->


@endsection
