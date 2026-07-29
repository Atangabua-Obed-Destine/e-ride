@section('title', translate('Update Vehicle Request List'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-20">
                <h2 class="fs-20 text-capitalize">{{ translate('Update Vehicle Request List') }}</h2>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted text-capitalize">{{ translate('Total Pending Request') }} : </span>
                    <span class="text-primary fs-16 fw-bold" id="total_record_count">{{ $vehicles?->count() }}</span>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                        <form class="search-form search-form_style-two">
                            <div class="input-group search-form__input_group">
                                <span class="search-form__icon">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" value="{{ request()->get('search') }}"
                                       class="theme-input-style search-form__input"
                                       placeholder="{{ translate('Search') }}">
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
                                           href="{{ route('admin.vehicle.update.export', ['search' => request()->get('search'), 'file' => 'excel']) }}">{{ translate('excel') }}</a>
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
                                <th>{{ translate('Date_&_Time') }}</th>
                                <th>{{ translate('Before_Edit') }}</th>
                                <th>{{ translate('After_Edit') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($vehicles as $key => $vehicle)
                                <tr>
                                    <td class="sl">{{ $vehicles->firstItem() + $key }}</td>
                                    <td>#{{ $vehicle?->ref_id }}</td>
                                    <td>
                                        {{ date('d/m/Y', strtotime($vehicle->updated_at)) }}
                                        <br>
                                        {{ date('h:i A', strtotime($vehicle->updated_at)) }}
                                    </td>
                                    <td class="p-0">
                                        <table class="table table-borderless d-flex align-items-center m-0">
                                            @if(array_key_exists('category_id', $vehicle?->draft))
                                                <tr>
                                                    <td class="py-1">{{ translate('Category') }}</td>
                                                    <td class="py-1">: {{ $vehicle?->category?->name }}</td>
                                                </tr>
                                            @endif

                                            @if(array_key_exists('brand_id', $vehicle?->draft))
                                                <tr>
                                                    <td class="py-1">{{ translate('Brand') }}</td>
                                                    <td class="py-1">: {{ $vehicle?->brand?->name }}</td>
                                                </tr>
                                            @endif

                                            @if(array_key_exists('model_id', $vehicle?->draft))
                                                <tr>
                                                    <td class="py-1">{{ translate('Model') }}</td>
                                                    <td class="py-1">: {{ $vehicle?->model?->name }}</td>
                                                </tr>
                                            @endif

                                            @if(array_key_exists('licence_plate_number', $vehicle?->draft))
                                                <tr>
                                                    <td class="py-1">{{ translate('License Plate Number') }}</td>
                                                    <td class="py-1">: {{ $vehicle?->licence_plate_number }}</td>
                                                </tr>
                                            @endif

                                            @if(array_key_exists('licence_expire_date', $vehicle?->draft))
                                                <tr>
                                                    <td class="py-1">{{ translate('License Expiry Date') }}</td>
                                                    <td class="py-1">
                                                        : {{ date('Y-m-d', strtotime($vehicle?->licence_expire_date)) }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                    <td class="p-0">
                                        <table class="table table-borderless d-flex align-items-center m-0">
                                            @if(array_key_exists('category_id', $vehicle?->draft) && $vehicle?->category_id !== $vehicle?->draft['category_id'])
                                                <tr>
                                                    <td class="py-1">{{ translate('Category') }}</td>
                                                    <td class="py-1">: {{ $vehicle?->draft['category'] }}</td>
                                                </tr>
                                            @endif

                                            @if(array_key_exists('brand_id', $vehicle?->draft) && $vehicle->brand_id !== $vehicle?->draft['brand_id'])
                                                <tr>
                                                    <td class="py-1">{{ translate('Brand') }}</td>
                                                    <td class="py-1">: {{ $vehicle?->draft['brand'] }}</td>
                                                </tr>
                                            @endif

                                            @if(array_key_exists('model_id', $vehicle?->draft) && $vehicle?->model_id !== $vehicle?->draft['model_id'])
                                                <tr>
                                                    <td class="py-1">{{ translate('Model') }}</td>
                                                    <td class="py-1">: {{ $vehicle->draft['model'] }}</td>
                                                </tr>
                                            @endif

                                            @if(array_key_exists('licence_plate_number', $vehicle?->draft) && $vehicle->licence_plate_number !== $vehicle?->draft['licence_plate_number'])
                                                <tr>
                                                    <td class="py-1">{{ translate('License Plate Number') }}</td>
                                                    <td class="py-1">
                                                        : {{ $vehicle->draft['licence_plate_number'] }}</td>
                                                </tr>
                                            @endif

                                            @if(array_key_exists('licence_expire_date', $vehicle?->draft) &&$vehicle?->licence_expire_date !== $vehicle?->draft['licence_expire_date'])
                                                <tr>
                                                    <td class="py-1">{{ translate('License Expiry Date') }}</td>
                                                    <td class="py-1">
                                                        : {{ date('Y-m-d', strtotime($vehicle->draft['licence_expire_date'])) }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                    <td class="action">
                                        <div class="d-flex justify-content-center gap-2 align-items-center">
                                            <div class="d-flex justify-content-center gap-2 align-items-center">
                                                <a href="{{ route('admin.vehicle.update.details', $vehicle->id) }}"
                                                   class="btn btn-outline-link btn-action" data-bs-toggle="tooltip"
                                                   title="View Details">
                                                    <i class="bi bi-eye-fill"></i>
                                                </a>

                                                <a
                                                    data-url="{{route('admin.vehicle.update.approved', [$vehicle->id])}}"
                                                    data-icon="{{ dynamicAsset('public/assets/admin-module/img/modal/mark.png') }}"
                                                    data-confirm-btn="{{translate("Approve")}}"
                                                    class="btn btn-outline-success btn-action d-flex justify-content-center align-items-center approval-button-vehicle-update"
                                                    data-bs-toggle="tooltip" title="{{translate("Approve")}}">
                                                    <i class="bi bi-check2 fs-16"></i>
                                                </a>


                                                <a
                                                    data-url="{{route('admin.vehicle.update.denied', [$vehicle->id])}}"
                                                    data-icon="{{ dynamicAsset('public/assets/admin-module/img/modal/mark.png') }}"
                                                    data-confirm-btn="{{translate("Deny")}}"
                                                    class="btn btn-outline-danger btn-action d-flex justify-content-center align-items-center deny-button-vehicle-update"
                                                    data-bs-toggle="tooltip" title="{{translate("Deny")}}">
                                                    <i class="bi bi-x-lg"></i>
                                                </a>
                                                @if($vehicle->vehicle_request_status == DENIED)
                                                    <a href="{{ route('admin.vehicle.request.edit', $vehicle->id) }}"
                                                       class="btn btn-outline-info btn-action">
                                                        <i class="bi bi-pencil-fill"></i>
                                                    </a>

                                                    <a
                                                        data-url="{{route('admin.vehicle.permanent-delete', [$vehicle->id])}}"
                                                        data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
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
                                        <div
                                            class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                            <img
                                                src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}"
                                                alt="" width="100">
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
