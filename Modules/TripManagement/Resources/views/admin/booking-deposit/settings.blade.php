@extends('adminmodule::layouts.master')

@section('title', translate('Booking_Deposit_Settings'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 860px">
            <h2 class="fs-22 mb-3">{{ translate('Booking_Deposits') }}</h2>
            @include('tripmanagement::admin.booking-deposit._nav', ['active' => 'settings'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form action="{{ route('admin.booking-deposit.settings.update') }}" method="POST">
                @csrf @method('PUT')
                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1" style="min-width: 240px">
                            <h5 class="mb-1">{{ translate('Ask_for_a_booking_deposit') }}</h5>
                            <p class="fs-12 text-muted mb-0">{{ translate('After a driver accepts a ride or parcel, the customer pays the deposit with MoMo or the E-Ride wallet before the driver sets off. It counts toward the fare. If they cancel late they lose it, and it replaces the cancellation fee. Food and shop orders are already prepaid and never ask for one.') }}</p>
                            <p class="fs-12 text-warning mb-0 mt-2">{{ translate('Turn this on only when customers and drivers have the app version that supports deposits. Older customer apps cannot pay, so their trips would be cancelled after the payment time.') }}</p>
                        </div>
                        <label class="switcher">
                            <input type="hidden" name="status" value="0">
                            <input class="switcher_input" type="checkbox" name="status" value="1" {{ $settings['status'] ? 'checked' : '' }}>
                            <span class="switcher_control"></span>
                        </label>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <p class="fs-12 text-muted">{{ translate('The amount is set per zone and vehicle category in Fare setup, next to the fares. 0 means no deposit for that category.') }}
                            <a href="{{ route('admin.fare.trip.index') }}">{{ translate('Open_fare_setup') }}</a></p>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Time_to_pay_after_a_driver_accepts_(minutes)') }}</label>
                                <input type="number" name="window_minutes" class="form-control" required min="1" max="30" value="{{ $settings['window_minutes'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('When it runs out, the trip is cancelled and the driver is free for other requests. A MoMo payment already in progress gets 2 more minutes. Also the time to pay when booking a scheduled ride.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Free_cancellation_after_paying_(seconds)') }}</label>
                                <input type="number" name="free_cancel_seconds" class="form-control" required min="0" max="600" value="{{ $settings['free_cancel_seconds'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('A customer who changes their mind right after paying gets the deposit back. 0 to turn off.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Driver_late_allowance_(minutes)') }}</label>
                                <input type="number" name="late_minutes" class="form-control" required min="1" max="120" value="{{ $settings['late_minutes'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('If the driver is this many minutes past the arrival time shown and not at the pickup, the customer can cancel and get the deposit back.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('E-Ride_share_of_a_kept_deposit_(%)') }}</label>
                                <input type="number" name="admin_share_percent" class="form-control" required min="0" max="100" step="0.01" value="{{ $settings['admin_share_percent'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('When a customer cancels late, the driver gets the rest, added to their wallet. 0 gives it all to the driver.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="mb-1">{{ translate('Scheduled rides') }}</h5>
                        <p class="fs-12 text-muted">{{ translate('Rides booked ahead. These apply when scheduled rides are on in Business setup.') }}</p>
                        <label class="form-label">{{ translate('When the customer pays the deposit') }}</label>
                        <div class="d-flex flex-column gap-2 mb-3">
                            <label class="d-flex gap-2 align-items-start">
                                <input type="radio" name="scheduled_timing" value="booking" class="mt-1" {{ $settings['scheduled_timing'] === 'booking' ? 'checked' : '' }}>
                                <span><b>{{ translate('When booking') }}</b><br><span class="fs-12 text-muted">{{ translate('The customer pays right away, with the time to pay above. Drivers see the ride only once it is paid, so every scheduled ride they accept is confirmed.') }}</span></span>
                            </label>
                            <label class="d-flex gap-2 align-items-start">
                                <input type="radio" name="scheduled_timing" value="before_pickup" class="mt-1" {{ $settings['scheduled_timing'] === 'before_pickup' ? 'checked' : '' }}>
                                <span><b>{{ translate('Near the pickup time') }}</b><br><span class="fs-12 text-muted">{{ translate('Once a driver has accepted, the customer is asked to pay a set time before the pickup. Not paid in time: the ride is cancelled and the driver is free.') }}</span></span>
                            </label>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Near the pickup: ask this long before (minutes)') }}</label>
                                <input type="number" name="scheduled_before_minutes" class="form-control" required min="10" max="1440" value="{{ $settings['scheduled_before_minutes'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('If a driver accepts later than this, it is asked when they accept.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Near the pickup: time to pay (minutes)') }}</label>
                                <input type="number" name="scheduled_window_minutes" class="form-control" required min="1" max="1440" value="{{ $settings['scheduled_window_minutes'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('Never past the pickup time itself.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Free cancellation until (minutes before the pickup)') }}</label>
                                <input type="number" name="scheduled_free_cancel_minutes" class="form-control" required min="0" max="2880" value="{{ $settings['scheduled_free_cancel_minutes'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('Before that, a customer who cancels gets the deposit back. After it, the driver and E-Ride share it as for any late cancel. While no driver has taken the ride, cancelling is always free.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('No driver: cancel this long after the pickup time (minutes)') }}</label>
                                <input type="number" name="scheduled_no_driver_minutes" class="form-control" required min="0" max="240" value="{{ $settings['scheduled_no_driver_minutes'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('The customer is told, and a paid deposit goes back to their wallet. A driver who drops a scheduled ride before its time: it is offered to other drivers and the deposit carries over.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
