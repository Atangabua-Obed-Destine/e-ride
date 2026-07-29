@extends('adminmodule::layouts.master')

@section('title', translate('Trip_Fare_Setup'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <form action="{{ route('admin.fare.trip.store') }}" method="post" id="trip-store-form">
            @csrf
            <div class="container-fluid">
                <h2 class="fs-22 mb-4">{{ translate('Trip_Fare_Setup') }} - <span
                        class="text-primary">{{ $zone->name }} {{ translate('zone') }}</span></h2>
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-3 text-capitalize">{{ translate('available_vehicles_in_this_zone') }}</h5>

                        <div class="d-flex flex-wrap align-items-center gap-4 gap-xl-5">
                            @forelse($vehicleCategories as $vehicleCategory)
                                @php($trip = $tripFares?->where('vehicle_category_id', $vehicleCategory->id)->first())
                                <label class="custom-checkbox cmn_focus rounded pe-1">
                                    <input type="checkbox" class="test" name="vehicle_category_{{ $vehicleCategory->id }}" tabindex="1"
                                           value="{{ $vehicleCategory->id }}" @if ($trip?->vehicle_category_id == $vehicleCategory->id) checked @endif>
                                    {{ $vehicleCategory->name }}
                                </label>
                            @empty
                            @endforelse
                        </div>
                        <input type="hidden" name="zone_id" value="{{ $zone->id }}">
                        <input type="hidden" name="default_fare_id" value="{{ $defaultTripFare?->id }}">
                    </div>
                    <div class="card-body">
                        <h5 class="mb-4 text-capitalize">{{ translate('Default_Fare_Setup') }}</h5>
                        <div class="row gy-4 custom-class-fare">
                            <div class="col-sm-6 col-lg-4">
                                <label for="base_fare" class="form-label">{{ translate('Base_Fare') }}</label>
                                <div class="input-group_tooltip">
                                    <input type="number" class="form-control part-1-input copy-value" step=".01"
                                           min="0.01" name="base_fare" id="base_fare" max="99999999"
                                           placeholder="{{ translate('Base_Fare') }}"
                                           value="{{ $defaultTripFare->base_fare ?? 0 }}" required tabindex="2">
                                    <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('set_the_initial_fare_for_starting_a_trip') }}"></i>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <label for="base_fare_per_km" class="form-label">{{ translate('Fare_(Per_km)') }}</label>
                                <div class="input-group_tooltip">
                                    <input type="number" class="form-control part-1-input copy-value" step=".01"
                                           min="0.01" max="99999999" name="base_fare_per_km"
                                           placeholder="{{ translate('Fare_(Per_km)') }}" id="base_fare_per_km"
                                           value="{{ $defaultTripFare->base_fare_per_km ?? 0 }}" required tabindex="3">
                                    <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('set_the_fare_(per_km)_which_will_be_added_with_the_base_fare') }}"></i>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <label for="cancellation_fee"
                                       class="form-label">{{ translate('Cancellation_Fee_(%)') }}</label>
                                <div class="input-group_tooltip">
                                    <input type="number" name="cancellation_fee" min="0" max="100"
                                           step=".01" class="form-control part-1-input copy-value"
                                           placeholder="{{ translate('Cancellation_Fee_(%)') }}" id="cancellation_fee"
                                           value="{{ $defaultTripFare->cancellation_fee_percent ?? 0 }}" required tabindex="4">
                                    <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('set_the_trip_cancellation_fee_in_percentage_from_the_total_fee_for_the_users_which_will_be_counted_after_exceeding_the_minimum_cancellation_fee') }}"></i>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <label for="min_cancellation_fee"
                                       class="form-label">{{ translate('Minimum_Cancellation_Fee_($)') }}</label>
                                <div class="input-group_tooltip">
                                    <input type="number" name="min_cancellation_fee" step=".01" min="0"
                                           max="99999999" class="form-control part-1-input copy-value"
                                           placeholder="{{ translate('Minimum_Cancellation_Fee_($)') }}"
                                           id="min_cancellation_fee" value="{{ $defaultTripFare->min_cancellation_fee ?? 0 }}"
                                           required tabindex="5">
                                    <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('set_the_minimum_cancellation_fee_for_the_users_to_cancel_the_trip') }}"></i>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <label for="idle_fee" class="form-label">
                                    {{ translate('Idle_Fee_(Per_min)') }}
                                </label>
                                <div class="input-group_tooltip">
                                    <input type="number" name="idle_fee" step=".01" max="99999999"
                                           class="form-control part-1-input copy-value"
                                           placeholder="{{ translate('Idle_Fee_(Per_min)') }}" id="idle_fee"
                                           value="{{ $defaultTripFare->idle_fee_per_min ?? 0 }}" required tabindex="6">
                                    <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('set_the_fee_(per_min_)_for_the_customer_when_he/she_requests_to_wait_the_driver_on_an_ongoing_trip') }}"></i>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <label for="trip_delay_fee"
                                       class="form-label">{{ translate('Trip_Delay_Fee_(Per_min)') }}</label>
                                <div class="input-group_tooltip">
                                    <input type="number" name="trip_delay_fee" step=".01" max="99999999"
                                           class="form-control part-1-input copy-value"
                                           placeholder="{{ translate('Trip_Delay_Fee_(Per_min)') }}" id="trip_delay_fee"
                                           value="{{ $defaultTripFare->trip_delay_fee_per_min ?? 0 }}" required tabindex="7">
                                    <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('set_the_fee_(per_min)_for_the_customer_when_the_trip_took_longer_than_the_estimated_time') }}"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="w-0 flex-grow-1">
                                <h5 class="text-capitalize mb-2">

                                    {{ translate('category_wise_trip_fee') }}
                                </h5>
                                <div class="fs-12">
                                    {{ translate("When toggle is ON, the fare is set by category; when OFF, it's the same for all.") }}
                                </div>

                            </div>
                            <label class="switcher switcher-lg cmn_focus p-1 rounded">
                                <input data-url="" type="checkbox" name="category_wise_different_fare"
                                       id="use_category_wise" class="switcher_input toggle-switch-input use_category_wise" tabindex="8"
                                       {{ empty($defaultTripFare) || (!empty($defaultTripFare) && $defaultTripFare?->category_wise_different_fare == 1) ? 'checked' : '' }}
                                       onchange="toggleSwitcherState(this)">
                                <span id="switcher-text" data-state="off"
                                      class="switcher_control fs-12 fw-semibold text-capitalize">
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="fade-div" id="different-fare-div">
                            <div class="p-4">
                                <div class="table-responsive border border-primary rounded"
                                     style="--bs-border-opacity: .2">
                                    <table class="table align-middle table-borderless table-variation">
                                        <thead class="border-bottom border-primary" style="--bs-border-opacity: .2">
                                        <tr>
                                            <th>{{ translate('fare') }}</th>
                                            <th class="text-capitalize">{{ translate('default_price') }}</th>
                                            @forelse($vehicleCategories as $vehicleCategory)
                                                @php($trip = $tripFares?->firstWhere('vehicle_category_id', $vehicleCategory->id))
                                                <th
                                                    class="{{ $vehicleCategory->id }} {{ $vehicleCategory->id == $trip?->vehicle_category_id ? '' : 'd-none' }}">
                                                    {{ $vehicleCategory->name }}</th>
                                            @empty
                                            @endforelse
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2 text-primary fw-semibold">
                                                    <div class="text-capitalize">
                                                        {{ translate('base_fare') }}
                                                        ({{ session()->get('currency_symbol') ?? '$' }})
                                                    </div>
                                                    <i class="bi bi-info-circle-fill fs-14" data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('set_the_base_fare_for_starting_a_trip') }}">
                                                    </i>
                                                </div>
                                            </td>
                                            <td>
                                                <input readonly type="number" class="form-control base_fare_default"
                                                       value="{{ $defaultTripFare->base_fare ?? 0 }}" required tabindex="9">
                                            </td>
                                            @forelse($vehicleCategories as $vehicleCategory)
                                                @php($trip = $tripFares?->firstWhere('vehicle_category_id', $vehicleCategory->id))
                                                <td
                                                    class="{{ $vehicleCategory->id }} {{ $vehicleCategory->id == $trip?->vehicle_category_id ? '' : 'd-none' }}">
                                                    <input type="number" step=".01" min="0.01"
                                                           max="99999999" name="base_fare_{{ $vehicleCategory->id }}"
                                                           class="form-control base_fare_default part-2-input {{ $vehicleCategory->id }}"
                                                           value="{{ $trip?->base_fare ? round($trip->base_fare, 2) : $defaultTripFare->base_fare ?? 0 }}" tabindex="10">
                                                </td>
                                            @empty
                                            @endforelse

                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2 text-primary fw-semibold">
                                                    <div class="text-capitalize">
                                                        {{ translate('fare_per_km') }}
                                                        ({{ session()->get('currency_symbol') ?? '$' }})
                                                    </div>
                                                    <i class="bi bi-info-circle-fill fs-14" data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('set_the_fare_for_each_kilometer_added_with_the_base_fare') }}"></i>
                                                </div>
                                            </td>
                                            <td><input readonly type="number"
                                                       class="form-control base_fare_per_km_default"
                                                       value="{{ $defaultTripFare->base_fare_per_km ?? 0 }}" tabindex="11">
                                            </td>
                                            @forelse($vehicleCategories as $vehicleCategory)
                                                @php($trip = $tripFares?->firstWhere('vehicle_category_id', $vehicleCategory->id))
                                                <td
                                                    class="{{ $vehicleCategory->id }} {{ $vehicleCategory->id == $trip?->vehicle_category_id ? '' : 'd-none' }}">
                                                    <input type="number" step=".01" min=".01"
                                                           max="99999999"
                                                           name="base_fare_per_km_{{ $vehicleCategory->id }}"
                                                           class="form-control base_fare_per_km_default part-2-input {{ $vehicleCategory->id }}"
                                                           value="{{ $trip?->base_fare_per_km ? round($trip->base_fare_per_km, 2) : $defaultTripFare->base_fare_per_km ?? 0 }}" tabindex="12">
                                                </td>
                                            @empty
                                            @endforelse

                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2 text-primary fw-semibold">
                                                    <div class="text-capitalize">{{ translate('cancellation_fee') }}
                                                        (%)
                                                    </div>
                                                    <i class="bi bi-info-circle-fill fs-14" data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('set_the_trip_cancellation_fee_(in_percentage_of_the_total_trip_fee)_here._') .
                                                                translate('if_the_user_cancels_the_trip_they_must_pay_this_fee.') }}">
                                                    </i>
                                                </div>
                                            </td>
                                            <td>
                                                <input readonly type="number"
                                                       value="{{ $defaultTripFare->cancellation_fee_percent ?? 0 }}"
                                                       class="form-control cancellation_fee_default" tabindex="13">
                                            </td>
                                            @forelse($vehicleCategories as $vehicleCategory)
                                                @php($trip = $tripFares?->firstWhere('vehicle_category_id', $vehicleCategory->id))
                                                <td
                                                    class="{{ $vehicleCategory->id }} {{ $vehicleCategory->id == $trip?->vehicle_category_id ? '' : 'd-none' }}">
                                                    <input type="number" step=".01" min="0"
                                                           max="100"
                                                           name="cancellation_fee_{{ $vehicleCategory->id }}"
                                                           value="{{ $trip?->cancellation_fee_percent ? round($trip->cancellation_fee_percent, 2) : $defaultTripFare->cancellation_fee_percent ?? 0  }}"
                                                           class="form-control cancellation_fee_default part-2-input {{ $vehicleCategory->id }}"
                                                         tabindex="14">
                                                </td>
                                            @empty
                                            @endforelse
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2 text-primary fw-semibold">
                                                    <div class="text-capitalize">
                                                        {{ translate('minimum_cancellation_fee') }}
                                                        ({{ session()->get('currency_symbol') ?? '$' }})
                                                    </div>
                                                    <i class="bi bi-info-circle-fill fs-14" data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('set_the_minimum_trip_cancellation_fee_here._') .
                                                                translate('if_the_user_cancels_the_trip_they_must_pay_this_fee.') }}">
                                                    </i>
                                                </div>
                                            </td>
                                            <td><input readonly type="number"
                                                       value="{{ $defaultTripFare->min_cancellation_fee ?? 0 }}"
                                                       class="form-control min_cancellation_fee_default" tabindex="15">
                                            @forelse($vehicleCategories as $vehicleCategory)
                                                @php($trip = $tripFares?->where('vehicle_category_id', $vehicleCategory->id)->first())
                                                <td
                                                    class="{{ $vehicleCategory->id }} {{ $vehicleCategory->id == $trip?->vehicle_category_id ? '' : 'd-none' }}">
                                                    <input type="number" step=".01" min="0" max="99999999"
                                                           name="min_cancellation_fee_{{ $vehicleCategory->id }}"
                                                           value="{{ $trip?->min_cancellation_fee ? round($trip->min_cancellation_fee, 2) : $defaultTripFare->min_cancellation_fee ?? 0 }}"
                                                           class="form-control min_cancellation_fee_default part-2-input {{ $vehicleCategory->id }}"
                                                         tabindex="16">
                                                </td>
                                            @empty
                                            @endforelse
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2 text-primary fw-semibold">
                                                    <div class="text-capitalize">
                                                        {{ translate('idle_fee') }}
                                                        ({{ session()->get('currency_symbol') ?? '$' }})
                                                    </div>
                                                    <i class="bi bi-info-circle-fill fs-14" data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('set_the_idle_fee_(per_min)_here._') .
                                                                translate('if_the_driver_remains_idle_then_the_user_must_pay_this_fee.') }}">

                                                    </i>
                                                </div>
                                            </td>
                                            <td><input readonly type="number"
                                                       value="{{ $defaultTripFare->idle_fee_per_min ?? 0 }}"
                                                       class="form-control idle_fee_default" tabindex="17">
                                            @forelse($vehicleCategories as $vehicleCategory)
                                                @php($trip = $tripFares?->where('vehicle_category_id', $vehicleCategory->id)->first())
                                                <td
                                                    class="{{ $vehicleCategory->id }} {{ $vehicleCategory->id == $trip?->vehicle_category_id ? '' : 'd-none' }}">
                                                    <input type="number" step=".01" min="0" max="99999999"
                                                           name="idle_fee_{{ $vehicleCategory->id }}"
                                                           value="{{ $trip?->idle_fee_per_min ? round($trip->idle_fee_per_min, 2) : $defaultTripFare->idle_fee_per_min ?? 0 }}"
                                                           class="form-control idle_fee_default part-2-input {{ $vehicleCategory->id }}"
                                                         tabindex="18">
                                                </td>
                                            @empty
                                            @endforelse
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2 text-primary fw-semibold">
                                                    <div>{{ translate('Trip_Delay_Fee_(Per_min)') }}</div>
                                                    <i class="bi bi-info-circle-fill fs-14" data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('set_the_delay_fee_(per_min)_here._') .
                                                                translate('if_the_trip_takes_longer_then_estimated_time_then_the_user_must_pay_this_fee.') }}">
                                                    </i>
                                                </div>
                                            </td>
                                            <td>
                                                <input readonly type="number"
                                                       value="{{ $defaultTripFare->trip_delay_fee_per_min ?? 0 }}"
                                                       class="form-control trip_delay_fee_default" tabindex="19">
                                            </td>
                                            @forelse($vehicleCategories as $vehicleCategory)
                                                @php($trip = $tripFares?->where('vehicle_category_id', $vehicleCategory->id)->first())
                                                <td
                                                    class="{{ $vehicleCategory->id }} {{ $vehicleCategory->id == $trip?->vehicle_category_id ? '' : 'd-none' }}">
                                                    <input type="number" step=".01" min="0"
                                                           max="99999999"
                                                           name="trip_delay_fee_{{ $vehicleCategory->id }}"
                                                           value="{{ $trip?->trip_delay_fee_per_min ? round($trip->trip_delay_fee_per_min, 2) : $defaultTripFare->trip_delay_fee_per_min ?? 0 }}"
                                                           class="form-control trip_delay_fee_default part-2-input {{ $vehicleCategory->id }}"
                                                         tabindex="20">
                                                </td>
                                            @empty
                                            @endforelse
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-sticky">
                <div class="container-fluid">
                    <div class="d-flex justify-content-end gap-2 py-4">
                        <button type="button" class="btn btn-light btn-lg fw-semibold"
                                data-bs-dismiss="modal" tabindex="21">{{ translate('reset') }}</button>
                        <button type="submit" id="submit"
                                class="btn btn-primary btn-lg fw-semibold" tabindex="22">{{ translate('submit') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- End Main Content -->

@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/fare-management/trip/create.js') }}"></script>

    <script>
        "use strict";

        const inputCustomElements = document.querySelectorAll('.custom-class-fare input[type="number"]');

        inputCustomElements.forEach(input => {
            input.addEventListener('input', function() {
                if (parseFloat(this.value) < 0) {
                    // this.value = 1;
                    toastr.error('{{ translate('the_value_must_greater_than_0') }}')
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('trip-store-form');
            const part1Inputs = Array.from(form.querySelectorAll('.part-1-input'));
            const part2Inputs = Array.from(form.querySelectorAll('.part-2-input'));
            form.addEventListener('submit', function(event) {
                if ($('input[type="checkbox"]:checked').length <= 0) {
                    event.preventDefault();
                    toastr.error('{{ translate('must_select_at_least_one_vehicle_category') }}')
                    return false;
                }
                const part1Filled = part1Inputs.some(input => input.value.trim() !== '');
                const part2Filled = part2Inputs.some(input => input.value.trim() !== '');

                if (!part1Filled && !part2Filled) {
                    event.preventDefault();
                    toastr.error(
                        '{{ translate('please_enter_vehicle_wise_or_category_wise_information') }}')
                }
            });
        });

        const inputDifferentElements = document.querySelectorAll('#different-fare-div input[type="number"]');

        inputDifferentElements.forEach(input => {
            input.addEventListener('input', function() {
                if (parseFloat(this.value) < 0) {
                    // this.value = 1;
                    toastr.error('{{ translate('the_value_must_greater_than_0') }}')
                }
            });
        });
    </script>
    <script>
        // Category wise table show / hide
        function toggleSwitcherState(checkbox) {
            const switcherText = $('#switcher-text');
            const differentFareDiv = $('#different-fare-div');

            if (checkbox.checked) {
                switcherText.attr('data-state', 'on');
                differentFareDiv.addClass('show');
            } else {
                switcherText.attr('data-state', 'off');
                differentFareDiv.removeClass('show');
            }
            updateFooter();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const useCategoryWiseCheckbox = document.getElementById('use_category_wise');
            toggleSwitcherState(useCategoryWiseCheckbox);
            updateFooter();
        });

        // ------ Footer Sticky
        function updateFooter() {
            const $footer = $('.footer-sticky');
            const scrollPosition = $(window).scrollTop() + $(window).height();
            const documentHeight = $(document).height();

            if (scrollPosition >= documentHeight - 5) {
                $footer.addClass('no-shadow');
            } else {
                $footer.removeClass('no-shadow');
            }
        }

        $(window).on('scroll resize', updateFooter);
    </script>
@endpush
