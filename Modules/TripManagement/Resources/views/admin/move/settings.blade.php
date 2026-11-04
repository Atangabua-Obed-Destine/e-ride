@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride_Move_Settings'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 860px">
            <h2 class="fs-22 mb-3">{{ translate('E-Ride_Move') }}</h2>
            @include('tripmanagement::admin.move._nav', ['active' => 'settings'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form action="{{ route('admin.move.settings.update') }}" method="POST">
                @csrf @method('PUT')
                @foreach ([
                    ['status', 'Offer_E-Ride_Move', 'Customers book a tricycle, pickup, Dyna or truck like a ride: nearby trucks ring, one accepts, and the customer and driver agree the price in person. E-Ride sets no price and takes no commission.', 'Turn this on only when truck drivers are registered on the move vehicle types, the deposits are set, and both apps are updated.'],
                    ['deposit_status', 'Ask_for_a_booking_fee', 'After a driver accepts, the customer pays the amount set in Deposits (MoMo or wallet) before the driver sets off. E-Ride keeps it as the booking fee: the customer pays the driver the full agreed price.', null],
                    ['directory_status', 'Show_the_truck_owner_directory', 'When no truck is nearby or none accepts, customers see the owners in Directory with Call and WhatsApp buttons.', null],
                ] as [$name, $title, $text, $warning])
                    <div class="card mb-3">
                        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="flex-grow-1" style="min-width: 240px">
                                <h5 class="mb-1">{{ translate($title) }}</h5>
                                <p class="fs-12 text-muted mb-0">{{ translate($text) }}</p>
                                @if ($warning)
                                    <p class="fs-12 text-warning mb-0 mt-2">{{ translate($warning) }}</p>
                                @endif
                            </div>
                            <label class="switcher">
                                <input type="hidden" name="{{ $name }}" value="0">
                                <input class="switcher_input" type="checkbox" name="{{ $name }}" value="1" {{ $settings[$name] ? 'checked' : '' }}>
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                    </div>
                @endforeach

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Time_to_pay_after_a_driver_accepts_(minutes)') }}</label>
                                <input type="number" name="deposit_window_minutes" class="form-control" required min="1" max="30" value="{{ $settings['deposit_window_minutes'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('Longer than rides, so the customer can call the driver first. When it runs out, the move is cancelled and the driver is free again.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Free_cancellation_after_paying_(seconds)') }}</label>
                                <input type="number" name="deposit_free_cancel_seconds" class="form-control" required min="0" max="900" value="{{ $settings['deposit_free_cancel_seconds'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('A customer who changes their mind right after paying gets it back. 0 to turn off.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Driver_share_of_a_kept_booking_fee_(%)') }}</label>
                                <input type="number" name="deposit_driver_share_percent" class="form-control" required min="0" max="100" step="0.01" value="{{ $settings['deposit_driver_share_percent'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('When a customer cancels late, or they could not agree on the price at the pickup, the driver gets this share for the trip out (added to their wallet, paid out by E-Ride). 0 keeps it all for E-Ride.') }}</div>
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
