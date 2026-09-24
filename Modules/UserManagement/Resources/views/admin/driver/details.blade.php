@php use Modules\UserManagement\Enums\PauseReasonEnum; @endphp
@extends('adminmodule::layouts.master')

@section('title', translate('Driver_Details'))

@section('content')

    <!-- Main Content -->
    <div class="main-content">
        @php
            $collectCash = '<a href="' . route('admin.driver.cash.index', $driver->id)  .'" class="fw-semibold text-info" target="_blank">'. translate('Collect cash') .'</a>';
            $driverDetails = $driver->driverDetails;
        @endphp
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h2 class="fs-22 mb-0">{{ translate('driver') }} #{{ $driver->id }}</h2>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    @can('user_delete')
                        <a
                            data-url="{{ route('admin.driver.delete', ['id' => $driver->id]) }}"
                            data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                            data-title="{{ translate('Are you sure to delete this Driver')."?" }}"
                            data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will remove from the Driver list.') }}"
                            data-confirm-btn="{{translate("Yes, Delete")}}"
                            data-cancel-btn="{{translate("Not Now")}}"
                            class="btn text-danger bg-danger bg-opacity-10 d-flex align-items-center gap-2 p-2 fs-14  delete-button"
                            data-bs-toggle="tooltip" title="{{translate("Delete")}}">
                            <i class="bi bi-trash-fill"></i>
                            {{ translate('Delete') }}
                        </a>
                    @endcan
                    @can('user_log')
                        <a href="{{ route('admin.driver.log') }}?id={{ $driver->id }}"
                           class="btn text-dark bg-white border-C5D2D2 d-flex align-items-center gap-2 p-2 fs-14">
                            <i class="bi bi-clock-fill"></i>
                            {{ translate('Activity log') }}
                        </a>
                    @endcan
                    @can('user_edit')
                        @php($statusLock = !$driver->is_active ? translate('Driver is suspended') : $driverDetails->systemPauseMessage())
                        <label class="btn text-dark bg-white border-C5D2D2 d-flex align-items-center gap-2 p-2 fs-14 {{ $statusLock ? 'opacity-50' : 'driver-status-toggle' }}"
                               @if($statusLock) data-bs-toggle="tooltip" data-bs-title="{{ $statusLock }}"
                               @else role="button" data-id="{{ $driver->id }}" data-paused="{{ $driverDetails->isCurrentlyPaused() ? 1 : 0 }}" @endif>
                            {{ translate('Status') }}
                            <label class="switcher mb-0">
                                <input class="switcher_input" type="checkbox" onclick="return false;"
                                       {{ !$driverDetails->isCurrentlyPaused() ? 'checked' : '' }}
                                       {{ $statusLock ? 'disabled' : '' }}>
                                <span class="switcher_control"></span>
                            </label>
                        </label>
                        @if(!$driver->is_active)
                            <button type="button"
                                    class="btn btn-primary px-3 fs-14 fw-semibold driver-suspension-btn"
                                    data-url="{{ route('admin.driver.update-suspension-status', ['id' => $driver->id, 'action' => REACTIVATE]) }}"
                                    data-title="{{ translate('Are you sure want to un-suspend the driver') }}?"
                                    data-sub-title="{{ translate('The driver will regain login access and start receiving trip requests again.') }}"
                                    data-confirm-btn="{{ translate('Un-suspend Driver') }}"
                                    data-confirm-class="btn-primary">
                                {{ translate('Un-suspend Driver') }}
                            </button>
                        @else
                            <button type="button"
                                    class="btn btn-danger px-3 fs-14 fw-semibold driver-suspension-btn"
                                    data-url="{{ route('admin.driver.update-suspension-status', ['id' => $driver->id, 'action' => SUSPEND]) }}"
                                    data-title="{{ translate('Are you sure want to Suspend the driver') }}?"
                                    data-sub-title="{{ translate('Once suspended, the driver is blocked from login and dispatch until the suspension is removed.') }}"
                                    data-confirm-btn="{{ translate('Suspend Driver') }}"
                                    data-confirm-class="btn-danger">
                                {{ translate('Suspend Driver') }}
                            </button>
                        @endif
                        @if($driverDetails->is_verified)
                            <a href="{{ route('admin.driver.edit', ['id' => $driver->id]) }}"
                               class="btn btn-success text-white d-flex align-items-center gap-2 p-2 fs-14"
                            >
                                <i class="bi bi-pencil-square"></i>
                                {{ translate('edit') }}
                            </a>
                        @else
                            <div class="dropdown">
                                <button class="btn btn-primary d-flex align-items-center gap-2 px-3 fs-14 fw-semibold"
                                        type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                    <i class="bi bi-pencil-square"></i>
                                    {{ translate('Edit') }}
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton1">
                                    <li><a class="dropdown-item"
                                           href="{{ route('admin.driver.edit', ['id' => $driver->id]) }}">{{ translate('Edit Information') }}</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item"
                                           href="{{ route('admin.driver.mark-as-verified', ['id' => $driver->id]) }}">{{ translate('Verify Status') }}</a>
                                    </li>
                                </ul>
                            </div>
                        @endif
                    @endcan
                </div>
            </div>
            @if(!$driver->is_active)
                <div class="mt-3">
                    <div class="alert alert-danger-custom m-0" role="alert">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <img width="16"
                                 src="{{ dynamicAsset('public/assets/admin-module/img/svg/info-triangle-danger.svg') }}"
                                 alt="">
                            <strong class="text-danger">{{ translate('Account is suspended.') }}</strong>
                        </div>
                        <p class="fs-12 mb-0">
                            {{ translate('The driver is blocked from login and dispatch until the suspension is removed.') }}
                        </p>
                    </div>
                </div>
            @elseif($driverDetails->isCurrentlyPaused())
                <span class="bg-warning rounded bg-warning-10 d-flex align-items-centre gap-2 py-2 px-3 mb-20 mt-3">
                    <i class="bi bi-info-circle-fill text-warning mt-1"></i>
                    <div class="text-dark fw-medium">
                        @if($driverDetails->pauseRemainingLabel())
                            {{ translate('This driver is paused for {duration}.', replace: ['duration' => $driverDetails->pauseRemainingLabel()]) }}
                        @else
                            {{ translate('This driver is currently paused.') }}
                        @endif
                    </div>
                    @if($driverDetails->pause_reason == PauseReasonEnum::CASH_IN_HAND_LIMIT->value)
                        <div>{!! translate('Reason : Exceeded cash-in-hand limit.') !!} {!! translate('{collectCash} or contact the driver.', replace: ['collectCash' => $collectCash]) !!}</div>
                    @elseif($driverDetails->pause_reason == PauseReasonEnum::FACE_VERIFICATION->value)
                        <div>{{ translate('Reason : Face verification failure.') }}</div>
                    @elseif($driverDetails->pause_reason)
                        <div>{{ translate('Reason : {reason}', replace: ['reason' => $driverDetails->pause_reason]) }}</div>
                    @endif
                </span>
            @endif

            <div class="card my-3">
                <div class="card-body">
                    <div class="row gy-5">
                        <div class="col-lg-6">
                            <div class="">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-4">
                                    <h5 class="text-capitalize d-flex align-items-center gap-2 text-primary">
                                        <i class="bi bi-person-fill-gear"></i>
                                        {{ translate('driver_information') }}
                                    </h5>
                                </div>

                                <div class="media flex-wrap gap-3 gap-lg-4">
                                    <div class="avatar avatar-135 rounded position-relative">
                                        <img src="{{ onErrorImage(
                                            $driver?->profile_image,
                                            dynamicStorage('storage/app/public/driver/profile') . '/' . $driver?->profile_image,
                                            dynamicAsset('public/assets/admin-module/img/avatar/avatar.png'),
                                            'driver/profile/',
                                        ) }}"
                                             class="rounded dark-support custom-box-size" alt=""
                                             style="--size: 136px">
                                        @if( $driverDetails->isCurrentlyPaused())
                                            <div
                                                class="position-absolute top-0 start-0 h-100 w-100 bg-black bg-opacity-25 rounded custom-box-size"
                                                style="--size: 136px">
                                                <div class="d-flex justify-content-center align-items-end p-3 h-100">
                                                    <div class="bg-danger text-white fs-12 fw-medium rounded px-2 py-1">
                                                        {{ translate('paused') }}
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="media-body">
                                        <div class="d-flex flex-column align-items-start gap-1">
                                            <h6 class="mb-10 d-flex gap-1 align-items-center">
                                                {{ $driver?->first_name . ' ' . $driver?->last_name }}
                                                @if($driverDetails->isCurrentlyPaused())
                                                    <img width="14"
                                                         src="{{ dynamicAsset('public/assets/admin-module/img/svg/on-hold.svg') }}"
                                                         alt="" data-bs-toggle="tooltip" data-bs-placement="right"
                                                         data-bs-title="{{ translate('paused') }}">
                                                @endif
                                                @if($driverDetails->is_verified)
                                                    <span class="fs-14 lh-1" data-bs-toggle="tooltip"
                                                          data-bs-placement="bottom"
                                                          data-bs-title="{{ translate('Verified') }}">
                                                        <i class="bi bi-patch-check-fill text-success"></i>
                                                    </span>
                                                @else
                                                    <span class="fs-14 lh-1" data-bs-toggle="tooltip"
                                                          data-bs-placement="bottom"
                                                          data-bs-title="{{ translate('Unverified') }}">
                                                        <i class="bi bi-patch-exclamation-fill text-danger"></i>
                                                    </span>
                                                @endif
                                            </h6>
                                            <div class="d-flex gap-3 align-items-center mb-1">
                                                <div class="badge bg-primary text-capitalize">
                                                    {{ $driver->level->name ?? translate('no_level_found') }}
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    {{ number_format($driver->receivedReviews->avg('rating'), 1) }}
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="fw-bold">{{translate("phone")}}: </span>
                                                <a href="tel:{{ $driver->phone }}">{{ $driver->phone }}</a>
                                            </div>
                                            <div>
                                                <span class="fw-bold">{{translate("E-mail")}}: </span>
                                                <a href="mailto:{{ $driver->email }}">{{ $driver->email }}</a>
                                            </div>
                                            <div>
                                                <span class="fw-bold">{{translate("Service")}}: </span>
                                                <span>
                                                    @if($driverDetails?->service)
                                                        @if(in_array('ride_request',$driverDetails?->service) && in_array('parcel',$driverDetails?->service))
                                                            {{translate("Ride Request")}}, {{translate("Parcel")}}
                                                            ({{translate('capacity').'-'. ($driver->vehicle?->parcel_weight_capacity != null ? ($driver->vehicle?->parcel_weight_capacity . (businessConfig(key: 'parcel_weight_unit')?->value ?? 'kg')): translate('unlimited')) }}
                                                            )
                                                        @elseif(in_array('ride_request',$driverDetails?->service))
                                                            {{translate("Ride Request")}}
                                                        @elseif(in_array('parcel',$driverDetails?->service))
                                                            {{translate("Parcel")}}
                                                            ({{translate('capacity').'-'. ($driver->vehicle?->parcel_weight_capacity != null ? ($driver->vehicle?->parcel_weight_capacity . (businessConfig(key: 'parcel_weight_unit')?->value ?? 'kg')): translate('unlimited')) }}
                                                            )
                                                        @endif
                                                    @else
                                                        {{translate("Ride Request")}}, {{translate("Parcel")}}
                                                        ({{translate('capacity').'-'. ($driver->vehicle?->parcel_weight_capacity != null ? ($driver->vehicle?->parcel_weight_capacity . (businessConfig(key: 'parcel_weight_unit')?->value ?? 'kg')): translate('unlimited')) }}
                                                        )
                                                    @endif
                                                </span>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-4">
                                    <h5 class="d-flex align-items-center text-primary gap-2">
                                        <i class="bi bi-person-fill-gear text-primary text-capitalize"></i>
                                        {{ translate('driver_rate_info') }}
                                    </h5>
                                </div>

                                <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center">
                                    <div class="text-success text-capitalize">
                                        {{ translate('average_active_rate/day') }}</div>
                                    <div class="d-flex gap-2 align-items-center flex-grow-1">
                                        <div class="progress flex-grow-1">
                                            <div class="progress-bar bg-success" role="progressbar"
                                                 style="width: {{ round($commonData['avg_active_day']) }}%"
                                                 aria-valuenow="{{ round($commonData['avg_active_day'], 2) }}"
                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <div class="text-success">{{ round($commonData['avg_active_day'], 2) }}%</div>
                                    </div>
                                </div>

                                <div class="card mt-4">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-around flex-wrap gap-3">
                                            <div class="d-flex align-items-center flex-column gap-3">
                                                <div class="circle-progress"
                                                     data-parsent="{{ round($commonData['driver_avg_earning'], 2) }}"
                                                     data-color="#56DBCB">
                                                    <div class="content">
                                                        <h6 class="persent fs-12">
                                                            {{ abbreviateNumber($commonData['driver_avg_earning']) }}{{ getSession('currency_symbol') }}
                                                        </h6>
                                                    </div>
                                                </div>
                                                <h6 class="fw-semibold fs-12" style="color: #56DBCB">
                                                    {{ translate('avg._earning_value') }}</h6>
                                            </div>

                                            <div class="d-flex align-items-center flex-column gap-3">
                                                <div class="circle-progress"
                                                     data-parsent="{{ round($commonData['positive_review_rate']) ?? 0 }}"
                                                     data-color="#3B72FF">
                                                    <div class="content">
                                                        <h6 class="persent fs-12">
                                                            {{ round($commonData['positive_review_rate']) ?? 0 }}
                                                            %</h6>
                                                    </div>
                                                </div>
                                                <h6 class="fw-semibold fs-12 text-capitalize positive-review-color">
                                                    {{ translate('positive_review_rate') }}</h6>
                                            </div>

                                            <div class="d-flex align-items-center flex-column gap-3">
                                                <div class="circle-progress text-capitalize"
                                                     data-parsent="{{ round($commonData['success_rate'], 2) }}"
                                                     data-color="#76C351">
                                                    <div class="content">
                                                        <h6 class="persent fs-12">{{ round($commonData['success_rate'], 2) }}
                                                            %</h6>
                                                    </div>
                                                </div>
                                                <h6 class="fw-semibold fs-12 text-capitalize success-rate-color">
                                                    {{ translate('success_rate') }}</h6>
                                            </div>

                                            <div class="d-flex align-items-center flex-column gap-3">
                                                <div class="circle-progress"
                                                     data-parsent="{{ round($commonData['cancel_rate'], 2) }}"
                                                     data-color="#FF6767">
                                                    <div class="content">
                                                        <h6 class="persent fs-12">{{ round($commonData['cancel_rate'], 2) }}
                                                            %</h6>
                                                    </div>
                                                </div>
                                                <h6 class="fw-semibold fs-12 text-capitalize cancellation-rate-color">
                                                    {{ translate('cancelation_rate') }}</h6>
                                            </div>
                                            <div class="d-flex align-items-center flex-column gap-3">
                                                <div class="circle-progress"
                                                     data-parsent="{{ round($commonData['idle_rate_today'], 2) }}"
                                                     data-color="#FFA800">
                                                    <div class="content">
                                                        <h6 class="persent fs-12">{{ round($commonData['idle_rate_today'], 2) }}
                                                            %</h6>
                                                    </div>
                                                </div>
                                                <h6 class="fw-semibold fs-12" style="color: #FFA800">Today Idle Hour
                                                    Rate</h6>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-30">
                <div class="card-body">
                    <div class="row justify-content-between align-items-center g-2 mb-3">
                        <div class="col-sm-6">
                            <h5 class="text-capitalize d-flex align-items-center gap-2 text-primary">
                                <i class="bi bi-person-fill-gear"></i>
                                {{ translate('wallet_info') }}
                            </h5>
                        </div>
                    </div>
                    <div class="row g-4" id="order_stats">
                        <div class="col-lg-4">

                            <div class="card h-100 d-flex justify-content-center align-items-center">
                                <div
                                    class="card-body d-flex flex-column gap-10 align-items-center justify-content-center">
                                    <img width="48"
                                         src="{{ dynamicAsset('public/assets/admin-module/img/media/cc.png') }}"
                                         alt="">
                                    <h3 class="fw-bold mb-0 fs-3">
                                        {{ getCurrencyFormat($commonData['collectable_amount']) }}</h3>
                                    <div class="fw-bold text-capitalize mb-30">
                                        {{ translate('collectable_cash') }}
                                    </div>
                                </div>
                                @if($commonData['collectable_amount']>0)
                                    <a href="{{ route('admin.driver.cash.index', [$driver->id]) }}"
                                       class="text-capitalize btn btn-primary mb-4">{{ translate('collect_cash') }}</a>
                                @endif
                            </div>

                        </div>
                        <div class="col-lg-8">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="card card-body h-100 justify-content-center py-5">
                                        <div class="d-flex gap-2 justify-content-between align-items-center">
                                            <div class="d-flex flex-column align-items-start">
                                                <h3 class="fw-bold mb-1 fs-3">
                                                    {{ getCurrencyFormat($commonData['pending_withdraw']) }}</h3>
                                                <div class="text-capitalize mb-0 text-capitalize fw-bold">
                                                    {{ translate('pending_withdraw') }}</div>
                                            </div>
                                            <div>
                                                <img width="40" class="mb-2"
                                                     src="{{ dynamicAsset('public/assets/admin-module/img/media/pw.png') }}"
                                                     alt="">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card card-body h-100 justify-content-center py-5">
                                        <div class="d-flex gap-2 justify-content-between align-items-center">
                                            <div class="d-flex flex-column align-items-start">
                                                <h3 class="fw-bold mb-1 fs-3">
                                                    {{ getCurrencyFormat($commonData['already_withdrawn']) }}</h3>
                                                <div class="fw-bold text-capitalize mb-0">
                                                    {{ translate('already_withdrawn') }}</div>
                                            </div>
                                            <div>
                                                <img width="40"
                                                     src="{{ dynamicAsset('public/assets/admin-module/img/media/aw.png') }}"
                                                     alt="">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card card-body h-100 justify-content-center py-5">
                                        <div class="d-flex gap-2 justify-content-between align-items-center">
                                            <div class="d-flex flex-column align-items-start">
                                                <h3 class="mb-1 fs-3 fw-bold">
                                                    {{ getCurrencyFormat($commonData['withdrawable_amount']) }}
                                                </h3>
                                                <div class="fw-bold text-capitalize mb-0">
                                                    {{ translate('withdrawable_amount') }}</div>
                                            </div>
                                            <div>
                                                <img width="40" class="mb-2"
                                                     src="{{ dynamicAsset('public/assets/admin-module/img/media/withdraw.png') }}"
                                                     alt="">
                                            </div>
                                        </div>
                                    </div>
                                </div>


                                <div class="col-md-6">
                                    <div class="card card-body h-100 justify-content-center py-5">
                                        <div class="d-flex gap-2 justify-content-between align-items-center">
                                            <div class="d-flex flex-column align-items-start">
                                                <h3 class="mb-1 fs-3 fw-bold">
                                                    {{ getCurrencyFormat($commonData['total_earning'] + $commonData['already_withdrawn']) }}
                                                </h3>
                                                <div class="text-capitalize mb-0 fw-bold">
                                                    {{ translate('total_earning') }}</div>
                                            </div>
                                            <div>
                                                <img width="40"
                                                     src="{{ dynamicAsset('public/assets/admin-module/img/media/withdraw-icon.png') }}"
                                                     alt="">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="d-flex mb-4">
                <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a href="{{ route('admin.driver.show', ['id' => $driver->id, 'tab' => 'overview']) }}"
                           class="nav-link {{ $commonData['tab'] == 'overview' ? 'active' : '' }}">{{ translate('overview') }}</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $commonData['tab'] == 'vehicle' ? 'active' : '' }}"
                           href="{{ route('admin.driver.show', ['id' => $driver->id, 'tab' => 'vehicle']) }}"
                           tabindex="-1">{{ translate('vehicle') }}</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="{{ route('admin.driver.show', ['id' => $driver->id, 'tab' => 'trips']) }}"
                           class="nav-link {{ $commonData['tab'] == 'trips' ? 'active' : '' }}"
                           tabindex="-1">{{ translate('trips') }}</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="{{ route('admin.driver.show', ['id' => $driver->id, 'tab' => 'transaction']) }}"
                           class="nav-link {{ $commonData['tab'] == 'transaction' ? 'active' : '' }}" role="tab"
                           tabindex="-1">{{translate("Transaction")}}</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="{{ route('admin.driver.show', ['id' => $driver->id, 'tab' => 'review', 'reviewed_by' => 'customer']) }}"
                           class="nav-link {{ $commonData['tab'] == 'review' ? 'active' : '' }}"
                           tabindex="-1">{{ translate('review') }}</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="{{ route('admin.driver.show', ['id' => $driver->id, 'tab' => 'availability']) }}"
                           class="nav-link {{ $commonData['tab'] == 'availability' ? 'active' : '' }}"
                           tabindex="-1">{{ translate('Availability') }}</a>
                    </li>
                </ul>
            </div>

            <div class="tab-content">
                @if ($commonData['tab'] == 'overview')
                    @include('usermanagement::admin.driver.partials.overview', [
                        'commonData' => $commonData,
                        'otherData' => $otherData,
                    ])
                @endif
                @if ($commonData['tab'] == 'vehicle')
                    @include('usermanagement::admin.driver.partials.vehicle', [
                        'commonData' => $commonData,
                        'otherData' => $otherData,
                    ])
                @endif
                @if ($commonData['tab'] == 'trips')
                    @include('usermanagement::admin.driver.partials.trips', [
                        'commonData' => $commonData,
                        'otherData' => $otherData,
                    ])
                @endif
                @if ($commonData['tab'] == 'transaction')
                    @include('usermanagement::admin.driver.partials.transaction', [
                        'commonData' => $commonData,
                        'otherData' => $otherData,
                    ])
                @endif
                @if ($commonData['tab'] == 'review')
                    @include('usermanagement::admin.driver.partials.review', [
                        'commonData' => $commonData,
                        'otherData' => $otherData,
                    ])
                @endif

                @if ($commonData['tab'] == 'availability')
                    @include('usermanagement::admin.driver.partials.availability', [
                        'commonData' => $commonData,
                        'otherData' => $otherData,
                    ])
                @endif
            </div>
        </div>
    </div>
    <!-- End Main Content -->


    <div class="modal fade" id="sameTimeEveryDayModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-5 pt-0 px-4">
                    <div class="max-349 mx-auto text-center">
                        <img alt="img" class="mb-20" src="{{ dynamicAsset('public/assets/admin-module/img/modal/delete-worning.png') }}">
                        <h3 class="text-dark fs-18 mb-2">
                            {{ translate('Are you sure want to set same time for every day') }}?
                        </h3>
                        <p class="fs-14 mb-4 pb-1">
                            {{ translate('The available time set for the first day will be applied to all days of the week.') }}
                        </p>
                        <div class="btn--container justify-content-center">
                            <button type="button" class="btn btn-secondary min-w-120" data-bs-dismiss="modal">
                                {{ translate('Cancel') }}
                            </button>
                            <button type="button" id="sameTimeConfirmBtn" class="btn btn-primary min-w-120" data-bs-dismiss="modal">
                                {{ translate('Yes') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteScheduleModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-5 pt-0 px-4">
                    <div class="max-349 mx-auto text-center">
                        <img alt="img" class="mb-20" src="{{ dynamicAsset('public/assets/admin-module/img/modal/delete-worning.png') }}">
                        <h3 class="text-dark fs-18 mb-2">
                            {{ translate('Are you sure want to delete this schedule') }}?
                        </h3>
                        <p class="fs-14 mb-4 pb-1">
                            {{ translate('Once deleted, this time slot will be removed from the availability schedule.') }}
                        </p>
                        <div class="btn--container justify-content-center">
                            <button type="button" class="btn btn-secondary min-w-120" data-bs-dismiss="modal">
                                {{ translate('Cancel') }}
                            </button>
                            <button type="button" id="deleteScheduleConfirmBtn" class="btn btn-danger min-w-120" data-bs-dismiss="modal">
                                {{ translate('Yes, Delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" id="addshedule_offcanvas" style="--bs-offcanvas-width: 500px;z-index: 1051">
        <div class="offcanvas-header">
            <h6 class="offcanvas-title fs-16 flex-grow-1">
                {{ translate('Create Schedule') }}
            </h6>
            <button type="button" class="btn-close border" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body scrollbar-thin">
            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                <div class="form-group mb-20">
                    <label for="addSlotStart"
                        class="input-label text-capitalize mb-2 text-dark fw-medium">{{ translate('Start_time') }}</label>
                    <input type="time" id="addSlotStart" class="form-control" name="start_time" required>
                </div>
                <div class="form-group">
                    <label for="addSlotEnd"
                        class="input-label text-capitalize mb-2 text-dark fw-medium">{{ translate('End_time') }}</label>
                    <input type="time" id="addSlotEnd" class="form-control" name="end_time" required>
                </div>
            </div>
        </div>
        <div class="offcanvas-footer d-flex gap-3 bg-white shadow position-sticky bottom-0 p-3 justify-content-center">
            <button type="reset" class="btn w-100 btn-secondary text-capitalize fw-semibold min-w-120">
                {{ translate('reset') }}
            </button>
            <button type="button" id="addSlotConfirmBtn" class="btn w-100 btn-primary text-capitalize fw-semibold cmn_focus  min-w-120">
                {{ translate('submit') }}
            </button>
        </div>
    </div>


@endsection

@push('script')
    <!-- Apex Chart -->
    <script src="{{ dynamicAsset('public/assets/admin-module/plugins/apex/apexcharts.min.js') }}"></script>
    <script>
        document.addEventListener('click', function (event) {
            const toggle = event.target.closest('.js-additional-info-toggle');

            if (!toggle) {
                return;
            }

            const wrapper = toggle.closest('.additional-info-value-wrap');
            const shortValue = wrapper?.querySelector('.js-additional-info-short');
            const fullValue = wrapper?.querySelector('.js-additional-info-full');

            if (!shortValue || !fullValue) {
                return;
            }

            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

            shortValue.classList.toggle('d-none', !isExpanded);
            fullValue.classList.toggle('d-none', isExpanded);
            toggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
            toggle.textContent = isExpanded ? toggle.dataset.seeMore : toggle.dataset.seeLess;
        });
    </script>
@endpush
