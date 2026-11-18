@extends('adminmodule::layouts.master')

@section('title', translate('Ways_to_pay'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 860px">
            <h2 class="fs-22 mb-1">{{ translate('Ways_to_pay') }}</h2>
            <p class="fs-12 text-muted mb-3">{{ translate('How customers pay for rides and parcels. Mobile Money is always available. Turn a way off and the apps stop offering it, and new trips and payments with it are refused. A move is paid to the driver directly, whatever is set here.') }}</p>
            <form action="{{ route('admin.payment-ways.update') }}" method="POST">
                @csrf @method('PUT')
                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1" style="min-width: 240px">
                            <h5 class="mb-1">{{ translate('Mobile_Money') }}</h5>
                            <p class="fs-12 text-muted mb-0">{{ translate('MTN MoMo and Orange Money, through the payment gateway. Always on.') }}</p>
                        </div>
                        <span class="badge bg-success">{{ translate('Always_on') }}</span>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1" style="min-width: 240px">
                            <h5 class="mb-1">{{ translate('Cash') }}</h5>
                            <p class="fs-12 text-muted mb-0">{{ translate('The customer pays the driver in cash at the end of the trip. Off: customers cannot choose cash, and a parcel can no longer be paid by the receiver on delivery (the sender pays in the app).') }}</p>
                        </div>
                        <label class="switcher">
                            <input type="hidden" name="cash" value="0">
                            <input class="switcher_input" type="checkbox" name="cash" value="1" {{ $cash ? 'checked' : '' }}>
                            <span class="switcher_control"></span>
                        </label>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1" style="min-width: 240px">
                            <h5 class="mb-1">{{ translate('E-Ride_wallet') }}</h5>
                            <p class="fs-12 text-muted mb-0">{{ translate('The customer pays from a balance in the app. Off: customers cannot pay with it, and the app hides the wallet. Drivers keep their own earnings wallet.') }}</p>
                        </div>
                        <label class="switcher">
                            <input type="hidden" name="wallet" value="0">
                            <input class="switcher_input" type="checkbox" name="wallet" value="1" {{ $wallet ? 'checked' : '' }}>
                            <span class="switcher_control"></span>
                        </label>
                    </div>
                </div>
                <p class="fs-12 text-muted">{{ translate('Trips already booked with a way you turn off can still be paid with Mobile Money at the end.') }}</p>
                <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary">{{ translate('Save') }}</button></div>
            </form>
        </div>
    </div>
@endsection
