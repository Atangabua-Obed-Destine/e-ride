@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride_Move'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-3">{{ translate('E-Ride_Move') }}</h2>
            @include('tripmanagement::admin.move._nav', ['active' => 'list'])

            <div class="row g-3 mb-3">
                @foreach ([
                    ['Requested_this_month', $stats['requested'], 'Moves customers asked for'],
                    ['Completed_this_month', $stats['completed'], 'Moves that reached the destination'],
                    ['Booking_fees_this_month', getCurrencyFormat($stats['fees']), 'Deposits kept as the booking fee'],
                    ['Directory_contacts_this_month', $stats['contacts'], 'Calls and WhatsApp taps on truck owners'],
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
                            @foreach ([PENDING, ACCEPTED, OUT_FOR_PICKUP, ONGOING, COMPLETED, CANCELLED] as $s)
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
                                <th>{{ translate('Move') }}</th>
                                <th>{{ translate('Customer') }}</th>
                                <th>{{ translate('Driver') }}</th>
                                <th>{{ translate('Vehicle') }}</th>
                                <th>{{ translate('What_is_moved') }}</th>
                                <th>{{ translate('Booking_fee') }}</th>
                                <th>{{ translate('Date') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($moves as $move)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.trip.show', ['id' => $move->id, 'page' => 'summary']) }}">#{{ $move->ref_id }}</a>
                                        <div class="fs-12 text-muted text-capitalize">{{ translate($move->current_status) }}</div>
                                        @if ($move->trip_cancellation_reason)
                                            <div class="fs-12 text-muted">{{ translate($move->trip_cancellation_reason) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ trim($move->customer?->first_name . ' ' . $move->customer?->last_name) }}<div class="fs-12 text-muted">{{ $move->customer?->phone }}</div></td>
                                    <td>
                                        @php($driver = $move->driver ?? ($move->deposit?->driver_id ? \Modules\UserManagement\Entities\User::find($move->deposit->driver_id) : null))
                                        {{ trim($driver?->first_name . ' ' . $driver?->last_name) ?: '-' }}<div class="fs-12 text-muted">{{ $driver?->phone }}</div>
                                    </td>
                                    <td>{{ $move->vehicleCategory?->name }}</td>
                                    <td class="fs-12" style="max-width: 260px">
                                        {{ \Illuminate\Support\Str::limit($move->note, 120) }}
                                        <div class="text-muted">{{ $move->coordinate?->pickup_address }} → {{ $move->coordinate?->destination_address }}</div>
                                        @if (!empty($move->move_photos))
                                            <div class="d-flex gap-1 mt-1">
                                                @foreach (app(\Modules\TripManagement\Service\MoveTripService::class)->photoUrls($move->move_photos) as $url)
                                                    <a href="{{ $url }}" target="_blank"><img src="{{ $url }}" alt="" width="40" height="40" class="rounded" style="object-fit: cover"></a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($move->deposit)
                                            <div class="fw-semibold">{{ getCurrencyFormat($move->deposit->amount) }}</div>
                                            @if ($move->deposit->status === 'applied')
                                                <span class="badge badge-success">{{ translate('Kept_as_booking_fee') }}</span>
                                            @else
                                                @include('tripmanagement::admin.booking-deposit._status', ['status' => $move->deposit->status])
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="fs-12 text-nowrap">{{ $move->created_at->format('d M Y, H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-5">{{ translate('No_moves_yet') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $moves->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
