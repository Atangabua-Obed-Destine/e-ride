@section('title', translate('vehicle_details'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex gap-3 justify-content-between flex-wrap mb-4">
                <h2 class="fs-22 mb-3">{{ translate('vehicle') }} #{{ $vehicle->ref_id }}</h2>
                <div class="d-flex justify-content-center flex-wrap gap-3">

                    <a
                        data-url="{{route('admin.vehicle.update.approved', [$vehicle->id])}}"
                        data-icon="{{ dynamicAsset('public/assets/admin-module/img/modal/mark.png') }}"
                        data-confirm-btn="{{translate("Approve")}}"
                        class="btn btn-primary min-w-120 d-flex justify-content-center approval-button-vehicle-update"
                    >
                        <i class="bi bi-check2 fs-16"></i>{{ translate('Approve') }}
                    </a>

                    <a
                        data-url="{{route('admin.vehicle.update.denied', [$vehicle->id])}}"
                        data-icon="{{ dynamicAsset('public/assets/admin-module/img/modal/delete.png') }}"
                        data-confirm-btn="{{translate("Deny")}}"
                        class="btn btn-danger min-w-120 d-flex justify-content-center deny-button-vehicle-update"
                    >
                        <i class="bi bi-x fs-16"></i>{{ translate('Deny') }}
                    </a>
                    <a href="{{ route('admin.vehicle.update.edit', $vehicle->id) }}"
                       class="btn btn-outline-link min-w-120 d-flex justify-content-center">
                        <i class="bi bi-pencil-fill"></i> {{ translate('Edit') }}
                    </a>
                </div>

            </div>
            <div class="card border analytical_data mb-3">
                <div class="card-body">
                    <div class="row gy-4">
                        <div class="col-lg-6">
                            <div class="">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-4">
                                    <h5 class="d-flex align-items-center gap-2 text-primary text-uppercase">
                                        <i class="bi bi-person-fill-gear"></i>
                                        {{ translate('vehicle_info') }}
                                    </h5>
                                </div>

                                <div class="media gap-3 gap-lg-4">
                                    <div class="avatar avatar-135 rounded">
                                        <img src="{{ onErrorImage(
                                            $vehicle?->model?->image,
                                            dynamicStorage('storage/app/public/vehicle/model') . '/' . $vehicle?->model?->image,
                                            dynamicAsset('public/assets/admin-module/img/media/upload-file.png'),
                                            'vehicle/model/',
                                        ) }}"
                                             class="rounded dark-support fit-object-contain" alt="">
                                    </div>
                                    <div class="media-body">
                                        <div class="d-flex flex-column align-items-start gap-1">
                                            <h6 class="mb-10">{{ $vehicle?->brand?->name }}
                                                - {{ $vehicle?->model?->name }}</h6>
                                            <div class="row g-2 w-100">
                                                <div class="col-sm-6">
                                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                                        <span class="text-muted">{{ translate('brand') }} -
                                                        </span><span
                                                            class="text-dark">{{ $vehicle?->brand?->name }}</span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6">
                                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                                        <span class="text-muted">{{ translate('category') }} -
                                                        </span><span
                                                            class="text-dark">{{ $vehicle?->category?->name }}</span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6">
                                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                                        <span class="text-muted">{{ translate('model') }} -
                                                        </span><span
                                                            class="text-dark">{{ $vehicle?->model?->name }}</span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6">
                                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                                        <span class="text-muted">{{ translate('owner') }} -
                                                        </span><span
                                                            class="text-dark text-capitalize">{{ $vehicle->ownership }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-4">
                                    <h5 class="d-flex align-items-center gap-2">
                                        <i class="bi bi-person-fill-gear text-primary"></i>
                                        {{ translate('driver_details') }}
                                    </h5>
                                </div>

                                <div class="media gap-3 gap-lg-4">
                                    <div class="avatar avatar-135 rounded">
                                        <img src="{{ onErrorImage(
                                            $vehicle?->driver?->profile_image,
                                            dynamicStorage('storage/app/public/driver/profile') . '/'. $vehicle?->driver?->profile_image,
                                            dynamicAsset('public/assets/admin-module/img/avatar/avatar.png'),
                                            'driver/profile/',
                                        ) }}"
                                             class="rounded dark-support fit-object" alt="">
                                    </div>
                                    <div class="media-body">
                                        <div class="d-flex flex-column align-items-start gap-1">
                                            <h6 class="mb-2"><a
                                                    href="{{route('admin.driver.show', $vehicle?->driver?->id)}}">{{ $vehicle?->driver?->first_name }}
                                                    {{ $vehicle?->driver?->last_name }}</a></h6>
                                            <div class="badge bg-primary text-capitalize">
                                                {{ $vehicle?->driver?->level?->name ?? translate('no_level_found') }}
                                            </div>
                                            <a href="tel:{{ $vehicle?->driver?->phone }}">{{ $vehicle?->driver?->phone }}</a>
                                            <a href="mailto:{{$vehicle?->driver?->email}}">{{ $vehicle?->driver?->email }}</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border analytical_data mb-3">
                <div class="card-body">
                    <h5 class="text-primary mb-3 d-flex gap-2 align-items-center"><i
                            class="bi bi-truck-front-fill"></i>{{ translate('vehicle_specification') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-borderX-0 p-lg">
                            <tbody>
                            <tr>
                                <td>{{ translate('viin') }}</td>
                                <td>{{ $vehicle->vin_number }}</td>
                                <td>{{ translate('fuel_type') }}</td>
                                <td>{{ $vehicle->fuel_type }}</td>
                            </tr>
                            <tr>
                                <td>{{ translate('licence_plate_number') }}</td>
                                <td>{{ $vehicle->licence_plate_number }}</td>
                                <td>{{ translate('engine') }}</td>
                                <td>{{ $vehicle?->model?->engine }} {{ translate('cc') }}</td>
                            </tr>
                            <tr>
                                <td>{{ translate('licence_expire_date') }}</td>
                                <td>{{ \Carbon\Carbon::parse($vehicle->licence_expire_date)->format('Y-m-d') }}</td>
                                <td>{{ translate('seat_capacity') }}</td>
                                <td>{{ $vehicle?->model?->seat_capacity }}</td>
                            </tr>
                            <tr>
                                <td>{{ translate('transmission') }}</td>
                                <td>{{ $vehicle->transmission }}</td>
                                <td>{{ translate('hatch_bag_capacity') }}</td>
                                <td>{{ $vehicle?->model?->hatch_bag_capacity }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @if(count($vehicle?->documents) > 0)
                <div class="card border analytical_data">
                    <div class="card-body">
                        <h5 class="text-primary align-items-center mb-3 text-uppercase">{{ translate('attached_documents') }}</h5>
                        <div class="d-flex gap-3 flex-wrap">
                            @foreach($vehicle?->documents as $doc)
                                <div class="mb-2">
                                    <a href="{{ dynamicStorage('storage/app/public/vehicle/document/') }}/{{ $doc }}"
                                       download="{{ $doc }}"
                                       class="border border-C5D2D2 rounded p-3 d-flex align-items-center gap-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img class="w-30px aspect-1"
                                                 src="{{ getExtensionIcon($doc) }}"
                                                 alt="">
                                            <h6 class="fs-12">{{ $doc }}</h6>
                                        </div>
                                        <i class="bi bi-arrow-down-circle-fill fs-20 text-primary"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
@endpush
