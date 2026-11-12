@extends('adminmodule::layouts.master')

@section('title', translate('Health rides'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="fs-22 mb-1">{{ translate('Health rides') }}</h2>
                    <p class="fs-12 text-muted mb-0">{{ translate('Urgent rides to a hospital, clinic or pharmacy, booked from the Health ride button in the app. They skip bidding, have no surge or extra fare, and reach drivers further away. The app says plainly that this is not an ambulance.') }}</p>
                </div>
                @if(!$settings['enabled'])
                    <span class="badge bg-warning text-dark fs-12">{{ translate('Off: turn it on in Settings') }}</span>
                @endif
            </div>

            <div class="row g-3">
                <div class="col-xl-8">
                    <ul class="nav nav--tabs p-1 rounded bg-white mb-3">
                        @foreach(\Modules\TripManagement\Http\Controllers\Web\HealthRideAdminController::PERIODS as $key => $label)
                            <li class="nav-item"><a class="nav-link {{ $period === $key ? 'active' : '' }}" href="{{ route('admin.health-ride.index', ['period' => $key]) }}">{{ translate($label) }}</a></li>
                        @endforeach
                    </ul>
                    <div class="row g-2 mb-3">
                        @foreach([
                            ['Requested', $stats['total']],
                            ['Taken by a driver', $stats['accepted']],
                            ['Usual wait for a driver', $stats['median_wait'] !== null ? $stats['median_wait'] . ' min' : '-'],
                            ['Completed', $stats['completed']],
                            ['Cancelled', $stats['cancelled']],
                        ] as [$label, $value])
                            <div class="col-6 col-md"><div class="card h-100"><div class="card-body p-3">
                                <div class="fs-12 text-muted">{{ translate($label) }}</div>
                                <div class="fs-20 fw-bold">{{ $value }}</div>
                            </div></div></div>
                        @endforeach
                    </div>

                    <div class="card"><div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle fs-13">
                                <thead class="table-light"><tr>
                                    <th>{{ translate('Trip') }}</th><th>{{ translate('Rider') }}</th><th>{{ translate('To') }}</th>
                                    <th>{{ translate('Driver') }}</th><th class="text-center">{{ translate('Wait') }}</th><th>{{ translate('Status') }}</th><th class="text-end">{{ translate('Fare') }}</th>
                                </tr></thead>
                                <tbody>
                                @forelse($trips as $trip)
                                    @php($wait = $trip->tripStatus?->accepted ? round(\Illuminate\Support\Carbon::parse($trip->tripStatus->accepted)->diffInSeconds($trip->created_at, true) / 60, 1) : null)
                                    <tr>
                                        <td><a href="{{ route('admin.trip.show', ['id' => $trip->id, 'page' => 'summary']) }}">#{{ $trip->ref_id }}</a>
                                            <div class="fs-11 text-muted">{{ $trip->created_at->format('d M, H:i') }}</div></td>
                                        <td>{{ trim($trip->customer?->first_name . ' ' . $trip->customer?->last_name) }}<div class="fs-11 text-muted">{{ $trip->customer?->phone }}</div></td>
                                        <td class="text-truncate" style="max-width: 220px" title="{{ $trip->coordinate?->destination_address }}">{{ $trip->coordinate?->destination_address }}</td>
                                        <td>{{ $trip->driver ? trim($trip->driver->first_name . ' ' . $trip->driver->last_name) : '-' }}</td>
                                        <td class="text-center">{{ $wait !== null ? $wait . ' min' : '-' }}</td>
                                        <td><span class="badge badge-{{ $trip->current_status === 'completed' ? 'success' : ($trip->current_status === 'cancelled' ? 'danger' : 'info') }}">{{ translate($trip->current_status) }}</span></td>
                                        <td class="text-end text-nowrap">{{ set_currency_symbol($trip->paid_fare > 0 ? $trip->paid_fare : $trip->estimated_fare) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-4">{{ translate('No health rides in this period.') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        {{ $trips->links() }}
                    </div></div>
                </div>

                <div class="col-xl-4">
                    @can('trip_edit')
                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-3">{{ translate('Settings') }}</h5>
                            <form action="{{ route('admin.health-ride.settings') }}" method="POST">@csrf
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="health_rides_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="health-enabled" name="health_rides_enabled" value="1" {{ $settings['enabled'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="health-enabled">{{ translate('Health rides are on') }}</label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="health_rides_deposit" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="health-deposit" name="health_rides_deposit" value="1" {{ $settings['deposit'] ? 'checked' : '' }}>
                                    <label class="form-check-label fs-12" for="health-deposit">{{ translate('Ask the booking deposit on health rides') }}</label>
                                    <div class="fs-11 text-muted">{{ translate('Off: the driver sets off at once, with no payment step. The daily limit still stops misuse.') }}</div>
                                </div>
                                <div class="mb-2"><label class="form-label fs-12 mb-1">{{ translate('Health rides a rider can book per day') }}</label>
                                    <input type="number" name="health_rides_daily_limit" min="1" max="10" class="form-control form-control-sm" value="{{ $settings['daily_limit'] }}"></div>
                                <div class="mb-2"><label class="form-label fs-12 mb-1">{{ translate('Search radius for drivers (km), 0 for twice the normal radius') }}</label>
                                    <input type="number" step="any" name="health_rides_radius_km" min="0" max="500" class="form-control form-control-sm" value="{{ $settings['radius_km'] }}">
                                    <div class="fs-11 text-muted mt-1">{{ translate(key: 'Now {r} km. Normal rides: {n} km.', replace: ['r' => rtrim(rtrim(number_format($radiusKm, 1), '0'), '.'), 'n' => rtrim(rtrim(number_format($normalRadius, 1), '0'), '.')]) }}
                                        <a href="{{ route('admin.business.setup.info.settings') }}">{{ translate('Change the normal radius in Business setup') }}</a></div></div>
                                <div class="mb-2"><label class="form-label fs-12 mb-1">{{ translate('Emergency numbers shown in the app, one per line: Name: number') }}</label>
                                    <textarea name="health_rides_emergency_numbers" rows="4" maxlength="600" class="form-control form-control-sm">{{ $settings['emergency_numbers'] }}</textarea></div>
                                <button class="btn btn-outline-primary w-100 mt-2">{{ translate('Save settings') }}</button>
                            </form>
                        </div></div>
                    @endcan
                    <div class="card"><div class="card-body fs-12">
                        <h6 class="mb-2">{{ translate('How a health ride works') }}</h6>
                        <ul class="ps-3 mb-0 text-muted">
                            <li>{{ translate('The rider picks a hospital, clinic or pharmacy near them, nearest first.') }}</li>
                            <li>{{ translate('The normal fare, with no surge or extra fare, and no bidding.') }}</li>
                            <li>{{ translate('Drivers see it in red as a Health ride. It is not an ambulance: the app shows the emergency numbers.') }}</li>
                            <li>{{ translate('A ride cancelled before a driver took it does not count toward the daily limit.') }}</li>
                        </ul>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
@endsection
