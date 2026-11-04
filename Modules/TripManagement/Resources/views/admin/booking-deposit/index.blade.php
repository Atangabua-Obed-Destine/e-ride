@extends('adminmodule::layouts.master')

@section('title', translate('Booking_Deposits'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-3">{{ translate('Booking_Deposits') }}</h2>
            @include('tripmanagement::admin.booking-deposit._nav', ['active' => 'list'])

            <div class="row g-3 mb-3">
                @foreach ([
                    ['Held_now', getCurrencyFormat($stats['held']), 'Paid deposits of trips not finished yet'],
                    ['Kept_this_month', getCurrencyFormat($stats['forfeited']), 'Customers who cancelled late'],
                    ['Paid_to_drivers_this_month', getCurrencyFormat($stats['to_drivers']), 'Their share of kept deposits'],
                    ['Refunded_this_month', getCurrencyFormat($stats['refunded']), 'Driver cancels and free cancels'],
                    ['Not_paid_this_month', $stats['expired'], 'Trips cancelled because the deposit was not paid'],
                ] as [$label, $value, $hint])
                    <div class="col-sm-6 col-lg">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="fs-12 text-muted mb-1">{{ translate($label) }}</div>
                                <div class="fs-20 fw-semibold">{{ $value }}</div>
                                <div class="fs-12 text-muted">{{ translate($hint) }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card">
                <div class="card-body">
                    <form class="d-flex flex-wrap gap-2 mb-3" method="GET">
                        <select name="status" class="form-select w-auto" onchange="this.form.submit()">
                            <option value="">{{ translate('All_statuses') }}</option>
                            @foreach (\Modules\TripManagement\Http\Controllers\Web\BookingDepositController::STATUSES as $s)
                                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ translate($s) }}</option>
                            @endforeach
                        </select>
                        <input type="search" name="search" value="{{ $search }}" class="form-control w-auto" placeholder="{{ translate('Trip_ID') }}">
                        <button class="btn btn-primary">{{ translate('Search') }}</button>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>{{ translate('Trip') }}</th>
                                <th>{{ translate('Customer') }}</th>
                                <th>{{ translate('Driver') }}</th>
                                <th class="text-end">{{ translate('Amount') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Paid_with') }}</th>
                                <th>{{ translate('Outcome') }}</th>
                                <th>{{ translate('Date') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($deposits as $deposit)
                                <tr>
                                    <td>
                                        @if ($deposit->trip)
                                            <a href="{{ route('admin.trip.show', ['id' => $deposit->trip_request_id, 'page' => 'summary']) }}">#{{ $deposit->trip->ref_id }}</a>
                                            <div class="fs-12 text-muted text-capitalize">{{ translate($deposit->trip->type) }} · {{ translate($deposit->trip->current_status) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ trim($deposit->customer?->first_name . ' ' . $deposit->customer?->last_name) }}<div class="fs-12 text-muted">{{ $deposit->customer?->phone }}</div></td>
                                    <td>{{ trim($deposit->driver?->first_name . ' ' . $deposit->driver?->last_name) }}<div class="fs-12 text-muted">{{ $deposit->driver?->phone }}</div></td>
                                    <td class="text-end fw-semibold">{{ getCurrencyFormat($deposit->amount) }}</td>
                                    <td>@include('tripmanagement::admin.booking-deposit._status', ['status' => $deposit->status])</td>
                                    <td class="text-capitalize">{{ $deposit->payment_method ? translate($deposit->payment_method) : '-' }}</td>
                                    <td class="fs-12">
                                        @if ($deposit->status === 'forfeited')
                                            {{ translate('Driver') }}: {{ getCurrencyFormat($deposit->driver_share) }}<br>
                                            E-Ride: {{ getCurrencyFormat($deposit->admin_share) }}
                                        @elseif ($deposit->refunded_amount > 0)
                                            {{ translate('Refunded') }}: {{ getCurrencyFormat($deposit->refunded_amount) }}
                                        @endif
                                        @if ($deposit->note)
                                            <div class="text-muted">{{ translate($deposit->note) }}</div>
                                        @endif
                                    </td>
                                    <td class="fs-12 text-nowrap">{{ $deposit->created_at->format('d M Y, H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-5">{{ translate('No_deposits_yet') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $deposits->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
