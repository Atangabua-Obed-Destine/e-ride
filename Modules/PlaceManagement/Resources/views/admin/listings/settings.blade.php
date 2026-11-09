@extends('adminmodule::layouts.master')

@section('title', translate('Homes_&_spaces_policy'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 860px">
            @include('placemanagement::admin.listings._nav', ['active' => 'settings'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form action="{{ route('admin.listing.settings.save') }}" method="POST">
                @csrf
                @php($field = function ($name, $label, $hint = null, $type = 'number') use ($settings) {
                    $value = old($name, is_array($settings[$name] ?? null) ? implode(', ', $settings[$name]) : ($settings[$name] ?? ''));
                    return '<div class="col-sm-6"><label class="form-label">' . e($label) . '</label><input type="' . $type . '" step="any" name="' . $name . '" class="form-control" value="' . e($value) . '">'
                        . ($hint ? '<div class="fs-12 text-muted mt-1">' . e($hint) . '</div>' : '') . '</div>';
                })
                <div class="card mb-3"><div class="card-body">
                    <h5 class="mb-1">{{ translate('Still_available') }}</h5>
                    <p class="fs-12 text-muted">{{ translate('Owners confirm their place is still free on this schedule. Late listings show Not confirmed recently; at twice the period they are hidden until the owner confirms.') }}</p>
                    <div class="row g-3">
                        {!! $field('confirm_days_short', translate('Confirm every (days): hostel rooms, rooms, studios')) !!}
                        {!! $field('confirm_days_long', translate('Confirm every (days): apartments, offices, shops')) !!}
                        {!! $field('late_after_days', translate('Not confirmed recently after (days late)')) !!}
                        {!! $field('stale_days', translate('Ask for a better price or photos after (days without a visit)')) !!}
                        {!! $field('taken_reports', translate('Already taken reports that start the check')) !!}
                        {!! $field('taken_hours', translate('Hours the owner has to confirm after those reports')) !!}
                    </div>
                </div></div>

                <div class="card mb-3"><div class="card-body">
                    <h5 class="mb-1">{{ translate('Verified_and_strikes') }}</h5>
                    <div class="row g-3">
                        {!! $field('verified_months', translate('E-Ride Verified lasts (months)')) !!}
                        {!! $field('visit_radius_m', translate('Staff must be within (metres of the pin)')) !!}
                        {!! $field('strike_limit', translate('Upheld reports before a pause from listing')) !!}
                        {!! $field('strike_months', translate('Counted over (months)')) !!}
                        {!! $field('ban_days', translate('Pause from listing (days)')) !!}
                    </div>
                </div></div>

                <div class="card mb-3"><div class="card-body">
                    <h5 class="mb-1">{{ translate('Areas_and_landmark') }}</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">{{ translate('Neighbourhoods_owners_can_pick,_comma_separated') }}</label>
                            <textarea name="neighbourhoods" class="form-control" rows="2" required>{{ old('neighbourhoods', implode(', ', $settings['neighbourhoods'])) }}</textarea>
                        </div>
                        {!! $field('landmark_name', translate('Landmark seekers measure from (e.g. UBa main gate)'), translate('Leave empty to show no distance.'), 'text') !!}
                        <div class="col-sm-6"></div>
                        {!! $field('landmark_latitude', translate('Landmark latitude')) !!}
                        {!! $field('landmark_longitude', translate('Landmark longitude')) !!}
                    </div>
                </div></div>

                <div class="card mb-3"><div class="card-body">
                    <h5 class="mb-1">{{ translate('Reservations') }}</h5>
                    <p class="fs-12 text-muted">{{ translate('After an accepted visit, a seeker can pay the owner reservation amount to E-Ride. E-Ride holds it until the move-in, then pays the owner minus the fee, or refunds the seeker. Owners choose it per listing.') }}</p>
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="reservations_enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="reservations_enabled" name="reservations_enabled" value="1" {{ old('reservations_enabled', $settings['reservations_enabled']) ? 'checked' : '' }}>
                        <label class="form-check-label" for="reservations_enabled">{{ translate('Seekers can reserve through E-Ride') }}</label>
                    </div>
                    <div class="row g-3">
                        {!! $field('reservation_min', translate('Smallest amount (FCFA)')) !!}
                        {!! $field('reservation_max', translate('Largest amount (FCFA)'), translate('Never more than one year of rent either.')) !!}
                        {!! $field('reservation_fee_percent', translate('E-Ride fee (% of the reservation, from the owner payout)')) !!}
                        {!! $field('reservation_pay_minutes', translate('Minutes to pay once started')) !!}
                        {!! $field('reservation_accept_hours', translate('Hours the owner has to accept')) !!}
                        {!! $field('reservation_release_days', translate('Days after the move-in date before automatic release')) !!}
                        {!! $field('reservation_move_in_days', translate('Move-in date at most (days ahead)')) !!}
                    </div>
                </div></div>

                <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
            </form>
        </div>
    </div>
@endsection
