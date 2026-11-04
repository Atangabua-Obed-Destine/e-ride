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
                                <div class="fs-12 text-muted mt-1">{{ translate('When it runs out, the trip is cancelled and the driver is free for other requests. A MoMo payment already in progress gets 2 more minutes.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Time_to_pay_for_scheduled_rides_(minutes)') }}</label>
                                <input type="number" name="scheduled_window_minutes" class="form-control" required min="1" max="1440" value="{{ $settings['scheduled_window_minutes'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('Scheduled rides are often accepted long before the pickup, so the customer may need longer.') }}</div>
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

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
